<?php

class RelatorioMensal
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Soma os lançamentos do mês, agrupados por categoria.
     * Traz também o "tipo" (fixa/variavel/receita/dividas_parcelados)
     * para facilitar a organização na tela.
     */
    public function totalPorCategoria(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                c.id AS categoria_id,
                c.tipo AS categoria_tipo,
                c.nome AS categoria_nome,
                c.e_reembolso,
                SUM(l.valor) AS total
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano
             GROUP BY c.id, c.tipo, c.nome, c.e_reembolso
             ORDER BY c.tipo, total DESC"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        return $stmt->fetchAll();
    }

    /**
     * Soma total por "tipo" (Fixa, Variável, Receita, Dívidas e Parcelados).
     * É o equivalente aos totais que aparecem no topo do Relatório Mensal
     * da planilha (Fixas, Variáveis, Receita, etc).
     */
    public function totalPorTipo(int $usuarioId, int $mes, int $ano): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.tipo AS categoria_tipo, SUM(l.valor) AS total
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano
             GROUP BY c.tipo"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);

        // Transforma em algo tipo { fixa: 123.45, variavel: 678.90, ... }
        $resultado = ['fixa' => 0, 'variavel' => 0, 'receita' => 0, 'dividas_parcelados' => 0];
        foreach ($stmt->fetchAll() as $linha) {
            $resultado[$linha['categoria_tipo']] = (float) $linha['total'];
        }
        return $resultado;
    }

    /**
     * Saldo do mês = total de receitas (exceto reembolsos) menos total de despesas.
     * Usado tanto para o saldo do mês quanto, futuramente, para o saldo acumulado.
     */
    public function saldoDoMes(int $usuarioId, int $mes, int $ano): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN c.tipo = 'receita' THEN l.valor ELSE 0 END), 0) AS entradas,
                COALESCE(SUM(CASE WHEN c.tipo != 'receita' THEN l.valor ELSE 0 END), 0) AS saidas
             FROM lancamentos l
             JOIN categorias c ON c.id = l.categoria_id
             WHERE l.usuario_id = :usuario_id
                AND MONTH(l.data) = :mes
                AND YEAR(l.data) = :ano"
        );
        $stmt->execute(['usuario_id' => $usuarioId, 'mes' => $mes, 'ano' => $ano]);
        $linha = $stmt->fetch();

        return (float) $linha['entradas'] - (float) $linha['saidas'];
    }
}
