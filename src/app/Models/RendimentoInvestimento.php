<?php

class RendimentoInvestimento
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listar(int $usuarioId, ?int $mes = null, ?int $ano = null): array
    {
        $sql = "SELECT r.id, r.mes, r.ano, r.valor, r.data_recebimento,
                        a.id AS ativo_id, a.nome AS ativo_nome
                 FROM rendimentos_investimentos r
                 JOIN ativos a ON a.id = r.ativo_id
                 WHERE r.usuario_id = :usuario_id";

        $parametros = ['usuario_id' => $usuarioId];

        if ($mes !== null && $ano !== null) {
            $sql .= " AND r.mes = :mes AND r.ano = :ano";
            $parametros['mes'] = $mes;
            $parametros['ano'] = $ano;
        }

        $sql .= " ORDER BY r.ano DESC, r.mes DESC, a.nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function criar(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rendimentos_investimentos (usuario_id, ativo_id, mes, ano, valor, data_recebimento)
             VALUES (:usuario_id, :ativo_id, :mes, :ano, :valor, :data_recebimento)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor), data_recebimento = VALUES(data_recebimento)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'ativo_id' => $dados['ativo_id'],
            'mes' => $dados['mes'],
            'ano' => $dados['ano'],
            'valor' => $dados['valor'],
            'data_recebimento' => $dados['data_recebimento'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
