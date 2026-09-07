<?php
declare(strict_types=1);

class PaginationHelper
{
    /**
     * Safely read GET value
     */
    protected static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Basic sanitizer (safe fallback)
     */
    protected static function sanitize($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return htmlspecialchars(
            trim((string)$value),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    /**
     * Get pagination parameters
     */
    public static function getPaginationParams(
        int $page = 1,
        int $limit = 20,
        int $maxLimit = 100
    ): array {
        $page  = (int) self::get('page', $page);
        $limit = (int) self::get('limit', $limit);

        $page  = max(1, $page);
        $limit = max(1, min($maxLimit, $limit));

        return [
            'page'   => $page,
            'limit'  => $limit,
            'offset' => ($page - 1) * $limit
        ];
    }

    /**
     * Get search & sort parameters
     */
    public static function getSearchParams(): array
    {
        $order = strtoupper((string) self::get('order', 'ASC'));

        return [
            'search' => self::sanitize(self::get('search', '')),
            'sort'   => self::sanitize(self::get('sort', '')),
            'order'  => in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC'
        ];
    }

    /**
     * Get filters
     */
    public static function getFilterParams(array $allowedFilters = []): array
    {
        $filters = [];

        foreach ($allowedFilters as $key) {
            $value = self::sanitize(self::get('filter_' . $key, ''));
            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    /**
     * Pagination metadata
     */
    public static function getPaginationMeta(
        int $total,
        int $page,
        int $limit
    ): array {
        $limit = max(1, $limit);
        $totalPages = max(1, (int) ceil($total / $limit));

        // Clamp page inside range
        $page = max(1, min($page, $totalPages));

        $offset = ($page - 1) * $limit;

        return [
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => $totalPages,
            'hasNext'    => $page < $totalPages,
            'hasPrev'    => $page > 1,
            'offset'     => $offset,
            'from'       => $total > 0 ? $offset + 1 : 0,
            'to'         => min($offset + $limit, $total)
        ];
    }

    /**
     * Build search WHERE clause
     */
    public static function buildSearchWhereClause(
        array $searchFields,
        string $searchTerm
    ): array {
        $searchTerm = trim($searchTerm);

        if ($searchTerm === '' || empty($searchFields)) {
            return ['clause' => '', 'params' => []];
        }

        $clauses = [];
        $params  = [];

        foreach ($searchFields as $field) {
            // Field name hardening
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                continue;
            }

            $clauses[] = "`$field` LIKE ?";
            $params[]  = '%' . $searchTerm . '%';
        }

        if (!$clauses) {
            return ['clause' => '', 'params' => []];
        }

        return [
            'clause' => '(' . implode(' OR ', $clauses) . ')',
            'params' => $params
        ];
    }

    /**
     * Build ORDER BY clause (SAFE)
     */
    public static function buildOrderClause(
        string $sortField,
        string $order,
        string $defaultField = 'id',
        array $allowedFields = []
    ): string {
        $sortField = $sortField ?: $defaultField;

        if ($allowedFields && !in_array($sortField, $allowedFields, true)) {
            $sortField = $defaultField;
        }

        // Identifier hardening
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $sortField)) {
            $sortField = $defaultField;
        }

        $order = strtoupper($order);
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

        return "ORDER BY `$sortField` $order";
    }

    /**
     * Format data for template
     */
    public static function formatForTemplate(
        array $items,
        int $total,
        int $page,
        int $limit,
        array $filters = []
    ): array {
        $order = strtoupper((string) self::get('order', 'ASC'));

        return [
            'items'      => $items,
            'pagination' => self::getPaginationMeta($total, $page, $limit),
            'filters'    => $filters,
            'search'     => self::sanitize(self::get('search', '')),
            'sort'       => self::sanitize(self::get('sort', '')),
            'order'      => in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC',
            'limit'      => $limit
        ];
    }
}
