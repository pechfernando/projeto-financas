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
        $horizonte = isset($_GET['horizonte']) ? (int) $_GET['horizonte'] : 12;
        $projecao = $this->fluxoCaixa->projecaoAnualRecorrentes($this->recorrentes, usuarioAtualId(), $horizonte);
        jsonResponse($projecao);
    }
}
