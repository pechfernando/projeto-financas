const API_BASE = '/api';

// ---------------------------------------------------------------------
// Categorias
// ---------------------------------------------------------------------

const formCategoria = document.getElementById('form-categoria');
const campoCategoriaId = document.getElementById('categoria-id');
const campoCategoriaAtivo = document.getElementById('categoria-ativo');
const campoCategoriaTipo = document.getElementById('categoria-tipo');
const campoCategoriaNome = document.getElementById('categoria-nome');
const campoCategoriaDescricao = document.getElementById('categoria-descricao');
const campoCategoriaReembolso = document.getElementById('categoria-reembolso');
const tituloFormCategoria = document.getElementById('titulo-form-categoria');
const botaoSalvarCategoria = document.getElementById('botao-salvar-categoria');
const botaoCancelarCategoria = document.getElementById('botao-cancelar-categoria');
const mensagemErroCategoria = document.getElementById('mensagem-erro-categoria');
const mensagemSucessoCategoria = document.getElementById('mensagem-sucesso-categoria');
const corpoTabelaCategorias = document.getElementById('corpo-tabela-categorias');

const ROTULOS_TIPO_CATEGORIA = {
    fixa: 'Fixa',
    variavel: 'Variável',
    receita: 'Receita',
    dividas_parcelados: 'Dívidas e Parcelados',
};

async function carregarCategorias() {
    const resposta = await fetch(`${API_BASE}/categorias?todas=1`);
    const categorias = await resposta.json();
    renderizarTabelaCategorias(categorias);
}

function renderizarTabelaCategorias(categorias) {
    if (categorias.length === 0) {
        corpoTabelaCategorias.innerHTML = '<tr><td colspan="5">Nenhuma categoria cadastrada ainda.</td></tr>';
        return;
    }

    corpoTabelaCategorias.innerHTML = '';

    for (const categoria of categorias) {
        const linha = document.createElement('tr');
        const statusClasse = categoria.ativo == 1 ? 'status-pago' : 'status-pendente';
        const statusTexto = categoria.ativo == 1 ? 'Ativa' : 'Inativa';
        const rotuloAcaoAtivo = categoria.ativo == 1 ? 'Desativar' : 'Reativar';

        linha.innerHTML = `
            <td data-rotulo="Tipo">${ROTULOS_TIPO_CATEGORIA[categoria.tipo] ?? categoria.tipo}</td>
            <td data-rotulo="Nome">${categoria.nome}${categoria.e_reembolso == 1 ? ' <span class="ajuda-inline">(reembolso)</span>' : ''}</td>
            <td data-rotulo="Descrição">${categoria.descricao ?? ''}</td>
            <td data-rotulo="Status"><span class="${statusClasse}">${statusTexto}</span></td>
            <td class="acoes-linha">
                <button type="button" data-acao="editar" data-id="${categoria.id}">Editar</button>
                <button type="button" data-acao="alternar-ativo" data-id="${categoria.id}" class="${categoria.ativo == 1 ? 'apagar' : 'secundario'}">${rotuloAcaoAtivo}</button>
            </td>
        `;
        corpoTabelaCategorias.appendChild(linha);
    }
}

formCategoria.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    esconderMensagens(mensagemErroCategoria, mensagemSucessoCategoria);

    const dados = {
        tipo: campoCategoriaTipo.value,
        nome: campoCategoriaNome.value.trim(),
        descricao: campoCategoriaDescricao.value.trim() || null,
        e_reembolso: campoCategoriaReembolso.checked,
        ativo: campoCategoriaAtivo.value === '1',
    };

    const id = campoCategoriaId.value;
    const url = id ? `${API_BASE}/categorias/${id}` : `${API_BASE}/categorias`;
    const metodo = id ? 'PUT' : 'POST';

    const resposta = await fetch(url, {
        method: metodo,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    const resultado = await resposta.json();

    if (!resposta.ok) {
        mostrarMensagem(mensagemErroCategoria, resultado.erro ?? 'Erro ao salvar categoria');
        return;
    }

    limparFormularioCategoria();
    mostrarMensagem(mensagemSucessoCategoria, 'Categoria salva com sucesso!');
    await carregarCategorias();
});

corpoTabelaCategorias.addEventListener('click', async (evento) => {
    const botao = evento.target.closest('button');
    if (!botao) return;

    const id = botao.dataset.id;

    if (botao.dataset.acao === 'editar') {
        await preencherFormularioCategoriaParaEdicao(id);
    }

    if (botao.dataset.acao === 'alternar-ativo') {
        await alternarAtivoCategoria(id);
    }
});

async function preencherFormularioCategoriaParaEdicao(id) {
    const resposta = await fetch(`${API_BASE}/categorias/${id}`);
    const categoria = await resposta.json();

    campoCategoriaId.value = categoria.id;
    campoCategoriaAtivo.value = categoria.ativo;
    campoCategoriaTipo.value = categoria.tipo;
    campoCategoriaNome.value = categoria.nome;
    campoCategoriaDescricao.value = categoria.descricao ?? '';
    campoCategoriaReembolso.checked = categoria.e_reembolso == 1;

    tituloFormCategoria.textContent = 'Editar Categoria';
    botaoSalvarCategoria.textContent = 'Salvar Alterações';
    botaoCancelarCategoria.hidden = false;
    esconderMensagens(mensagemErroCategoria, mensagemSucessoCategoria);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function alternarAtivoCategoria(id) {
    const resposta = await fetch(`${API_BASE}/categorias/${id}`);
    const categoria = await resposta.json();

    const novoStatusAtivo = categoria.ativo == 1 ? 0 : 1;
    const pergunta = novoStatusAtivo === 0
        ? 'Desativar essa categoria? Os lançamentos que já usam ela continuam intactos, mas ela deixa de aparecer para novos lançamentos.'
        : 'Reativar essa categoria?';

    if (!confirm(pergunta)) return;

    await fetch(`${API_BASE}/categorias/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            tipo: categoria.tipo,
            nome: categoria.nome,
            descricao: categoria.descricao,
            e_reembolso: categoria.e_reembolso == 1,
            ativo: novoStatusAtivo === 1,
        }),
    });

    await carregarCategorias();
}

botaoCancelarCategoria.addEventListener('click', limparFormularioCategoria);

function limparFormularioCategoria() {
    formCategoria.reset();
    campoCategoriaId.value = '';
    campoCategoriaAtivo.value = '1';
    tituloFormCategoria.textContent = 'Nova Categoria';
    botaoSalvarCategoria.textContent = 'Adicionar Categoria';
    botaoCancelarCategoria.hidden = true;
}

// ---------------------------------------------------------------------
// Formas de Pagamento
// ---------------------------------------------------------------------

const formForma = document.getElementById('form-forma');
const campoFormaId = document.getElementById('forma-id');
const campoFormaAtivo = document.getElementById('forma-ativo');
const campoFormaNome = document.getElementById('forma-nome');
const campoFormaTipo = document.getElementById('forma-tipo');
const campoFormaLimite = document.getElementById('forma-limite');
const campoLimiteCredito = document.getElementById('campo-limite-credito');
const tituloFormForma = document.getElementById('titulo-form-forma');
const botaoSalvarForma = document.getElementById('botao-salvar-forma');
const botaoCancelarForma = document.getElementById('botao-cancelar-forma');
const mensagemErroForma = document.getElementById('mensagem-erro-forma');
const mensagemSucessoForma = document.getElementById('mensagem-sucesso-forma');
const corpoTabelaFormas = document.getElementById('corpo-tabela-formas');

const ROTULOS_TIPO_FORMA = {
    debito_dinheiro_pix: 'Débito / Dinheiro / Pix',
    cartao_credito: 'Cartão de Crédito',
};

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function atualizarVisibilidadeLimite() {
    campoLimiteCredito.hidden = campoFormaTipo.value !== 'cartao_credito';
}
campoFormaTipo.addEventListener('change', atualizarVisibilidadeLimite);
atualizarVisibilidadeLimite();

async function carregarFormas() {
    const resposta = await fetch(`${API_BASE}/formas-pagamento?todas=1`);
    const formas = await resposta.json();
    renderizarTabelaFormas(formas);
}

function renderizarTabelaFormas(formas) {
    if (formas.length === 0) {
        corpoTabelaFormas.innerHTML = '<tr><td colspan="5">Nenhuma forma de pagamento cadastrada ainda.</td></tr>';
        return;
    }

    corpoTabelaFormas.innerHTML = '';

    for (const forma of formas) {
        const linha = document.createElement('tr');
        const statusClasse = forma.ativo == 1 ? 'status-pago' : 'status-pendente';
        const statusTexto = forma.ativo == 1 ? 'Ativa' : 'Inativa';
        const rotuloAcaoAtivo = forma.ativo == 1 ? 'Desativar' : 'Reativar';

        linha.innerHTML = `
            <td data-rotulo="Nome">${forma.nome}</td>
            <td data-rotulo="Tipo">${ROTULOS_TIPO_FORMA[forma.tipo] ?? forma.tipo}</td>
            <td data-rotulo="Limite">${forma.limite_credito ? formatarMoeda(forma.limite_credito) : '—'}</td>
            <td data-rotulo="Status"><span class="${statusClasse}">${statusTexto}</span></td>
            <td class="acoes-linha">
                <button type="button" data-acao="editar" data-id="${forma.id}">Editar</button>
                <button type="button" data-acao="alternar-ativo" data-id="${forma.id}" class="${forma.ativo == 1 ? 'apagar' : 'secundario'}">${rotuloAcaoAtivo}</button>
            </td>
        `;
        corpoTabelaFormas.appendChild(linha);
    }
}

formForma.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    esconderMensagens(mensagemErroForma, mensagemSucessoForma);

    const dados = {
        nome: campoFormaNome.value.trim(),
        tipo: campoFormaTipo.value,
        limite_credito: campoFormaTipo.value === 'cartao_credito' && campoFormaLimite.value
            ? campoFormaLimite.value
            : null,
        ativo: campoFormaAtivo.value === '1',
    };

    const id = campoFormaId.value;
    const url = id ? `${API_BASE}/formas-pagamento/${id}` : `${API_BASE}/formas-pagamento`;
    const metodo = id ? 'PUT' : 'POST';

    const resposta = await fetch(url, {
        method: metodo,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
    });

    const resultado = await resposta.json();

    if (!resposta.ok) {
        mostrarMensagem(mensagemErroForma, resultado.erro ?? 'Erro ao salvar forma de pagamento');
        return;
    }

    limparFormularioForma();
    mostrarMensagem(mensagemSucessoForma, 'Forma de pagamento salva com sucesso!');
    await carregarFormas();
});

corpoTabelaFormas.addEventListener('click', async (evento) => {
    const botao = evento.target.closest('button');
    if (!botao) return;

    const id = botao.dataset.id;

    if (botao.dataset.acao === 'editar') {
        await preencherFormularioFormaParaEdicao(id);
    }

    if (botao.dataset.acao === 'alternar-ativo') {
        await alternarAtivoForma(id);
    }
});

async function preencherFormularioFormaParaEdicao(id) {
    const resposta = await fetch(`${API_BASE}/formas-pagamento/${id}`);
    const forma = await resposta.json();

    campoFormaId.value = forma.id;
    campoFormaAtivo.value = forma.ativo;
    campoFormaNome.value = forma.nome;
    campoFormaTipo.value = forma.tipo;
    campoFormaLimite.value = forma.limite_credito ?? '';
    atualizarVisibilidadeLimite();

    tituloFormForma.textContent = 'Editar Forma de Pagamento';
    botaoSalvarForma.textContent = 'Salvar Alterações';
    botaoCancelarForma.hidden = false;
    esconderMensagens(mensagemErroForma, mensagemSucessoForma);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function alternarAtivoForma(id) {
    const resposta = await fetch(`${API_BASE}/formas-pagamento/${id}`);
    const forma = await resposta.json();

    const novoStatusAtivo = forma.ativo == 1 ? 0 : 1;
    const pergunta = novoStatusAtivo === 0
        ? 'Desativar essa forma de pagamento? Os lançamentos que já usam ela continuam intactos, mas ela deixa de aparecer para novos lançamentos.'
        : 'Reativar essa forma de pagamento?';

    if (!confirm(pergunta)) return;

    await fetch(`${API_BASE}/formas-pagamento/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            nome: forma.nome,
            tipo: forma.tipo,
            limite_credito: forma.limite_credito,
            ativo: novoStatusAtivo === 1,
        }),
    });

    await carregarFormas();
}

botaoCancelarForma.addEventListener('click', limparFormularioForma);

function limparFormularioForma() {
    formForma.reset();
    campoFormaId.value = '';
    campoFormaAtivo.value = '1';
    tituloFormForma.textContent = 'Nova Forma de Pagamento';
    botaoSalvarForma.textContent = 'Adicionar Forma de Pagamento';
    botaoCancelarForma.hidden = true;
    atualizarVisibilidadeLimite();
}

// ---------------------------------------------------------------------
// Utilitários compartilhados
// ---------------------------------------------------------------------

function mostrarMensagem(elemento, texto) {
    elemento.textContent = texto;
    elemento.hidden = false;
    if (elemento === mensagemSucessoCategoria || elemento === mensagemSucessoForma) {
        setTimeout(() => { elemento.hidden = true; }, 3000);
    }
}

function esconderMensagens(...elementos) {
    for (const elemento of elementos) {
        elemento.hidden = true;
    }
}

carregarCategorias();
carregarFormas();
