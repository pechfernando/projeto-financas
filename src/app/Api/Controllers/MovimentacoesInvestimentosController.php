<?php

class MovimentacoesInvestimentosController
{
    public function __construct(private MovimentacaoInvestimento $model)
    {
    }

    public function listar(array $parametros): void
    {
        jsonResponse($this->model->listar(usuarioAtualId()));
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['ativo_id']) || empty($dados['data'])
            || !isset($dados['quantidade']) || !isset($dados['valor_total'])) {
            jsonError('Os campos ativo, data, quantidade e valor total são obrigatórios', 422);
        }

        if (!isset($dados['preco_unitario']) && $dados['quantidade'] > 0) {
            $dados['preco_unitario'] = round($dados['valor_total'] / $dados['quantidade'], 4);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Movimentação registrada com sucesso'], 201);
    }

    public function apagar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $ok = $this->model->apagar((int) $parametros['id'], $usuarioId);

        if (!$ok) {
            jsonError('Movimentação não encontrada', 404);
        }

        jsonResponse(['mensagem' => 'Movimentação apagada com sucesso']);
    }

    public function carteira(array $parametros): void
    {
        $usuarioId = usuarioAtualId();

        $porAtivo = $this->model->resumoPorAtivo($usuarioId);
        $porAtivo = array_map(function ($item) {
            $item['preco_medio'] = $item['quantidade_total'] > 0
                ? round($item['valor_investido_total'] / $item['quantidade_total'], 4)
                : 0;
            return $item;
        }, $porAtivo);

        $porTipo = $this->model->resumoPorTipo($usuarioId);
        $totalGeral = array_sum(array_column($porAtivo, 'valor_investido_total'));

        jsonResponse([
            'por_ativo' => $porAtivo,
            'por_tipo' => $porTipo,
            'total_investido' => $totalGeral,
        ]);
    }
}
