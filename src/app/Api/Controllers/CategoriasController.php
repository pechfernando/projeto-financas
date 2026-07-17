<?php

class CategoriasController
{
    public function __construct(private Categoria $model)
    {
    }

    public function listar(array $parametros): void
    {
        // Por padrão só traz as ativas (usado nos formulários de lançamento).
        // A tela de Configurações passa ?todas=1 para poder gerenciar
        // (e reativar) as categorias desativadas também.
        $apenasAtivas = !isset($_GET['todas']);
        $categorias = $this->model->listar(usuarioAtualId(), $apenasAtivas);
        jsonResponse($categorias);
    }

    public function buscar(array $parametros): void
    {
        $categoria = $this->model->buscarPorId((int) $parametros['id'], usuarioAtualId());

        if (!$categoria) {
            jsonError('Categoria não encontrada', 404);
        }

        jsonResponse($categoria);
    }

    public function criar(array $parametros): void
    {
        $dados = corpoRequisicao();
        $erro = $this->validar($dados);
        if ($erro) {
            jsonError($erro, 422);
        }

        $dados['usuario_id'] = usuarioAtualId();

        try {
            $id = $this->model->criar($dados);
        } catch (PDOException $e) {
            jsonError($this->mensagemDuplicidade($e), 422);
        }

        jsonResponse(['id' => $id, 'mensagem' => 'Categoria criada com sucesso'], 201);
    }

    public function atualizar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Categoria não encontrada', 404);
        }

        $dados = corpoRequisicao();
        $erro = $this->validar($dados);
        if ($erro) {
            jsonError($erro, 422);
        }

        try {
            $this->model->atualizar($id, $usuarioId, $dados);
        } catch (PDOException $e) {
            jsonError($this->mensagemDuplicidade($e), 422);
        }

        jsonResponse(['mensagem' => 'Categoria atualizada com sucesso']);
    }

    public function apagar(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $id = (int) $parametros['id'];

        if (!$this->model->buscarPorId($id, $usuarioId)) {
            jsonError('Categoria não encontrada', 404);
        }

        $resultado = $this->model->apagar($id, $usuarioId);

        if ($resultado !== true) {
            jsonError($resultado, 409);
        }

        jsonResponse(['mensagem' => 'Categoria excluída com sucesso']);
    }
    /**
     * Validação básica dos dados recebidos. Devolve uma mensagem de erro
     * (string) se algo estiver errado, ou null se estiver tudo certo.
     */
    private function validar(array $dados): ?string
    {
        if (empty($dados['tipo'])) {
            return 'O campo tipo é obrigatório';
        }
        if (empty($dados['nome'])) {
            return 'O campo nome é obrigatório';
        }
        return null;
    }

    /**
     * A tabela categorias tem uma restrição única (usuario_id, tipo, nome).
     * Como "remover" aqui só desativa (não apaga a linha), é possível tentar
     * criar/renomear uma categoria para um nome que já existe, só que
     * desativado. Traduz esse erro do banco numa mensagem compreensível.
     */
    private function mensagemDuplicidade(PDOException $e): string
    {
        if ((string) $e->getCode() === '23000') {
            return 'Já existe uma categoria com esse tipo e nome. Se ela estiver desativada, você pode reativá-la na lista abaixo.';
        }
        return 'Erro ao salvar categoria: ' . $e->getMessage();
    }
}
