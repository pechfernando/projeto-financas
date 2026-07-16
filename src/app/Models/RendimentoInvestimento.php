<?php

class RendimentoInvestimento
{
    public function __construct(
        private PDO $pdo,
        private Categoria $categoriaModel,
        private FormaPagamento $formaPagamentoModel,
    ) {
    }

    /**
     * Lista os rendimentos do usuário. Filtros opcionais:
     * - mes + ano: só aquele mês
     * - apenas ano: o ano inteiro (usado na tela de Rendimentos)
     * - nenhum filtro: tudo
     */
    public function listar(int $usuarioId, ?int $mes = null, ?int $ano = null): array
    {
        $sql = "SELECT r.id, r.mes, r.ano, r.valor, r.data_recebimento,
                        a.id AS ativo_id, a.nome AS ativo_nome, a.tipo_ativo
                 FROM rendimentos_investimentos r
                 JOIN ativos a ON a.id = r.ativo_id
                 WHERE r.usuario_id = :usuario_id";

        $parametros = ['usuario_id' => $usuarioId];

        if ($mes !== null && $ano !== null) {
            $sql .= " AND r.mes = :mes AND r.ano = :ano";
            $parametros['mes'] = $mes;
            $parametros['ano'] = $ano;
        } elseif ($ano !== null) {
            $sql .= " AND r.ano = :ano";
            $parametros['ano'] = $ano;
        }

        $sql .= " ORDER BY r.ano DESC, r.mes DESC, a.nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    /**
     * Cria (ou atualiza, se já existir rendimento desse ativo nesse mês/ano)
     * o registro de rendimento, e sincroniza um lançamento de receita
     * correspondente na categoria "Investimentos" — é isso que faz o valor
     * aparecer automaticamente no Relatório Mensal, Orçamento e Fluxo de Caixa.
     */
    public function criar(array $dados): int
    {
        $usuarioId = (int) $dados['usuario_id'];
        $ativoId = (int) $dados['ativo_id'];
        $mes = (int) $dados['mes'];
        $ano = (int) $dados['ano'];
        $valor = (float) $dados['valor'];
        $dataRecebimento = $dados['data_recebimento'] ?? $this->ultimoDiaDoMes($mes, $ano);

        // Verifica se já existe rendimento (e lançamento vinculado) para
        // esse ativo/mês/ano, para decidir entre criar ou atualizar o lançamento.
        $stmt = $this->pdo->prepare(
            "SELECT id, lancamento_id FROM rendimentos_investimentos
             WHERE ativo_id = :ativo_id AND mes = :mes AND ano = :ano"
        );
        $stmt->execute(['ativo_id' => $ativoId, 'mes' => $mes, 'ano' => $ano]);
        $existente = $stmt->fetch();

        $nomeAtivo = $this->nomeDoAtivo($ativoId);
        $descricao = "Rendimento recebido - {$nomeAtivo}";

        if ($existente && $existente['lancamento_id']) {
            // Já existe: apenas atualiza o valor/data do lançamento existente.
            $lancamentoId = (int) $existente['lancamento_id'];
            $stmt = $this->pdo->prepare(
                "UPDATE lancamentos SET valor = :valor, data = :data, descricao = :descricao
                 WHERE id = :id AND usuario_id = :usuario_id"
            );
            $stmt->execute([
                'valor' => $valor,
                'data' => $dataRecebimento,
                'descricao' => $descricao,
                'id' => $lancamentoId,
                'usuario_id' => $usuarioId,
            ]);
        } else {
            // Não existe ainda: garante a categoria/forma de pagamento padrão
            // (criando na primeira vez) e insere um novo lançamento de receita.
            $categoriaId = $this->categoriaModel->buscarOuCriarCategoriaInvestimentos($usuarioId);
            $formaPagamentoId = $this->formaPagamentoModel->obterOuCriarFormaPadrao($usuarioId);

            $stmt = $this->pdo->prepare(
                "INSERT INTO lancamentos (usuario_id, categoria_id, forma_pagamento_id, valor, data, descricao, status_pagamento)
                 VALUES (:usuario_id, :categoria_id, :forma_pagamento_id, :valor, :data, :descricao, 'pago')"
            );
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'categoria_id' => $categoriaId,
                'forma_pagamento_id' => $formaPagamentoId,
                'valor' => $valor,
                'data' => $dataRecebimento,
                'descricao' => $descricao,
            ]);
            $lancamentoId = (int) $this->pdo->lastInsertId();
        }

        // Upsert do rendimento em si, já com o lancamento_id vinculado.
        $stmt = $this->pdo->prepare(
            "INSERT INTO rendimentos_investimentos (usuario_id, ativo_id, mes, ano, valor, data_recebimento, lancamento_id)
             VALUES (:usuario_id, :ativo_id, :mes, :ano, :valor, :data_recebimento, :lancamento_id)
             ON DUPLICATE KEY UPDATE
                valor = VALUES(valor),
                data_recebimento = VALUES(data_recebimento),
                lancamento_id = VALUES(lancamento_id)"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'ativo_id' => $ativoId,
            'mes' => $mes,
            'ano' => $ano,
            'valor' => $valor,
            'data_recebimento' => $dataRecebimento,
            'lancamento_id' => $lancamentoId,
        ]);

        // Busca o id real do registro (upsert não garante lastInsertId correto
        // no caso de UPDATE), usando a chave única ativo/mês/ano.
        $stmt = $this->pdo->prepare(
            "SELECT id FROM rendimentos_investimentos WHERE ativo_id = :ativo_id AND mes = :mes AND ano = :ano"
        );
        $stmt->execute(['ativo_id' => $ativoId, 'mes' => $mes, 'ano' => $ano]);
        return (int) $stmt->fetchColumn();
    }

    private function nomeDoAtivo(int $ativoId): string
    {
        $stmt = $this->pdo->prepare("SELECT nome FROM ativos WHERE id = :id");
        $stmt->execute(['id' => $ativoId]);
        return (string) $stmt->fetchColumn();
    }

    private function ultimoDiaDoMes(int $mes, int $ano): string
    {
        return (new DateTime("{$ano}-{$mes}-01"))->format('Y-m-t');
    }
}
