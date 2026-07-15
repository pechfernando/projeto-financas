<?php

class FormasPagamentoController
{
    public function __construct(private FormaPagamento $model)
    {
    }

    public function listar(array $parametros): void
    {
        $formasPagamento = $this->model->listar(usuarioAtualId());
        jsonResponse($formasPagamento);
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['nome']) || empty($dados['tipo'])) {
            jsonError('Os campos nome e tipo são obrigatórios', 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Forma de pagamento criada com sucesso'], 201);
    }
}
