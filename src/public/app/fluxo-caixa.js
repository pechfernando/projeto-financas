const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');
const textoFolego = document.getElementById('texto-folego');
const cardFolego = document.getElementById('card-folego');
const corpoTabela = document.getElementById('corpo-tabela-fluxo');
const canvasGrafico = document.getElementById('grafico-saldo');

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

    campoMes.addEventListener('change', carregarFluxo);
    campoAno.addEventListener('change', carregarFluxo);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function carregarFluxo() {
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/fluxo-caixa?mes=${mes}&ano=${ano}&meses=6`);
    const dados = await resposta.json();

    renderizarFolego(dados.folego_financeiro);
    renderizarTabela(dados.serie);
    renderizarGrafico(dados.serie);
}

function renderizarFolego(folego) {
    cardFolego.classList.remove('folego-alerta');

    if (!folego) {
        textoFolego.textContent = 'Ainda não há dados suficientes para calcular o fôlego financeiro.';
        return;
    }

    if (folego.meses_de_folego === null) {
        textoFolego.innerHTML = `Gasto médio mensal (últimos meses): <strong>${formatarMoeda(folego.media_gastos_mensais)}</strong>.
            Não foi possível estimar o fôlego (saldo acumulado zerado ou negativo).`;
        cardFolego.classList.add('folego-alerta');
        return;
    }

    textoFolego.innerHTML = `No ritmo atual de gastos (média de <strong>${formatarMoeda(folego.media_gastos_mensais)}</strong>/mês),
        seu saldo dura aproximadamente <strong>${folego.meses_de_folego} meses</strong> sem nenhuma entrada nova.`;

    if (folego.meses_de_folego < 2) {
        cardFolego.classList.add('folego-alerta');
    }
}

function renderizarTabela(serie) {
    corpoTabela.innerHTML = '';
    for (const item of serie) {
        const classeSaldo = item.saldo_do_mes >= 0 ? 'saldo-positivo' : 'saldo-negativo';
        const classeAcumulado = item.saldo_acumulado >= 0 ? 'saldo-positivo' : 'saldo-negativo';

        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Mês">${NOMES_MESES[item.mes - 1]}/${item.ano}</td>
            <td data-rotulo="Saldo do Mês" class="${classeSaldo}">${formatarMoeda(item.saldo_do_mes)}</td>
            <td data-rotulo="Saldo Acumulado" class="${classeAcumulado}">${formatarMoeda(item.saldo_acumulado)}</td>
        `;
        corpoTabela.appendChild(linha);
    }
}

function renderizarGrafico(serie) {
    if (typeof Chart === 'undefined') return;

    const rotulos = serie.map((item) => `${NOMES_MESES_ABREV[item.mes - 1]}/${String(item.ano).slice(-2)}`);
    const valores = serie.map((item) => item.saldo_acumulado);

    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(canvasGrafico, {
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

iniciarSeletores();
carregarFluxo();
