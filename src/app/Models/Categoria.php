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

    public function buscarPorId(int $id, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, tipo, nome, descricao, e_reembolso, ativo
             FROM categorias WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Atualização completa: dados do cadastro (tipo/nome/descricao/e_reembolso)
     * e também o campo 'ativo' — usado tanto para editar a categoria quanto
     * para desativá-la/reativá-la a partir da tela de Configurações.
     * Desativar uma categoria não apaga nem desvincula os lançamentos que já
     * usam ela; só impede que ela seja escolhida em lançamentos novos.
     */
    public function atualizar(int $id, int $usuarioId, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE categorias SET
                tipo = :tipo,
                nome = :nome,
                descricao = :descricao,
                e_reembolso = :e_reembolso,
                ativo = :ativo
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute([
            'tipo' => $dados['tipo'],
            'nome' => $dados['nome'],
            'descricao' => $dados['descricao'] ?? null,
            'e_reembolso' => !empty($dados['e_reembolso']) ? 1 : 0,
            'ativo' => array_key_exists('ativo', $dados) ? (!empty($dados['ativo']) ? 1 : 0) : 1,
            'id' => $id,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Retorna o id da categoria "Investimentos" (tipo receita) do usuário,
     * criando-a automaticamente na primeira vez — é nela que os rendimentos
     * de investimentos caem, para aparecerem no Relatório Mensal como receita.
     */
    public function buscarOuCriarCategoriaInvestimentos(int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM categorias WHERE usuario_id = :usuario_id AND tipo = 'receita' AND nome = 'Investimentos'"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        return $this->criar([
            'usuario_id' => $usuarioId,
            'tipo' => 'receita',
            'nome' => 'Investimentos',
            'descricao' => 'Gerada automaticamente para os rendimentos registrados na tela de Investimentos',
        ]);
    }
}
