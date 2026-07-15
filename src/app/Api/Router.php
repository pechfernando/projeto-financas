<?php

/**
 * Roteador simples: casa o método HTTP + caminho da URL com uma função.
 * Suporta parâmetros dinâmicos tipo /lancamentos/{id}.
 *
 * Exemplo de uso:
 *   $router->get('/lancamentos', [$controller, 'listar']);
 *   $router->get('/lancamentos/{id}', [$controller, 'buscar']);
 */
class Router
{
    private array $rotas = [];

    public function get(string $caminho, callable $acao): void
    {
        $this->rotas['GET'][$caminho] = $acao;
    }

    public function post(string $caminho, callable $acao): void
    {
        $this->rotas['POST'][$caminho] = $acao;
    }

    public function put(string $caminho, callable $acao): void
    {
        $this->rotas['PUT'][$caminho] = $acao;
    }

    public function delete(string $caminho, callable $acao): void
    {
        $this->rotas['DELETE'][$caminho] = $acao;
    }

    public function despachar(string $metodo, string $caminhoRequisicao): void
    {
        $rotasDoMetodo = $this->rotas[$metodo] ?? [];

        foreach ($rotasDoMetodo as $padrao => $acao) {
            $regex = $this->converterParaRegex($padrao);
            if (preg_match($regex, $caminhoRequisicao, $matches)) {
                // Remove os índices numéricos, mantém só os nomeados ({id} => valor)
                $parametros = array_filter(
                    $matches,
                    fn($chave) => !is_numeric($chave),
                    ARRAY_FILTER_USE_KEY
                );
                call_user_func($acao, $parametros);
                return;
            }
        }

        jsonError('Rota não encontrada: ' . $metodo . ' ' . $caminhoRequisicao, 404);
    }

    private function converterParaRegex(string $padrao): string
    {
        // Transforma "/lancamentos/{id}" em uma regex "/^\/lancamentos\/(?<id>[^\/]+)$/"
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?<$1>[^/]+)', $padrao);
        return '#^' . $regex . '$#';
    }
}
