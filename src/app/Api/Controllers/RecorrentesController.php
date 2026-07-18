<?php

class RecorrentesController
{
    public function __construct(private RecorrenteFinanceiro $recorrentes)
    {
    }

    public function listar(): void
    {
        $todas = isset($_GET['todas']) && $_GET['todas'] == '1';
        jsonResponse($this->recorrentes->listar(usuarioAtualId(), $todas));
    }

    public function buscar(array $parametros): void
    {
        $item = $this->recorrentes->buscar(usuarioAtualId(), (int) $parametros['id']);
        if (!$item) {
            jsonError('Lançamento recorrente não encontrado', 404);
            return;
        }
        jsonResponse($item);
    }

    public function criar(): void
    {
        $dados = corpoRequisicao();
        try {
            $id = $this->recorrentes->criar(usuarioAtualId(), $dados);
            jsonResponse(['id' => $id], 201);
        } catch (PDOException $e) {
            jsonError('Erro ao criar lançamento recorrente: ' . $e->getMessage(), 500);
        }
    }

    public function atualizar(array $parametros): void
    {
        $dados = corpoRequisicao();
        try {
            $this->recorrentes->atualizar(usuarioAtualId(), (int) $parametros['id'], $dados);
            jsonResponse(['sucesso' => true]);
        } catch (PDOException $e) {
            jsonError('Erro ao atualizar lançamento recorrente: ' . $e->getMessage(), 500);
        }
    }

    public function buscarPorCategoria(array $parametros): void
    {
        $item = $this->recorrentes->buscarPorCategoria(usuarioAtualId(), (int) $parametros['categoriaId']);
        jsonResponse($item); // pode devolver null — o frontend já trata isso
    }

    public function apagar(array $parametros): void
    {
        $this->recorrentes->apagar(usuarioAtualId(), (int) $parametros['id']);
        jsonResponse(['sucesso' => true]);
    }
}
