<?php

class FormasPagamentoController
{
    public function __construct(private FormaPagamento $model)
    {
    }

    public function listar(array $parametros): void
    {
        // Por padrão só traz as ativas (usado no formulário de lançamento).
        // A tela de Configurações passa ?todas=1 para poder gerenciar
        // (e reativar) as formas de pagamento desativadas também.
        $apenasAtivas = !isset($_GET['todas']);
        $formasPagamento = $this->model->listar(usuarioAtualId(), $apenasAtivas);
        jsonResponse($formasPagamento);
    }

    public function buscar(array $parametros): void
    {
        $forma = $this->model->buscarPorId((int) $parametros['id'], usuarioAtualId());

        if (!$forma) {
            jsonError('Forma de pagamento não encontrada', 404);
        }

        jsonResponse($forma);
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

        jsonResponse(['id' => $id, 'mensagem' => 'Forma de pagamento criada com sucesso'], 201);
    }

    public function atualizar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Forma de pagamento não encontrada', 404);
        }

        $dados = corpoRequisicao();
        $erro = $this->validar($dados);
        if ($erro) {
            jsonError($erro, 422);
        }

        $this->model->atualizar($id, $usuarioId, $dados);
        jsonResponse(['mensagem' => 'Forma de pagamento atualizada com sucesso']);
    }

    public function apagar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Forma de pagamento não encontrada', 404);
        }

        $resultado = $this->model->apagar($id, $usuarioId);

        if ($resultado !== true) {
            jsonError($resultado, 409);
        }

        jsonResponse(['mensagem' => 'Forma de pagamento excluída com sucesso']);
    }

    /**
     * Validação básica dos dados recebidos. Devolve uma mensagem de erro
     * (string) se algo estiver errado, ou null se estiver tudo certo.
     */
    private function validar(array $dados): ?string
    {
        if (empty($dados['nome'])) {
            return 'O campo nome é obrigatório';
        }
        if (empty($dados['tipo'])) {
            return 'O campo tipo é obrigatório';
        }
        if ($dados['tipo'] === 'cartao_credito' && !empty($dados['limite_credito']) && !is_numeric($dados['limite_credito'])) {
            return 'O limite de crédito deve ser um número';
        }
        return null;
    }
}
