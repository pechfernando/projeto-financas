<?php

/**
 * Funções auxiliares para padronizar as respostas da API.
 * Toda resposta sai em JSON, com o header correto e o status HTTP certo.
 */

function jsonResponse(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $mensagem, int $statusCode = 400): void
{
    jsonResponse(['erro' => $mensagem], $statusCode);
}

/**
 * Lê o corpo da requisição (JSON enviado pelo frontend) e devolve como array.
 */
function corpoRequisicao(): array
{
    $conteudo = file_get_contents('php://input');
    $dados = json_decode($conteudo, true);
    return is_array($dados) ? $dados : [];
}
