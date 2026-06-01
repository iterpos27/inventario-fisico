<?php

declare(strict_types=1);

require_once APP_INCLUDES_PATH . '/product_codes.php';
require_once APP_INCLUDES_PATH . '/observability.php';
require_once APP_INCLUDES_PATH . '/search_cache.php';

final class ProductImportReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    public function __construct(private int $startRow, private int $endRow)
    {
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        return $row === 1 || ($row >= $this->startRow && $row <= $this->endRow);
    }
}

function product_import_flush_batch(PDO $pdo, array &$batch): array
{
    if (!$batch) {
        return ['procesados' => 0, 'insertados' => 0, 'actualizados' => 0];
    }

    $codes = array_column($batch, 'codigo');
    $existing = [];
    if ($codes) {
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $pdo->prepare("SELECT codigo FROM productos WHERE codigo IN ({$placeholders})");
        $stmt->execute($codes);
        $existing = array_flip(array_column($stmt->fetchAll(), 'codigo'));
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

    $pdo->prepare($sql)->execute($params);
    $processed = count($batch);
    $updated = 0;
    foreach ($batch as $product) {
        if (isset($existing[$product['codigo']])) {
            $updated++;
        }
    }
    $batch = [];

    return [
        'procesados' => $processed,
        'insertados' => $processed - $updated,
        'actualizados' => $updated,
    ];
}

function product_import_create_job(PDO $pdo, string $filePath, string $originalName, string $extension, int $userId): int
{
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    if ($highestRow < 2) {
        throw new RuntimeException('Archivo sin datos');
    }

    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    $headers = [];
    for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
        $header = strtolower(trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex) . '1')->getValue()));
        $headers[$columnIndex] = str_replace(["\xEF\xBB\xBF", ' ', '_', '-'], ['', '', '', ''], $header);
    }

    $codigoCol = array_search('codigo', $headers, true);
    $descripcionCol = array_search('descripcion', $headers, true);
    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    if ($codigoCol === false || $descripcionCol === false) {
        throw new RuntimeException('Columnas requeridas no encontradas');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO import_jobs
            (usuario_id, archivo, nombre_original, extension, codigo_col, descripcion_col, total_rows, estado, actualizado_en)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
    );
    $stmt->execute([$userId, $filePath, $originalName, $extension, (int) $codigoCol, (int) $descripcionCol, $highestRow]);

    return (int) $pdo->lastInsertId();
}

function product_import_process_job(PDO $pdo, int $jobId, int $chunkSize = 800): array
{
    $stmt = $pdo->prepare('SELECT * FROM import_jobs WHERE id = ? FOR UPDATE');
    $pdo->beginTransaction();
    try {
        $stmt->execute([$jobId]);
        $job = $stmt->fetch();
        if (!$job) {
            throw new RuntimeException('Importacion no encontrada');
        }
        if (in_array($job['estado'], ['procesando', 'finalizado', 'fallido'], true)) {
            $pdo->commit();
            return $job;
        }

        $currentRow = max(2, (int) $job['current_row']);
        $totalRows = (int) $job['total_rows'];
        $endRow = min($totalRows, $currentRow + $chunkSize - 1);
        $pdo->prepare("UPDATE import_jobs SET estado = 'procesando', actualizado_en = NOW() WHERE id = ?")->execute([$jobId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $startedAt = microtime(true);
    $batch = [];
    $procesados = 0;
    $insertados = 0;
    $actualizados = 0;
    $omitidos = 0;
    $errores = 0;

    try {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile((string) $job['archivo']);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ProductImportReadFilter($currentRow, $endRow));
        $spreadsheet = $reader->load((string) $job['archivo']);
        $sheet = $spreadsheet->getActiveSheet();

        for ($rowIndex = $currentRow; $rowIndex <= $endRow; $rowIndex++) {
            $codigo = normalizar_codigo_producto($sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((int) $job['codigo_col']) . $rowIndex)->getValue());
            $descripcion = trim((string) $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((int) $job['descripcion_col']) . $rowIndex)->getValue());
            if ($codigo === '' || $descripcion === '') {
                $omitidos++;
                continue;
            }

            $batch[] = ['codigo' => $codigo, 'descripcion' => $descripcion];
            if (count($batch) >= 500) {
                $result = product_import_flush_batch($pdo, $batch);
                $procesados += $result['procesados'];
                $insertados += $result['insertados'];
                $actualizados += $result['actualizados'];
            }
        }
        $result = product_import_flush_batch($pdo, $batch);
        $procesados += $result['procesados'];
        $insertados += $result['insertados'];
        $actualizados += $result['actualizados'];

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $nextRow = $endRow + 1;
        $finished = $nextRow > $totalRows;
        $stmt = $pdo->prepare(
            "UPDATE import_jobs
             SET current_row = ?, procesados = procesados + ?, insertados = insertados + ?,
                 actualizados = actualizados + ?, omitidos = omitidos + ?, errores = errores + ?,
                 estado = ?, actualizado_en = NOW(), finalizado_en = IF(? = 'finalizado', NOW(), finalizado_en)
             WHERE id = ?"
        );
        $status = $finished ? 'finalizado' : 'pendiente';
        $stmt->execute([$nextRow, $procesados, $insertados, $actualizados, $omitidos, $errores, $status, $status, $jobId]);
        if ($finished) {
            @unlink((string) $job['archivo']);
            search_cache_invalidate();
            audit_log($pdo, 'import_completed', 'productos', $jobId, ['procesados' => $procesados]);
        }
        monitor_duration($pdo, 'product_import_chunk', $startedAt, 1500, ['job_id' => $jobId, 'rows' => $endRow - $currentRow + 1]);
    } catch (Throwable $exception) {
        $pdo->prepare("UPDATE import_jobs SET estado = 'fallido', error_message = ?, actualizado_en = NOW(), finalizado_en = NOW() WHERE id = ?")
            ->execute([mb_substr($exception->getMessage(), 0, 1000), $jobId]);
        app_log($pdo, 'error', 'product_import_failed', 'Fallo importacion de productos', ['job_id' => $jobId, 'error' => $exception->getMessage()]);
        throw $exception;
    }

    $stmt = $pdo->prepare('SELECT * FROM import_jobs WHERE id = ?');
    $stmt->execute([$jobId]);
    return $stmt->fetch() ?: [];
}
