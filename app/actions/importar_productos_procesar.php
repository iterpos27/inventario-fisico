<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('productos', ['error' => 'Solicitud invalida']));
    exit;
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Location: ' . page_url('productos', ['error' => 'Instale dependencias con composer require phpoffice/phpspreadsheet']));
    exit;
}
require_once $autoload;
require_once APP_INCLUDES_PATH . '/import_jobs.php';

@set_time_limit(120);
@ini_set('memory_limit', '512M');

$missingPhpExtensions = [];
foreach (['zip', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        $missingPhpExtensions[] = $extension;
    }
}
if ($missingPhpExtensions) {
    header('Location: ' . page_url('productos', [
        'error' => 'Apache no tiene activas las extensiones PHP requeridas: ' . implode(', ', $missingPhpExtensions) . '. Active las extensiones en XAMPP y reinicie Apache.',
    ]));
    exit;
}

if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . page_url('productos', ['error' => 'Seleccione un archivo valido']));
    exit;
}
if ((int) ($_FILES['archivo']['size'] ?? 0) > 30 * 1024 * 1024) {
    header('Location: ' . page_url('productos', ['error' => 'El archivo no puede superar 30 MB']));
    exit;
}

$extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    header('Location: ' . page_url('productos', ['error' => 'Formato no permitido']));
    exit;
}

$uploadDir = STORAGE_PATH . '/imports';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}
$savedFile = $uploadDir . DIRECTORY_SEPARATOR . 'productos_' . bin2hex(random_bytes(8)) . '_' . date('Ymd_His') . '.' . $extension;
if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $savedFile)) {
    header('Location: ' . page_url('productos', ['error' => 'No se pudo guardar el archivo temporal']));
    exit;
}

try {
    $jobId = product_import_create_job(
        $pdo,
        $savedFile,
        (string) ($_FILES['archivo']['name'] ?? basename($savedFile)),
        $extension,
        (int) $_SESSION['usuario_id']
    );
    audit_log($pdo, 'import_created', 'productos', $jobId, ['archivo' => $_FILES['archivo']['name'] ?? '']);
    header('Location: ' . page_url('productos', ['import_job' => $jobId]));
} catch (Throwable $exception) {
    @unlink($savedFile);
    app_log($pdo, 'error', 'product_import_create_failed', 'No se pudo crear importacion', ['error' => $exception->getMessage()]);
    header('Location: ' . page_url('productos', ['error' => 'No se pudo preparar la importacion. Revise el formato e intente nuevamente.']));
}
exit;
