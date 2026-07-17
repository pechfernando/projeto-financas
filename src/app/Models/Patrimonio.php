<?php

class Patrimonio
{
    public function __construct(private PDO $pdo)
    {
    }

    // ---------------------------------------------------------------
    // Contas de patrimônio (bancos, corretoras, dinheiro em espécie...)
    // ---------------------------------------------------------------

    public function listarContas(int $usuarioId, bool $apenasAtivas = true): array
    {
        $sql = "SELECT id, nome, tipo, ordem, ativo
                FROM contas_patrimonio WHERE usuario_id = :usuario_id";

        if ($apenasAtivas) {
            $sql .= " AND ativo = 1";
        }

        $sql .= " ORDER BY ordem, nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['usuario_id' => $usuarioId]);
        return $stmt->fetchAll();
    }

    public function buscarContaPorId(int $id, int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nome, tipo, ordem, ativo FROM contas_patrimonio WHERE id = :id AND usuario_id = :usuario_id"
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        return $stmt->fetch() ?: [];
    }

    public function atualizarConta(int $id, array $dados): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE contas_patrimonio SET nome = :nome, tipo = :tipo, ordem = :ordem, ativo = :ativo
             WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute([
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'] ?? null,
            'ordem' => (int) ($dados['ordem'] ?? 0),
            'ativo' => $dados['ativo'] ? 1 : 0,
            'id' => $id,
            'usuario_id' => $dados['usuario_id'],
        ]);
    }

    public function apagarConta(int $id, int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM contas_patrimonio WHERE id = :id AND usuario_id = :usuario_id"
        );
        return $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
    }

    public function criarConta(array $dados): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contas_patrimonio (usuario_id, nome, tipo, ordem)
             VALUES (:usuario_id, :nome, :tipo, :ordem)"
        );
        $stmt->execute([
            'usuario_id' => $dados['usuario_id'],
            'nome' => $dados['nome'],
            'tipo' => $dados['tipo'] ?? null,
            'ordem' => $dados['ordem'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // ---------------------------------------------------------------
    // Saldos mensais (snapshot manual do valor em cada conta)
    // ---------------------------------------------------------------

    /**
     * Lista todas as contas ativas do usuário já com o saldo lançado
     * naquele mês/ano (0 se ainda não foi lançado) — para preencher a
     * tabela de lançamento de saldos, igual ao padrão usado no Orçamento.
     */
    public function listarSaldosDoMes(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                cp.id AS conta_id,
                cp.nome AS conta_nome,
                cp.tipo AS conta_tipo,
                COALESCE(sm.valor, 0) AS valor
             FROM contas_patrimonio cp
             LEFT JOIN saldos_mensais sm
                ON sm.conta_id = cp.id
                AND sm.usuario_id = :usuario_id
                AND sm.mes = :mes
                AND sm.ano = :ano
             WHERE cp.usuario_id = :usuario_id_where AND cp.ativo = 1
             ORDER BY cp.ordem, cp.nome"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'usuario_id_where' => $usuarioId,
            'mes' => $mes,
            'ano' => $ano,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Cria ou atualiza (upsert) o saldo de uma conta num mês/ano —
     * mesmo padrão de upsert usado no Model de Orçamento.
     */
    public function salvarSaldo(int $usuarioId, int $contaId, int $mes, int $ano, float $valor): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO saldos_mensais (usuario_id, conta_id, mes, ano, valor)
             VALUES (:usuario_id, :conta_id, :mes, :ano, :valor)
             ON DUPLICATE KEY UPDATE valor = :valor_upd"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'conta_id' => $contaId,
            'mes' => $mes,
            'ano' => $ano,
            'valor' => $valor,
            'valor_upd' => $valor,
        ]);
    }

    /**
     * Soma de todos os saldos lançados manualmente naquele mês/ano —
     * é o "saldo real" usado na reconciliação.
     */
    public function totalSaldosDoMes(int $usuarioId, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(valor), 0)
             FROM saldos_mensais
             WHERE usuario_id = :usuario_id AND mes = :mes AND ano = :ano"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Série dos últimos N meses com o total de patrimônio lançado
     * (soma dos saldos_mensais) em cada um — usada no gráfico de
     * evolução patrimonial. Meses sem nenhum saldo lançado aparecem
     * como null (para o gráfico não fingir que era zero).
     */
    public function serieEvolucao(int $usuarioId, int $mesRef, int $anoRef, int $quantidadeMeses = 6): array
    {
        $serie = [];
        $data = new DateTime("{$anoRef}-{$mesRef}-01");

        $meses = [];
        for ($i = $quantidadeMeses - 1; $i >= 0; $i--) {
            $referencia = (clone $data)->modify("-{$i} months");
            $meses[] = ['mes' => (int) $referencia->format('n'), 'ano' => (int) $referencia->format('Y')];
        }

        foreach ($meses as $m) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) AS quantidade, COALESCE(SUM(valor), 0) AS total
                 FROM saldos_mensais
                 WHERE usuario_id = :usuario_id AND mes = :mes AND ano = :ano"
            );
            $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $m['mes'], 'ano' => $m['ano']]);
            $linha = $stmt->fetch();

            $serie[] = [
                'mes' => $m['mes'],
                'ano' => $m['ano'],
                'total_patrimonio' => $linha['quantidade'] > 0 ? (float) $linha['total'] : null,
            ];
        }

        return $serie;
    }
}
