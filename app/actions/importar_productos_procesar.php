<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/product_codes.php';
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

use PhpOffice\PhpSpreadsheet\IOFactory;

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
if ((int) ($_FILES['archivo']['size'] ?? 0) > 10 * 1024 * 1024) {
    header('Location: ' . page_url('productos', ['error' => 'El archivo no puede superar 10 MB']));
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
        $codigo = normalizar_codigo_producto($row[$codigoCol] ?? '');
        $descripcion = trim((string) ($row[$descripcionCol] ?? ''));
        if ($codigo === '' || $descripcion === '') {
            continue;
        }
        $stmt->execute([$codigo, $descripcion]);
        $procesados++;
    }
    $pdo->commit();

    @unlink($savedFile);
    header('Location: ' . page_url('productos', ['msg' => "Importacion completada: {$procesados} productos procesados"]));
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error al importar productos: ' . $exception->getMessage());
    @unlink($savedFile);
    header('Location: ' . page_url('productos', ['error' => 'No se pudo importar el archivo. Revise el formato e intente nuevamente.']));
}
exit;


