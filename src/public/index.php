<?php

require_once __DIR__ . '/../app/Config/database.php';

$pdo = getConexaoBanco();

// Testa a conexão listando as tabelas existentes no banco
$stmt = $pdo->query('SHOW TABLES');
$tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Aplicação Financeira - Teste de Conexão</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 60px auto; color: #222; }
        h1 { color: #ea6524; }
        .ok { color: green; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>
    <h1>plano.</h1>
    <p class="ok">✅ Conexão com o banco de dados funcionando!</p>

    <?php if (count($tabelas) > 0): ?>
        <p>Tabelas encontradas no banco:</p>
        <ul>
            <?php foreach ($tabelas as $tabela): ?>
                <li><?= htmlspecialchars($tabela) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Nenhuma tabela encontrada ainda. Se você acabou de subir os containers,
        o schema.sql deveria ter rodado automaticamente. Veja o README para mais detalhes.</p>
    <?php endif; ?>
</body>
</html>
