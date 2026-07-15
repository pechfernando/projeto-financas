const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');
const totaisTipoEl = document.getElementById('totais-tipo');
const saldoMesEl = document.getElementById('saldo-mes');
const corpoTabela = document.getElementById('corpo-tabela-relatorio');
const containerGraficoCanvas = document.getElementById('grafico-variaveis');
const mensagemSemVariaveis = document.getElementById('mensagem-sem-variaveis');

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const ROTULOS_TIPO = {
    fixa: 'Fixas',
    variavel: 'Variáveis',
    receita: 'Receita',
    dividas_parcelados: 'Dívidas e Parcelados',
};

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
    for (let ano = anoAtual - 3; ano <= anoAtual + 1; ano++) {
        const opcao = document.createElement('option');
        opcao.value = ano;
        opcao.textContent = ano;
        campoAno.appendChild(opcao);
    }
    campoAno.value = anoAtual;

    campoMes.addEventListener('change', carregarRelatorio);
    campoAno.addEventListener('change', carregarRelatorio);
}

async function carregarRelatorio() {
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/relatorio-mensal?mes=${mes}&ano=${ano}`);
    const dados = await resposta.json();

    renderizarTotaisPorTipo(dados.por_tipo);
    renderizarSaldo(dados.saldo_do_mes);
    renderizarTabela(dados.por_categoria);
    renderizarGrafico(dados.gastos_variaveis);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function renderizarTotaisPorTipo(porTipo) {
    totaisTipoEl.innerHTML = '';
    for (const tipo of Object.keys(ROTULOS_TIPO)) {
        const item = document.createElement('div');
        item.className = 'total-tipo-item';
        item.innerHTML = `
            <span class="rotulo">${ROTULOS_TIPO[tipo]}</span>
            <span class="valor">${formatarMoeda(porTipo[tipo] ?? 0)}</span>
        `;
        totaisTipoEl.appendChild(item);
    }
}

function renderizarSaldo(saldo) {
    const classe = saldo >= 0 ? 'saldo-positivo' : 'saldo-negativo';
    saldoMesEl.innerHTML = `Saldo do mês: <span class="${classe}">${formatarMoeda(saldo)}</span>`;
}

function renderizarTabela(porCategoria) {
    if (porCategoria.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="2">Nenhum lançamento nesse mês.</td></tr>';
        return;
    }

    corpoTabela.innerHTML = '';
    for (const linha of porCategoria) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td data-rotulo="Categoria">${ROTULOS_TIPO[linha.categoria_tipo]}: ${linha.categoria_nome}</td>
            <td data-rotulo="Total">${formatarMoeda(linha.total)}</td>
        `;
        corpoTabela.appendChild(tr);
    }
}

function renderizarGrafico(gastosVariaveis) {
    if (typeof Chart === 'undefined') {
        containerGraficoCanvas.hidden = true;
        mensagemSemVariaveis.hidden = false;
        mensagemSemVariaveis.textContent = 'Não foi possível carregar a biblioteca do gráfico (verifique sua conexão com a internet).';
        return;
    }

    if (gastosVariaveis.length === 0) {
        containerGraficoCanvas.hidden = true;
        mensagemSemVariaveis.hidden = false;
        if (grafico) grafico.destroy();
        return;
    }

    containerGraficoCanvas.hidden = false;
    mensagemSemVariaveis.hidden = true;

    const rotulos = gastosVariaveis.map((g) => `${g.categoria_nome} (${g.percentual}%)`);
    const valores = gastosVariaveis.map((g) => g.total);

    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(containerGraficoCanvas, {
        type: 'doughnut',
        data: {
            labels: rotulos,
            datasets: [{
                data: valores,
                backgroundColor: [
                    '#ea6524', '#f2a13c', '#1a9c6d', '#3c8cf2', '#d64545',
                    '#8e44ad', '#16a085', '#e67e22', '#2c3e50', '#f39c12',
                    '#27ae60', '#c0392b', '#2980b9', '#7f8c8d', '#d35400',
                ],
            }],
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
            },
        },
    });
}

iniciarSeletores();
carregarRelatorio();
