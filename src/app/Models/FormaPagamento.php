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

    public function buscarPorId(int $id, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, tipo, limite_credito, ativo
             FROM formas_pagamento WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Atualização completa: dados do cadastro (nome/tipo/limite_credito) e
     * também o campo 'ativo' — usado tanto para editar quanto para
     * desativar/reativar a forma de pagamento a partir da tela de
     * Configurações. Desativar não apaga nem desvincula lançamentos já
     * feitos com ela; só impede que seja escolhida em lançamentos novos.
     */
    public function atualizar(int $id, int $usuarioId, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE formas_pagamento SET
                nome = :nome,
                tipo = :tipo,
                limite_credito = :limite_credito,
                ativo = :ativo
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute([
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'],
            'limite_credito' => $dados['limite_credito'] ?? null,
            'ativo' => array_key_exists('ativo', $dados) ? (!empty($dados['ativo']) ? 1 : 0) : 1,
            'id' => $id,
            'usuario_id' => $usuarioId,
        ]);
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
