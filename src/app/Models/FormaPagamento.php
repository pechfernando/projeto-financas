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

    /**
     * Retorna o id de uma forma de pagamento "débito/dinheiro/pix" do
     * usuário para usar como padrão em lançamentos gerados automaticamente
     * (como o lançamento de receita criado a partir de um rendimento).
     * Se o usuário não tiver nenhuma, cria uma chamada "Outros" para não
     * travar o cadastro.
     */
    public function obterOuCriarFormaPadrao(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM formas_pagamento
             WHERE usuario_id = :usuario_id AND tipo = 'debito_dinheiro_pix'
             ORDER BY id LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        return $this->criar([
            'usuario_id' => $usuarioId,
            'nome' => 'Outros',
            'tipo' => 'debito_dinheiro_pix',
        ]);
    }
}
