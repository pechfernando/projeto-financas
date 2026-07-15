# Plano - Aplicação Financeira

Aplicação própria para substituir a planilha de controle financeiro,
cobrindo: lançamentos, relatório mensal, orçamento, investimentos e
patrimônio.

## Como rodar localmente (Docker)

### Pré-requisitos
- Docker Desktop instalado e rodando

### Passo a passo

1. Copie o arquivo de variáveis de ambiente:
   ```bash
   cp .env.example .env
   ```
   (pode deixar os valores padrão para desenvolvimento local)

2. Suba os containers:
   ```bash
   docker compose up -d
   ```

3. Na **primeira vez** que você subir os containers, o MySQL vai
   automaticamente rodar os arquivos dentro de `database/` (schema.sql
   e seed.sql) e já criar todas as tabelas com alguns dados de teste.

   > Se você já subiu os containers antes e mudou o schema.sql, isso
   > **não** roda de novo sozinho (o MySQL só executa esses scripts na
   > primeira inicialização do volume). Nesse caso, rode:
   > ```bash
   > docker compose down -v
   > docker compose up -d
   > ```
   > (o `-v` apaga o volume do banco e recria do zero — cuidado, isso
   > apaga dados que você tenha lançado manualmente também)

4. Acesse no navegador:
   - **Aplicação**: http://localhost:8080
   - **phpMyAdmin** (visualizar/editar o banco visualmente): http://localhost:8081
     - Servidor: `db` | Usuário e senha: os que você definiu no `.env`

5. Se a página inicial mostrar "✅ Conexão com o banco de dados
   funcionando!" e listar as tabelas, está tudo certo.

## Estrutura de pastas

```
projeto-financas/
├── docker-compose.yml       # Define os containers (app, banco, phpmyadmin)
├── docker/php/Dockerfile    # Configuração do container PHP
├── database/
│   ├── schema.sql           # Estrutura das tabelas
│   └── seed.sql             # Dados iniciais de teste
├── src/
│   ├── public/               # Pasta pública (raiz do site)
│   │   └── index.php
│   └── app/
│       ├── Config/           # Conexão com banco, configurações gerais
│       ├── Models/           # Regras de acesso a cada tabela
│       ├── Controllers/      # Lógica de cada tela/ação
│       └── Api/               # Endpoints da API (JSON) usados pelo frontend
└── .env.example
```

## Próximos passos

- [ ] Fase 1: CRUD de lançamentos + categorias + formas de pagamento
- [ ] Fase 2: Relatório mensal (totais por categoria + gráfico)
- [ ] Fase 3: Orçamento + saldo/fluxo de caixa
- [ ] Fase 4: Investimentos
- [ ] Fase 5: Patrimônio
- [ ] Fase 6: PWA / mobile
- [ ] Fase 7: Migração dos dados da planilha antiga
