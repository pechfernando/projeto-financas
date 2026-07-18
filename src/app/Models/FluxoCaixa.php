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
    /**
     * Soma os lançamentos reais de despesa variável já cadastrados num mês/ano
     * específico. Diferente dos recorrentes, isso busca dados REAIS já
     * lançados (não uma estimativa) — por isso funciona tanto pra meses
     * passados quanto pra meses futuros em que o usuário já adiantou algum
     * lançamento variável.
     */
    private function despesasVariaveisReaisDoMes(int $usuarioId, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(l.valor), 0)
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND c.tipo = 'variavel'
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Detalhamento real de um mês específico, quebrado por tipo de categoria.
     * Usado nos meses passados/atual da tabela de Fôlego Financeiro, onde já
     * existem lançamentos reais (não é estimativa).
     */
    private function detalhamentoRealDoMes(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN c.tipo = 'receita' THEN l.valor ELSE 0 END), 0) AS receitas,
                COALESCE(SUM(CASE WHEN c.tipo = 'fixa' THEN l.valor ELSE 0 END), 0) AS despesas_fixas,
                COALESCE(SUM(CASE WHEN c.tipo = 'variavel' THEN l.valor ELSE 0 END), 0) AS despesas_variaveis,
                COALESCE(SUM(CASE WHEN c.tipo = 'dividas_parcelados' THEN l.valor ELSE 0 END), 0) AS dividas
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
            'despesas_fixas' => (float) $res['despesas_fixas'],
            'despesas_variaveis' => (float) $res['despesas_variaveis'],
            'dividas' => (float) $res['dividas'],
        ];
    }

    /**
     * Monta a tabela do Fôlego Financeiro para um período customizado
     * (De mesInicio/anoInicio Até mesFim/anoFim). Meses até o mês atual
     * (inclusive) usam dados REAIS já lançados; meses depois do mês atual usam
     * a projeção por Lançamentos Recorrentes + despesas variáveis já
     * antecipadas.
     *
     * Retorna:
     * - 'meses': array em ordem cronológica, cada item com mes, ano, rotulo,
     *   eh_real (bool), receitas, despesas_fixas, despesas_variaveis, dividas,
     *   saldo_do_mes, saldo_acumulado
     * - 'mes_esgotamento': null, ou {mes, ano, rotulo} do primeiro mês em que o
     *   saldo acumulado projetado fica <= 0 (procurado a partir de hoje, até 60
     *   meses à frente — independente do período exibido)
     */
    public function projecaoPersonalizada(
        RecorrenteFinanceiro $recorrentes,
        int $usuarioId,
        int $mesInicio,
        int $anoInicio,
        int $mesFim,
        int $anoFim
    ): array {
        $hoje = new DateTime('now');
        $mesAtual = (int) $hoje->format('n');
        $anoAtual = (int) $hoje->format('Y');
        $chaveMesAtual = $anoAtual * 100 + $mesAtual;

        $nomesMesesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        $cursor = new DateTime("{$anoInicio}-{$mesInicio}-01");
        $fim = new DateTime("{$anoFim}-{$mesFim}-01");

        $meses = [];
        $saldoAcumuladoProjetado = null; // só é inicializado quando entra na parte projetada

        while ($cursor <= $fim) {
            $m = (int) $cursor->format('n');
            $a = (int) $cursor->format('Y');
            $chaveMes = $a * 100 + $m;
            $ehReal = $chaveMes <= $chaveMesAtual;

            if ($ehReal) {
                $detalhe = $this->detalhamentoRealDoMes($usuarioId, $m, $a);
                $saldoDoMes = $detalhe['receitas'] - $detalhe['despesas_fixas']
                    - $detalhe['despesas_variaveis'] - $detalhe['dividas'];
                $saldoAcumulado = $this->saldoAcumulado($usuarioId, $m, $a);

                // Prepara o ponto de partida pra quando a projeção começar
                $saldoAcumuladoProjetado = $saldoAcumulado;
            } else {
                $receitas = $recorrentes->totalPorTipoNoMes($usuarioId, 'receita', $m, $a);
                $despesasFixas = $recorrentes->totalPorTipoNoMes($usuarioId, 'despesa_fixa', $m, $a);
                $dividas = $recorrentes->totalPorTipoNoMes($usuarioId, 'divida_parcelada', $m, $a);
                $despesasVariaveis = $this->despesasVariaveisReaisDoMes($usuarioId, $m, $a);

                $detalhe = [
                    'receitas' => $receitas,
                    'despesas_fixas' => $despesasFixas,
                    'despesas_variaveis' => $despesasVariaveis,
                    'dividas' => $dividas,
                ];
                $saldoDoMes = $receitas - $despesasFixas - $dividas - $despesasVariaveis;
                if ($saldoAcumuladoProjetado === null) {
                    // Fallback de segurança se o período só cobrir o futuro
                    $saldoAcumuladoProjetado = $this->saldoAcumulado($usuarioId, $mesAtual, $anoAtual);
                }
                $saldoAcumuladoProjetado += $saldoDoMes;
                $saldoAcumulado = $saldoAcumuladoProjetado;
            }

            $meses[] = [
                'mes' => $m,
                'ano' => $a,
                'rotulo' => $nomesMesesAbrev[$m - 1] . '/' . substr((string) $a, 2),
                'eh_real' => $ehReal,
                'receitas' => round($detalhe['receitas'], 2),
                'despesas_fixas' => round($detalhe['despesas_fixas'], 2),
                'despesas_variaveis' => round($detalhe['despesas_variaveis'], 2),
                'dividas' => round($detalhe['dividas'], 2),
                'saldo_do_mes' => round($saldoDoMes, 2),
                'saldo_acumulado' => round($saldoAcumulado, 2),
            ];

            $cursor->modify('+1 month');
        }

        $mesEsgotamento = $this->buscarMesEsgotamento($recorrentes, $usuarioId);

        return [
            'meses' => $meses,
            'mes_esgotamento' => $mesEsgotamento,
        ];
    }

    /**
     * Busca, a partir de hoje, o primeiro mês futuro em que o saldo acumulado
     * projetado (por recorrentes) fica <= 0. Procura até 60 meses à frente,
     * independente do período que está sendo exibido na tabela.
     */
    private function buscarMesEsgotamento(RecorrenteFinanceiro $recorrentes, int $usuarioId): ?array
    {
        $hoje = new DateTime('now');
        $mesAtual = (int) $hoje->format('n');
        $anoAtual = (int) $hoje->format('Y');
        $saldoAcumulado = $this->saldoAcumulado($usuarioId, $mesAtual, $anoAtual);

        $nomesMesesAbrev = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

        for ($i = 1; $i <= 60; $i++) {
            $referencia = (clone $hoje)->modify("+{$i} months");
            $m = (int) $referencia->format('n');
            $a = (int) $referencia->format('Y');

            $receitas = $recorrentes->totalPorTipoNoMes($usuarioId, 'receita', $m, $a);
            $despesasFixas = $recorrentes->totalPorTipoNoMes($usuarioId, 'despesa_fixa', $m, $a);
            $dividas = $recorrentes->totalPorTipoNoMes($usuarioId, 'divida_parcelada', $m, $a);
            $despesasVariaveis = $this->despesasVariaveisReaisDoMes($usuarioId, $m, $a);

            $saldoAcumulado += ($receitas - $despesasFixas - $dividas - $despesasVariaveis);

            if ($saldoAcumulado <= 0) {
                return [
                    'mes' => $m,
                    'ano' => $a,
                    'rotulo' => $nomesMesesAbrev[$m - 1] . '/' . substr((string) $a, 2),
                ];
            }
        }

        return null;
    }
}
