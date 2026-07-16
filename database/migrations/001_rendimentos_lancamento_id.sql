-- =====================================================================
-- MIGRAÇÃO 001: vincula rendimentos de investimentos a um lançamento
-- =====================================================================
-- Rode isso manualmente (via phpMyAdmin ou linha de comando) se você já
-- tem o banco rodando e NÃO quer apagar os dados que já lançou.
--
-- Se preferir simplesmente recriar o banco do zero (perde os dados de
-- teste, tudo bem se ainda for só teste), rode em vez disso:
--   docker compose down -v && docker compose up -d
-- =====================================================================

ALTER TABLE rendimentos_investimentos
    ADD COLUMN lancamento_id INT UNSIGNED NULL AFTER data_recebimento,
    ADD FOREIGN KEY (lancamento_id) REFERENCES lancamentos(id) ON DELETE SET NULL;
