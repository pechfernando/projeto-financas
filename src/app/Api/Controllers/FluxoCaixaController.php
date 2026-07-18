<?php

class FluxoCaixaController
{
    public function __construct(
        private FluxoCaixa $fluxoCaixa,
        private RecorrenteFinanceiro $recorrentes
    ) {
    }

    public function obterSaldoInicial(array $parametros): void
    {
        jsonResponse(['saldo_inicial_caixa' => $this->fluxoCaixa->obterSaldoInicial(usuarioAtualId())]);
    }

    public function salvarSaldoInicial(array $parametros): void
    {
        $dados = corpoRequisicao();
        $valor = isset($dados['saldo_inicial_caixa']) ? (float) $dados['saldo_inicial_caixa'] : 0.00;
        $this->fluxoCaixa->salvarSaldoInicial(usuarioAtualId(), $valor);
        jsonResponse(['mensagem' => 'Saldo inicial de caixa salvo com sucesso']);
    }

    public function resumo(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
        $quantidadeMeses = isset($_GET['meses']) ? (int) $_GET['meses'] : 6;

        $serie = $this->fluxoCaixa->serieMensal($usuarioId, $mes, $ano, $quantidadeMeses);

        jsonResponse([
            'mes' => $mes,
            'ano' => $ano,
            'serie' => $serie,
        ]);
    }

    public function folegoAnual(): void
    {
        $hoje = new DateTime('now');

        $mesInicio = isset($_GET['mes_inicio']) ? (int) $_GET['mes_inicio'] : (int) (clone $hoje)->modify('-3 months')->format('n');
        $anoInicio = isset($_GET['ano_inicio']) ? (int) $_GET['ano_inicio'] : (int) (clone $hoje)->modify('-3 months')->format('Y');
        $mesFim = isset($_GET['mes_fim']) ? (int) $_GET['mes_fim'] : (int) (new DateTime('now'))->modify('+12 months')->format('n');
        $anoFim = isset($_GET['ano_fim']) ? (int) $_GET['ano_fim'] : (int) (new DateTime('now'))->modify('+12 months')->format('Y');

        $projecao = $this->fluxoCaixa->projecaoPersonalizada(
            $this->recorrentes,
            usuarioAtualId(),
            $mesInicio,
            $anoInicio,
            $mesFim,
            $anoFim
        );

        jsonResponse($projecao);
    }
}
