const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');
const corpoTabela = document.getElementById('corpo-tabela-orcamento');
const form = document.getElementById('form-orcamento');
const botaoCopiar = document.getElementById('botao-copiar-mes-anterior');
const mensagemSucesso = document.getElementById('mensagem-sucesso');

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
    for (let ano = anoAtual - 1; ano <= anoAtual + 1; ano++) {
        const opcao = document.createElement('option');
        opcao.value = ano;
        opcao.textContent = ano;
        campoAno.appendChild(opcao);
    }
    campoAno.value = anoAtual;

    campoMes.addEventListener('change', carregarOrcamento);
    campoAno.addEventListener('change', carregarOrcamento);
}

function formatarMoeda(valor) {
    return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

async function carregarOrcamento() {
    mensagemSucesso.hidden = true;
    const mes = campoMes.value;
    const ano = campoAno.value;

    const resposta = await fetch(`${API_BASE}/orcamento-mensal?mes=${mes}&ano=${ano}`);
    const dados = await resposta.json();

    renderizarTabela(dados.itens);
}

function renderizarTabela(itens) {
    if (itens.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="3">Nenhuma categoria cadastrada ainda.</td></tr>';
        return;
    }

    corpoTabela.innerHTML = '';
    let tipoAnterior = null;

    for (const item of itens) {
        // Insere uma linha de "cabeçalho de grupo" quando o tipo muda
        if (item.categoria_tipo !== tipoAnterior) {
            const linhaGrupo = document.createElement('tr');
            linhaGrupo.innerHTML = `<td colspan="3" class="grupo-categoria">${ROTULOS_TIPO[item.categoria_tipo]}</td>`;
            corpoTabela.appendChild(linhaGrupo);
            tipoAnterior = item.categoria_tipo;
        }

        const previsto = Number(item.valor_previsto);
        const realizado = Number(item.valor_realizado);
        const estourou = item.categoria_tipo !== 'receita' && previsto > 0 && realizado > previsto;

        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Categoria">${item.categoria_nome}</td>
            <td data-rotulo="Previsto">
                <input type="number" step="0.01" min="0"
                    class="input-previsto"
                    data-categoria-id="${item.categoria_id}"
                    value="${previsto > 0 ? previsto : ''}"
                    placeholder="0,00">
            </td>
            <td data-rotulo="Realizado" class="${estourou ? 'valor-estourado' : ''}">
                ${formatarMoeda(realizado)}
            </td>
        `;
        corpoTabela.appendChild(linha);
    }
}

form.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const inputs = corpoTabela.querySelectorAll('.input-previsto');
    const itens = Array.from(inputs).map((input) => ({
        categoria_id: input.dataset.categoriaId,
        valor_previsto: input.value === '' ? 0 : input.value,
    }));

    await fetch(`${API_BASE}/orcamento-mensal`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mes: campoMes.value, ano: campoAno.value, itens }),
    });

    mensagemSucesso.hidden = false;
    setTimeout(() => { mensagemSucesso.hidden = true; }, 3000);
});

botaoCopiar.addEventListener('click', async () => {
    if (!confirm('Isso vai copiar os valores previstos do mês anterior para as categorias que ainda não têm valor definido neste mês. Continuar?')) {
        return;
    }

    await fetch(`${API_BASE}/orcamento-mensal/copiar-mes-anterior?mes=${campoMes.value}&ano=${campoAno.value}`, {
        method: 'POST',
    });

    await carregarOrcamento();
});

iniciarSeletores();
carregarOrcamento();
