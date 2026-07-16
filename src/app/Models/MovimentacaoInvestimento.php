<?php

class MovimentacaoInvestimento
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.tipo_movimento, m.data, m.quantidade, m.preco_unitario, m.valor_total,
                    a.id AS ativo_id, a.nome AS ativo_nome, a.tipo_ativo
             FROM movimentacoes_investimentos m
             JOIN ativos a ON a.id = m.ativo_id
             WHERE m.usuario_id = :usuario_id
             ORDER BY m.data DESC, m.id DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO movimentacoes_investimentos
                (usuario_id, ativo_id, tipo_movimento, data, quantidade, preco_unitario, valor_total)
             VALUES
                (:usuario_id, :ativo_id, :tipo_movimento, :data, :quantidade, :preco_unitario, :valor_total)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'ativo_id' => $dados['ativo_id'],
            'tipo_movimento' => $dados['tipo_movimento'] ?? 'compra',
            'data' => $dados['data'],
            'quantidade' => $dados['quantidade'],
            'preco_unitario' => $dados['preco_unitario'],
            'valor_total' => $dados['valor_total'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function apagar(int $id, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM movimentacoes_investimentos WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    }

    /**
     * Resumo consolidado por ativo: quantidade total em carteira (compras
     * menos vendas), valor total investido, e o preço médio pago.
     * É a base da tela "Minha Carteira".
     */
    public function resumoPorAtivo(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                a.id AS ativo_id,
                a.nome AS ativo_nome,
                a.tipo_ativo,
                a.subcategoria,
                SUM(CASE WHEN m.tipo_movimento = 'compra' THEN m.quantidade ELSE -m.quantidade END) AS quantidade_total,
                SUM(CASE WHEN m.tipo_movimento = 'compra' THEN m.valor_total ELSE -m.valor_total END) AS valor_investido_total
             FROM movimentacoes_investimentos m
             JOIN ativos a ON a.id = m.ativo_id
             WHERE m.usuario_id = :usuario_id
             GROUP BY a.id, a.nome, a.tipo_ativo, a.subcategoria
             HAVING quantidade_total > 0
             ORDER BY valor_investido_total DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /**
     * Total investido agrupado por tipo de ativo (FII, Ação, Renda Fixa...),
     * usado no gráfico de distribuição da carteira.
     */
    public function resumoPorTipo(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                a.tipo_ativo,
                SUM(CASE WHEN m.tipo_movimento = 'compra' THEN m.valor_total ELSE -m.valor_total END) AS valor_investido_total
             FROM movimentacoes_investimentos m
             JOIN ativos a ON a.id = m.ativo_id
             WHERE m.usuario_id = :usuario_id
             GROUP BY a.tipo_ativo
             HAVING valor_investido_total > 0
             ORDER BY valor_investido_total DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }
}
