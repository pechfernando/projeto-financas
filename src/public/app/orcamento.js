const API_BASE = '/api';

const campoMes = document.getElementById('mes');
const campoAno = document.getElementById('ano');
const corpoTabela = document.getElementById('corpo-tabela-orcamento');
const form = document.getElementById('form-orcamento');
const botaoCopiar = document.getElementById('botao-copiar-mes-anterior');
const mensagemSucesso = document.getElementById('mensagem-sucesso');
const painelResumo = document.getElementById('resumo-orcamento-geral');

const NOMES_MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

const ROTULOS_TIPO = {
    fixa: 'Despesas Fixas',
    variavel: 'Despesas Variáveis',
    receita: 'Receitas',
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

    calcularERenderizarResumos(dados.itens);
    renderizarTabela(dados.itens);
}

function calcularERenderizarResumos(itens) {
    let recPrevisto = 0, recRealizado = 0;
    let despPrevisto = 0, despRealizado = 0;

    itens.forEach(item => {
        const previsto = Number(item.valor_previsto);
        const realizado = Number(item.valor_realizado);

        if (item.categoria_tipo === 'receita') {
            recPrevisto += previsto;
            recRealizado += realizado;
        } else {
            despPrevisto += previsto;
            despRealizado += realizado;
        }
    });

    const saldoPrevisto = recPrevisto - despPrevisto;
    const saldoRealizado = recRealizado - despRealizado;

    painelResumo.innerHTML = `
        <div class="total-tipo-item">
            <span class="rotulo">Receitas Planejadas</span>
            <span class="valor" style="color: var(--verde);">${formatarMoeda(recRealizado)}</span>
            <span class="ajuda-inline">Meta: ${formatarMoeda(recPrevisto)}</span>
        </div>
        <div class="total-tipo-item">
            <span class="rotulo">Limite de Despesas</span>
            <span class="valor" style="color: ${despRealizado > despPrevisto && despPrevisto > 0 ? 'var(--vermelho)' : 'var(--texto)'};">
                ${formatarMoeda(despRealizado)}
            </span>
            <span class="ajuda-inline">Teto: ${formatarMoeda(despPrevisto)}</span>
        </div>
        <div class="total-tipo-item">
            <span class="rotulo">Resultado Geral</span>
            <span class="valor ${saldoRealizado >= 0 ? 'saldo-positivo' : 'saldo-negativo'}">${formatarMoeda(saldoRealizado)}</span>
            <span class="ajuda-inline">Previsto: ${formatarMoeda(saldoPrevisto)}</span>
        </div>
    `;
}

function renderizarTabela(itens) {
    if (itens.length === 0) {
        corpoTabela.innerHTML = '<tr><td colspan="4">Nenhuma categoria cadastrada ainda.</td></tr>';
        return;
    }

    corpoTabela.innerHTML = '';
    let tipoAnterior = null;

    for (const item of itens) {
        if (item.categoria_tipo !== tipoAnterior) {
            const linhaGrupo = document.createElement('tr');
            linhaGrupo.innerHTML = `<td colspan="4" class="grupo-categoria">${ROTULOS_TIPO[item.categoria_tipo] ?? item.categoria_tipo}</td>`;
            corpoTabela.appendChild(linhaGrupo);
            tipoAnterior = item.categoria_tipo;
        }

        const previsto = Number(item.valor_previsto);
        const realizado = Number(item.valor_realizado);
        
        let infoProgresso = '';
        let classeDisponivel = '';
        let valorDisponivelStr = '';

        if (item.categoria_tipo === 'receita') {
            const dif = realizado - previsto;
            if (previsto > 0) {
                const perc = Math.min((realizado / previsto) * 100, 100);
                infoProgresso = `
                    <div class="progresso-container">
                        <div class="progresso-barra verde" style="width: ${perc}%"></div>
                    </div>
                    <div class="progresso-info">${perc.toFixed(0)}% da meta atingida</div>
                `;
            }
            if (dif >= 0) {
                valorDisponivelStr = `+${formatarMoeda(dif)}`;
                classeDisponivel = 'saldo-positivo';
            } else {
                valorDisponivelStr = `Falta ${formatarMoeda(Math.abs(dif))}`;
                classeDisponivel = 'status-pendente';
            }
        } else {
            const disp = previsto - realizado;
            if (previsto > 0) {
                const perc = (realizado / previsto) * 100;
                let classeCor = 'verde';
                if (perc > 100) classeCor = 'vermelho';
                else if (perc > 80) classeCor = 'amarelo';

                infoProgresso = `
                    <div class="progresso-container">
                        <div class="progresso-barra ${classeCor}" style="width: ${Math.min(perc, 100)}%"></div>
                    </div>
                    <div class="progresso-info">${perc.toFixed(0)}% do limite usado</div>
                `;
            }
            if (disp >= 0) {
                valorDisponivelStr = formatarMoeda(disp);
                classeDisponivel = 'saldo-positivo';
            } else {
                valorDisponivelStr = `Estourou por ${formatarMoeda(Math.abs(disp))}`;
                classeDisponivel = 'saldo-negativo';
            }
        }

        const linha = document.createElement('tr');
        linha.innerHTML = `
            <td data-rotulo="Categoria">
                <strong>${item.categoria_nome}</strong>
                ${infoProgresso}
            </td>
            <td data-rotulo="Previsto">
                <input type="number" step="0.01" min="0"
                    class="input-previsto"
                    data-categoria-id="${item.categoria_id}"
                    value="${previsto > 0 ? previsto : ''}"
                    placeholder="0,00">
            </td>
            <td data-rotulo="Realizado">
                ${formatarMoeda(realizado)}
            </td>
            <td data-rotulo="Disponível" class="col-disponivel ${classeDisponivel}">
                ${valorDisponivelStr}
            </td>
        `;
        corpoTabela.appendChild(linha);
    }

    // Configura os escutadores para salvar automaticamente ao mudar valor ou perder foco
    corpoTabela.querySelectorAll('.input-previsto').forEach(input => {
        input.addEventListener('change', salvarItem);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur(); // Trona o blur responsável por disparar o salvamento
            }
        });
    });
}

async function salvarItem() {
    mensagemSucesso.textContent = 'Salvando...';
    mensagemSucesso.className = 'mensagem-sucesso';
    mensagemSucesso.hidden = false;

    const inputs = corpoTabela.querySelectorAll('.input-previsto');
    const itens = Array.from(inputs).map((input) => ({
        categoria_id: input.dataset.categoriaId,
        valor_previsto: input.value === '' ? 0 : input.value,
    }));

    try {
        await fetch(`${API_BASE}/orcamento-mensal`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mes: campoMes.value, ano: campoAno.value, itens }),
        });

        mensagemSucesso.textContent = 'Alterações salvas!';
        setTimeout(() => {
            if (mensagemSucesso.textContent === 'Alterações salvas!') {
                mensagemSucesso.hidden = true;
            }
        }, 2000);

        // Recarrega dados para atualizar os cards de resumos, colunas disponíveis e barras
        const mes = campoMes.value;
        const ano = campoAno.value;
        const resposta = await fetch(`${API_BASE}/orcamento-mensal?mes=${mes}&ano=${ano}`);
        const dados = await resposta.json();
        calcularERenderizarResumos(dados.itens);
        
        // Apenas atualiza os valores na tela sem recriar toda a tabela para evitar perder o foco
        dados.itens.forEach(item => {
            const input = corpoTabela.querySelector(`.input-previsto[data-categoria-id="${item.categoria_id}"]`);
            if (input) {
                const tr = input.closest('tr');
                const realizadoCel = tr.querySelector('td[data-rotulo="Realizado"]');
                const disponivelCel = tr.querySelector('td[data-rotulo="Disponível"]');
                const previsto = Number(item.valor_previsto);
                const realizado = Number(item.valor_realizado);

                // Atualiza coluna realizado
                realizadoCel.textContent = formatarMoeda(realizado);

                // Atualiza barra de progresso
                const containerForte = tr.querySelector('td[data-rotulo="Categoria"]');
                let infoProgresso = '';
                if (item.categoria_tipo === 'receita') {
                    const dif = realizado - previsto;
                    if (previsto > 0) {
                        const perc = Math.min((realizado / previsto) * 100, 100);
                        infoProgresso = `
                            <div class="progresso-container">
                                <div class="progresso-barra verde" style="width: ${perc}%"></div>
                            </div>
                            <div class="progresso-info">${perc.toFixed(0)}% da meta atingida</div>
                        `;
                    }
                    disponivelCel.className = `col-disponivel ${dif >= 0 ? 'saldo-positivo' : 'status-pendente'}`;
                    disponivelCel.textContent = dif >= 0 ? `+${formatarMoeda(dif)}` : `Falta ${formatarMoeda(Math.abs(dif))}`;
                } else {
                    const disp = previsto - realizado;
                    if (previsto > 0) {
                        const perc = (realizado / previsto) * 100;
                        let classeCor = 'verde';
                        if (perc > 100) classeCor = 'vermelho';
                        else if (perc > 80) classeCor = 'amarelo';

                        infoProgresso = `
                            <div class="progresso-container">
                                <div class="progresso-barra ${classeCor}" style="width: ${Math.min(perc, 100)}%"></div>
                            </div>
                            <div class="progresso-info">${perc.toFixed(0)}% do limite usado</div>
                        `;
                    }
                    disponivelCel.className = `col-disponivel ${disp >= 0 ? 'saldo-positivo' : 'saldo-negativo'}`;
                    disponivelCel.textContent = disp >= 0 ? formatarMoeda(disp) : `Estourou por ${formatarMoeda(Math.abs(disp))}`;
                }

                // Substitui ou remove barra existente
                const barraAntiga = containerForte.querySelector('.progresso-container');
                const infoAntiga = containerForte.querySelector('.progresso-info');
                if (barraAntiga) barraAntiga.remove();
                if (infoAntiga) infoAntiga.remove();

                if (infoProgresso) {
                    const template = document.createElement('div');
                    template.innerHTML = infoProgresso.trim();
                    containerForte.appendChild(template.firstElementChild);
                    containerForte.appendChild(template.lastElementChild);
                }
            }
        });

    } catch (err) {
        mensagemSucesso.textContent = 'Erro ao salvar!';
        mensagemSucesso.className = 'mensagem-erro';
    }
}

botaoCopiar.addEventListener('click', async () => {
    if (!confirm('Isso vai copiar os valores previstos do mês anterior para as categorias que ainda não têm valor definido neste mês (ou estão zeradas). Continuar?')) {
        return;
    }

    await fetch(`${API_BASE}/orcamento-mensal/copiar-mes-anterior?mes=${campoMes.value}&ano=${campoAno.value}`, {
        method: 'POST',
    });

    await carregarOrcamento();
});

iniciarSeletores();
carregarOrcamento();
