const API_BASE = '/api';

const totalInvestidoEl = document.getElementById('total-investido');
const corpoTabelaCarteira = document.getElementById('corpo-tabela-carteira');
const corpoTabelaHistorico = document.getElementById('corpo-tabela-historico-movimentacoes');
const canvasGraficoTipo = document.getElementById('grafico-tipo-ativo');
const mensagemSemInvestimentos = document.getElementById('mensagem-sem-investimentos');

const formAtivo = document.getElementById('form-ativo');
const formMovimentacao = document.getElementById('form-movimentacao');
const formRendimento = document.getElementById('form-rendimento');
const mensagemSucesso = document.getElementById('mensagem-sucesso-investimentos');

const campoMovAtivo = document.getElementById('mov-ativo');
const campoRendAtivo = document.getElementById('rend-ativo');
const campoRendMes = document.getElementById('rend-mes');
const campoRendAno = document.getElementById('rend-ano');

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const ROTULOS_TIPO_ATIVO = {
    fii: 'FII',
    acao: 'Ação',
    renda_fixa: 'Renda Fixa',
    cripto: 'Cripto',
    fundo: 'Fundo',
    outro: 'Outro',
};

let grafico = null;

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function iniciarSeletoresData() {
    const hoje = new Date();

    NOMES_MESES.forEach((nome, indice) => {
        const opcao = document.createElement('option');
        opcao.value = indice + 1;
        opcao.textContent = nome;
        campoRendMes.appendChild(opcao);
    });
    campoRendMes.value = hoje.getMonth() + 1;

    const anoAtual = hoje.getFullYear();
    for (let ano = anoAtual - 2; ano <= anoAtual + 1; ano++) {
        const opcao = document.createElement('option');
        opcao.value = ano;
        opcao.textContent = ano;
        campoRendAno.appendChild(opcao);
    }
    campoRendAno.value = anoAtual;

    document.getElementById('mov-data').value = hoje.toISOString().slice(0, 10);
}

async function carregarAtivos() {
    const resposta = await fetch(`${API_BASE}/ativos`);
    const ativos = await resposta.json();

    for (const select of [campoMovAtivo, campoRendAtivo]) {
        select.innerHTML = '<option value="">Escolher...</option>';
        for (const ativo of ativos) {
            const opcao = document.createElement('option');
            opcao.value = ativo.id;
            opcao.textContent = `${ativo.nome} (${ROTULOS_TIPO_ATIVO[ativo.tipo_ativo]})`;
            select.appendChild(opcao);
        }
    }
}

async function carregarCarteira() {
    const resposta = await fetch(`${API_BASE}/carteira-investimentos`);
    const dados = await resposta.json();

    totalInvestidoEl.innerHTML = `Total investido: <strong>${formatarMoeda(dados.total_investido)}</strong>`;
    renderizarTabelaCarteira(dados.por_ativo);
    renderizarGraficoTipo(dados.por_tipo);
}

async function carregarHistoricoMovimentacoes() {
    const resposta = await fetch(`${API_BASE}/movimentacoes-investimentos`);
    const movimentacoes = await resposta.json();
    renderizarTabelaHistorico(movimentacoes);
}

function renderizarTabelaHistorico(movimentacoes) {
    if (movimentacoes.length === 0) {
        corpoTabelaHistorico.innerHTML = '<tr><td colspan="6">Nenhuma movimentação registrada ainda.</td></tr>';
        return;
    }

    corpoTabelaHistorico.innerHTML = '';
    for (const mov of movimentacoes) {
        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Data">${formatarData(mov.data)}</td>
            <td data-rotulo="Ativo">${mov.ativo_nome}</td>
            <td data-rotulo="Qtd.">${Number(mov.quantidade).toLocaleString('pt-BR')}</td>
            <td data-rotulo="Preço Unit.">${formatarMoeda(mov.preco_unitario)}</td>
            <td data-rotulo="Valor Total">${formatarMoeda(mov.valor_total)}</td>
            <td class="acoes-linha"><button type="button" class="secundario apagar" data-id="${mov.id}">Apagar</button></td>
        `;
        corpoTabelaHistorico.appendChild(linha);
    }

    corpoTabelaHistorico.querySelectorAll('.apagar').forEach((botao) => {
        botao.addEventListener('click', async () => {
            if (!confirm('Apagar essa movimentação? Isso também recalcula sua carteira.')) return;

            await fetch(`${API_BASE}/movimentacoes-investimentos/${botao.dataset.id}`, { method: 'DELETE' });
            await carregarHistoricoMovimentacoes();
            await carregarCarteira();
        });
    });
}

function formatarData(dataIso) {
    const [ano, mes, dia] = dataIso.split('-');
    return `${dia}/${mes}/${ano}`;
}

function renderizarTabelaCarteira(porAtivo) {
    if (porAtivo.length === 0) {
        corpoTabelaCarteira.innerHTML = '<tr><td colspan="4">Nenhum investimento cadastrado ainda.</td></tr>';
        return;
    }

    corpoTabelaCarteira.innerHTML = '';
    for (const item of porAtivo) {
        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Ativo">${item.ativo_nome} <small>(${ROTULOS_TIPO_ATIVO[item.tipo_ativo]})</small></td>
            <td data-rotulo="Quantidade">${Number(item.quantidade_total).toLocaleString('pt-BR')}</td>
            <td data-rotulo="Preço Médio">${formatarMoeda(item.preco_medio)}</td>
            <td data-rotulo="Valor Investido">${formatarMoeda(item.valor_investido_total)}</td>
        `;
        corpoTabelaCarteira.appendChild(linha);
    }
}

function renderizarGraficoTipo(porTipo) {
    if (typeof Chart === 'undefined' || porTipo.length === 0) {
        canvasGraficoTipo.hidden = true;
        mensagemSemInvestimentos.hidden = false;
        if (grafico) grafico.destroy();
        return;
    }

    canvasGraficoTipo.hidden = false;
    mensagemSemInvestimentos.hidden = true;

    const rotulos = porTipo.map((item) => ROTULOS_TIPO_ATIVO[item.tipo_ativo] ?? item.tipo_ativo);
    const valores = porTipo.map((item) => item.valor_investido_total);

    if (grafico) {
        grafico.destroy();
    }

    grafico = new Chart(canvasGraficoTipo, {
        type: 'doughnut',
        data: {
            labels: rotulos,
            datasets: [{
                data: valores,
                backgroundColor: ['#ea6524', '#f2a13c', '#1a9c6d', '#3c8cf2', '#8e44ad', '#d64545'],
            }],
        },
        options: {
            plugins: { legend: { position: 'bottom' } },
        },
    });
}

formAtivo.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const dados = {
        nome: document.getElementById('ativo-nome').value,
        tipo_ativo: document.getElementById('ativo-tipo').value,
        subcategoria: document.getElementById('ativo-subcategoria').value,
    };

    await fetch(`${API_BASE}/ativos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    formAtivo.reset();
    exibirMensagemSucesso('Ativo cadastrado com sucesso!');
    await carregarAtivos();
});

formMovimentacao.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const dados = {
        ativo_id: campoMovAtivo.value,
        tipo_movimento: 'compra',
        data: document.getElementById('mov-data').value,
        quantidade: document.getElementById('mov-quantidade').value,
        preco_unitario: document.getElementById('mov-preco-unitario').value,
        valor_total: document.getElementById('mov-valor-total').value,
    };

    await fetch(`${API_BASE}/movimentacoes-investimentos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    formMovimentacao.reset();
    document.getElementById('mov-data').value = new Date().toISOString().slice(0, 10);
    exibirMensagemSucesso('Compra registrada com sucesso!');
    await carregarCarteira();
    await carregarHistoricoMovimentacoes();
});

formRendimento.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const dados = {
        ativo_id: campoRendAtivo.value,
        mes: campoRendMes.value,
        ano: campoRendAno.value,
        valor: document.getElementById('rend-valor').value,
    };

    await fetch(`${API_BASE}/rendimentos-investimentos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    formRendimento.reset();
    exibirMensagemSucesso('Rendimento registrado e lançado como Receita: Investimentos no seu Relatório Mensal!');
});

function exibirMensagemSucesso(texto) {
    mensagemSucesso.textContent = texto;
    mensagemSucesso.hidden = false;
    setTimeout(() => { mensagemSucesso.hidden = true; }, 3000);
}

async function iniciar() {
    iniciarSeletoresData();
    await carregarAtivos();
    await carregarCarteira();
    await carregarHistoricoMovimentacoes();
}

iniciar();
