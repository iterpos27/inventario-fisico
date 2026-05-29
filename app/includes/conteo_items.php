<?php

declare(strict_types=1);

require_once APP_PATH . '/repositories/ProductRepository.php';

const CONTEO_MAX_CANTIDAD = 999999.99;
const CONTEO_DECIMALES = 2;

/**
 * @return array<int, float>
 */
function normalizar_items_conteo(array $items): array
{
    $cantidades = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $productoId = (int) ($item['producto_id'] ?? 0);
        $cantidad = filter_var($item['cantidad'] ?? 0, FILTER_VALIDATE_FLOAT);
        if ($productoId <= 0 || $cantidad === false || $cantidad < 0 || $cantidad > CONTEO_MAX_CANTIDAD) {
            continue;
        }

        $cantidades[$productoId] = round((float) $cantidad, CONTEO_DECIMALES);
    }

    return $cantidades;
}

function reemplazar_detalle_conteo(PDO $pdo, int $conteoId, array $items): int
{
    $cantidades = normalizar_items_conteo($items);

    $pdo->prepare('DELETE FROM conteo_detalle WHERE conteo_id = ?')->execute([$conteoId]);
    if (!$cantidades) {
        return 0;
    }

    $productos = (new ProductRepository($pdo))->findActiveByIds(array_keys($cantidades));
    if (!$productos) {
        return 0;
    }

    $productosPorId = [];
    foreach ($productos as $producto) {
        $productosPorId[(int) $producto['id']] = $producto;
    }

    $values = [];
    $params = [];
    foreach ($cantidades as $productoId => $cantidad) {
        if (!isset($productosPorId[$productoId])) {
            continue;
        }
        $producto = $productosPorId[$productoId];
        $values[] = '(?, ?, ?, ?, ?)';
        $params[] = $conteoId;
        $params[] = $productoId;
        $params[] = $producto['codigo'];
        $params[] = $producto['descripcion'];
        $params[] = $cantidad;
    }

    if (!$values) {
        return 0;
    }

    $sql = 'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad) VALUES ' . implode(', ', $values);
    $pdo->prepare($sql)->execute($params);

    return count($values);
}


