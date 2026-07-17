-- =====================================================================
-- SCHEMA DO BANCO DE DADOS - APLICAÇÃO FINANCEIRA
-- Baseado na planilha "Plano - Despesas e Receitas"
-- =====================================================================
-- Charset UTF8MB4 para suportar acentuação e emojis sem problemas
SET NAMES utf8mb4;

-- =====================================================================
-- 1. USUÁRIOS
-- Já nasce pronta para multiusuário (você + família no futuro)
-- =====================================================================
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    saldo_inicial_caixa DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 2. CATEGORIAS
-- Ex: Fixa: Habitação, Variável: Restaurante, Receita: Salário, etc.
-- 'tipo' = o grande grupo (equivale às seções da planilha)
-- 'e_reembolso' = marca categorias tipo "Receita: outras" quando são
--                 empréstimo/reembolso recebido, não receita "de verdade"
-- =====================================================================
CREATE TABLE categorias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('fixa', 'variavel', 'receita', 'dividas_parcelados') NOT NULL,
    nome VARCHAR(150) NOT NULL,
    descricao VARCHAR(255) NULL,
    e_reembolso BOOLEAN NOT NULL DEFAULT FALSE,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_categoria_usuario (usuario_id, tipo, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 3. FORMAS DE PAGAMENTO
-- Débito/Dinheiro/Pix + Cartões de crédito (com limite, quando aplicável)
-- =====================================================================
CREATE TABLE formas_pagamento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('debito_dinheiro_pix', 'cartao_credito') NOT NULL,
    limite_credito DECIMAL(12,2) NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 4. LANÇAMENTOS
-- O coração do sistema: cada gasto/receita individual
-- Substitui a aba alimentada pelo Google Forms
-- =====================================================================
CREATE TABLE lancamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    forma_pagamento_id INT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data DATE NOT NULL,
    descricao VARCHAR(255) NULL,
    parcelas TINYINT UNSIGNED NULL,
    status_pagamento ENUM('pago', 'pendente') NOT NULL DEFAULT 'pago',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (forma_pagamento_id) REFERENCES formas_pagamento(id),
    INDEX idx_lancamentos_data (usuario_id, data),
    INDEX idx_lancamentos_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 5. ORÇAMENTO MENSAL (Previsto)
-- Valor planejado por categoria, por mês/ano
-- =====================================================================
CREATE TABLE orcamento_mensal (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    categoria_id INT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL, -- 1 a 12
    ano SMALLINT UNSIGNED NOT NULL,
    valor_previsto DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    UNIQUE KEY uq_orcamento (usuario_id, categoria_id, mes, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 6. ATIVOS (Investimentos)
-- Catálogo genérico: FIIs, ações, renda fixa, cripto, etc.
-- =====================================================================
CREATE TABLE ativos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL, -- ex: BTLG11, Tesouro Selic 2029
    tipo_ativo ENUM('fii', 'acao', 'renda_fixa', 'cripto', 'fundo', 'outro') NOT NULL,
    subcategoria VARCHAR(100) NULL, -- ex: Tijolo Logística, Papel CDI (principalmente para FIIs)
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ativo_usuario (usuario_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 7. MOVIMENTAÇÕES DE INVESTIMENTOS
-- Compras (e futuramente vendas) de ativos
-- =====================================================================
CREATE TABLE movimentacoes_investimentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    ativo_id INT UNSIGNED NOT NULL,
    tipo_movimento ENUM('compra', 'venda') NOT NULL DEFAULT 'compra',
    data DATE NOT NULL,
    quantidade DECIMAL(14,6) NOT NULL, -- decimal p/ suportar cripto (fracionado)
    preco_unitario DECIMAL(12,4) NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL, -- já incluindo taxas
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ativo_id) REFERENCES ativos(id),
    INDEX idx_movimentacoes_data (usuario_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 8. RENDIMENTOS DE INVESTIMENTOS
-- Dividendos/rendimentos recebidos por ativo, por mês
-- 'lancamento_id' vincula esse rendimento a um lançamento de receita
-- gerado automaticamente, para que ele apareça no Relatório Mensal,
-- Orçamento e Fluxo de Caixa como "Receita: Investimentos"
-- =====================================================================
CREATE TABLE rendimentos_investimentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    ativo_id INT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_recebimento DATE NULL,
    lancamento_id INT UNSIGNED NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (ativo_id) REFERENCES ativos(id),
    FOREIGN KEY (lancamento_id) REFERENCES lancamentos(id) ON DELETE SET NULL,
    UNIQUE KEY uq_rendimento (ativo_id, mes, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 9. CONTAS DE PATRIMÔNIO
-- Onde o dinheiro fica: bancos, corretoras, fundos, "dinheiro em espécie"
-- =====================================================================
CREATE TABLE contas_patrimonio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nome VARCHAR(100) NOT NULL, -- ex: Itaú, Inter, Fundo Mapfre, LCI, Dinheiro
    tipo VARCHAR(100) NULL, -- ex: conta corrente, investimento, dinheiro físico
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_conta_usuario (usuario_id, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 10. SALDOS MENSAIS (Patrimônio)
-- Snapshot manual do valor em cada conta, por mês
-- Usado para o gráfico de evolução patrimonial e reconciliação
-- =====================================================================
CREATE TABLE saldos_mensais (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    conta_id INT UNSIGNED NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    valor DECIMAL(14,2) NOT NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_id) REFERENCES contas_patrimonio(id),
    UNIQUE KEY uq_saldo_mensal (conta_id, mes, ano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- 11. LANÇAMENTOS RECORRENTES
-- =====================================================================
CREATE TABLE lancamentos_recorrentes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo ENUM('receita', 'despesa_fixa', 'divida_parcelada') NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor_mensal DECIMAL(10,2) NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- FIM DO SCHEMA
-- =====================================================================
