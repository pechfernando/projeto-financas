# Registro de Alterações — Sessão de Desenvolvimento

Resumo detalhado de tudo que foi implementado nesta sessão, para você manter
como histórico do projeto. Organizado em duas entregas: **Fase 5 (Patrimônio)**
e **Ajustes na tela de Investimentos**.

---

## 1. Fase 5 — Patrimônio

O banco de dados já tinha as tabelas `contas_patrimonio` e `saldos_mensais`
modeladas desde a Fase 0, mas não existia nenhum código de aplicação para
elas. Implementamos a fase completa.

### Regra de negócio definida
A **reconciliação** compara:
- **Saldo real** = soma dos saldos que você lança manualmente por conta, no mês
- **Saldo calculado** = saldo acumulado do Fluxo de Caixa (receitas − despesas
  de todos os lançamentos até aquele mês)
- **Diferença** = saldo real − saldo calculado (verde se ≈ 0, vermelho se não)

### Arquivos criados
| Arquivo | O que faz |
|---|---|
| `src/app/Models/Patrimonio.php` | CRUD de contas, upsert de saldos mensais, total do mês, série de evolução (últimos N meses) |
| `src/app/Api/Controllers/PatrimonioController.php` | Endpoints da API; usa também o `FluxoCaixa` model para calcular a reconciliação |
| `src/public/app/patrimonio.html` | Tela: seletor de mês/ano, card de reconciliação, tabela de saldos por conta, gráfico de evolução, cadastro de contas |
| `src/public/app/patrimonio.js` | Lógica da tela acima |

### Arquivos modificados
| Arquivo | O que mudou |
|---|---|
| `src/public/api/index.php` | Registrado `Patrimonio` model + `PatrimonioController`; novas rotas (ver abaixo) |
| `src/public/app/style.css` | Classes `.ajuda-inline` e `.tabela-reconciliacao` |
| `index.html`, `relatorio.html`, `orcamento.html`, `fluxo-caixa.html`, `investimentos.html` | Link "Patrimônio" adicionado ao menu |
| `README.md` | Checklist: Fase 5 marcada como concluída |

### Rotas novas na API
```
GET    /contas-patrimonio
POST   /contas-patrimonio
GET    /saldos-mensais?mes=&ano=
POST   /saldos-mensais
GET    /evolucao-patrimonial?mes=&ano=&meses=
```

---

## 2. Ajustes na tela de Investimentos

Três pedidos separados, implementados juntos:

### 2.1 — Campo de preço unitário na compra
- Novo campo **obrigatório** "Preço Unitário (da cota, no dia)" no formulário
  de registrar compra (`investimentos.html` / `.js`)
- Adicionei também uma seção **"Histórico de Compras"**, que não existia:
  tabela com data, ativo, quantidade, preço unitário, valor total e botão de
  apagar (o endpoint de apagar já existia no backend, só não estava exposto
  na tela)

### 2.2 — Rendimento entra no Relatório Mensal como Receita
Antes, o formulário de "Registrar Rendimento" salvava o valor mas ele não
aparecia em nenhum lugar. Agora, ao registrar um rendimento:

1. O sistema busca (ou cria, na primeira vez) uma categoria **"Investimentos"**
   (tipo receita)
2. O sistema busca (ou cria) uma forma de pagamento padrão do tipo
   débito/dinheiro/pix
3. Cria (ou **atualiza**, se você reenviar o mesmo ativo/mês/ano) um
   **lançamento de receita** vinculado ao rendimento

Como o Relatório Mensal, Orçamento, Fluxo de Caixa e a reconciliação de
Patrimônio já leem tudo direto da tabela `lancamentos`, o valor passa a
aparecer automaticamente em **todos** eles — sem lógica duplicada.

> ⚠️ **Decisão que tomei, vale confirmar com você:** usei a forma de
> pagamento "Débito/Dinheiro/Pix" como padrão para esse lançamento
> automático. Se seus dividendos entram em outro lugar, me avisa que ajusto.

### 2.3 — Nova tela de Rendimentos
`src/public/app/rendimentos.html` + `rendimentos.js` — tela dedicada para
acompanhar o que já foi recebido:
- Filtro por ano
- Total recebido no ano
- Gráfico + tabela de total por ativo
- Detalhe mês a mês, com subtotal de cada mês

### Arquivos criados
| Arquivo | O que faz |
|---|---|
| `database/migrations/001_rendimentos_lancamento_id.sql` | Script para atualizar um banco já existente sem perder dados |
| `src/public/app/rendimentos.html` | Tela de Rendimentos |
| `src/public/app/rendimentos.js` | Lógica da tela acima |

### Arquivos modificados
| Arquivo | O que mudou |
|---|---|
| `database/schema.sql` | Tabela `rendimentos_investimentos` ganhou a coluna `lancamento_id` (FK para `lancamentos`, `ON DELETE SET NULL`) |
| `src/app/Models/Categoria.php` | Método novo: `buscarOuCriarCategoriaInvestimentos()` |
| `src/app/Models/FormaPagamento.php` | Método novo: `obterOuCriarFormaPadrao()` |
| `src/app/Models/RendimentoInvestimento.php` | Reescrito: agora recebe `Categoria` e `FormaPagamento` como dependências e sincroniza o lançamento a cada `criar()`. `listar()` passou a aceitar filtro só por ano (sem mês) |
| `src/public/api/index.php` | `RendimentoInvestimento` agora é instanciado com as duas dependências novas |
| `src/public/app/investimentos.html` | Campo de preço unitário; seção de histórico de compras; link para a tela de Rendimentos |
| `src/public/app/investimentos.js` | Envio do `preco_unitario`; carregar/renderizar/apagar histórico; mensagem de sucesso do rendimento atualizada |
| Menu de todas as páginas | Link "Rendimentos" adicionado |

---

## ⚠️ Passo necessário antes de rodar

O schema do banco mudou (nova coluna `lancamento_id`). Escolha uma opção:

**Opção A — Recriar o banco do zero** (perde dados de teste, tudo bem se
ainda for só teste):
```bash
docker compose down -v
docker compose up -d
```

**Opção B — Migrar sem perder dados**, rodando manualmente (via phpMyAdmin
ou linha de comando):
```
database/migrations/001_rendimentos_lancamento_id.sql
```

---

## Pendências / próximos passos possíveis
- [ ] Confirmar se a forma de pagamento padrão dos rendimentos está correta
- [ ] Fase 6 — PWA / mobile
- [ ] Fase 7 — Migração dos dados da planilha antiga
- [ ] (Sugestão) Apagar um rendimento hoje não remove o lançamento vinculado
      automaticamente — se isso for importante pra você, dá pra implementar
