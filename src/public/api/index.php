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

require_once __DIR__ . '/../../app/Api/Controllers/LancamentosController.php';
require_once __DIR__ . '/../../app/Api/Controllers/CategoriasController.php';
require_once __DIR__ . '/../../app/Api/Controllers/FormasPagamentoController.php';
require_once __DIR__ . '/../../app/Api/Controllers/RelatorioMensalController.php';

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

$router = new Router();

// Lançamentos
$router->get('/lancamentos', [$lancamentosController, 'listar']);
$router->get('/lancamentos/{id}', [$lancamentosController, 'buscar']);
$router->post('/lancamentos', [$lancamentosController, 'criar']);
$router->put('/lancamentos/{id}', [$lancamentosController, 'atualizar']);
$router->delete('/lancamentos/{id}', [$lancamentosController, 'apagar']);

// Categorias
$router->get('/categorias', [$categoriasController, 'listar']);
$router->post('/categorias', [$categoriasController, 'criar']);

// Formas de pagamento
$router->get('/formas-pagamento', [$formasPagamentoController, 'listar']);
$router->post('/formas-pagamento', [$formasPagamentoController, 'criar']);

// Relatório mensal
$router->get('/relatorio-mensal', [$relatorioMensalController, 'resumo']);

// Descobre o caminho da requisição, removendo o prefixo /api
$caminhoCompleto = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$caminho = preg_replace('#^/api#', '', $caminhoCompleto);
$caminho = $caminho === '' ? '/' : $caminho;

$router->despachar($_SERVER['REQUEST_METHOD'], $caminho);
