<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? null)) {
    header('Location: ' . page_url('reportes'));
    exit;
}

$autoload = ROOT_PATH . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Location: ' . page_url('reportes', ['error' => 'excel']));
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tomaId = (int) ($_POST['toma_id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . page_url('reportes'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, numero_toma, nombre_toma FROM tomas_fisicas WHERE id = ?');
    $stmt->execute([$tomaId]);
    $toma = $stmt->fetch();
    if (!$toma) {
        throw new RuntimeException('Toma no encontrada');
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
        throw new RuntimeException('Sin productos contados');
    }

    $dir = STORAGE_PATH . '/conteos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $relativePath = 'storage/conteos/consolidado_toma_' . $tomaId . '_' . date('Ymd_His') . '.xlsx';
    $fullPath = ROOT_PATH . '/' . $relativePath;

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

    header('Location: ' . action_url('descargar_consolidado', ['toma_id' => $tomaId]));
} catch (Throwable $exception) {
    error_log('Error al generar consolidado: ' . $exception->getMessage());
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'consolidado']));
}
exit;
