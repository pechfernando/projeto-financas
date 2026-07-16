<?php

class RendimentosInvestimentosController
{
    public function __construct(private RendimentoInvestimento $model)
    {
    }

    public function listar(array $parametros): void
    {
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : null;
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : null;

        jsonResponse($this->model->listar(usuarioAtualId(), $mes, $ano));
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['ativo_id']) || empty($dados['mes']) || empty($dados['ano'])
            || !isset($dados['valor'])) {
            jsonError('Os campos ativo, mês, ano e valor são obrigatórios', 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Rendimento registrado com sucesso'], 201);
    }
}
