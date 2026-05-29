<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Dependencias no instaladas']);
    exit;
}
require_once $autoload;
require_once APP_INCLUDES_PATH . '/import_jobs.php';

header('Content-Type: application/json; charset=utf-8');

$jobId = (int) ($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
if ($jobId <= 0 || !verify_csrf($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'message' => 'Importacion invalida']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM import_jobs WHERE id = ? AND usuario_id = ? LIMIT 1');
$stmt->execute([$jobId, (int) $_SESSION['usuario_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['ok' => false, 'message' => 'Importacion no encontrada']);
    exit;
}

try {
    $job = product_import_process_job($pdo, $jobId);
    echo json_encode(['ok' => true, 'job' => $job], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo procesar la importacion']);
}
