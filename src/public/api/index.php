<?php

/**
 * Ponto de entrada de toda a API.
 * Todas as requisições para /api/* caem aqui (veja o .htaccess),
 * e esse arquivo decide para qual Controller encaminhar.
 */

// Permite que o frontend (rodando em outra porta/origem durante o
// desenvolvimento) consiga chamar essa API sem bloqueio de CORS.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Api/Response.php';
require_once __DIR__ . '/../../app/Api/Router.php';
require_once __DIR__ . '/../../app/Api/Auth.php';

require_once __DIR__ . '/../../app/Models/Lancamento.php';
require_once __DIR__ . '/../../app/Models/Categoria.php';
require_once __DIR__ . '/../../app/Models/FormaPagamento.php';
require_once __DIR__ . '/../../app/Models/RelatorioMensal.php';
require_once __DIR__ . '/../../app/Models/Orcamento.php';
require_once __DIR__ . '/../../app/Models/FluxoCaixa.php';
require_once __DIR__ . '/../../app/Models/Ativo.php';
require_once __DIR__ . '/../../app/Models/MovimentacaoInvestimento.php';
require_once __DIR__ . '/../../app/Models/RendimentoInvestimento.php';
require_once __DIR__ . '/../../app/Models/Patrimonio.php';
require_once __DIR__ . '/../../app/Models/RecorrenteFinanceiro.php';

require_once __DIR__ . '/../../app/Api/Controllers/LancamentosController.php';
require_once __DIR__ . '/../../app/Api/Controllers/CategoriasController.php';
require_once __DIR__ . '/../../app/Api/Controllers/FormasPagamentoController.php';
require_once __DIR__ . '/../../app/Api/Controllers/RelatorioMensalController.php';
require_once __DIR__ . '/../../app/Api/Controllers/OrcamentoController.php';
require_once __DIR__ . '/../../app/Api/Controllers/FluxoCaixaController.php';
require_once __DIR__ . '/../../app/Api/Controllers/AtivosController.php';
require_once __DIR__ . '/../../app/Api/Controllers/MovimentacoesInvestimentosController.php';
require_once __DIR__ . '/../../app/Api/Controllers/RendimentosInvestimentosController.php';
require_once __DIR__ . '/../../app/Api/Controllers/PatrimonioController.php';
require_once __DIR__ . '/../../app/Api/Controllers/RecorrentesController.php';

// Trata qualquer erro inesperado como uma resposta JSON (em vez de
// devolver uma página de erro HTML, que quebraria o frontend)
set_exception_handler(function (Throwable $e) {
    jsonError('Erro interno: ' . $e->getMessage(), 500);
});

$pdo = getConexaoBanco();

$lancamentosController = new LancamentosController(new Lancamento($pdo));
$categoriasController = new CategoriasController(new Categoria($pdo));
$formasPagamentoController = new FormasPagamentoController(new FormaPagamento($pdo));
$relatorioMensalController = new RelatorioMensalController(new RelatorioMensal($pdo));
$orcamentoController = new OrcamentoController(new Orcamento($pdo));
$recorrenteFinanceiro = new RecorrenteFinanceiro($pdo);
$recorrentesController = new RecorrentesController($recorrenteFinanceiro);
$fluxoCaixaController = new FluxoCaixaController(new FluxoCaixa($pdo), $recorrenteFinanceiro);
$ativosController = new AtivosController(new Ativo($pdo));
$movimentacoesController = new MovimentacoesInvestimentosController(new MovimentacaoInvestimento($pdo));
$rendimentosController = new RendimentosInvestimentosController(
    new RendimentoInvestimento($pdo, new Categoria($pdo), new FormaPagamento($pdo))
);
$patrimonioController = new PatrimonioController(new Patrimonio($pdo), new FluxoCaixa($pdo));

$router = new Router();

// Lançamentos
$router->get('/lancamentos', [$lancamentosController, 'listar']);
$router->get('/lancamentos/{id}', [$lancamentosController, 'buscar']);
$router->post('/lancamentos', [$lancamentosController, 'criar']);
$router->put('/lancamentos/{id}', [$lancamentosController, 'atualizar']);
$router->delete('/lancamentos/{id}', [$lancamentosController, 'apagar']);

// Categorias
$router->get('/categorias', [$categoriasController, 'listar']);
$router->get('/categorias/{id}', [$categoriasController, 'buscar']);
$router->post('/categorias', [$categoriasController, 'criar']);
$router->put('/categorias/{id}', [$categoriasController, 'atualizar']);
$router->delete('/categorias/{id}', [$categoriasController, 'apagar']);

// Formas de pagamento
$router->get('/formas-pagamento', [$formasPagamentoController, 'listar']);
$router->get('/formas-pagamento/{id}', [$formasPagamentoController, 'buscar']);
$router->post('/formas-pagamento', [$formasPagamentoController, 'criar']);
$router->put('/formas-pagamento/{id}', [$formasPagamentoController, 'atualizar']);
$router->delete('/formas-pagamento/{id}', [$formasPagamentoController, 'apagar']);

// Relatório mensal
$router->get('/relatorio-mensal', [$relatorioMensalController, 'resumo']);

// Orçamento mensal
$router->get('/orcamento-mensal', [$orcamentoController, 'listar']);
$router->post('/orcamento-mensal', [$orcamentoController, 'salvar']);
$router->post('/orcamento-mensal/copiar-mes-anterior', [$orcamentoController, 'copiarMesAnterior']);

// Lançamentos Recorrentes
$router->get('/recorrentes', [$recorrentesController, 'listar']);
$router->get('/recorrentes/por-categoria/{categoriaId}', [$recorrentesController, 'buscarPorCategoria']);
$router->get('/recorrentes/{id}', [$recorrentesController, 'buscar']);
$router->post('/recorrentes', [$recorrentesController, 'criar']);
$router->put('/recorrentes/{id}', [$recorrentesController, 'atualizar']);
$router->delete('/recorrentes/{id}', [$recorrentesController, 'apagar']);

// Fôlego Financeiro (projeção anual)
$router->get('/folego-anual', [$fluxoCaixaController, 'folegoAnual']);

// Fluxo de caixa
$router->get('/fluxo-caixa', [$fluxoCaixaController, 'resumo']);
$router->get('/configuracoes/saldo-inicial', [$fluxoCaixaController, 'obterSaldoInicial']);
$router->post('/configuracoes/saldo-inicial', [$fluxoCaixaController, 'salvarSaldoInicial']);

// Investimentos
$router->get('/ativos', [$ativosController, 'listar']);
$router->post('/ativos', [$ativosController, 'criar']);

$router->get('/movimentacoes-investimentos', [$movimentacoesController, 'listar']);
$router->post('/movimentacoes-investimentos', [$movimentacoesController, 'criar']);
$router->delete('/movimentacoes-investimentos/{id}', [$movimentacoesController, 'apagar']);
$router->get('/carteira-investimentos', [$movimentacoesController, 'carteira']);

$router->get('/rendimentos-investimentos', [$rendimentosController, 'listar']);
$router->post('/rendimentos-investimentos', [$rendimentosController, 'criar']);
$router->delete('/rendimentos-investimentos/{id}', [$rendimentosController, 'apagar']);

// Patrimônio
$router->get('/contas-patrimonio', [$patrimonioController, 'listarContas']);
$router->get('/contas-patrimonio/{id}', [$patrimonioController, 'buscar']);
$router->post('/contas-patrimonio', [$patrimonioController, 'criarConta']);
$router->put('/contas-patrimonio/{id}', [$patrimonioController, 'atualizar']);
$router->delete('/contas-patrimonio/{id}', [$patrimonioController, 'apagar']);
$router->get('/saldos-mensais', [$patrimonioController, 'saldosDoMes']);
$router->post('/saldos-mensais', [$patrimonioController, 'salvarSaldos']);
$router->get('/evolucao-patrimonial', [$patrimonioController, 'evolucao']);

// Descobre o caminho da requisição, removendo o prefixo /api
$caminhoCompleto = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$caminho = preg_replace('#^/api#', '', $caminhoCompleto);
$caminho = $caminho === '' ? '/' : $caminho;

$router->despachar($_SERVER['REQUEST_METHOD'], $caminho);
