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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

@set_time_limit(300);
@ini_set('memory_limit', '512M');

function flush_product_import_batch(PDO $pdo, array &$batch): int
{
    if (!$batch) {
        return 0;
    }

    $values = [];
    $params = [];
    foreach ($batch as $product) {
        $values[] = '(?, ?, 1)';
        $params[] = $product['codigo'];
        $params[] = $product['descripcion'];
    }

    $sql = 'INSERT INTO productos (codigo, descripcion, estado) VALUES '
        . implode(', ', $values)
        . ' ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), estado = 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $processed = count($batch);
    $batch = [];

    return $processed;
}

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
    $reader = IOFactory::createReaderForFile($savedFile);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($savedFile);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    if ($highestRow < 2) {
        throw new RuntimeException('Archivo sin datos');
    }

    $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    $headerRow = [];
    for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
        $headerRow[$columnIndex] = $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . '1')->getValue();
    }

    $headers = [];
    foreach ($headerRow as $columnIndex => $header) {
        $header = strtolower(trim((string) $header));
        $headers[$columnIndex] = str_replace(["\xEF\xBB\xBF", ' ', '_', '-'], ['', '', '', ''], $header);
    }

    $codigoCol = array_search('codigo', $headers, true);
    $descripcionCol = array_search('descripcion', $headers, true);
    if ($codigoCol === false || $descripcionCol === false) {
        throw new RuntimeException('Columnas requeridas no encontradas');
    }

    $procesados = 0;
    $batch = [];
    $batchSize = 500;
    $pdo->beginTransaction();
    for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
        $codigo = normalizar_codigo_producto($sheet->getCell(Coordinate::stringFromColumnIndex((int) $codigoCol) . $rowIndex)->getValue());
        $descripcion = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex((int) $descripcionCol) . $rowIndex)->getValue());
        if ($codigo === '' || $descripcion === '') {
            continue;
        }

        $batch[] = [
            'codigo' => $codigo,
            'descripcion' => $descripcion,
        ];

        if (count($batch) >= $batchSize) {
            $procesados += flush_product_import_batch($pdo, $batch);
        }
    }
    $procesados += flush_product_import_batch($pdo, $batch);
    $pdo->commit();

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);
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


