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
     * Projeta o saldo do mês e o saldo acumulado para os próximos meses a
     * partir do saldo acumulado real atual, usando os Lançamentos Recorrentes
     * cadastrados (receitas, despesas fixas e dívidas/parcelas). Despesas
     * variáveis nunca entram na projeção futura (sempre R$0).
     *
     * Retorna:
     * - 'meses': array com $horizonteMeses entradas: mes, ano, rotulo (ex:
     *   'jan/26'), receitas, despesas_fixas, dividas, saldo_projetado,
     *   saldo_acumulado_projetado
     * - 'mes_esgotamento': null, ou {mes, ano, rotulo} do primeiro mês em que
     *   o saldo acumulado projetado fica <= 0 (procurado até 60 meses à
     *   frente, mesmo que seja além do horizonte exibido)
     */
    public function projecaoAnualRecorrentes(
        RecorrenteFinanceiro $recorrentes,
        int $usuarioId,
        int $horizonteMeses = 12
    ): array {
        $hoje = new DateTime('now');
        $mesAtual = (int) $hoje->format('n');
        $anoAtual = (int) $hoje->format('Y');

        $saldoAcumulado = $this->saldoAcumulado($usuarioId, $mesAtual, $anoAtual);

        $nomesMesesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $meses = [];
        $mesEsgotamento = null;
        $limiteBusca = max($horizonteMeses, 60);

        for ($i = 1; $i <= $limiteBusca; $i++) {
            $referencia = (clone $hoje)->modify("+{$i} months");
            $m = (int) $referencia->format('n');
            $a = (int) $referencia->format('Y');

            $receitas = $recorrentes->totalPorTipoNoMes($usuarioId, 'receita', $m, $a);
            $despesasFixas = $recorrentes->totalPorTipoNoMes($usuarioId, 'despesa_fixa', $m, $a);
            $dividas = $recorrentes->totalPorTipoNoMes($usuarioId, 'divida_parcelada', $m, $a);
            // Despesas variáveis futuras propositalmente NÃO entram aqui (sempre R$0).

            $saldoProjetado = $receitas - $despesasFixas - $dividas;
            $saldoAcumulado += $saldoProjetado;

            if ($mesEsgotamento === null && $saldoAcumulado <= 0) {
                $mesEsgotamento = [
                    'mes' => $m,
                    'ano' => $a,
                    'rotulo' => $nomesMesesAbrev[$m - 1] . '/' . substr((string) $a, 2),
                ];
            }

            if ($i <= $horizonteMeses) {
                $meses[] = [
                    'mes' => $m,
                    'ano' => $a,
                    'rotulo' => $nomesMesesAbrev[$m - 1] . '/' . substr((string) $a, 2),
                    'receitas' => round($receitas, 2),
                    'despesas_fixas' => round($despesasFixas, 2),
                    'dividas' => round($dividas, 2),
                    'saldo_projetado' => round($saldoProjetado, 2),
                    'saldo_acumulado_projetado' => round($saldoAcumulado, 2),
                ];
            }

            if ($i >= $horizonteMeses && $mesEsgotamento !== null) {
                break;
            }
        }

        return [
            'meses' => $meses,
            'mes_esgotamento' => $mesEsgotamento,
        ];
    }
}
