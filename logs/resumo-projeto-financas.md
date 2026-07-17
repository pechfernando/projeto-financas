# Resumo do Projeto — Aplicação Financeira "plano."

> Este arquivo resume tudo que foi decidido e construído até agora, para retomar o
> desenvolvimento em uma nova conversa sem precisar reexplicar o contexto.
> Basta anexar este arquivo no início do novo chat.

## 1. Contexto e objetivo

O usuário (Fernando) usava uma planilha do Excel/Google Sheets (fornecida por uma
consultoria financeira chamada "Plano") para controlar gastos, orçamento e
investimentos. A consultoria vai parar de atualizar a planilha, então ele decidiu
criar sua própria aplicação web, reaproveitando a lógica da planilha antiga.

**Requisitos principais:**
- Deve funcionar tanto no PC quanto no celular (aplicação web responsiva)
- Usuário é intermediário/avançado em programação, disposto a codar com ajuda
- Hospedagem: Hostinger, plano **Single** (hospedagem compartilhada — só suporta
  PHP + MySQL, sem SSH/terminal, só FTP/Gerenciador de Arquivos)
- Ambiente de dev local: **Docker** + **VS Code** (terminal PowerShell no Windows)
- Repositório: GitHub (`https://github.com/pechfernando/Projeto-financas`, privado)
- Futuro: pode virar multiusuário (família), pode investir em outros tipos de
  ativos além de FIIs (ainda incerto quais)

## 2. Decisões de arquitetura

- **Sem framework pesado** (tipo Laravel): como não há SSH na Hostinger, optamos
  por **PHP puro organizado em pastas** (Models, Controllers, API), sem Composer/CLI
  no servidor. Deploy via Git (Hostinger tem integração com Git no painel).
- **Frontend**: HTML + CSS + JavaScript puro (sem framework), consumindo uma
  **API REST em JSON** via `fetch` — arquitetura desacoplada (backend só entrega
  dados, frontend só consome). Isso facilita evoluir para PWA depois.
- **Banco de dados**: MySQL, acesso via PDO.
- **Gráficos**: Chart.js via CDN (`https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js`
  — **atenção**: a versão `4.4.4` NÃO existe no cdnjs e quebra o carregamento;
  usar sempre `4.4.1` ou verificar versões disponíveis antes de trocar).
- **Charset**: sempre usar `utf8mb4` — já tivemos um bug de acentuação
  (mojibake tipo "ComunicaÃ§Ã£o") porque o `seed.sql` rodava sem `SET NAMES
  utf8mb4;` no início. Isso já foi corrigido, mas é um lembrete pra sempre
  incluir essa linha em novos scripts SQL.
- **Autenticação**: ainda NÃO implementada. Existe uma função temporária
  `usuarioAtualId()` em `src/app/Api/Auth.php` que sempre retorna `1` (o
  "Usuário Teste" do seed). Quando implementarmos login de verdade, só essa
  função precisa mudar — o resto do código já depende dela.
- **Interatividade**: telas SPA-like por página (cada tela é um `.html` com seu
  `.js` próprio, sem recarregar a página inteira ao interagir, mas navegação
  entre telas é por link normal `<a href>`).

## 3. Mapeamento da planilha antiga (o que virou o quê)

A planilha tinha: um Google Forms de entrada, uma aba de lançamentos brutos, uma
aba de "Relatório Mensal" (com Realizado x Previsto, gráfico de rosca — o
usuário só usa a parte central "Apontamento" e o gráfico de rosca dos gastos
variáveis, não usa Previsto/Forma de Pagamento lateral), uma aba de compras de
FIIs, e uma aba grande de planejamento anual (Receitas/Despesas por mês,
Saldo/Saldo Acumulado — usado como indicador de "fôlego financeiro" já que o
usuário não tem renda fixa —, e uma tabela de Saldos por conta/investimento
preenchida manualmente).

**Conceitos importantes capturados:**
- Categorias seguem o padrão `Tipo: Nome (descrição)` — ex: "Variável: Restaurante".
  Separamos em `tipo` (fixa/variavel/receita/dividas_parcelados) + `nome`.
- Existem lançamentos "Receita: outras" que na verdade são reembolsos/empréstimos
  de terceiros (ex: "Pai devendo luz") — não é receita de verdade. Campo
  `e_reembolso` na categoria marca isso.
- "Riscado" na planilha = pago/recebido; sem risco = pendente. Viramos campo
  `status_pagamento` (enum: pago/pendente).
- O usuário nunca usa parcelamento nem "Receita: Saldo Final Mês" na prática.
- Cartões de crédito têm limite (Nubank 12800, Santander 4900, Inter 6500,
  Itaú Black 15000).
- O "Previsão/Diferença" na planilha antiga era, na real, um workaround pra bug
  de fórmula (o usuário calculava o saldo real na mão e sobrescrevia). Decidimos
  reinterpretar isso como **reconciliação** (comparar saldo calculado vs saldo
  real das contas) ao invés de replicar o bug — funcionalidade planejada pra
  Fase 5 (Patrimônio), ainda não construída.
- Compras de FIIs: data, fundo (ticker), valor da cota, número de cotas, valor
  final com taxas, tipo (Tijolo Logística, Papel CDI, etc. — é característica do
  **ativo**, não da compra, porque só aparecia preenchido na primeira compra de
  cada fundo).
- Usuário só compra (nunca vendeu ainda, mas quer poder registrar venda no
  futuro) e pode vir a investir em outras coisas além de FIIs (modelo genérico).
- Rendimentos/dividendos de FIIs eram registrados dentro da aba de receitas da
  planilha anual — ou seja, **contam como receita real** no fluxo de caixa.

## 4. Schema do banco de dados (completo)

Tabelas criadas em `database/schema.sql`:

1. **usuarios** — id, nome, email, senha_hash (multiusuário pronto pro futuro)
2. **categorias** — usuario_id, tipo (enum), nome, descricao, `e_reembolso`, ativo, ordem
3. **formas_pagamento** — usuario_id, nome, tipo (debito_dinheiro_pix/cartao_credito), limite_credito
4. **lancamentos** — usuario_id, categoria_id, forma_pagamento_id, valor, data, descricao, parcelas, `status_pagamento` (pago/pendente)
5. **orcamento_mensal** — usuario_id, categoria_id, mes, ano, valor_previsto (unique por usuario+categoria+mes+ano)
6. **ativos** — usuario_id, nome, tipo_ativo (fii/acao/renda_fixa/cripto/fundo/outro), subcategoria
7. **movimentacoes_investimentos** — usuario_id, ativo_id, tipo_movimento (compra/venda), data, quantidade, preco_unitario, valor_total
8. **rendimentos_investimentos** — usuario_id, ativo_id, `lancamento_id` (FK nullable, vincula ao lançamento gerado automaticamente), mes, ano, valor, data_recebimento (unique por ativo+mes+ano)
9. **contas_patrimonio** — usuario_id, nome, tipo, ordem (ainda não implementada no backend/frontend — Fase 5)
10. **saldos_mensais** — usuario_id, conta_id, mes, ano, valor (ainda não implementada — Fase 5)

Todas as tabelas com `usuario_id` têm FK para `usuarios(id) ON DELETE CASCADE`.
Charset `utf8mb4` em tudo.

`database/seed.sql` cria um usuário de teste (id=1, email teste@exemplo.com) e
algumas categorias/formas de pagamento iniciais — **sempre com `SET NAMES
utf8mb4;` no topo**.

## 5. Ambiente de desenvolvimento

- **Docker Compose** (`docker-compose.yml`) com 3 serviços:
  - `app`: PHP 8.3 + Apache (Dockerfile em `docker/php/Dockerfile`), porta 8080,
    document root apontado pra `/var/www/html/public`
  - `db`: MySQL 8.0, porta 3306, roda `schema.sql` e `seed.sql` automaticamente
    na **primeira** inicialização do volume (pasta `database/` montada em
    `/docker-entrypoint-initdb.d`). Tem `command:
    --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci` pra
    garantir UTF-8 por padrão.
  - `phpmyadmin`: porta 8081, interface visual do banco
- Variáveis de ambiente em `.env` (não versionado — só `.env.example` vai pro Git)
- Para recriar o banco do zero (aplicar mudanças no schema.sql):
  ```powershell
  docker compose down -v
  docker compose up -d
  ```
- Repositório Git já configurado e funcionando:
  `https://github.com/pechfernando/Projeto-financas`
- Terminal usado: **PowerShell** dentro do VS Code

## 6. Estrutura de pastas do projeto

```
projeto-financas/
├── docker-compose.yml
├── docker/php/Dockerfile
├── database/
│   ├── schema.sql
│   └── seed.sql
├── .env.example
├── .gitignore
├── README.md
└── src/
    ├── public/                        (document root do Apache)
    │   ├── index.php                  (página de diagnóstico/teste de conexão)
    │   ├── .htaccess                  (redireciona /api/* pro front controller)
    │   ├── api/
    │   │   └── index.php              (front controller da API, registra rotas)
    │   └── app/                       (frontend — telas da aplicação)
    │       ├── index.html + app.js    (Lançamentos)
    │       ├── relatorio.html + relatorio.js
    │       ├── orcamento.html + orcamento.js
    │       ├── fluxo-caixa.html + fluxo-caixa.js
    │       ├── investimentos.html + investimentos.js
    │       └── style.css              (compartilhado por todas as telas)
    └── app/
        ├── Config/database.php        (conexão PDO via variáveis de ambiente)
        ├── Api/
        │   ├── Router.php             (roteador simples, suporta {id} dinâmico)
        │   ├── Response.php           (helpers jsonResponse/jsonError/corpoRequisicao)
        │   ├── Auth.php               (usuarioAtualId() — hardcoded 1 por enquanto)
        │   └── Controllers/
        │       ├── LancamentosController.php
        │       ├── CategoriasController.php
        │       ├── FormasPagamentoController.php
        │       ├── RelatorioMensalController.php
        │       ├── OrcamentoController.php
        │       ├── FluxoCaixaController.php
        │       ├── AtivosController.php
        │       ├── MovimentacoesInvestimentosController.php
        │       └── RendimentosInvestimentosController.php
        └── Models/
            ├── Lancamento.php
            ├── Categoria.php          (tem obterOuCriarPorNome — find-or-create)
            ├── FormaPagamento.php     (tem obterOuCriarPadrao — find-or-create)
            ├── RelatorioMensal.php
            ├── Orcamento.php
            ├── FluxoCaixa.php
            ├── Ativo.php
            ├── MovimentacaoInvestimento.php
            └── RendimentoInvestimento.php
```

## 7. O que já foi construído (por fase)

### ✅ Fase 0 — Fundação
Schema completo, Docker rodando, Git configurado, VS Code conectado.

### ✅ Fase 1 — Lançamentos
CRUD completo (criar, listar, editar, apagar) em `/app/` (index.html). API:
`GET/POST /api/lancamentos`, `GET/PUT/DELETE /api/lancamentos/{id}`,
`GET /api/categorias`, `GET /api/formas-pagamento`. Testado e funcionando.

### ✅ Fase 2 — Relatório Mensal
Tela `/app/relatorio.html`: seletor de mês/ano, totais por tipo (Fixas,
Variáveis, Receita, Dívidas), saldo do mês, tabela "Apontamento por Categoria",
gráfico de rosca dos gastos variáveis (Chart.js). API: `GET
/api/relatorio-mensal?mes=X&ano=Y`. Testado e funcionando (depois de corrigir
bug de encoding UTF-8 e versão do Chart.js).

### ✅ Fase 3 — Orçamento + Fluxo de Caixa
- `/app/orcamento.html`: lista categorias agrupadas por tipo, campo editável de
  valor Previsto + coluna Realizado (calculada automaticamente), destaque
  vermelho se Realizado > Previsto, botão "copiar valores do mês anterior".
  API: `GET/POST /api/orcamento-mensal`, `POST
  /api/orcamento-mensal/copiar-mes-anterior`.
- `/app/fluxo-caixa.html`: Saldo do mês, Saldo Acumulado (calculado a partir dos
  lançamentos, sem precisar de saldo inicial cadastrado — vai ficar preciso
  quando migrarmos o histórico na Fase 7), gráfico de linha da evolução,
  indicador de "Fôlego Financeiro" (quantos meses o saldo dura no ritmo médio
  de gastos, com alerta visual se < 2 meses). API: `GET /api/fluxo-caixa?mes=X&ano=Y&meses=N`.
- Testado e funcionando. Usuário pediu explicação de uso da tela de Orçamento
  (já explicada: preencher previsto no início do mês, usar botão de copiar,
  Realizado é só leitura, salvar).

### 🔶 Fase 4 — Investimentos (em andamento, quase concluída)
- `/app/investimentos.html`: Minha Carteira (tabela consolidada por ativo:
  quantidade, preço médio, valor investido — calculado automaticamente),
  gráfico de distribuição por tipo de ativo, cadastro de ativo, registro de
  movimentação (compra), registro de rendimento. API: `GET/POST /api/ativos`,
  `GET/POST /api/movimentacoes-investimentos`, `DELETE
  /api/movimentacoes-investimentos/{id}`, `GET /api/carteira-investimentos`,
  `GET/POST /api/rendimentos-investimentos`.
- **Testado e funcionando no geral**, mas usuário pediu 2 melhorias que estavam
  sendo implementadas quando o chat ficou grande demais:
  1. **Campo de "Valor da Cota" no formulário de movimentação** — já
     adicionado no HTML (`mov-preco-unitario`), com texto de ajuda explicando
     que o Valor Total é calculado automaticamente (cotas × valor da cota) mas
     pode ser ajustado manualmente por causa de taxas. **FALTA**: atualizar o
     `investimentos.js` para implementar o cálculo automático bidirecional
     (JS ainda não foi editado com essa lógica — só o HTML foi alterado).
  2. **Rendimentos não apareciam em lugar nenhum e não contavam no fluxo de
     caixa.** Decisão tomada: rendimentos registrados devem gerar
     automaticamente um lançamento na categoria "Receita: Rendimentos de
     Investimentos", contando no Saldo do Mês/Fluxo de Caixa. **Isso já foi
     implementado no backend** (Categoria::obterOuCriarPorNome,
     FormaPagamento::obterOuCriarPadrao, RendimentoInvestimento com
     lancamento_id vinculado, RendimentosInvestimentosController orquestrando
     tudo) — inclusive corrigimos um bug onde o `index.php` não estava
     passando todas as dependências pro construtor do
     `RendimentosInvestimentosController` (já corrigido). **FALTA**: 1) testar
     esse fluxo ponta a ponta; 2) adicionar uma tabela de histórico de
     "Rendimentos Recebidos" na tela de investimentos (o usuário perguntou
     "onde vejo isso?" — hoje só existe o formulário de cadastro, sem nenhuma
     listagem visível na tela).

### ⬜ Fase 5 — Patrimônio (não iniciada)
Cadastro de contas/investimentos (`contas_patrimonio`) e saldos mensais
manuais (`saldos_mensais`) — tabelas já existem no schema, falta
Model/Controller/rotas/frontend. Deve incluir: reconciliação (comparar saldo
calculado dos lançamentos vs saldo real informado manualmente) e gráfico de
evolução patrimonial.

### ⬜ Fase 6 — PWA / Mobile (não iniciada)
Ajustes de responsividade (já está responsivo em CSS básico) e transformar em
PWA instalável.

### ⬜ Fase 7 — Migração dos dados da planilha antiga (não iniciada, proposital)
Normalizar (categorias, formas de pagamento, valores, datas) e importar as
~2700 linhas de lançamentos históricos + histórico de saldos/investimentos.
Vai resolver a imprecisão do Saldo Acumulado (que hoje começa do zero).

## 8. Bugs já resolvidos (para não repetir)

1. **Acentuação corrompida (mojibake)**: causa era `seed.sql` sem `SET NAMES
   utf8mb4;`. Corrigido — sempre incluir essa linha em scripts SQL novos.
2. **Gráfico de rosca não aparecia**: causa era usar Chart.js versão `4.4.4`,
   que não existe no cdnjs. Corrigido para `4.4.1`. Adicionamos também uma
   verificação defensiva no JS (`typeof Chart === 'undefined'`) pra não quebrar
   silenciosamente se o CDN falhar de novo.
3. **RendimentosInvestimentosController não recebia todas as dependências no
   `index.php`** (só passava 1 argumento ao invés de 5) — corrigido.

## 9. Como retomar

1. Cole este arquivo no início de uma nova conversa com o Claude.
2. Diga que quer continuar de onde parou: terminar a Fase 4 (JS do cálculo
   automático de preço unitário + tabela de histórico de rendimentos + testar
   o fluxo de rendimento→lançamento automático), depois seguir pra Fase 5.
3. O projeto local do usuário já reflete tudo isso (ele foi aplicando os zips
   incrementais que o Claude gerou a cada fase). Não é necessário re-enviar o
   projeto inteiro do zero — só os arquivos/trechos que mudarem a partir de
   agora.
