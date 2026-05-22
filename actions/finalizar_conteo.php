<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

if (current_user_role() === 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'El administrador solo crea conteos']);
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Instale PhpSpreadsheet con Composer']);
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || !verify_csrf($payload['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$items = $payload['items'] ?? [];
$conteoId = (int) ($payload['conteo_id'] ?? 0);

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Ingrese productos']);
    exit;
}
if ($conteoId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Seleccione un conteo creado por el administrador']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT c.id, c.toma_id
         FROM conteos c
         INNER JOIN tomas_fisicas t ON t.id = c.toma_id
         WHERE c.id = ? AND c.usuario_id = ? AND c.estado = 'borrador' AND t.estado = 'abierta'"
    );
    $stmt->execute([$conteoId, (int) $_SESSION['usuario_id']]);
    $conteoActivo = $stmt->fetch();
    if (!$conteoActivo) {
        throw new RuntimeException('Conteo no disponible');
    }
    $tomaId = (int) $conteoActivo['toma_id'];
    $pdo->prepare('DELETE FROM conteo_detalle WHERE conteo_id = ?')->execute([$conteoId]);

    $stmtProducto = $pdo->prepare('SELECT codigo, descripcion FROM productos WHERE id = ? AND estado = 1');
    $stmtDetalle = $pdo->prepare(
        'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($items as $item) {
        $productoId = (int) ($item['producto_id'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);
        if ($productoId <= 0 || $cantidad < 0) {
            continue;
        }
        $stmtProducto->execute([$productoId]);
        $producto = $stmtProducto->fetch();
        if ($producto) {
            $stmtDetalle->execute([$conteoId, $productoId, $producto['codigo'], $producto['descripcion'], $cantidad]);
        }
    }

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
        throw new RuntimeException('Sin detalle');
    }

    $dir = UPLOADS_PATH . '/conteos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $relativePath = 'uploads/conteos/conteo_' . $conteoId . '_' . date('Ymd_His') . '.xlsx';
    $fullPath = dirname(__DIR__) . '/' . $relativePath;

    $spreadsheet = new Spreadsheet();
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
    (new Xlsx($spreadsheet))->save($fullPath);

    $stmt = $pdo->prepare("UPDATE conteos SET estado = 'finalizado', fecha_finalizacion = NOW(), archivo_excel = ? WHERE id = ?");
    $stmt->execute([$relativePath, $conteoId]);

    $stmt = $pdo->prepare("UPDATE toma_usuarios SET estado = 'finalizado' WHERE toma_id = ? AND usuario_id = ?");
    $stmt->execute([$tomaId, (int) $_SESSION['usuario_id']]);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS asignados,
                SUM(CASE WHEN estado = 'finalizado' THEN 1 ELSE 0 END) AS finalizados
         FROM toma_usuarios
         WHERE toma_id = ?"
    );
    $stmt->execute([$tomaId]);
    $avance = $stmt->fetch();
    if ($avance && (int) $avance['asignados'] > 0 && (int) $avance['asignados'] === (int) $avance['finalizados']) {
        $stmt = $pdo->prepare("UPDATE tomas_fisicas SET estado = 'finalizada', fecha_finalizacion = NOW() WHERE id = ?");
        $stmt->execute([$tomaId]);
    }

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'conteo_id' => $conteoId,
        'message' => 'Conteo finalizado',
        'download_url' => BASE_URL . '/actions/descargar_excel.php?id=' . $conteoId,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No se pudo finalizar el conteo']);
}
