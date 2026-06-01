<?php

declare(strict_types=1);

require_once APP_PATH . '/repositories/ProductRepository.php';
require_once APP_INCLUDES_PATH . '/conteo_items.php';

function sincronizar_detalle_conteo(PDO $pdo, int $conteoId, array $upsert, array $remove): int
{
    $remove = array_values(array_unique(array_filter(array_map('intval', $remove), static fn(int $id): bool => $id > 0)));
    if ($remove) {
        $placeholders = implode(',', array_fill(0, count($remove), '?'));
        $pdo->prepare("DELETE FROM conteo_detalle WHERE conteo_id = ? AND producto_id IN ({$placeholders})")
            ->execute(array_merge([$conteoId], $remove));
    }

    $cantidades = normalizar_items_conteo($upsert);
    if ($cantidades) {
        $productos = (new ProductRepository($pdo))->findActiveByIds(array_keys($cantidades));
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

        if ($values) {
            $sql = 'INSERT INTO conteo_detalle (conteo_id, producto_id, codigo, descripcion, cantidad) VALUES '
                . implode(', ', $values)
                . ' ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad), codigo = VALUES(codigo), descripcion = VALUES(descripcion)';
            $pdo->prepare($sql)->execute($params);
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM conteo_detalle WHERE conteo_id = ?');
    $stmt->execute([$conteoId]);

    return (int) $stmt->fetchColumn();
}
