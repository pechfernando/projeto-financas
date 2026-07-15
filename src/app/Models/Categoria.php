<?php

class Categoria
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT id, tipo, nome, descricao, e_reembolso, ativo
                FROM categorias
                WHERE usuario_id = :usuario_id";

        if ($apenasAtivas) {
            $sql .= " AND ativo = 1";
        }

        $sql .= " ORDER BY tipo, ordem, nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO categorias (usuario_id, tipo, nome, descricao, e_reembolso)
             VALUES (:usuario_id, :tipo, :nome, :descricao, :e_reembolso)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'tipo' => $dados['tipo'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'e_reembolso' => !empty($dados['e_reembolso']) ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
