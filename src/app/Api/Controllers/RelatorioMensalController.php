<?php

class RelatorioMensalController
{
    public function __construct(private RelatorioMensal $model)
    {
    }

    public function resumo(array $parametros): void
    {
        $usuarioId = usuarioAtualId();

        $mes = isset($_GET['mes']) ? (int) $_GET['mes'] : (int) date('n');
        $ano = isset($_GET['ano']) ? (int) $_GET['ano'] : (int) date('Y');

        if ($mes < 1 || $mes > 12) {
            jsonError('Mês inválido', 422);
        }

        $porCategoria = $this->model->totalPorCategoria($usuarioId, $mes, $ano);
        $porTipo = $this->model->totalPorTipo($usuarioId, $mes, $ano);
        $saldo = $this->model->saldoDoMes($usuarioId, $mes, $ano);

        // Separa especificamente os gastos variáveis, calculando o percentual
        // de cada categoria dentro do total variável (para o gráfico de rosca)
        $totalVariavel = $porTipo['variavel'];
        $gastosVariaveis = [];
        foreach ($porCategoria as $linha) {
            if ($linha['categoria_tipo'] === 'variavel') {
                $gastosVariaveis[] = [
                    'categoria_nome' => $linha['categoria_nome'],
                    'total' => (float) $linha['total'],
                    'percentual' => $totalVariavel > 0
                        ? round(((float) $linha['total'] / $totalVariavel) * 100, 1)
                        : 0,
                ];
            }
        }

        jsonResponse([
            'mes' => $mes,
            'ano' => $ano,
            'por_categoria' => $porCategoria,
            'por_tipo' => $porTipo,
            'gastos_variaveis' => $gastosVariaveis,
            'saldo_do_mes' => $saldo,
        ]);
    }
}
