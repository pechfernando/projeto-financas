const API_BASE = '/api';

const campoAno = document.getElementById('ano');
const tituloTotalAno = document.getElementById('titulo-total-ano');
const totalAnoEl = document.getElementById('total-ano');
const canvasGraficoPorAtivo = document.getElementById('grafico-por-ativo');
const mensagemSemRendimentos = document.getElementById('mensagem-sem-rendimentos');
const tabelaPorAtivo = document.getElementById('tabela-por-ativo');
const corpoTabelaPorAtivo = document.getElementById('corpo-tabela-por-ativo');
const corpoTabelaDetalhe = document.getElementById('corpo-tabela-detalhe');

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

let grafico = null;

function iniciarSeletores() {
    const anoAtual = new Date().getFullYear();
    for (let ano = anoAtual; ano >= anoAtual - 4; ano--) {
        const opcao = document.createElement('option');
        opcao.value = ano;
        opcao.textContent = ano;
        campoAno.appendChild(opcao);
    }
    campoAno.value = anoAtual;

    campoAno.addEventListener('change', carregarRendimentos);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function carregarRendimentos() {
    const ano = campoAno.value;
    tituloTotalAno.textContent = `Total Recebido em ${ano}`;

    const resposta = await fetch(`${API_BASE}/rendimentos-investimentos?ano=${ano}`);
    const rendimentos = await resposta.json();

    renderizarTotalAno(rendimentos);
    renderizarPorAtivo(rendimentos);
    renderizarDetalheMensal(rendimentos);
}

function renderizarTotalAno(rendimentos) {
    const total = rendimentos.reduce((soma, r) => soma + Number(r.valor), 0);
    totalAnoEl.innerHTML = `<strong>${formatarMoeda(total)}</strong>`;
}

function agruparPorAtivo(rendimentos) {
    const mapa = new Map();
    for (const r of rendimentos) {
        const atual = mapa.get(r.ativo_nome) ?? 0;
        mapa.set(r.ativo_nome, atual + Number(r.valor));
    }
    return Array.from(mapa.entries())
        .map(([ativo_nome, total]) => ({ ativo_nome, total }))
        .sort((a, b) => b.total - a.total);
}

function renderizarPorAtivo(rendimentos) {
    if (rendimentos.length === 0) {
        canvasGraficoPorAtivo.hidden = true;
        mensagemSemRendimentos.hidden = false;
        tabelaPorAtivo.hidden = true;
        if (grafico) grafico.destroy();
        return;
    }

    canvasGraficoPorAtivo.hidden = false;
    mensagemSemRendimentos.hidden = true;
    tabelaPorAtivo.hidden = false;

    const porAtivo = agruparPorAtivo(rendimentos);

    corpoTabelaPorAtivo.innerHTML = '';
    for (const item of porAtivo) {
        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Ativo">${item.ativo_nome}</td>
            <td data-rotulo="Total no Ano">${formatarMoeda(item.total)}</td>
        `;
        corpoTabelaPorAtivo.appendChild(linha);
    }

    if (typeof Chart === 'undefined') return;

    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(canvasGraficoPorAtivo, {
        type: 'doughnut',
        data: {
            labels: porAtivo.map((item) => item.ativo_nome),
            datasets: [{
                data: porAtivo.map((item) => item.total),
                backgroundColor: [
                    '#ea6524', '#f2a13c', '#1a9c6d', '#3c8cf2', '#d64545',
                    '#8e44ad', '#16a085', '#e67e22', '#2c3e50', '#f39c12',
                ],
            }],
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        },
    });
}

function renderizarDetalheMensal(rendimentos) {
    if (rendimentos.length === 0) {
        corpoTabelaDetalhe.innerHTML = '<tr><td colspan="3">Nenhum rendimento registrado neste ano ainda.</td></tr>';
        return;
    }

    // A API já retorna ordenado por ano/mês desc, ativo asc — só precisamos
    // inserir uma linha de subtotal sempre que o mês mudar.
    corpoTabelaDetalhe.innerHTML = '';
    let mesAnterior = null;

    for (const r of rendimentos) {
        if (r.mes !== mesAnterior) {
            const totalDoMes = rendimentos
                .filter((item) => item.mes === r.mes)
                .reduce((soma, item) => soma + Number(item.valor), 0);

            const linhaGrupo = document.createElement('tr');
            linhaGrupo.innerHTML = `
                <td colspan="2" class="grupo-categoria">${NOMES_MESES[r.mes - 1]}</td>
                <td class="grupo-categoria">${formatarMoeda(totalDoMes)}</td>
            `;
            corpoTabelaDetalhe.appendChild(linhaGrupo);
            mesAnterior = r.mes;
        }

        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Mês">${NOMES_MESES[r.mes - 1]}</td>
            <td data-rotulo="Ativo">${r.ativo_nome}</td>
            <td data-rotulo="Valor">${formatarMoeda(r.valor)}</td>
        `;
        corpoTabelaDetalhe.appendChild(linha);
    }
}

iniciarSeletores();
carregarRendimentos();
