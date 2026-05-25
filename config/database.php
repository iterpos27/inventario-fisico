<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

$dbHost = env_value('DB_HOST', 'localhost');
$dbName = env_value('DB_NAME', 'centro_ruliman_inventario');
$dbUser = env_value('DB_USER', 'inventario_app');
$dbPass = env_value('DB_PASS', '');
$dbCharset = env_value('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    require_once __DIR__ . '/schema.php';
    ensure_schema($pdo);
} catch (PDOException $exception) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos. Revise config/database.php.');
}



