<?php

class Ativo
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId, bool $apenasAtivos = true): array
    {
        $sql = "SELECT id, nome, tipo_ativo, subcategoria, ativo
                FROM ativos WHERE usuario_id = :usuario_id";

        if ($apenasAtivos) {
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
            "INSERT INTO ativos (usuario_id, nome, tipo_ativo, subcategoria)
             VALUES (:usuario_id, :nome, :tipo_ativo, :subcategoria)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'nome' => $dados['nome'],
            'tipo_ativo' => $dados['tipo_ativo'],
            'subcategoria' => $dados['subcategoria'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
