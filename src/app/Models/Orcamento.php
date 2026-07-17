<?php

class Orcamento
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista todas as categorias ativas do usuário, já com o valor previsto
     * (cadastrado no orçamento) e o valor realizado (somado dos lançamentos
     * de fato) daquele mês/ano — para comparação lado a lado.
     */
    public function listarComRealizado(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                c.id AS categoria_id,
                c.tipo AS categoria_tipo,
                c.nome AS categoria_nome,
                COALESCE(om.valor_previsto, 0) AS valor_previsto,
                COALESCE((
                    SELECT SUM(l.valor)
                    FROM lancamentos l
                    WHERE l.categoria_id = c.id
                        AND l.usuario_id = :usuario_id_sub
                        AND MONTH(l.data) = :mes_sub
                        AND YEAR(l.data) = :ano_sub
                ), 0) AS valor_realizado
             FROM categorias c
             LEFT JOIN orcamento_mensal om
                ON om.categoria_id = c.id
                AND om.usuario_id = :usuario_id
                AND om.mes = :mes
                AND om.ano = :ano
             WHERE c.usuario_id = :usuario_id_where AND c.ativo = 1
             ORDER BY c.tipo, c.nome"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'usuario_id_sub' => $usuarioId,
            'usuario_id_where' => $usuarioId,
            'mes' => $mes,
            'mes_sub' => $mes,
            'ano' => $ano,
            'ano_sub' => $ano,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Copia os valores previstos do mês anterior para o mês atual —
     * só para categorias que ainda não têm valor definido no mês atual
     * (não sobrescreve o que a pessoa já preencheu manualmente).
     */
    public function copiarMesAnterior(int $usuarioId, int $mes, int $ano): int
    {
        $mesAnterior = $mes === 1 ? 12 : $mes - 1;
        $anoAnterior = $mes === 1 ? $ano - 1 : $ano;

        $stmt = $this->pdo->prepare(
            "INSERT INTO orcamento_mensal (usuario_id, categoria_id, mes, ano, valor_previsto)
              SELECT source.usuario_id, source.categoria_id, :mes, :ano, source.valor_previsto
              FROM orcamento_mensal AS source
              WHERE source.usuario_id = :usuario_id AND source.mes = :mes_anterior AND source.ano = :ano_anterior
              ON DUPLICATE KEY UPDATE valor_previsto = IF(orcamento_mensal.valor_previsto = 0, VALUES(valor_previsto), orcamento_mensal.valor_previsto)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'mes' => $mes,
            'ano' => $ano,
            'mes_anterior' => $mesAnterior,
            'ano_anterior' => $anoAnterior,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Cria ou atualiza (upsert) o valor previsto de uma categoria num mês/ano.
     * Graças à UNIQUE KEY do banco (usuario_id, categoria_id, mes, ano),
     * salvar de novo no mesmo mês/categoria só atualiza o valor existente.
     */
    public function salvar(int $usuarioId, int $categoriaId, int $mes, int $ano, float $valorPrevisto): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orcamento_mensal (usuario_id, categoria_id, mes, ano, valor_previsto)
             VALUES (:usuario_id, :categoria_id, :mes, :ano, :valor_previsto)
             ON DUPLICATE KEY UPDATE valor_previsto = :valor_previsto_upd"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'categoria_id' => $categoriaId,
            'mes' => $mes,
            'ano' => $ano,
            'valor_previsto' => $valorPrevisto,
            'valor_previsto_upd' => $valorPrevisto,
        ]);
    }
}
