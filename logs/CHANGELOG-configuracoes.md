# Registro de Alterações — Menu de Configurações

Resumo detalhado do desenvolvimento do **Menu de Configurações**, implementado para permitir o gerenciamento (cadastro, edição, desativação/reativação) de **Categorias** e **Formas de Pagamento**.

---

## 1. Objetivo da Implementação
Anteriormente, o sistema contava com categorias e formas de pagamento fixas inseridas via `seed.sql`. Com esta nova funcionalidade, o usuário tem controle total sobre suas opções de lançamentos direto pela interface do sistema, podendo:
- Cadastrar novas categorias (e definir se representam reembolsos).
- Cadastrar novas formas de pagamento (e gerenciar limites de cartões de crédito).
- Editar nomes, descrições e tipos existentes.
- **Desativar/Reativar** categorias e formas de pagamento (garantindo a integridade dos lançamentos passados, mas ocultando-as para novos lançamentos).

---

## 2. Arquivos Criados e Modificados

### Arquivos Criados
| Arquivo | O que faz |
|---|---|
| `src/public/app/configuracoes.html` | Tela de Configurações: formulários de cadastro/edição e tabelas de listagem para categorias e formas de pagamento. |
| `src/public/app/configuracoes.js` | Lógica da tela de configurações, incluindo requisições para a API e manipulação do DOM. |

### Arquivos Modificados
| Arquivo | O que mudou |
|---|---|
| `src/app/Models/Categoria.php` | Métodos `buscarPorId()` e `atualizar()` para persistir edições e status ativo/inativo. |
| `src/app/Models/FormaPagamento.php` | Métodos `buscarPorId()` e `atualizar()` para gerenciar edições, limites de crédito e status ativo/inativo. |
| `src/app/Api/Controllers/CategoriasController.php` | Métodos `buscar()`, `atualizar()` e suporte ao parâmetro `?todas=1` no método `listar()`. |
| `src/app/Api/Controllers/FormasPagamentoController.php` | Métodos `buscar()`, `atualizar()` e suporte ao parâmetro `?todas=1` no método `listar()`. |
| `src/public/api/index.php` | Registro das novas rotas de busca e atualização na API (ver seção abaixo). |
| `src/public/app/style.css` | Adicionado estilo `.campo-checkbox` para inputs de marcação. |
| Menus de todas as páginas `.html` | Adicionado o link para a página "Configurações" no cabeçalho de navegação. |

---

## 3. Novas Rotas de API

As rotas existentes foram estendidas para suportar a listagem completa (incluindo desativados), busca por ID e atualização:

```
// Categorias
GET    /api/categorias          -> Retorna apenas categorias ativas
GET    /api/categorias?todas=1  -> Retorna todas as categorias (ativas e inativas)
GET    /api/categorias/{id}      -> Busca uma categoria específica
POST   /api/categorias          -> Cadastra uma nova categoria
PUT    /api/categorias/{id}      -> Atualiza os dados de uma categoria (incluindo status ativo)

// Formas de Pagamento
GET    /api/formas-pagamento          -> Retorna apenas formas ativas
GET    /api/formas-pagamento?todas=1  -> Retorna todas as formas (ativas e inativas)
GET    /api/formas-pagamento/{id}      -> Busca uma forma específica
POST   /api/formas-pagamento          -> Cadastra uma nova forma de pagamento
PUT    /api/formas-pagamento/{id}      -> Atualiza os dados (incluindo limite de crédito e status ativo)
```

---

## 4. Funcionamento das Regras de Negócio

### Desativação Segura
- Quando uma categoria ou forma de pagamento é desativada (`ativo = 0`), ela **não é apagada do banco**.
- Lançamentos anteriores continuam existindo e mantêm a referência para a categoria/forma desativada.
- No formulário de novos lançamentos (`src/public/app/app.js`), apenas as categorias/formas que retornam de `GET /api/categorias` e `GET /api/formas-pagamento` (ou seja, as ativas) são mostradas.
- A tela de Configurações passa o parâmetro `?todas=1` para listar também as desativadas, permitindo que sejam reativadas a qualquer momento.

### Restrição Única
- A tabela `categorias` possui restrição única por `(usuario_id, tipo, nome)`. Se o usuário tentar criar ou renomear uma categoria ativa/inativa para um nome idêntico sob o mesmo tipo, o sistema captura o erro do banco de dados (código SQL `23000`) e exibe uma mensagem amigável instruindo-o a reativar o registro se ele estiver inativo.
