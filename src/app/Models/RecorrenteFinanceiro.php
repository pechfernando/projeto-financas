<?php

class RecorrenteFinanceiro
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId, bool $todas = false): array
    {
        $sql = "SELECT * FROM lancamentos_recorrentes WHERE usuario_id = :usuario_id";
        if (!$todas) {
            $sql .= " AND ativo = 1";
        }
        $sql .= " ORDER BY tipo, descricao";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function buscar(int $usuarioId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lancamentos_recorrentes WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    public function criar(int $usuarioId, array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lancamentos_recorrentes
                (usuario_id, tipo, categoria_id, descricao, valor_mensal, data_inicio, data_fim, ativo)
             VALUES (:usuario_id, :tipo, :categoria_id, :descricao, :valor_mensal, :data_inicio, :data_fim, :ativo)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $dados['tipo'],
            'categoria_id' => !empty($dados['categoria_id']) ? $dados['categoria_id'] : null,
            'descricao' => $dados['descricao'],
            'valor_mensal' => $dados['valor_mensal'],
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => $dados['data_fim'] ?: null,
            'ativo' => isset($dados['ativo']) ? (int) $dados['ativo'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function atualizar(int $usuarioId, int $id, array $dados): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lancamentos_recorrentes SET
                tipo = :tipo,
                categoria_id = :categoria_id,
                descricao = :descricao,
                valor_mensal = :valor_mensal,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                ativo = :ativo
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute([
            'tipo' => $dados['tipo'],
            'categoria_id' => !empty($dados['categoria_id']) ? $dados['categoria_id'] : null,
            'descricao' => $dados['descricao'],
            'valor_mensal' => $dados['valor_mensal'],
            'data_inicio' => $dados['data_inicio'],
            'data_fim' => $dados['data_fim'] ?: null,
            'ativo' => isset($dados['ativo']) ? (int) $dados['ativo'] : 1,
            'id' => $id,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Busca o recorrente ativo vinculado a uma categoria específica, se existir.
     * Usado para substituir automaticamente o valor_mensal quando um lançamento
     * real diferente é registrado nessa categoria.
     */
    public function buscarPorCategoria(int $usuarioId, int $categoriaId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lancamentos_recorrentes
             WHERE usuario_id = :usuario_id AND categoria_id = :categoria_id AND ativo = 1
             LIMIT 1"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'categoria_id' => $categoriaId]);
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Atualiza só o valor_mensal de um recorrente (usado na substituição
     * automática, sem precisar reenviar todos os outros campos).
     */
    public function atualizarValor(int $usuarioId, int $id, float $novoValor): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lancamentos_recorrentes SET valor_mensal = :valor
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['valor' => $novoValor, 'id' => $id, 'usuario_id' => $usuarioId]);
    }

    public function apagar(int $usuarioId, int $id): void
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM lancamentos_recorrentes WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    }

    /**
     * Soma os valores mensais dos itens recorrentes de um tipo específico
     * que estão "ativos" (ativo=1) e "vigentes" no mês/ano informado —
     * ou seja, data_inicio <= último dia do mês, e (data_fim IS NULL OU
     * data_fim >= primeiro dia do mês).
     */
    public function totalPorTipoNoMes(int $usuarioId, string $tipo, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(valor_mensal), 0)
             FROM lancamentos_recorrentes
             WHERE usuario_id = :usuario_id
                AND tipo = :tipo
                AND ativo = 1
                AND data_inicio <= LAST_DAY(STR_TO_DATE(CONCAT(:ano, '-', :mes, '-01'), '%Y-%m-%d'))
                AND (data_fim IS NULL OR data_fim >= STR_TO_DATE(CONCAT(:ano2, '-', :mes2, '-01'), '%Y-%m-%d'))"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'tipo' => $tipo,
            'mes' => $mes,
            'ano' => $ano,
            'mes2' => $mes,
            'ano2' => $ano,
        ]);
        return (float) $stmt->fetchColumn();
    }
}
