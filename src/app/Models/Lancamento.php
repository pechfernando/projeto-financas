<?php

class Lancamento
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista lançamentos de um usuário, com filtro opcional de mês/ano.
     * Já traz o nome da categoria e da forma de pagamento junto (JOIN),
     * pra não precisar de várias requisições no frontend.
     */
    public function listar(int $usuarioId, ?int $mes = null, ?int $ano = null): array
    {
        $sql = "SELECT
                    l.id, l.valor, l.data, l.descricao, l.parcelas, l.status_pagamento,
                    c.id AS categoria_id, c.nome AS categoria_nome, c.tipo AS categoria_tipo,
                    fp.id AS forma_pagamento_id, fp.nome AS forma_pagamento_nome
                FROM lancamentos l
                JOIN categorias c ON c.id = l.categoria_id
                JOIN formas_pagamento fp ON fp.id = l.forma_pagamento_id
                WHERE l.usuario_id = :usuario_id";

        $parametros = ['usuario_id' => $usuarioId];

        if ($mes !== null && $ano !== null) {
            $sql .= " AND MONTH(l.data) = :mes AND YEAR(l.data) = :ano";
            $parametros['mes'] = $mes;
            $parametros['ano'] = $ano;
        }

        $sql .= " ORDER BY l.data DESC, l.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id, int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lancamentos WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lancamentos
                (usuario_id, categoria_id, forma_pagamento_id, valor, data, descricao, parcelas, status_pagamento)
             VALUES
                (:usuario_id, :categoria_id, :forma_pagamento_id, :valor, :data, :descricao, :parcelas, :status_pagamento)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'categoria_id' => $dados['categoria_id'],
            'forma_pagamento_id' => $dados['forma_pagamento_id'],
            'valor' => $dados['valor'],
            'data' => $dados['data'],
            'descricao' => $dados['descricao'] ?? null,
            'parcelas' => $dados['parcelas'] ?? null,
            'status_pagamento' => $dados['status_pagamento'] ?? 'pago',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function atualizar(int $id, int $usuarioId, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lancamentos SET
                categoria_id = :categoria_id,
                forma_pagamento_id = :forma_pagamento_id,
                valor = :valor,
                data = :data,
                descricao = :descricao,
                parcelas = :parcelas,
                status_pagamento = :status_pagamento
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute([
            'categoria_id' => $dados['categoria_id'],
            'forma_pagamento_id' => $dados['forma_pagamento_id'],
            'valor' => $dados['valor'],
            'data' => $dados['data'],
            'descricao' => $dados['descricao'] ?? null,
            'parcelas' => $dados['parcelas'] ?? null,
            'status_pagamento' => $dados['status_pagamento'] ?? 'pago',
            'id' => $id,
            'usuario_id' => $usuarioId,
        ]);
    }

    public function apagar(int $id, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM lancamentos WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    }
}
