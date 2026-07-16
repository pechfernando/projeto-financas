<?php

class AtivosController
{
    public function __construct(private Ativo $model)
    {
    }

    public function listar(array $parametros): void
    {
        jsonResponse($this->model->listar(usuarioAtualId()));
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['nome']) || empty($dados['tipo_ativo'])) {
            jsonError('Os campos nome e tipo do ativo são obrigatórios', 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Ativo cadastrado com sucesso'], 201);
    }
}
