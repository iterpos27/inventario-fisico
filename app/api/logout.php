<?php
require_once __DIR__ . '/bootstrap.php';

$token = api_bearer_token();
if ($token !== '') {
    $stmt = $pdo->prepare('UPDATE api_tokens SET revocado = 1 WHERE token_hash = ?');
    $stmt->execute([hash('sha256', $token)]);
}

api_json(['ok' => true]);
