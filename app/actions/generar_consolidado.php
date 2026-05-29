<?php
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once APP_INCLUDES_PATH . '/auth.php';
require_once APP_INCLUDES_PATH . '/observability.php';
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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tomaId = (int) ($_POST['toma_id'] ?? 0);
if ($tomaId <= 0) {
    header('Location: ' . page_url('reportes'));
    exit;
}

try {
    $startedAt = microtime(true);
    $stmt = $pdo->prepare('SELECT id, numero_toma, nombre_toma FROM tomas_fisicas WHERE id = ?');
    $stmt->execute([$tomaId]);
    $toma = $stmt->fetch();
    if (!$toma) {
        throw new RuntimeException('Toma no encontrada');
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT u.id, u.nombre
         FROM conteos c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.toma_id = ? AND c.estado = 'finalizado'
         ORDER BY u.nombre"
    );
    $stmt->execute([$tomaId]);
    $usuarios = $stmt->fetchAll();
    if (!$usuarios) {
        throw new RuntimeException('Sin usuarios finalizados');
    }

    $stmt = $pdo->prepare(
        "SELECT d.producto_id, d.codigo, d.descripcion, c.usuario_id, SUM(d.cantidad) AS cantidad
         FROM conteos c
         INNER JOIN conteo_detalle d ON d.conteo_id = c.id
         WHERE c.toma_id = ? AND c.estado = 'finalizado'
         GROUP BY d.producto_id, d.codigo, d.descripcion, c.usuario_id
         ORDER BY d.codigo, d.descripcion"
    );
    $stmt->execute([$tomaId]);
    $detalles = $stmt->fetchAll();
    if (!$detalles) {
        throw new RuntimeException('Sin productos contados');
    }

    $productos = [];
    foreach ($detalles as $detalle) {
        $productoKey = (string) ($detalle['producto_id'] ?: $detalle['codigo']);
        if (!isset($productos[$productoKey])) {
            $productos[$productoKey] = [
                'codigo' => $detalle['codigo'],
                'descripcion' => $detalle['descripcion'],
                'usuarios' => [],
                'total' => 0.0,
            ];
        }

        $cantidad = (float) $detalle['cantidad'];
        $usuarioId = (int) $detalle['usuario_id'];
        $productos[$productoKey]['usuarios'][$usuarioId] = $cantidad;
        $productos[$productoKey]['total'] += $cantidad;
    }

    $stmt = $pdo->prepare('SELECT archivo_excel FROM tomas_fisicas WHERE id = ? AND archivo_excel IS NOT NULL AND archivo_excel <> "" LIMIT 1');
    $stmt->execute([$tomaId]);
    $cachedPath = $stmt->fetchColumn();
    if ($cachedPath && is_file(ROOT_PATH . '/' . $cachedPath)) {
        audit_log($pdo, 'download_cached_consolidado', 'toma', $tomaId);
        header('Location: ' . action_url('descargar_consolidado', ['toma_id' => $tomaId]));
        exit;
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
    $headers = ['Codigo', 'Descripcion'];
    foreach ($usuarios as $usuario) {
        $headers[] = $usuario['nombre'];
    }
    $headers[] = 'Cantidad total';
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('1:1')->getFont()->setBold(true);
    $sheet->freezePane('A2');

    $row = 2;
    foreach ($productos as $producto) {
        $values = [$producto['codigo'], $producto['descripcion']];
        foreach ($usuarios as $usuario) {
            $usuarioId = (int) $usuario['id'];
            $values[] = array_key_exists($usuarioId, $producto['usuarios'])
                ? $producto['usuarios'][$usuarioId]
                : '';
        }
        $values[] = $producto['total'];
        $sheet->fromArray($values, null, "A{$row}");
        $row++;
    }

    for ($columnIndex = 1; $columnIndex <= count($headers); $columnIndex++) {
        $column = Coordinate::stringFromColumnIndex($columnIndex);
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    (new Xlsx($spreadsheet))->save($fullPath);

    $stmt = $pdo->prepare('UPDATE tomas_fisicas SET archivo_excel = ? WHERE id = ?');
    $stmt->execute([$relativePath, $tomaId]);
    audit_log($pdo, 'generate_consolidado', 'toma', $tomaId, ['productos' => count($productos)]);
    monitor_duration($pdo, 'generar_consolidado', $startedAt, 1200, ['toma_id' => $tomaId, 'productos' => count($productos)]);

    header('Location: ' . action_url('descargar_consolidado', ['toma_id' => $tomaId]));
} catch (Throwable $exception) {
    app_log($pdo, 'error', 'generar_consolidado_failed', 'Error al generar consolidado', ['toma_id' => $tomaId, 'error' => $exception->getMessage()]);
    header('Location: ' . page_url('toma_detalle', ['id' => $tomaId, 'error' => 'consolidado']));
}
exit;
