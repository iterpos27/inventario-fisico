<?php

declare(strict_types=1);

function reemplazar_detalle_conteo(PDO $pdo, int $conteoId, array $items): int
{
    $cantidades = [];
    foreach ($items as $item) {
        $productoId = (int) ($item['producto_id'] ?? 0);
        $cantidad = (float) ($item['cantidad'] ?? 0);
        if ($productoId <= 0 || $cantidad < 0) {
            continue;
        }
        $cantidades[$productoId] = $cantidad;
    }

    $pdo->prepare('DELETE FROM conteo_detalle WHERE conteo_id = ?')->execute([$conteoId]);
    if (!$cantidades) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($cantidades), '?'));
    $stmt = $pdo->prepare("SELECT id, codigo, descripcion FROM productos WHERE estado = 1 AND id IN ({$placeholders})");
    $stmt->execute(array_keys($cantidades));
    $productos = $stmt->fetchAll();
    if (!$productos) {
        return 0;
    }

    $values = [];
    $params = [];
    foreach ($productos as $producto) {
        $productoId = (int) $producto['id'];
        $values[] = '(?, ?, ?, ?, ?)';
        $params[] = $conteoId;
        $params[] = $productoId;
        $params[] = $producto['codigo'];
        $params[] = $producto['descripcion'];
        $params[] = $cantidades[$productoId];
    }

    $sql = 'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad) VALUES ' . implode(', ', $values);
    $pdo->prepare($sql)->execute($params);

    return count($productos);
}

