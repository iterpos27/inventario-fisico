<?php
require_once __DIR__ . '/bootstrap.php';

$user = api_require_user($pdo);
$conteoId = (int) ($_GET['conteo_id'] ?? 0);
if ($conteoId <= 0) {
    api_json(['ok' => false, 'message' => 'Conteo invalido'], 422);
}

$stmt = $pdo->prepare('SELECT id FROM conteos WHERE id = ? AND usuario_id = ? LIMIT 1');
$stmt->execute([$conteoId, (int) $user['id']]);
if (!$stmt->fetch()) {
    api_json(['ok' => false, 'message' => 'Conteo no disponible'], 404);
}

$stmt = $pdo->prepare('SELECT producto_id, codigo, descripcion, cantidad FROM conteo_detalle WHERE conteo_id = ? ORDER BY id');
$stmt->execute([$conteoId]);

api_json(['ok' => true, 'items' => $stmt->fetchAll()]);
