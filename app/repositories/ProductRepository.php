<?php

declare(strict_types=1);

require_once APP_INCLUDES_PATH . '/search_cache.php';

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
        $fullTextQuery = $this->fullTextBooleanQuery($search);
        $sortColumns = [
            'codigo' => 'codigo',
            'descripcion' => 'descripcion',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'descripcion';
        $sortDirection = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if ($search !== '') {
            if ($searchIsCode) {
                $where .= $fullTextQuery !== ''
                    ? " AND (codigo LIKE :q_codigo ESCAPE '!' OR MATCH(descripcion) AGAINST(:q_fulltext IN BOOLEAN MODE))"
                    : " AND codigo LIKE :q_codigo ESCAPE '!'";
                $params[':q_codigo'] = $this->likePattern($search, false);
                if ($fullTextQuery !== '') {
                    $params[':q_fulltext'] = $fullTextQuery;
                }
            } elseif ($fullTextQuery !== '') {
                $where .= ' AND MATCH(descripcion) AGAINST(:q_fulltext IN BOOLEAN MODE)';
                $params[':q_fulltext'] = $fullTextQuery;
            } else {
                $where .= " AND descripcion LIKE :q_descripcion ESCAPE '!'";
                $params[':q_descripcion'] = $this->likePattern($search, true);
            }
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
                'SELECT id, codigo, descripcion
                 FROM productos
                 WHERE estado = 1 AND codigo = ?
                 LIMIT 1'
            );
            $stmt->execute([$search]);
            $exact = $stmt->fetchAll();
            if ($exact) {
                return $exact;
            }
        }

        $cacheKey = search_cache_key('product_search_active', [mb_strtolower($search), $limit]);
        $cached = search_cache_get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        if ($searchIsCode) {
            $fullTextQuery = $this->fullTextBooleanQuery($search);
            if ($fullTextQuery !== '') {
                $stmt = $this->pdo->prepare(
                    "SELECT id, codigo, descripcion
                     FROM (
                        SELECT id, codigo, descripcion, 0 AS prioridad, 0 AS relevancia
                        FROM productos
                        WHERE estado = 1 AND codigo LIKE :q_code ESCAPE '!'
                        UNION ALL
                        SELECT id, codigo, descripcion, 1 AS prioridad,
                               MATCH(descripcion) AGAINST(:q_score IN BOOLEAN MODE) AS relevancia
                        FROM productos
                        WHERE estado = 1
                          AND MATCH(descripcion) AGAINST(:q_match IN BOOLEAN MODE)
                          AND codigo NOT LIKE :q_code_skip ESCAPE '!'
                     ) resultados
                     ORDER BY prioridad, relevancia DESC, codigo
                     LIMIT :limit"
                );
                $codePattern = $this->likePattern($search, false);
                $stmt->bindValue(':q_code', $codePattern);
                $stmt->bindValue(':q_code_skip', $codePattern);
                $stmt->bindValue(':q_score', $fullTextQuery);
                $stmt->bindValue(':q_match', $fullTextQuery);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                $results = $stmt->fetchAll();
                search_cache_set($cacheKey, $results);
                return $results;
            }

            $stmt = $this->pdo->prepare(
                "SELECT id, codigo, descripcion
                 FROM productos
                 WHERE estado = 1 AND codigo LIKE :q ESCAPE '!'
                 ORDER BY codigo
                 LIMIT :limit"
            );
            $stmt->bindValue(':q', $this->likePattern($search, false));
        } else {
            $fullTextQuery = $this->fullTextBooleanQuery($search);
            if ($fullTextQuery !== '') {
                $stmt = $this->pdo->prepare(
                    "SELECT id, codigo, descripcion,
                            MATCH(descripcion) AGAINST(:q_score IN BOOLEAN MODE) AS relevancia
                     FROM productos
                     WHERE estado = 1
                       AND MATCH(descripcion) AGAINST(:q_match IN BOOLEAN MODE)
                     ORDER BY relevancia DESC, descripcion, codigo
                     LIMIT :limit"
                );
                $stmt->bindValue(':q_score', $fullTextQuery);
                $stmt->bindValue(':q_match', $fullTextQuery);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();

                $results = $stmt->fetchAll();
                search_cache_set($cacheKey, $results);
                return $results;
            }

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

        $results = $stmt->fetchAll();
        search_cache_set($cacheKey, $results);
        return $results;
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

    private function fullTextBooleanQuery(string $value): string
    {
        $clean = preg_replace('/[%_+\-<>()~*"@]+/u', ' ', $value) ?? '';
        $terms = preg_split('/\s+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY);
        $terms = array_values(array_filter(array_map(
            static fn (string $term): string => trim($term),
            $terms ?: []
        ), static fn (string $term): bool => mb_strlen($term) >= 3));

        if (!$terms) {
            return '';
        }

        return implode(' ', array_map(static fn (string $term): string => '+' . $term . '*', $terms));
    }
}
