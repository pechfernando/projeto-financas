<?php

class PatrimonioController
{
    public function __construct(
        private Patrimonio $model,
        private FluxoCaixa $fluxoCaixaModel,
    ) {
    }

    public function listarContas(array $parametros): void
    {
        jsonResponse($this->model->listarContas(usuarioAtualId()));
    }

    public function criarConta(array $parametros): void
    {
        $dados = corpoRequisicao();

        if (empty($dados['nome'])) {
            jsonError('O campo nome é obrigatório', 422);
        }

        $dados['usuario_id'] = usuarioAtualId();
        $id = $this->model->criarConta($dados);

        jsonResponse(['id' => $id, 'mensagem' => 'Conta cadastrada com sucesso'], 201);
    }

    /**
     * Lista as contas com o saldo lançado no mês, e já traz junto a
     * reconciliação: saldo real (soma dos saldos lançados) x saldo
     * calculado (saldo acumulado do Fluxo de Caixa) x diferença entre eles.
     */
    public function saldosDoMes(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

        $contas = $this->model->listarSaldosDoMes($usuarioId, $mes, $ano);
        $saldoReal = $this->model->totalSaldosDoMes($usuarioId, $mes, $ano);
        $saldoCalculado = $this->fluxoCaixaModel->saldoAcumulado($usuarioId, $mes, $ano);

        jsonResponse([
            'mes' => $mes,
            'ano' => $ano,
            'contas' => $contas,
            'reconciliacao' => [
                'saldo_real' => $saldoReal,
                'saldo_calculado' => $saldoCalculado,
                'diferenca' => round($saldoReal - $saldoCalculado, 2),
            ],
        ]);
    }

    /**
     * Salva o saldo de todas as contas de um mês de uma vez (mesmo
     * padrão usado no OrcamentoController::salvar).
     */
    public function salvarSaldos(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $dados = corpoRequisicao();

        if (empty($dados['mes']) || empty($dados['ano']) || !isset($dados['itens'])) {
            jsonError('Campos mes, ano e itens são obrigatórios', 422);
        }

        foreach ($dados['itens'] as $item) {
            if (!isset($item['conta_id'], $item['valor'])) {
                continue;
            }
            $this->model->salvarSaldo(
                $usuarioId,
                (int) $item['conta_id'],
                (int) $dados['mes'],
                (int) $dados['ano'],
                (float) $item['valor']
            );
        }

        jsonResponse(['mensagem' => 'Saldos salvos com sucesso']);
    }

    public function evolucao(array $parametros): void
    {
        $usuarioId = usuarioAtualId();
        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');
        $meses = isset($_GET['meses']) ? (int) $_GET['meses'] : 6;

        jsonResponse(['serie' => $this->model->serieEvolucao($usuarioId, $mes, $ano, $meses)]);
    }
}
