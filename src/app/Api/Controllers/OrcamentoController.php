<?php

class OrcamentoController
{
    public function __construct(private Orcamento $model)
    {
    }

    public function listar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

        $itens = $this->model->listarComRealizado($usuarioId, $mes, $ano);
        jsonResponse(['mes' => $mes, 'ano' => $ano, 'itens' => $itens]);
    }

    /**
     * Salva o orçamento inteiro do mês de uma vez (vários itens),
     * pra o frontend poder mandar tudo junto ao clicar em "Salvar".
     */
    public function salvar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $dados = corpoRequisicao();

        if (empty($dados['mes']) || empty($dados['ano']) || !isset($dados['itens'])) {
            jsonError('Campos mes, ano e itens são obrigatórios', 422);
        }

        foreach ($dados['itens'] as $item) {
            if (!isset($item['categoria_id'], $item['valor_previsto'])) {
                continue;
            }
            $this->model->salvar(
                $usuarioId,
                (int) $item['categoria_id'],
                (int) $dados['mes'],
                (int) $dados['ano'],
                (float) $item['valor_previsto']
            );
        }

        jsonResponse(['mensagem' => 'Orçamento salvo com sucesso']);
    }

    public function copiarMesAnterior(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

        $quantidade = $this->model->copiarMesAnterior($usuarioId, $mes, $ano);
        jsonResponse(['mensagem' => 'Valores copiados do mês anterior', 'categorias_copiadas' => $quantidade]);
    }
}
