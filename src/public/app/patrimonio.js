const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');

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

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];
const NOMES_MESES_ABREV = [
    'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez',
];

let grafico = null;

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
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function carregarTudo() {
    mensagemSucessoSaldos.hidden = true;
    await Promise.all([
        carregarSaldosDoMes(),
        carregarEvolucao(),
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
}

function renderizarReconciliacao(reconciliacao) {
    const { saldo_real, saldo_calculado, diferenca } = reconciliacao;

    valorSaldoReal.textContent = formatarMoeda(saldo_real);
    valorSaldoCalculado.textContent = formatarMoeda(saldo_calculado);
    valorDiferenca.innerHTML = `<strong>${formatarMoeda(diferenca)}</strong>`;

    valorDiferenca.classList.remove('saldo-positivo', 'saldo-negativo');
    // Diferença perto de zero (centavos de arredondamento) não é tratada como alerta
    if (Math.abs(diferenca) < 0.01) {
        valorDiferenca.classList.add('saldo-positivo');
    } else {
        valorDiferenca.classList.add('saldo-negativo');
    }
}

async function carregarEvolucao() {
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/evolucao-patrimonial?mes=${mes}&ano=${ano}&meses=6`);
    const dados = await resposta.json();

    renderizarGraficoEvolucao(dados.serie);
}

function renderizarGraficoEvolucao(serie) {
    if (typeof Chart === 'undefined') return;

    const meses_com_dado = serie.filter((item) => item.total_patrimonio !== null);
    if (meses_com_dado.length < 2) {
        canvasGrafico.hidden = true;
        mensagemSemEvolucao.hidden = false;
        if (grafico) grafico.destroy();
        return;
    }

    canvasGrafico.hidden = false;
    mensagemSemEvolucao.hidden = true;

    const rotulos = serie.map((item) => `${NOMES_MESES_ABREV[item.mes - 1]}/${String(item.ano).slice(-2)}`);
    const valores = serie.map((item) => item.total_patrimonio);

    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(canvasGrafico, {
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

formSaldos.addEventListener('submit', async (evento) => {
    evento.preventDefault();

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
    setTimeout(() => { mensagemSucessoSaldos.hidden = true; }, 3000);

    await carregarTudo();
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

iniciarSeletores();
carregarTudo();
