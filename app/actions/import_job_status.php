<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$jobId = (int) ($_GET['job_id'] ?? 0);
if ($jobId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Importacion invalida']);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM import_jobs WHERE id = ? AND usuario_id = ? LIMIT 1');
$stmt->execute([$jobId, (int) $_SESSION['usuario_id']]);
$job = $stmt->fetch();
if (!$job) {
    echo json_encode(['ok' => false, 'message' => 'Importacion no encontrada']);
    exit;
}

echo json_encode(['ok' => true, 'job' => $job], JSON_UNESCAPED_UNICODE);
