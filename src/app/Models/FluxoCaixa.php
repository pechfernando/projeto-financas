<?php

class FluxoCaixa
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Saldo de um mês específico: total de receitas menos total de despesas.
     */
    public function saldoDoMes(int $usuarioId, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN c.tipo = 'receita' THEN l.valor ELSE -l.valor END), 0) AS saldo
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Obtém o saldo inicial de caixa configurado para o usuário.
     */
    public function obterSaldoInicial(int $usuarioId): float
    {
        $stmt = $this->pdo->prepare("SELECT saldo_inicial_caixa FROM usuarios WHERE id = :id");
        $stmt->execute(['id' => $usuarioId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Salva o saldo inicial de caixa para o usuário.
     */
    public function salvarSaldoInicial(int $usuarioId, float $valor): void
    {
        $stmt = $this->pdo->prepare("UPDATE usuarios SET saldo_inicial_caixa = :valor WHERE id = :id");
        $stmt->execute(['valor' => $valor, 'id' => $usuarioId]);
    }

    /**
     * Saldo acumulado até o final do mês/ano informado — soma de tudo desde
     * o primeiro lançamento registrado somado ao saldo inicial configurado.
     */
    public function saldoAcumulado(int $usuarioId, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN c.tipo = 'receita' THEN l.valor ELSE -l.valor END), 0) AS saldo
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND l.data <= LAST_DAY(STR_TO_DATE(CONCAT(:ano, '-', :mes, '-01'), '%Y-%m-%d'))"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        $saldoLancamentos = (float) $stmt->fetchColumn();

        $saldoInicial = $this->obterSaldoInicial($usuarioId);

        return $saldoLancamentos + $saldoInicial;
    }

    /**
     * Retorna o detalhamento de receitas e despesas de um mês específico.
     */
    public function saldoDoMesDetelhado(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 
                COALESCE(SUM(CASE WHEN c.tipo = 'receita' THEN l.valor ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN c.tipo != 'receita' THEN l.valor ELSE 0 END), 0) AS despesas
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        $res = $stmt->fetch();
        return [
            'receitas' => (float) $res['receitas'],
            'despesas' => (float) $res['despesas'],
            'saldo' => (float) ($res['receitas'] - $res['despesas'])
        ];
    }

    /**
     * Série dos últimos N meses (incluindo o mês/ano de referência),
     * com saldo do mês e saldo acumulado de cada um — usada pra montar
     * a tabela/gráfico de evolução.
     */
    public function serieMensal(int $usuarioId, int $mesRef, int $anoRef, int $quantidadeMeses = 6): array
    {
        $serie = [];
        $data = new DateTime("{$anoRef}-{$mesRef}-01");

        // Monta a lista de meses em ordem cronológica (do mais antigo pro mais recente)
        $meses = [];
        for ($i = $quantidadeMeses - 1; $i >= 0; $i--) {
            $referencia = (clone $data)->modify("-{$i} months");
            $meses[] = ['mes' => (int) $referencia->format('n'), 'ano' => (int) $referencia->format('Y')];
        }

        foreach ($meses as $m) {
            $detalhe = $this->saldoDoMesDetelhado($usuarioId, $m['mes'], $m['ano']);
            $serie[] = [
                'mes' => $m['mes'],
                'ano' => $m['ano'],
                'receitas' => $detalhe['receitas'],
                'despesas' => $detalhe['despesas'],
                'saldo_do_mes' => $detalhe['saldo'],
                'saldo_acumulado' => $this->saldoAcumulado($usuarioId, $m['mes'], $m['ano']),
            ];
        }

        return $serie;
    }

    /**
     * Estima quantos meses o saldo acumulado atual dura, no ritmo médio
     * de gastos dos últimos meses (considerando só despesas, sem receita).
     * Retorna null se não houver dados suficientes ou gasto médio zerado.
     */
    public function folegoFinanceiro(int $usuarioId, int $mes, int $ano, int $mesesParaMedia = 3): ?array
    {
        $totalGastos = 0;
        $mesesComDados = 0;
        $data = new DateTime("{$ano}-{$mes}-01");

        for ($i = 0; $i < $mesesParaMedia; $i++) {
            $referencia = (clone $data)->modify("-{$i} months");
            $m = (int) $referencia->format('n');
            $a = (int) $referencia->format('Y');

            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(SUM(l.valor), 0)
                 FROM lancamentos l
                 JOIN categorias c ON c.id = l.categoria_id
                 WHERE l.usuario_id = :usuario_id
                    AND c.tipo != 'receita'
                    AND MONTH(l.data) = :mes
                    AND YEAR(l.data) = :ano"
            );
            $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $m, 'ano' => $a]);
            $gastoDoMes = (float) $stmt->fetchColumn();

            if ($gastoDoMes > 0) {
                $totalGastos += $gastoDoMes;
                $mesesComDados++;
            }
        }

        if ($mesesComDados === 0) {
            return null;
        }

        $mediaGastos = $totalGastos / $mesesComDados;
        $saldoAtual = $this->saldoAcumulado($usuarioId, $mes, $ano);

        if ($mediaGastos <= 0 || $saldoAtual <= 0) {
            return ['media_gastos_mensais' => $mediaGastos, 'meses_de_folego' => null];
        }

        return [
            'media_gastos_mensais' => $mediaGastos,
            'meses_de_folego' => round($saldoAtual / $mediaGastos, 1),
        ];
    }
}
