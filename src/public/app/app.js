const API_BASE = '/api';

const form = document.getElementById('form-lancamento');
const campoId = document.getElementById('lancamento-id');
const campoValor = document.getElementById('valor');
const campoData = document.getElementById('data');
const campoCategoria = document.getElementById('categoria');
const campoFormaPagamento = document.getElementById('forma-pagamento');
const campoDescricao = document.getElementById('descricao');
const campoStatusPagamento = document.getElementById('status-pagamento');
const tituloFormulario = document.getElementById('titulo-formulario');
const botaoCancelar = document.getElementById('botao-cancelar');
const mensagemErro = document.getElementById('mensagem-erro');
const corpoTabela = document.getElementById('corpo-tabela-lancamentos');

let categorias = [];
let formasPagamento = [];

async function iniciar() {
    await Promise.all([carregarCategorias(), carregarFormasPagamento()]);
    await carregarLancamentos();

    // Preenche a data com hoje, por conveniência
    campoData.value = new Date().toISOString().slice(0, 10);
}

async function carregarCategorias() {
    const resposta = await fetch(`${API_BASE}/categorias`);
    categorias = await resposta.json();

    campoCategoria.innerHTML = '<option value="">Escolher...</option>';
    for (const categoria of categorias) {
        const opcao = document.createElement('option');
        opcao.value = categoria.id;
        opcao.textContent = `${rotuloTipo(categoria.tipo)}: ${categoria.nome}`;
        campoCategoria.appendChild(opcao);
    }
}

async function carregarFormasPagamento() {
    const resposta = await fetch(`${API_BASE}/formas-pagamento`);
    formasPagamento = await resposta.json();

    campoFormaPagamento.innerHTML = '<option value="">Escolher...</option>';
    for (const forma of formasPagamento) {
        const opcao = document.createElement('option');
        opcao.value = forma.id;
        opcao.textContent = forma.nome;
        campoFormaPagamento.appendChild(opcao);
    }
}

async function carregarLancamentos() {
    const resposta = await fetch(`${API_BASE}/lancamentos`);
    const lancamentos = await resposta.json();
    renderizarTabela(lancamentos);
}

function renderizarTabela(lancamentos) {
    if (lancamentos.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="7">Nenhum lançamento ainda.</td></tr>';
        return;
    }

    corpoTabela.innerHTML = '';

    for (const l of lancamentos) {
        const linha = document.createElement('tr');

        const dataFormatada = new Date(l.data + 'T00:00:00').toLocaleDateString('pt-BR');
        const valorFormatado = Number(l.valor).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
        const classeStatus = l.status_pagamento === 'pago' ? 'status-pago' : 'status-pendente';
        const rotuloStatus = l.status_pagamento === 'pago' ? 'Pago' : 'Pendente';

        linha.innerHTML = `
            <td data-rotulo="Data">${dataFormatada}</td>
            <td data-rotulo="Categoria">${rotuloTipo(l.categoria_tipo)}: ${l.categoria_nome}</td>
            <td data-rotulo="Descrição">${l.descricao ?? ''}</td>
            <td data-rotulo="Forma de Pagamento">${l.forma_pagamento_nome}</td>
            <td data-rotulo="Status"><span class="${classeStatus}">${rotuloStatus}</span></td>
            <td data-rotulo="Valor">${valorFormatado}</td>
            <td class="acoes-linha">
                <button type="button" data-acao="editar" data-id="${l.id}">Editar</button>
                <button type="button" data-acao="apagar" data-id="${l.id}" class="apagar">Apagar</button>
            </td>
        `;
        corpoTabela.appendChild(linha);
    }
}

function rotuloTipo(tipo) {
    const rotulos = {
        fixa: 'Fixa',
        variavel: 'Variável',
        receita: 'Receita',
        dividas_parcelados: 'Dívidas e Parcelados',
    };
    return rotulos[tipo] ?? tipo;
}

form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    esconderErro();

    const dados = {
        categoria_id: campoCategoria.value,
        forma_pagamento_id: campoFormaPagamento.value,
        valor: campoValor.value,
        data: campoData.value,
        descricao: campoDescricao.value,
        status_pagamento: campoStatusPagamento.value,
    };

    const id = campoId.value;
    const url = id ? `${API_BASE}/lancamentos/${id}` : `${API_BASE}/lancamentos`;
    const metodo = id ? 'PUT' : 'POST';

    const resposta = await fetch(url, {
        method: metodo,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    const resultado = await resposta.json();

    if (!resposta.ok) {
        mostrarErro(resultado.erro ?? 'Erro ao salvar lançamento');
        return;
    }

    // NOVO: substitui automaticamente o valor do recorrente vinculado a essa
    // categoria, se existir e o valor lançado for diferente do cadastrado.
    await sincronizarValorRecorrente(dados.categoria_id, dados.valor);

    limparFormulario();
    await carregarLancamentos();
});

async function sincronizarValorRecorrente(categoriaId, valorLancado) {
    try {
        const resposta = await fetch(`${API_BASE}/recorrentes/por-categoria/${categoriaId}`);
        if (!resposta.ok) return;

        const recorrente = await resposta.json();
        if (!recorrente) return; // nenhum recorrente vinculado a essa categoria

        const valorNovo = parseFloat(valorLancado);
        const valorAtualRecorrente = parseFloat(recorrente.valor_mensal);

        if (valorNovo !== valorAtualRecorrente) {
            await fetch(`${API_BASE}/recorrentes/${recorrente.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tipo: recorrente.tipo,
                    categoria_id: recorrente.categoria_id,
                    descricao: recorrente.descricao,
                    valor_mensal: valorNovo,
                    data_inicio: recorrente.data_inicio,
                    data_fim: recorrente.data_fim,
                    ativo: recorrente.ativo,
                }),
            });
        }
    } catch (erro) {
        // Falha silenciosa proposital: a substituição automática é uma
        // conveniência, não pode travar o fluxo normal de lançar algo.
        console.error('Não foi possível sincronizar o valor do recorrente:', erro);
    }
}

corpoTabela.addEventListener('click', async (evento) => {
    const botao = evento.target.closest('button');
    if (!botao) return;

    const id = botao.dataset.id;

    if (botao.dataset.acao === 'editar') {
        await preencherFormularioParaEdicao(id);
    }

    if (botao.dataset.acao === 'apagar') {
        if (!confirm('Tem certeza que deseja apagar esse lançamento?')) return;
        await fetch(`${API_BASE}/lancamentos/${id}`, { method: 'DELETE' });
        await carregarLancamentos();
    }
});

async function preencherFormularioParaEdicao(id) {
    const resposta = await fetch(`${API_BASE}/lancamentos/${id}`);
    const lancamento = await resposta.json();

    campoId.value = lancamento.id;
    campoValor.value = lancamento.valor;
    campoData.value = lancamento.data;
    campoCategoria.value = lancamento.categoria_id;
    campoFormaPagamento.value = lancamento.forma_pagamento_id;
    campoDescricao.value = lancamento.descricao ?? '';
    campoStatusPagamento.value = lancamento.status_pagamento;

    tituloFormulario.textContent = 'Editar Lançamento';
    botaoCancelar.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

botaoCancelar.addEventListener('click', limparFormulario);

function limparFormulario() {
    form.reset();
    campoId.value = '';
    campoData.value = new Date().toISOString().slice(0, 10);
    tituloFormulario.textContent = 'Novo Lançamento';
    botaoCancelar.hidden = true;
    esconderErro();
}

function mostrarErro(texto) {
    mensagemErro.textContent = texto;
    mensagemErro.hidden = false;
}

function esconderErro() {
    mensagemErro.hidden = true;
}

iniciar();
