<?php

/**
 * Conexão com o banco de dados usando PDO.
 * As credenciais vêm de variáveis de ambiente (definidas no docker-compose.yml
 * localmente, e no painel da Hostinger quando estiver em produção).
 */
function getConexaoBanco(): PDO
{
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbName = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Em produção, não queremos mostrar detalhes do erro para o usuário final
        die('Erro ao conectar com o banco de dados: ' . $e->getMessage());
    }
}
