<?php

class LancamentosController
{
    public function __construct(private Lancamento $model)
    {
    }

    public function listar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : null;
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : null;

        $lancamentos = $this->model->listar($usuarioId, $mes, $ano);
        jsonResponse($lancamentos);
    }

    public function buscar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $lancamento = $this->model->buscarPorId((int) $parametros['id'], $usuarioId);

        if (!$lancamento) {
            jsonError('Lançamento não encontrado', 404);
        }

        jsonResponse($lancamento);
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();
        $erro = $this->validar($dados);
        if ($erro) {
            jsonError($erro, 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criar($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Lançamento criado com sucesso'], 201);
    }

    public function atualizar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Lançamento não encontrado', 404);
        }

        $dados = corpoRequisicao();
        $erro = $this->validar($dados);
        if ($erro) {
            jsonError($erro, 422);
        }

        $this->model->atualizar($id, $usuarioId, $dados);
        jsonResponse(['mensagem' => 'Lançamento atualizado com sucesso']);
    }

    public function apagar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Lançamento não encontrado', 404);
        }

        $this->model->apagar($id, $usuarioId);
        jsonResponse(['mensagem' => 'Lançamento apagado com sucesso']);
    }

    /**
     * Validação básica dos dados recebidos. Devolve uma mensagem de erro
     * (string) se algo estiver errado, ou null se estiver tudo certo.
     */
    private function validar(array $dados): ?string
    {
        if (empty($dados['categoria_id'])) {
            return 'O campo categoria é obrigatório';
        }
        if (empty($dados['forma_pagamento_id'])) {
            return 'O campo forma de pagamento é obrigatório';
        }
        if (!isset($dados['valor']) || !is_numeric($dados['valor']) || $dados['valor'] <= 0) {
            return 'O valor deve ser um número maior que zero';
        }
        if (empty($dados['data'])) {
            return 'O campo data é obrigatório';
        }
        return null;
    }
}
