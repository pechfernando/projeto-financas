<?php

class FormaPagamento
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT id, nome, tipo, limite_credito, ativo
                FROM formas_pagamento
                WHERE usuario_id = :usuario_id";

        if ($apenasAtivas) {
            $sql .= " AND ativo = 1";
        }

        $sql .= " ORDER BY nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO formas_pagamento (usuario_id, nome, tipo, limite_credito)
             VALUES (:usuario_id, :nome, :tipo, :limite_credito)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'limite_credito' => $dados['limite_credito'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
