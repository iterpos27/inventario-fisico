<?php

declare(strict_types=1);

function generar_excel_conteo(PDO $pdo, int $conteoId): string
{
    $autoload = ROOT_PATH . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new RuntimeException('Instale PhpSpreadsheet con Composer');
    }
    require_once $autoload;

    $stmt = $pdo->prepare(
        'SELECT d.codigo, d.descripcion, d.cantidad, u.nombre AS usuario
         FROM conteo_detalle d
         INNER JOIN conteos c ON c.id = d.conteo_id
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE d.conteo_id = ?
         ORDER BY d.id'
    );
    $stmt->execute([$conteoId]);
    $detalles = $stmt->fetchAll();
    if (!$detalles) {
        throw new RuntimeException('Sin detalle para exportar');
    }

    $dir = UPLOADS_PATH . '/conteos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $relativePath = 'uploads/conteos/conteo_' . $conteoId . '_' . date('Ymd_His') . '.xlsx';
    $fullPath = PUBLIC_PATH . '/' . $relativePath;

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['Codigo', 'Descripcion', 'Cantidad', 'Usuario'], null, 'A1');

    $row = 2;
    foreach ($detalles as $detalle) {
        $sheet->setCellValue("A{$row}", $detalle['codigo']);
        $sheet->setCellValue("B{$row}", $detalle['descripcion']);
        $sheet->setCellValue("C{$row}", (float) $detalle['cantidad']);
        $sheet->setCellValue("D{$row}", $detalle['usuario']);
        $row++;
    }

    foreach (range('A', 'D') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($fullPath);

    return $relativePath;
}
