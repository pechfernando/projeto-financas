# Registro de Alterações — Fusão de Telas e Melhorias Avançadas

Resumo detalhado de todas as grandes modificações, fusões e novas funcionalidades avançadas desenvolvidas nesta sessão para manter o histórico evolutivo da aplicação **"plano."**.

---

## 1. Fusão de Investimentos & Rendimentos + Exclusão Física de Rendimentos

Eliminamos a tela externa `rendimentos.html` e unificamos tudo em uma experiência integrada em abas na página de Investimentos. Além disso, implementamos a exclusão física e lógica segura de rendimentos.

### Modificações Realizadas:
* **Remoção de Arquivos**: Os arquivos obsoletos `src/public/app/rendimentos.html` e `src/public/app/rendimentos.js` foram excluídos definitivamente.
* **Backend**:
  * **Model `RendimentoInvestimento.php`**: Criado o método `apagar(int $id, int $usuarioId)` para excluir fisicamente o rendimento da tabela e remover o correspondente lançamento de receita vinculado (`lancamento_id`), mantendo os relatórios íntegros.
  * **Controller `RendimentosInvestimentosController.php`**: Adicionado o método `apagar()` para tratar a requisição de exclusão segura.
  * **API (`index.php`)**: Registrada a rota `DELETE /api/rendimentos-investimentos/{id}`.
* **Frontend (`investimentos.html` / `investimentos.js`)**:
  * Adicionada a aba **"Rendimentos Recebidos"** ao lado de "Minha Carteira" e "Registrar".
  * Integrados todos os componentes visuais: filtro de ano, card de total anual, gráfico de distribuição e detalhe mensal por subtotais.
  * **Exclusão de Rendimento**: Adicionado botão "Apagar" na tabela mensal detalhada de rendimentos. Ao clicar, executa o `DELETE` e atualiza a interface instantaneamente.
  * **Navegação**: O link de "Rendimentos" foi removido do menu de navegação de todas as páginas.

---

## 2. Fusão de Patrimônio & Fluxo de Caixa (Cockpit Unificado)

A antiga tela `fluxo-caixa.html` foi eliminada, e toda a lógica de fluxo transacional e reservas de fôlego foi movida para dentro de uma interface coesa na tela de Patrimônio, criando um cockpit financeiro global.

### Modificações Realizadas:
* **Remoção de Arquivos**: Os arquivos obsoletos `src/public/app/fluxo-caixa.html` e `src/public/app/fluxo-caixa.js` foram excluídos definitivamente.
* **Backend**:
  * **Model `FluxoCaixa.php`**: O método `serieMensal` foi expandido. Agora, além dos saldos, ele computa e retorna o **total de receitas** e o **total de despesas** detalhados de cada mês de forma nativa.
* **Frontend (`patrimonio.html` / `patrimonio.js`)**:
  * Organizado em **3 abas dinâmicas**:
    1. **Visão Geral & Reconciliação**: Reúne o card de reconciliação, o gráfico de evolução patrimonial real e o card de **Fôlego Financeiro** (importado do fluxo de caixa).
    2. **Fluxo de Caixa Detalhado**: Contém o gráfico de saldo acumulado de caixa e a tabela analítica do fluxo (`Mês | Receitas (+) | Despesas (-) | Saldo do Mês (=) | Saldo Acumulado`).
    3. **Lançar Saldos & Contas**: Formulário de snapshot mensal e cadastro de contas.
  * **Navegação**: Removido o link de "Fluxo de Caixa" do cabeçalho de todas as páginas.

---

## 3. Lançamento de Saldos com Auto-Save on Blur

Substituímos o envio manual de formulário por salvamento automático sob demanda na aba "Lançar Saldos".

### Modificações Realizadas:
* **Frontend (`patrimonio.js`)**:
  * Conectado o evento `blur` (perda de foco) a todos os inputs da tabela de saldos.
  * Conforme o usuário digita os valores e clica fora, as informações são enviadas em segundo plano para o endpoint `POST /api/saldos-mensais`.
  * O sistema atualiza o indicador visual, a reconciliação do período e o gráfico de evolução sem reconstruir a tabela de inputs, mantendo o cursor estável e a digitação contínua.

---

## 4. Configuração de "Saldo Inicial de Caixa"

Criamos a flexibilidade para o usuário informar um saldo de caixa inicial que comporá o saldo acumulado total das transações desde a origem dos tempos.

### Modificações Realizadas:
* **Banco de Dados**:
  * Executado ALTER TABLE para adicionar o campo `saldo_inicial_caixa DECIMAL(12,2) NOT NULL DEFAULT 0.00` à tabela `usuarios`.
  * Atualizado o arquivo principal `database/schema.sql` com a nova estrutura.
* **Backend**:
  * **Model `FluxoCaixa.php`**: Adicionados os métodos `obterSaldoInicial()` e `salvarSaldoInicial()`. Atualizado o método `saldoAcumulado()` para realizar a soma do saldo transacional com este saldo inicial do usuário.
  * **Controller `FluxoCaixaController.php`**: Adicionadas as actions `obterSaldoInicial` e `salvarSaldoInicial`.
  * **API (`index.php`)**: Registrados os endpoints `GET/POST /api/configuracoes/saldo-inicial`.
* **Frontend (`configuracoes.html` / `configuracoes.js`)**:
  * Adicionada a aba **"Gerais"** na tela de configurações contendo o formulário para atualizar e salvar o saldo inicial de caixa a qualquer momento.

---

## 5. Horizonte Temporal Dinâmico nos Gráficos de Patrimônio & Caixa

Permitimos customizar dinamicamente o horizonte temporal histórico exibido, em vez de fixá-lo em 6 meses.

### Modificações Realizadas:
* **Frontend (`patrimonio.html` / `patrimonio.js`)**:
  * Adicionado o seletor **"Horizonte"** no menu de períodos com as opções: `Últimos 6 meses`, `Últimos 12 meses`, `Últimos 24 meses` e `Últimos 3 anos`.
  * O javascript escuta o evento `change` deste seletor e atualiza as chamadas das APIs `evolucao-patrimonial` e `fluxo-caixa`, atualizando os gráficos e tabelas com o novo período instantaneamente.

---

## 6. CRUD de Contas Patrimoniais com Soft-Disable

Unificamos a gestão de contas de patrimônio trazendo-a para o menu central de configurações com regras de integridade históricas.

### Modificações Realizadas:
* **Backend**:
  * **Model `Patrimonio.php`**: Criados os métodos `buscarContaPorId()`, `atualizarConta()` e `apagarConta()`.
  * **Controller `PatrimonioController.php`**:
    * Criadas as actions REST de buscar, atualizar e apagar.
    * O método de exclusão física trata o erro `23000` (chave estrangeira) caso existam saldos mensais atrelados a ela, bloqueando a remoção e sugerindo a desativação amigável.
  * **API (`index.php`)**: Registradas as novas rotas REST em `/api/contas-patrimonio`.
* **Frontend (`configuracoes.html` / `configuracoes.js`)**:
  * A página de configurações foi convertida para usar o sistema de abas do `tabs.js`.
  * Criada a aba **"Contas Patrimoniais"** com CRUD completo: formulário para cadastro e edição de nome, tipo e ordem de prioridade; e listagem com status (Ativa/Inativa), botão de Editar, botão de Ativar/Desativar e botão de Excluir física.
