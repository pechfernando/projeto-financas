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
        $id = $this->recorrentes->criar(usuarioAtualId(), $dados);
        jsonResponse(['id' => $id], 201);
    }

    public function atualizar(array $parametros): void
    {
        $dados = corpoRequisicao();
        $this->recorrentes->atualizar(usuarioAtualId(), (int) $parametros['id'], $dados);
        jsonResponse(['sucesso' => true]);
    }

    public function apagar(array $parametros): void
    {
        $this->recorrentes->apagar(usuarioAtualId(), (int) $parametros['id']);
        jsonResponse(['sucesso' => true]);
    }
}
