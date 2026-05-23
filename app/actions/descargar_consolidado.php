<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('Instale PhpSpreadsheet con Composer');
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tomaId = (int) ($_GET['toma_id'] ?? 0);
if ($tomaId <= 0) {
    http_response_code(404);
    exit('Toma no encontrada');
}

$stmt = $pdo->prepare('SELECT id, numero_toma, nombre_toma FROM tomas_fisicas WHERE id = ?');
$stmt->execute([$tomaId]);
$toma = $stmt->fetch();
if (!$toma) {
    http_response_code(404);
    exit('Toma no encontrada');
}

$stmt = $pdo->prepare(
    "SELECT d.codigo, d.descripcion, SUM(d.cantidad) AS cantidad, GROUP_CONCAT(DISTINCT u.nombre ORDER BY u.nombre SEPARATOR ', ') AS usuarios
     FROM conteos c
     INNER JOIN conteo_detalle d ON d.conteo_id = c.id
     INNER JOIN usuarios u ON u.id = c.usuario_id
     WHERE c.toma_id = ? AND c.estado = 'finalizado'
     GROUP BY d.producto_id, d.codigo, d.descripcion
     ORDER BY d.descripcion"
);
$stmt->execute([$tomaId]);
$detalles = $stmt->fetchAll();
if (!$detalles) {
    http_response_code(404);
    exit('No hay productos contados para consolidar');
}

$dir = UPLOADS_PATH . '/conteos';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$relativePath = 'uploads/conteos/consolidado_toma_' . $tomaId . '_' . date('Ymd_His') . '.xlsx';
$fullPath = PUBLIC_PATH . '/' . $relativePath;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Consolidado');
$sheet->fromArray(['Codigo', 'Descripcion', 'Cantidad total', 'Usuarios'], null, 'A1');

$row = 2;
foreach ($detalles as $detalle) {
    $sheet->setCellValue("A{$row}", $detalle['codigo']);
    $sheet->setCellValue("B{$row}", $detalle['descripcion']);
    $sheet->setCellValue("C{$row}", (float) $detalle['cantidad']);
    $sheet->setCellValue("D{$row}", $detalle['usuarios']);
    $row++;
}
foreach (range('A', 'D') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

(new Xlsx($spreadsheet))->save($fullPath);

$stmt = $pdo->prepare('UPDATE tomas_fisicas SET archivo_excel = ? WHERE id = ?');
$stmt->execute([$relativePath, $tomaId]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit;

