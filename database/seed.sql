SET NAMES utf8mb4;

-- =====================================================================
-- DADOS INICIAIS DE TESTE (rodados automaticamente após o schema.sql)
-- Isso é só para desenvolvimento local — os dados reais virão da
-- migração da planilha, que faremos numa fase futura.
-- =====================================================================

-- Usuário de teste. Senha ainda em texto puro só para não travar o
-- desenvolvimento agora — na Fase 1 (autenticação) vamos trocar por
-- hash de senha de verdade (password_hash do PHP).
INSERT INTO usuarios (nome, email, senha_hash) VALUES
('Usuário Teste', 'teste@exemplo.com', 'trocar_por_hash_real');

-- Algumas categorias iniciais, baseadas na sua planilha, só para termos
-- algo pra testar. Depois criamos uma tela de gerenciamento de categorias
-- e você cadastra o restante por lá.
INSERT INTO categorias (usuario_id, tipo, nome) VALUES
(1, 'variavel', 'Restaurante'),
(1, 'variavel', 'Letícia Supermercado'),
(1, 'fixa', 'Comunicação (Telefone fixo / Celular)'),
(1, 'receita', 'Salário'),
(1, 'dividas_parcelados', 'Dívidas e Parcelados');

INSERT INTO categorias (usuario_id, tipo, nome, e_reembolso) VALUES
(1, 'receita', 'outras', TRUE);

-- Formas de pagamento iniciais
INSERT INTO formas_pagamento (usuario_id, nome, tipo, limite_credito) VALUES
(1, 'Débito/Dinheiro/Pix', 'debito_dinheiro_pix', NULL),
(1, 'Cartão de Crédito Itaú', 'cartao_credito', 15000.00);
