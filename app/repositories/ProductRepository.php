<?php

declare(strict_types=1);

final class ProductRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function paginateActive(string $search, int $page, int $perPage, string $sort = 'descripcion', string $direction = 'asc'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $where = 'estado = 1';
        $params = [];
        $searchIsCode = $search !== '' && preg_match('/^\d+$/', $search) === 1;
        $sortColumns = [
            'codigo' => 'codigo',
            'descripcion' => 'descripcion',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'descripcion';
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if ($search !== '') {
            $where .= $searchIsCode
                ? " AND codigo LIKE :q_codigo ESCAPE '!'"
                : " AND descripcion LIKE :q_descripcion ESCAPE '!'";
            $params[$searchIsCode ? ':q_codigo' : ':q_descripcion'] = $this->likePattern($search, !$searchIsCode);
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM productos WHERE {$where}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $this->pdo->prepare(
            "SELECT id, codigo, descripcion
             FROM productos
             WHERE {$where}
             ORDER BY
                {$sortColumn} {$sortDirection},
                id ASC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchActive(string $search, int $limit = 20): array
    {
        $search = trim($search);
        $limit = max(1, min(50, $limit));
        if (mb_strlen($search) < 3) {
            return [];
        }

        $searchIsCode = preg_match('/^\d+$/', $search) === 1;
        if ($searchIsCode) {
            $stmt = $this->pdo->prepare(
                "SELECT id, codigo, descripcion
                 FROM productos
                 WHERE estado = 1 AND codigo LIKE :q ESCAPE '!'
                 ORDER BY codigo
                 LIMIT :limit"
            );
            $stmt->bindValue(':q', $this->likePattern($search, false));
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT id, codigo, descripcion
                 FROM productos
                 WHERE estado = 1 AND descripcion LIKE :q ESCAPE '!'
                 ORDER BY descripcion, codigo
                 LIMIT :limit"
            );
            $stmt->bindValue(':q', $this->likePattern($search, true));
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, codigo, descripcion FROM productos WHERE estado = 1 AND id IN ({$placeholders})");
        $stmt->execute($ids);

        return $stmt->fetchAll();
    }

    private function likePattern(string $value, bool $contains): string
    {
        $escaped = strtr($value, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);

        return $contains ? "%{$escaped}%" : "{$escaped}%";
    }
}
