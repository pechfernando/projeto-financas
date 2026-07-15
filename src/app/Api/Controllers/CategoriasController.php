<?php

class CategoriasController
{
    public function __construct(private Categoria $model)
    {
    }

    public function listar(array $parametros): void
    {
        $categorias = $this->model->listar(usuarioAtualId());
        jsonResponse($categorias);
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['tipo']) || empty($dados['nome'])) {
            jsonError('Os campos tipo e nome são obrigatórios', 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Categoria criada com sucesso'], 201);
    }
}
