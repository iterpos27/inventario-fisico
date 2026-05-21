<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . BASE_URL . '/importar_productos.php?error=Solicitud invalida');
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Location: ' . BASE_URL . '/importar_productos.php?error=Instale dependencias con composer require phpoffice/phpspreadsheet');
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\IOFactory;

$missingPhpExtensions = [];
foreach (['zip', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        $missingPhpExtensions[] = $extension;
    }
}
if ($missingPhpExtensions) {
    header('Location: ' . BASE_URL . '/importar_productos.php?error=' . urlencode(
        'Apache no tiene activas las extensiones PHP requeridas: ' . implode(', ', $missingPhpExtensions) . '. Active las extensiones en XAMPP y reinicie Apache.'
    ));
    exit;
}

if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . BASE_URL . '/importar_productos.php?error=Seleccione un archivo valido');
    exit;
}

$extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
    header('Location: ' . BASE_URL . '/importar_productos.php?error=Formato no permitido');
    exit;
}

$uploadDir = UPLOADS_PATH . '/productos';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}
$savedFile = $uploadDir . '/productos_' . date('Ymd_His') . '.' . $extension;
move_uploaded_file($_FILES['archivo']['tmp_name'], $savedFile);

try {
    $spreadsheet = IOFactory::load($savedFile);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
    if (count($rows) < 2) {
        throw new RuntimeException('Archivo sin datos');
    }

    $headerRow = reset($rows) ?: [];
    $headers = array_map(static function ($header): string {
        $header = strtolower(trim((string) $header));
        $header = str_replace(["\xEF\xBB\xBF", ' ', '_', '-'], ['', '', '', ''], $header);

        return $header;
    }, $headerRow);

    $codigoCol = array_search('codigo', $headers, true);
    $descripcionCol = array_search('descripcion', $headers, true);
    if ($codigoCol === false || $descripcionCol === false) {
        throw new RuntimeException('Columnas requeridas no encontradas');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO productos (codigo, descripcion, estado)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), estado = 1'
    );

    $procesados = 0;
    $pdo->beginTransaction();
    foreach (array_slice($rows, 1) as $row) {
        $codigo = trim((string) ($row[$codigoCol] ?? ''));
        $descripcion = trim((string) ($row[$descripcionCol] ?? ''));
        if ($codigo === '' || $descripcion === '') {
            continue;
        }
        $stmt->execute([$codigo, $descripcion]);
        $procesados++;
    }
    $pdo->commit();

    header('Location: ' . BASE_URL . '/importar_productos.php?msg=' . urlencode("Importacion completada: {$procesados} productos procesados"));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ' . BASE_URL . '/importar_productos.php?error=' . urlencode('No se pudo importar: ' . $exception->getMessage()));
}
exit;
