const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');
const campoHorizonte = document.getElementById('horizonte');

const valorSaldoReal = document.getElementById('valor-saldo-real');
const valorSaldoCalculado = document.getElementById('valor-saldo-calculado');
const valorDiferenca = document.getElementById('valor-diferenca');

const corpoTabelaSaldos = document.getElementById('corpo-tabela-saldos');
const formSaldos = document.getElementById('form-saldos');
const mensagemSucessoSaldos = document.getElementById('mensagem-sucesso-saldos');

const canvasGrafico = document.getElementById('grafico-patrimonio');
const mensagemSemEvolucao = document.getElementById('mensagem-sem-evolucao');

const formConta = document.getElementById('form-conta');
const campoContaNome = document.getElementById('conta-nome');
const campoContaTipo = document.getElementById('conta-tipo');

const corpoTabelaFluxo = document.getElementById('corpo-tabela-fluxo');
const canvasGraficoSaldoCaixa = document.getElementById('grafico-saldo-caixa');

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];
const NOMES_MESES_ABREV = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
];

let graficoPatrimonio = null;
let graficoSaldoCaixa = null;

function iniciarSeletores() {
    const hoje = new Date();

    NOMES_MESES.forEach((nome, indice) => {
        const opcao = document.createElement('option');
        opcao.value = indice + 1;
        opcao.textContent = nome;
        campoMes.appendChild(opcao);
    });
    campoMes.value = hoje.getMonth() + 1;

    const anoAtual = hoje.getFullYear();
    for (let ano = anoAtual - 2; ano <= anoAtual + 1; ano++) {
        const opcao = document.createElement('option');
        opcao.value = ano;
        opcao.textContent = ano;
        campoAno.appendChild(opcao);
    }
    campoAno.value = anoAtual;

    campoMes.addEventListener('change', carregarTudo);
    campoAno.addEventListener('change', carregarTudo);
    campoHorizonte.addEventListener('change', carregarTudo);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function carregarTudo() {
    mensagemSucessoSaldos.hidden = true;
    await Promise.all([
        carregarSaldosDoMes(),
        carregarEvolucao(),
        carregarFluxoCaixa(),
    ]);
}

async function carregarSaldosDoMes() {
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/saldos-mensais?mes=${mes}&ano=${ano}`);
    const dados = await resposta.json();

    renderizarTabelaSaldos(dados.contas);
    renderizarReconciliacao(dados.reconciliacao);
}

function renderizarTabelaSaldos(contas) {
    if (contas.length === 0) {
        corpoTabelaSaldos.innerHTML = '<tr><td colspan="2">Nenhuma conta cadastrada ainda. Cadastre uma abaixo.</td></tr>';
        return;
    }

    corpoTabelaSaldos.innerHTML = '';
    for (const conta of contas) {
        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Conta">${conta.conta_nome}${conta.conta_tipo ? ` <span class="ajuda-inline">(${conta.conta_tipo})</span>` : ''}</td>
            <td data-rotulo="Saldo">
                <input type="number" step="0.01"
                    class="input-saldo"
                    data-conta-id="${conta.conta_id}"
                    value="${Number(conta.valor) > 0 ? conta.valor : ''}"
                    placeholder="0,00">
            </td>
        `;
        corpoTabelaSaldos.appendChild(linha);
    }

    // Auto-save on blur!
    corpoTabelaSaldos.querySelectorAll('.input-saldo').forEach((input) => {
        input.addEventListener('blur', salvarSaldosAutomaticamente);
    });
}

async function salvarSaldosAutomaticamente() {
    const inputs = corpoTabelaSaldos.querySelectorAll('.input-saldo');
    const itens = Array.from(inputs).map((input) => ({
        conta_id: input.dataset.contaId,
        valor: input.value === '' ? 0 : input.value,
    }));

    await fetch(`${API_BASE}/saldos-mensais`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mes: campoMes.value, ano: campoAno.value, itens }),
    });

    mensagemSucessoSaldos.hidden = false;
    setTimeout(() => { mensagemSucessoSaldos.hidden = true; }, 1500);

    // Recarrega apenas reconciliação e o gráfico, mantendo o foco do input
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/saldos-mensais?mes=${mes}&ano=${ano}`);
    const dados = await resposta.json();
    renderizarReconciliacao(dados.reconciliacao);
    
    await carregarEvolucao();
}

function renderizarReconciliacao(reconciliacao) {
    const { saldo_real, saldo_calculado, diferenca } = reconciliacao;

    valorSaldoReal.textContent = formatarMoeda(saldo_real);
    valorSaldoCalculado.textContent = formatarMoeda(saldo_calculado);
    valorDiferenca.innerHTML = `<strong>${formatarMoeda(diferenca)}</strong>`;

    valorDiferenca.classList.remove('saldo-positivo', 'saldo-negativo');
    valorDiferenca.style.color = '';

    // Diferença perto de zero (centavos de arredondamento) não é tratada como alerta
    if (Math.abs(diferenca) < 0.1) {
        valorDiferenca.classList.add('saldo-positivo');
    } else if (diferenca > 0) {
        // Se a diferença for positiva (mais dinheiro real nas contas que o calculado), usa azul
        valorDiferenca.style.color = '#3c8cf2';
    } else {
        valorDiferenca.classList.add('saldo-negativo');
    }
}

async function carregarEvolucao() {
    const mes = campoMes.value;
    const ano = campoAno.value;
    const horizonte = campoHorizonte ? campoHorizonte.value : 6;

    const resposta = await fetch(`${API_BASE}/evolucao-patrimonial?mes=${mes}&ano=${ano}&meses=${horizonte}`);
    const dados = await resposta.json();

    renderizarGraficoEvolucao(dados.serie);
}

function renderizarGraficoEvolucao(serie) {
    if (typeof Chart === 'undefined') return;

    const meses_com_dado = serie.filter((item) => item.total_patrimonio !== null);
    if (meses_com_dado.length < 2) {
        canvasGrafico.hidden = true;
        mensagemSemEvolucao.hidden = false;
        if (graficoPatrimonio) graficoPatrimonio.destroy();
        return;
    }

    canvasGrafico.hidden = false;
    mensagemSemEvolucao.hidden = true;

    const rotulos = serie.map((item) => `${NOMES_MESES_ABREV[item.mes - 1]}/${String(item.ano).slice(-2)}`);
    const valores = serie.map((item) => item.total_patrimonio);

    if (graficoPatrimonio) {
        graficoPatrimonio.destroy();
    }

    graficoPatrimonio = new Chart(canvasGrafico, {
        type: 'line',
        data: {
            labels: rotulos,
            datasets: [{
                label: 'Patrimônio Total',
                data: valores,
                borderColor: '#1a9c6d',
                backgroundColor: 'rgba(26, 156, 109, 0.15)',
                fill: true,
                tension: 0.25,
                pointRadius: 4,
                spanGaps: true,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    ticks: {
                        callback: (valor) => formatarMoeda(valor),
                    },
                },
            },
        },
    });
}

async function carregarFluxoCaixa() {
    const mes = campoMes.value;
    const ano = campoAno.value;
    const horizonte = campoHorizonte ? campoHorizonte.value : 6;

    const resposta = await fetch(`${API_BASE}/fluxo-caixa?mes=${mes}&ano=${ano}&meses=${horizonte}`);
    const dados = await resposta.json();

    renderizarTabelaFluxo(dados.serie);
    renderizarGraficoSaldoCaixa(dados.serie);
}

function renderizarTabelaFluxo(serie) {
    if (!corpoTabelaFluxo) return;
    corpoTabelaFluxo.innerHTML = '';
    for (const item of serie) {
        const classeSaldo = item.saldo_do_mes >= 0 ? 'saldo-positivo' : 'saldo-negativo';
        const classeAcumulado = item.saldo_acumulado >= 0 ? 'saldo-positivo' : 'saldo-negativo';

        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Mês">${NOMES_MESES[item.mes - 1]}/${item.ano}</td>
            <td data-rotulo="Receitas (+)" class="saldo-positivo">${formatarMoeda(item.receitas)}</td>
            <td data-rotulo="Despesas (-)" class="saldo-negativo">${formatarMoeda(item.despesas)}</td>
            <td data-rotulo="Saldo do Mês (=)" class="${classeSaldo}">${formatarMoeda(item.saldo_do_mes)}</td>
            <td data-rotulo="Saldo Acumulado" class="${classeAcumulado}">${formatarMoeda(item.saldo_acumulado)}</td>
        `;
        corpoTabelaFluxo.appendChild(linha);
    }
}

function renderizarGraficoSaldoCaixa(serie) {
    if (typeof Chart === 'undefined' || !canvasGraficoSaldoCaixa) return;

    const rotulos = serie.map((item) => `${NOMES_MESES_ABREV[item.mes - 1]}/${String(item.ano).slice(-2)}`);
    const valores = serie.map((item) => item.saldo_acumulado);

    if (graficoSaldoCaixa) {
        graficoSaldoCaixa.destroy();
    }

    graficoSaldoCaixa = new Chart(canvasGraficoSaldoCaixa, {
        type: 'line',
        data: {
            labels: rotulos,
            datasets: [{
                label: 'Saldo Acumulado',
                data: valores,
                borderColor: '#ea6524',
                backgroundColor: 'rgba(234, 101, 36, 0.15)',
                fill: true,
                tension: 0.25,
                pointRadius: 4,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    ticks: {
                        callback: (valor) => formatarMoeda(valor),
                    },
                },
            },
        },
    });
}

formSaldos.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    await salvarSaldosAutomaticamente();
});

formConta.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    await fetch(`${API_BASE}/contas-patrimonio`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nome: campoContaNome.value,
            tipo: campoContaTipo.value || null,
        }),
    });

    campoContaNome.value = '';
    campoContaTipo.value = '';

    await carregarSaldosDoMes();
});

const seletorPresetFolego = document.getElementById('folego-preset');
const campoFolegoDe = document.getElementById('folego-de');
const campoFolegoAte = document.getElementById('folego-ate');
const botaoAplicarFolego = document.getElementById('botao-aplicar-folego');

function formatarInputMonth(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    return `${ano}-${mes}`;
}

function aplicarPresetFolego() {
    const hoje = new Date();
    const preset = seletorPresetFolego.value;

    if (preset === 'personalizado') {
        return; // não mexe nos campos De/Até, o usuário escolhe manualmente
    }

    let de, ate;

    if (preset === 'padrao') {
        de = new Date(hoje.getFullYear(), hoje.getMonth() - 3, 1);
        ate = new Date(hoje.getFullYear(), hoje.getMonth() + 12, 1);
    } else {
        // presets 12 / 24 / 36 => só meses à frente, sem histórico
        de = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 1);
        ate = new Date(hoje.getFullYear(), hoje.getMonth() + parseInt(preset, 10), 1);
    }

    campoFolegoDe.value = formatarInputMonth(de);
    campoFolegoAte.value = formatarInputMonth(ate);
    carregarFolegoAnual();
}

async function carregarFolegoAnual() {
    if (!campoFolegoDe.value || !campoFolegoAte.value) return;

    if (campoFolegoDe.value > campoFolegoAte.value) {
        alert('A data de início (De) não pode ser maior que a data de fim (Até).');
        return;
    }

    const [anoInicio, mesInicio] = campoFolegoDe.value.split('-').map(Number);
    const [anoFim, mesFim] = campoFolegoAte.value.split('-').map(Number);

    const parametros = new URLSearchParams({
        mes_inicio: mesInicio,
        ano_inicio: anoInicio,
        mes_fim: mesFim,
        ano_fim: anoFim,
    });

    const resposta = await fetch(`${API_BASE}/folego-anual?${parametros}`);
    const dados = await resposta.json();
    renderizarFolegoAnual(dados);
}

if (seletorPresetFolego) {
    seletorPresetFolego.addEventListener('change', aplicarPresetFolego);
}
if (botaoAplicarFolego) {
    botaoAplicarFolego.addEventListener('click', carregarFolegoAnual);
}

function renderizarFolegoAnual(dados) {
    const cabecalho = document.getElementById('cabecalho-projecao-folego');
    const linhaReceitas = document.getElementById('linha-receitas-projecao');
    const linhaDespesasFixas = document.getElementById('linha-despesas-fixas-projecao');
    const linhaDespesasVariaveis = document.getElementById('linha-despesas-variaveis-projecao');
    const linhaDividas = document.getElementById('linha-dividas-projecao');
    const linhaSaldo = document.getElementById('linha-saldo-projecao');
    const linhaAcumulado = document.getElementById('linha-saldo-acumulado-projecao');
    const textoEsgotamento = document.getElementById('texto-esgotamento-folego');
    const mensagemSemRecorrentes = document.getElementById('mensagem-sem-recorrentes');

    if (!dados || !dados.meses || dados.meses.length === 0) {
        cabecalho.innerHTML = '';
        linhaReceitas.innerHTML = '';
        linhaDespesasFixas.innerHTML = '';
        linhaDividas.innerHTML = '';
        if (linhaDespesasVariaveis) linhaDespesasVariaveis.innerHTML = '';
        linhaSaldo.innerHTML = '';
        linhaAcumulado.innerHTML = '';
        textoEsgotamento.textContent = '';
        return;
    }

    const nenhumRecorrente = dados.meses.every((m) =>
        m.receitas === 0 && m.despesas_fixas === 0 && m.dividas === 0
    );
    if (mensagemSemRecorrentes) {
        mensagemSemRecorrentes.hidden = !nenhumRecorrente;
    }

    cabecalho.innerHTML = '<th>Descrição</th>' +
        dados.meses.map((m) => `
            <th class="${m.eh_real ? 'coluna-real' : 'coluna-projetada'}">
                ${m.rotulo}<br><small>${m.eh_real ? '(real)' : '(projetado)'}</small>
            </th>
        `).join('');

    linhaReceitas.innerHTML = '<td>Receitas</td>' +
        dados.meses.map((m) => `<td class="valor-positivo">${formatarMoeda(m.receitas)}</td>`).join('');

    linhaDespesasFixas.innerHTML = '<td>Despesas Fixas</td>' +
        dados.meses.map((m) => `<td class="valor-negativo">${formatarMoeda(m.despesas_fixas)}</td>`).join('');

    if (linhaDespesasVariaveis) {
        linhaDespesasVariaveis.innerHTML = '<td>Despesas Variáveis</td>' +
            dados.meses.map((m) => {
                const classe = m.despesas_variaveis > 0 ? 'valor-negativo' : '';
                return `<td class="${classe}">${formatarMoeda(m.despesas_variaveis)}</td>`;
            }).join('');
    }

    linhaDividas.innerHTML = '<td>Dívidas / Parcelas</td>' +
        dados.meses.map((m) => `<td class="valor-negativo">${formatarMoeda(m.dividas)}</td>`).join('');

    linhaSaldo.innerHTML = '<td><strong>Saldo do Mês</strong></td>' +
        dados.meses.map((m) => {
            const classe = m.saldo_do_mes < 0 ? 'valor-negativo' : 'valor-positivo';
            return `<td class="${classe}"><strong>${formatarMoeda(m.saldo_do_mes)}</strong></td>`;
        }).join('');

    linhaAcumulado.innerHTML = '<td><strong>Saldo Acumulado</strong></td>' +
        dados.meses.map((m) => {
            const classe = m.saldo_acumulado < 0 ? 'valor-negativo' : 'valor-positivo';
            return `<td class="${classe}"><strong>${formatarMoeda(m.saldo_acumulado)}</strong></td>`;
        }).join('');

    if (dados.mes_esgotamento) {
        textoEsgotamento.innerHTML = `⚠️ No ritmo atual dos seus recorrentes cadastrados, seu saldo acumulado
            deve se esgotar por volta de <strong>${dados.mes_esgotamento.rotulo}</strong>.`;
    } else {
        textoEsgotamento.innerHTML = `No ritmo atual dos seus recorrentes cadastrados, seu saldo acumulado deve
            se manter positivo pelos próximos 60 meses, pelo menos.`;
    }
}

// Vincula o carregamento do fôlego à mudança de abas
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.dataset.tab === 'folego') {
            carregarFolegoAnual();
        }
    });
});

iniciarSeletores();
carregarTudo();

// Carrega o fôlego também se for a aba inicial carregada
const storageKey = `activeTab_${window.location.pathname}`;
const activeTab = localStorage.getItem(storageKey);
if (activeTab === 'folego') {
    carregarFolegoAnual();
} else {
    // Ao carregar a página pela primeira vez, aplicar o preset padrão:
    if (seletorPresetFolego) {
        seletorPresetFolego.value = 'padrao';
        aplicarPresetFolego();
    }
}
