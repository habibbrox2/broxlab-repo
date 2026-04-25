<?php

declare(strict_types=1);

class BroxScrapModel
{
    private mysqli $mysqli;
    /** @var array<string,bool>|null */
    private ?array $columnMap = null;
    /** @var array<string,bool>|null */
    private ?array $incomingColumnMap = null;
    /** @var array<string,bool>|null */
    private ?array $scrapingColumnMap = null;
    /** @var array<string,bool>|null */
    private ?array $pipelineRunColumnMap = null;
    private bool $incomingTableChecked = false;
    private bool $incomingTableExists = false;
    private bool $scrapingTableChecked = false;
    private bool $scrapingTableExists = false;
    private bool $pipelineRunTableChecked = false;
    private bool $pipelineRunTableExists = false;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    private function normalizeFingerprintValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if (is_string($value)) {
            $value = trim(mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value));
            return $value;
        }

        if (is_array($value)) {
            $normalized = $this->normalizeFingerprintArray($value);
            $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($json) ? $json : '';
        }

        return trim((string) $value);
    }

    private function normalizeFingerprintArray(array $value): array
    {
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        if ($isAssoc) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->normalizeFingerprintArray($item);
                continue;
            }

            $value[$key] = $this->normalizeFingerprintValue($item);
        }

        return $value;
    }

    private function buildContentFingerprint(string $dataType, array $item): string
    {
        $type = $this->normalizeType($dataType) ?? strtolower(trim($dataType));
        $parts = [$type];

        $title = $this->normalizeFingerprintValue($item['title'] ?? $item['name'] ?? null);
        $url = $this->normalizeFingerprintValue($item['url'] ?? $item['sourceUrl'] ?? $item['source_url'] ?? null);
        $sourceKey = $this->normalizeFingerprintValue($item['sourceKey'] ?? $item['source_key'] ?? null);
        $category = $this->normalizeFingerprintValue($item['category'] ?? $item['productCategory'] ?? null);
        $author = $this->normalizeFingerprintValue($item['author'] ?? null);
        $excerpt = $this->normalizeFingerprintValue($item['excerpt'] ?? null);
        $body = $this->normalizeFingerprintValue($item['bodyText'] ?? $item['body_text'] ?? null);

        $parts[] = $title;
        $parts[] = $url;
        $parts[] = $sourceKey;
        $parts[] = $category;
        $parts[] = $author;
        $parts[] = $excerpt;
        $parts[] = $body;

        if ($type === 'articles') {
            $tags = $this->normalizeFingerprintValue($item['tags'] ?? []);
            $publishedAt = $this->normalizeFingerprintValue($item['publishedAt'] ?? $item['published_at'] ?? null);
            $parts[] = $tags;
            $parts[] = $publishedAt;
        } elseif ($type === 'mobiles') {
            $brand = $this->normalizeFingerprintValue($item['brand'] ?? null);
            $model = $this->normalizeFingerprintValue($item['model'] ?? null);
            $price = $this->normalizeFingerprintValue($item['price'] ?? null);
            $status = $this->normalizeFingerprintValue($item['status'] ?? null);
            $keySpecs = $this->normalizeFingerprintValue($item['keySpecs'] ?? $item['key_specs'] ?? []);
            $specs = $this->normalizeFingerprintValue($item['specs'] ?? []);
            $parts[] = $brand;
            $parts[] = $model;
            $parts[] = $price;
            $parts[] = $status;
            $parts[] = $keySpecs;
            $parts[] = $specs;
        }

        $normalized = array_values(array_filter($parts, static fn($value): bool => trim((string) $value) !== ''));
        return hash('sha256', implode('|', $normalized));
    }

    private function ensureColumnExists(string $table, string $column, string $definition, ?string $afterColumn = null): bool
    {
        $stmt = $this->mysqli->prepare(
            'SELECT COUNT(*) AS total
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $exists = (int) ($row['total'] ?? 0) > 0;
        $stmt->close();
        if ($exists) {
            return true;
        }

        $sql = 'ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition;
        if ($afterColumn !== null && $afterColumn !== '') {
            $sql .= ' AFTER `' . $afterColumn . '`';
        }

        return (bool) $this->mysqli->query($sql);
    }

    private function ensureIncomingSchema(): bool
    {
        if (
            !$this->ensureColumnExists('push_incoming_items', 'content_fingerprint', 'VARCHAR(64) DEFAULT NULL', 'payload_json') ||
            !$this->ensureColumnExists('push_incoming_items', 'publish_metadata_json', 'LONGTEXT DEFAULT NULL', 'publish_error')
        ) {
            return false;
        }

        return true;
    }

    private function ensureScrapingSchema(): bool
    {
        if (
            !$this->ensureColumnExists('scraping_articles', 'content_fingerprint', 'VARCHAR(64) DEFAULT NULL', 'payload_json') ||
            !$this->ensureColumnExists('scraping_mobiles', 'content_fingerprint', 'VARCHAR(64) DEFAULT NULL', 'payload_json')
        ) {
            return false;
        }

        return true;
    }

    private function ensurePipelineRunsTable(): bool
    {
        if ($this->pipelineRunTableChecked) {
            return $this->pipelineRunTableExists;
        }

        $this->pipelineRunTableChecked = true;

        $existsResult = $this->mysqli->query("SHOW TABLES LIKE 'push_pipeline_runs'");
        if ($existsResult && $existsResult->num_rows > 0) {
            $this->pipelineRunTableExists = true;
            $this->ensurePipelineRunSchema();
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS push_pipeline_runs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_name VARCHAR(60) NOT NULL,
            trigger_source VARCHAR(20) NOT NULL DEFAULT 'manual',
            scope_type VARCHAR(20) DEFAULT NULL,
            batch_limit INT UNSIGNED NOT NULL DEFAULT 20,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            fetched_count INT UNSIGNED NOT NULL DEFAULT 0,
            published_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_duplicates INT UNSIGNED NOT NULL DEFAULT 0,
            ai_used_count INT UNSIGNED NOT NULL DEFAULT 0,
            ai_available TINYINT(1) DEFAULT NULL,
            provider_name VARCHAR(120) DEFAULT NULL,
            model_name VARCHAR(120) DEFAULT NULL,
            duration_ms INT UNSIGNED DEFAULT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME DEFAULT NULL,
            request_uri VARCHAR(768) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            summary_json LONGTEXT DEFAULT NULL,
            results_json LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pipeline_runs_action_name (action_name),
            INDEX idx_pipeline_runs_trigger_source (trigger_source),
            INDEX idx_pipeline_runs_status (status),
            INDEX idx_pipeline_runs_started_at (started_at),
            INDEX idx_pipeline_runs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $created = (bool) $this->mysqli->query($sql);
        $this->pipelineRunTableExists = $created;
        if ($created) {
            $this->ensurePipelineRunSchema();
        }

        return $created;
    }

    private function ensurePipelineRunSchema(): bool
    {
        if (
            !$this->ensureColumnExists('push_pipeline_runs', 'trigger_source', "VARCHAR(20) NOT NULL DEFAULT 'manual'", 'action_name') ||
            !$this->ensureColumnExists('push_pipeline_runs', 'scope_type', "VARCHAR(20) DEFAULT NULL", 'trigger_source') ||
            !$this->ensureColumnExists('push_pipeline_runs', 'batch_limit', 'INT UNSIGNED NOT NULL DEFAULT 20', 'scope_type') ||
            !$this->ensureColumnExists('push_pipeline_runs', 'status', "VARCHAR(20) NOT NULL DEFAULT 'success'", 'batch_limit') ||
            !$this->ensureColumnExists('push_pipeline_runs', 'summary_json', 'LONGTEXT DEFAULT NULL', 'error_message') ||
            !$this->ensureColumnExists('push_pipeline_runs', 'results_json', 'LONGTEXT DEFAULT NULL', 'summary_json')
        ) {
            return false;
        }

        return true;
    }

    public function saveScrapedItem(
        string $dataType,
        array $item,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        if (!$this->ensureScrapingTables()) {
            return ['ok' => false, 'id' => 0, 'image_path' => null];
        }

        $type = $this->normalizeType($dataType);
        if ($type === null) {
            return ['ok' => false, 'id' => 0, 'image_path' => null];
        }

        $source = $this->toNullableString($source);
        $trigger = $this->toNullableString($trigger);
        $pushedAtValue = $this->normalizePushedAt($pushedAt);
        $receivedAtValue = date('Y-m-d H:i:s');
        $payloadJson = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return ['ok' => false, 'id' => 0, 'image_path' => null];
        }

        $fingerprint = $this->buildContentFingerprint($type, $item);
        $imageUrl = $this->extractImageUrl($item);
        $imageInfo = $this->storeRemoteImage($imageUrl, $type, $this->toNullableString($item['title'] ?? $item['name'] ?? null));

        if ($type === 'articles') {
            $insertId = $this->insertScrapedArticleRow(
                $item,
                $source,
                $trigger,
                $pushedAtValue,
                $receivedAtValue,
                $payloadJson,
                $fingerprint,
                $ipAddress,
                $userAgent,
                $imageUrl,
                $imageInfo['image_path'] ?? null
            );

            return [
                'ok' => $insertId > 0,
                'id' => $insertId,
                'image_path' => $imageInfo['image_path'] ?? null,
                'image_url' => $imageUrl,
            ];
        }

        $insertId = $this->insertScrapedMobileRow(
            $item,
            $source,
            $trigger,
            $pushedAtValue,
            $receivedAtValue,
            $payloadJson,
            $fingerprint,
            $ipAddress,
            $userAgent,
            $imageUrl,
            $imageInfo['image_path'] ?? null
        );

        return [
            'ok' => $insertId > 0,
            'id' => $insertId,
            'image_path' => $imageInfo['image_path'] ?? null,
            'image_url' => $imageUrl,
        ];
    }

    public function saveScrapedItems(
        string $dataType,
        array $items,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $results = [];
        foreach ($items as $item) {
            $record = is_array($item) ? $item : ['value' => $item];
            $results[] = $this->saveScrapedItem($dataType, $record, $source, $trigger, $pushedAt, $ipAddress, $userAgent);
        }

        $saved = array_values(array_filter($results, static fn(array $row): bool => (int) ($row['id'] ?? 0) > 0));

        return [
            'first_id' => (int) ($saved[0]['id'] ?? 0),
            'item_ids' => array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $saved),
            'saved_count' => count($saved),
            'results' => $results,
        ];
    }

    public function getPushLogs(int $page = 1, int $limit = 50, ?string $filterType = null): array
    {
        if (!$this->tableExists()) {
            return ['logs' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => max(1, $page), 'limit' => max(1, min($limit, 200))];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 200));
        $type = $this->normalizeType($filterType);

        $total = 0;

        $selectColumns = $this->buildSelectColumns();

        if ($type !== null) {
            $countStmt = $this->mysqli->prepare('SELECT COUNT(*) AS total FROM push_logs WHERE data_type = ?');
            if ($countStmt) {
                $countStmt->bind_param('s', $type);
                $countStmt->execute();
                $countRes = $countStmt->get_result();
                if ($countRes) {
                    $row = $countRes->fetch_assoc();
                    $total = (int) ($row['total'] ?? 0);
                }
                $countStmt->close();
            }

            $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs WHERE data_type = ? ORDER BY received_at DESC LIMIT ? OFFSET ?');
            if (!$stmt) {
                return ['logs' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }

            $stmt->bind_param('sii', $type, $limit, $offset);
        } else {
            $countRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM push_logs');
            if ($countRes) {
                $row = $countRes->fetch_assoc();
                $total = (int) ($row['total'] ?? 0);
            }

            $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs ORDER BY received_at DESC LIMIT ? OFFSET ?');
            if (!$stmt) {
                return ['logs' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }

            $stmt->bind_param('ii', $limit, $offset);
        }

        $logs = [];
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['data_content'] = json_decode((string) ($row['data_content'] ?? '[]'), true);
                if (!is_array($row['data_content'])) {
                    $row['data_content'] = [];
                }
                $logs[] = $row;
            }
        }
        $stmt->close();

        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        return [
            'logs' => $logs,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'limit' => $limit,
        ];
    }

    public function getPushLog(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $selectColumns = $this->buildSelectColumns();
        $stmt = $this->mysqli->prepare('SELECT ' . $selectColumns . ' FROM push_logs WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['data_content'] = json_decode((string) ($row['data_content'] ?? '[]'), true);
        if (!is_array($row['data_content'])) {
            $row['data_content'] = [];
        }

        return $row;
    }

    public function getPushStats(): array
    {
        $stats = [
            'total_logs' => 0,
            'total_articles' => 0,
            'total_mobiles' => 0,
            'last_received' => null,
        ];

        if (!$this->tableExists()) {
            return $stats;
        }

        $sql = 'SELECT COUNT(*) AS total_logs, SUM(CASE WHEN data_type = "articles" THEN 1 ELSE 0 END) AS total_articles, SUM(CASE WHEN data_type = "mobiles" THEN 1 ELSE 0 END) AS total_mobiles, MAX(received_at) AS last_received FROM push_logs';
        $result = $this->mysqli->query($sql);
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_logs'] = (int) ($row['total_logs'] ?? 0);
            $stats['total_articles'] = (int) ($row['total_articles'] ?? 0);
            $stats['total_mobiles'] = (int) ($row['total_mobiles'] ?? 0);
            $stats['last_received'] = $row['last_received'] ?? null;
        }

        return $stats;
    }

    public function addLog(
        string $dataType,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null,
        string $status = 'received'
    ): int {
        $result = $this->addLogBatch($dataType, $items, $ipAddress, $userAgent, $source, $trigger, $pushedAt, $status);
        return (int) ($result['first_id'] ?? 0);
    }

    public function stageIncomingItems(
        string $dataType,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null
    ): array {
        if (!$this->ensureIncomingTable()) {
            return ['first_id' => 0, 'item_ids' => [], 'saved_count' => 0];
        }

        $type = $this->normalizeType($dataType);
        if ($type === null || $items === []) {
            return ['first_id' => 0, 'item_ids' => [], 'saved_count' => 0];
        }

        $pushedAtValue = $this->normalizePushedAt($pushedAt);
        $receivedAtValue = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO push_incoming_items (
            data_type,
            source,
            source_key,
            source_url,
            title,
            category,
            author,
            source_published_at,
            trigger_name,
            pushed_at,
            received_at,
            payload_json,
            content_fingerprint,
            publish_metadata_json,
            publish_status,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return ['first_id' => 0, 'item_ids' => [], 'saved_count' => 0];
        }

        $ids = [];
        $savedCount = 0;
        foreach ($items as $item) {
            $record = is_array($item) ? $item : ['value' => $item];
            $payloadJson = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payloadJson) || $payloadJson === '') {
                continue;
            }

            $rowSource = $this->toNullableString($record['source'] ?? null) ?? $source;
            $rowSourceKey = $this->toNullableString($record['sourceKey'] ?? $record['source_key'] ?? null);
            $rowUrl = $this->toNullableString($record['url'] ?? null);
            $rowTitle = $this->toNullableString($record['title'] ?? null);
            $rowCategory = $this->toNullableString($record['category'] ?? $record['productCategory'] ?? null);
            $rowAuthor = $this->toNullableString($record['author'] ?? null);
            $rowPublishedAt = $this->normalizePushedAt($this->toNullableString($record['publishedAt'] ?? $record['published_at'] ?? null));
            $rowTrigger = $trigger;
            $rowFingerprint = $this->buildContentFingerprint($type, $record);
            $publishMetadataJson = null;
            $publishStatus = 'pending';

            $stmt->bind_param(
                'sssssssssssssssss',
                $type,
                $rowSource,
                $rowSourceKey,
                $rowUrl,
                $rowTitle,
                $rowCategory,
                $rowAuthor,
                $rowPublishedAt,
                $rowTrigger,
                $pushedAtValue,
                $receivedAtValue,
                $payloadJson,
                $rowFingerprint,
                $publishMetadataJson,
                $publishStatus,
                $ipAddress,
                $userAgent
            );

            try {
                $ok = $stmt->execute();
            } catch (Throwable $e) {
                $ok = false;
            }

            if ($ok) {
                $ids[] = (int) $stmt->insert_id;
                $savedCount++;
            }
        }

        $stmt->close();

        return [
            'first_id' => $ids[0] ?? 0,
            'item_ids' => $ids,
            'saved_count' => $savedCount,
        ];
    }

    public function addLogBatch(
        string $dataType,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source = null,
        ?string $trigger = null,
        ?string $pushedAt = null,
        string $status = 'received'
    ): array {
        if (!$this->tableExists()) {
            return ['first_id' => 0, 'log_ids' => [], 'saved_count' => 0];
        }

        $type = $this->normalizeType($dataType);
        if ($type === null || $items === []) {
            return ['first_id' => 0, 'log_ids' => [], 'saved_count' => 0];
        }

        $pushedAtValue = $this->normalizePushedAt($pushedAt);
        $maxChunkJsonBytes = $this->resolveMaxChunkJsonBytes();
        $chunks = $this->splitItemsByJsonBudget($items, $maxChunkJsonBytes);
        if ($chunks === []) {
            return ['first_id' => 0, 'log_ids' => [], 'saved_count' => 0];
        }

        $logIds = [];
        $savedCount = 0;
        foreach ($chunks as $chunk) {
            $partial = $this->insertChunkWithFallback($type, $chunk, $ipAddress, $userAgent, $source, $trigger, $pushedAtValue, $status);
            if (!empty($partial['log_ids'])) {
                $logIds = array_merge($logIds, $partial['log_ids']);
            }
            $savedCount += (int) ($partial['saved_count'] ?? 0);
        }

        return [
            'first_id' => $logIds[0] ?? 0,
            'log_ids' => $logIds,
            'saved_count' => $savedCount,
        ];
    }

    private function insertChunkWithFallback(
        string $type,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source,
        ?string $trigger,
        ?string $pushedAtValue,
        string $status
    ): array {
        if ($items === []) {
            return ['log_ids' => [], 'saved_count' => 0];
        }

        $insertId = $this->insertLogRecord($type, $items, $ipAddress, $userAgent, $source, $trigger, $pushedAtValue, $status);
        if ($insertId > 0) {
            return ['log_ids' => [$insertId], 'saved_count' => count($items)];
        }

        if (count($items) <= 1) {
            return ['log_ids' => [], 'saved_count' => 0];
        }

        // Split recursively if insert failed (typically packet-size related for larger payloads).
        $mid = intdiv(count($items), 2);
        if ($mid <= 0) {
            return ['log_ids' => [], 'saved_count' => 0];
        }

        $left = array_slice($items, 0, $mid);
        $right = array_slice($items, $mid);

        $leftResult = $this->insertChunkWithFallback($type, $left, $ipAddress, $userAgent, $source, $trigger, $pushedAtValue, $status);
        $rightResult = $this->insertChunkWithFallback($type, $right, $ipAddress, $userAgent, $source, $trigger, $pushedAtValue, $status);

        return [
            'log_ids' => array_merge($leftResult['log_ids'] ?? [], $rightResult['log_ids'] ?? []),
            'saved_count' => (int) ($leftResult['saved_count'] ?? 0) + (int) ($rightResult['saved_count'] ?? 0),
        ];
    }

    private function insertLogRecord(
        string $type,
        array $items,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $source,
        ?string $trigger,
        ?string $pushedAtValue,
        string $status
    ): int {
        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return 0;
        }

        $itemCount = count($items);
        $hasSource = $this->hasColumn('source');
        $hasTrigger = $this->hasColumn('trigger_name');
        $hasPushedAt = $this->hasColumn('pushed_at');
        $hasStatus = $this->hasColumn('status');

        $columns = ['data_type', 'item_count', 'data_content', 'ip_address', 'user_agent'];
        $types = 'sisss';
        $values = [$type, $itemCount, $json, $ipAddress, $userAgent];

        if ($hasSource) {
            $columns[] = 'source';
            $types .= 's';
            $values[] = $source;
        }
        if ($hasTrigger) {
            $columns[] = 'trigger_name';
            $types .= 's';
            $values[] = $trigger;
        }
        if ($hasPushedAt) {
            $columns[] = 'pushed_at';
            $types .= 's';
            $values[] = $pushedAtValue;
        }
        if ($hasStatus) {
            $columns[] = 'status';
            $types .= 's';
            $values[] = $status;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO push_logs (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        try {
            $stmt = $this->mysqli->prepare($sql);
        } catch (Throwable $e) {
            return 0;
        }
        if (!$stmt) {
            return 0;
        }

        $bindParams = [$types];
        foreach ($values as $index => $value) {
            $bindParams[] = &$values[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        try {
            $ok = $stmt->execute();
        } catch (Throwable $e) {
            $stmt->close();
            return 0;
        }
        $insertId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();

        return $insertId;
    }

    private function resolveMaxChunkJsonBytes(): int
    {
        $raw = (int) ($_ENV['SCRAPER_PUSH_MAX_JSON_BYTES'] ?? 524288);
        // Keep a sane range to avoid too-small chunks or huge packets.
        return max(32768, min($raw, 2097152));
    }

    private function splitItemsByJsonBudget(array $items, int $maxJsonBytes): array
    {
        $chunks = [];
        $current = [];

        foreach ($items as $item) {
            $candidate = $current;
            $candidate[] = $item;

            $candidateJson = json_encode($candidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $candidateLen = is_string($candidateJson) ? strlen($candidateJson) : PHP_INT_MAX;

            if ($candidateLen <= $maxJsonBytes) {
                $current = $candidate;
                continue;
            }

            if ($current !== []) {
                $chunks[] = $current;
                $current = [$item];
                continue;
            }

            // Single record too large; still push it so fallback recursion can try smaller inserts.
            $chunks[] = [$item];
            $current = [];
        }

        if ($current !== []) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    public function deletePushLog(int $id): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $stmt = $this->mysqli->prepare('DELETE FROM push_logs WHERE id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function tableExists(): bool
    {
        $result = $this->mysqli->query("SHOW TABLES LIKE 'push_logs'");
        return $result && $result->num_rows > 0;
    }

    public function createTable(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS push_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(20) NOT NULL,
            item_count INT UNSIGNED NOT NULL,
            data_content JSON NOT NULL,
            source VARCHAR(120) DEFAULT NULL,
            trigger_name VARCHAR(120) DEFAULT NULL,
            pushed_at DATETIME DEFAULT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            INDEX idx_data_type (data_type),
            INDEX idx_source (source),
            INDEX idx_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return (bool) $this->mysqli->query($sql);
    }

    public function ensureIncomingTable(): bool
    {
        if ($this->incomingTableChecked) {
            return $this->incomingTableExists;
        }

        $this->incomingTableChecked = true;

        $existsResult = $this->mysqli->query("SHOW TABLES LIKE 'push_incoming_items'");
        if ($existsResult && $existsResult->num_rows > 0) {
            $this->incomingTableExists = true;
            $this->ensureIncomingSchema();
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS push_incoming_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(20) NOT NULL,
            source VARCHAR(120) DEFAULT NULL,
            source_key VARCHAR(120) DEFAULT NULL,
            source_url VARCHAR(768) DEFAULT NULL,
            title VARCHAR(500) DEFAULT NULL,
            category VARCHAR(120) DEFAULT NULL,
            author VARCHAR(190) DEFAULT NULL,
            source_published_at DATETIME DEFAULT NULL,
            trigger_name VARCHAR(120) DEFAULT NULL,
            pushed_at DATETIME DEFAULT NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            payload_json LONGTEXT NOT NULL,
            content_fingerprint VARCHAR(64) DEFAULT NULL,
            publish_metadata_json LONGTEXT DEFAULT NULL,
            publish_status VARCHAR(20) NOT NULL DEFAULT 'pending',
            publish_attempts INT UNSIGNED NOT NULL DEFAULT 0,
            published_content_id INT UNSIGNED DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            publish_error TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            INDEX idx_data_type (data_type),
            INDEX idx_publish_status (publish_status),
            INDEX idx_received_at (received_at),
            INDEX idx_source_key (source_key),
            INDEX idx_content_fingerprint (content_fingerprint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $created = (bool) $this->mysqli->query($sql);
        $this->incomingTableExists = $created;
        if ($created) {
            $this->ensureIncomingSchema();
        }
        return $created;
    }

    public function getPendingIncomingItems(int $limit = 20, ?string $dataType = null): array
    {
        if (!$this->ensureIncomingTable()) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $type = $this->normalizeType($dataType);

        if ($type !== null) {
            $stmt = $this->mysqli->prepare(
                "SELECT id, data_type, source, source_key, source_url, title, category, author, source_published_at, trigger_name, pushed_at, received_at, payload_json, content_fingerprint, publish_metadata_json, publish_status, publish_attempts
                 FROM push_incoming_items
                 WHERE publish_status = 'pending' AND data_type = ?
                 ORDER BY source_published_at DESC, id ASC
                 LIMIT ?"
            );
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('si', $type, $limit);
        } else {
            $stmt = $this->mysqli->prepare(
                "SELECT id, data_type, source, source_key, source_url, title, category, author, source_published_at, trigger_name, pushed_at, received_at, payload_json, content_fingerprint, publish_metadata_json, publish_status, publish_attempts
                 FROM push_incoming_items
                 WHERE publish_status = 'pending'
                 ORDER BY source_published_at DESC, id ASC
                 LIMIT ?"
            );
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $limit);
        }

        $items = [];
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
        $stmt->close();
        return $items;
    }

    public function getIncomingItems(
        int $page = 1,
        int $limit = 50,
        ?string $status = null,
        ?string $dataType = null
    ): array {
        if (!$this->ensureIncomingTable()) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => 1, 'limit' => 50];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 200));
        $offset = ($page - 1) * $limit;

        $statusNorm = null;
        if ($status !== null) {
            $s = strtolower(trim($status));
            if (in_array($s, ['pending', 'published', 'failed'], true)) {
                $statusNorm = $s;
            }
        }
        $typeNorm = $this->normalizeType($dataType);

        $where = [];
        $params = [];
        $types = '';

        if ($statusNorm !== null) {
            $where[] = 'publish_status = ?';
            $types .= 's';
            $params[] = $statusNorm;
        }
        if ($typeNorm !== null) {
            $where[] = 'data_type = ?';
            $types .= 's';
            $params[] = $typeNorm;
        }

        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $countSql = 'SELECT COUNT(*) AS total FROM push_incoming_items' . $whereSql;
        $countStmt = $this->mysqli->prepare($countSql);
        if (!$countStmt) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
        }
        if ($types !== '') {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $countRow = $countRes ? $countRes->fetch_assoc() : null;
        $total = (int) ($countRow['total'] ?? 0);
        $countStmt->close();

          $selectSql = 'SELECT id, data_type, source, source_key, source_url, title, category, author, source_published_at, trigger_name, pushed_at, received_at, payload_json, content_fingerprint, publish_metadata_json, publish_status, publish_attempts, published_content_id, published_at, publish_error, ip_address, user_agent
                        FROM push_incoming_items' . $whereSql . ' ORDER BY source_published_at DESC, id DESC LIMIT ? OFFSET ?';
        $stmt = $this->mysqli->prepare($selectSql);
        if (!$stmt) {
            return ['items' => [], 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $limit)), 'current_page' => $page, 'limit' => $limit];
        }

        $selectTypes = $types . 'ii';
        $selectParams = $params;
        $selectParams[] = $limit;
        $selectParams[] = $offset;
        $stmt->bind_param($selectTypes, ...$selectParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
        $stmt->close();

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $limit)),
            'current_page' => $page,
            'limit' => $limit,
        ];
    }

    public function getIncomingItem(int $id): ?array
    {
        if (!$this->ensureIncomingTable()) {
            return null;
        }
        $stmt = $this->mysqli->prepare(
          'SELECT id, data_type, source, source_key, source_url, title, category, author, source_published_at, trigger_name, pushed_at, received_at, payload_json, content_fingerprint, publish_metadata_json, publish_status, publish_attempts, published_content_id, published_at, publish_error, ip_address, user_agent
               FROM push_incoming_items
               WHERE id = ?
               LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }

    public function getIncomingItemsByIds(array $ids): array
    {
        if (!$this->ensureIncomingTable()) {
            return [];
        }

        $clean = array_values(array_filter(array_map('intval', $ids), static fn($v) => $v > 0));
        if ($clean === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($clean), '?'));
        $types = str_repeat('i', count($clean));
          $sql = "SELECT id, data_type, source, source_key, source_url, title, category, author, source_published_at, trigger_name, pushed_at, received_at, payload_json, content_fingerprint, publish_metadata_json, publish_status, publish_attempts, published_content_id, published_at, publish_error, ip_address, user_agent
                  FROM push_incoming_items
                  WHERE id IN ($placeholders)
                ORDER BY source_published_at DESC, id ASC";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$clean);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
        $stmt->close();
        return $items;
    }

    public function requeueFailedIncomingItems(int $limit = 20, ?string $dataType = null): array
    {
        if (!$this->ensureIncomingTable()) {
            return ['count' => 0, 'ids' => []];
        }

        $limit = max(1, min($limit, 200));
        $typeNorm = $this->normalizeType($dataType);
        $ids = [];

        if ($typeNorm !== null) {
            $stmt = $this->mysqli->prepare(
                "SELECT id FROM push_incoming_items
                 WHERE publish_status = 'failed' AND data_type = ?
                 ORDER BY source_published_at DESC, id ASC
                 LIMIT ?"
            );
            if (!$stmt) {
                return ['count' => 0, 'ids' => []];
            }
            $stmt->bind_param('si', $typeNorm, $limit);
        } else {
            $stmt = $this->mysqli->prepare(
                "SELECT id FROM push_incoming_items
                 WHERE publish_status = 'failed'
                 ORDER BY source_published_at DESC, id ASC
                 LIMIT ?"
            );
            if (!$stmt) {
                return ['count' => 0, 'ids' => []];
            }
            $stmt->bind_param('i', $limit);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int) ($row['id'] ?? 0);
            }
        }
        $stmt->close();

        if ($ids === []) {
            return ['count' => 0, 'ids' => []];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "UPDATE push_incoming_items
                SET publish_status = 'pending',
                    publish_error = NULL
                WHERE id IN ($placeholders)";
        $updateStmt = $this->mysqli->prepare($sql);
        if (!$updateStmt) {
            return ['count' => 0, 'ids' => []];
        }
        $updateStmt->bind_param($types, ...$ids);
        $ok = $updateStmt->execute();
        $affected = $ok ? (int) $updateStmt->affected_rows : 0;
        $updateStmt->close();

        return ['count' => $affected, 'ids' => $ids];
    }

    public function markIncomingItemPublished(int $id, int $publishedContentId, ?array $metadata = null): bool
    {
        if (!$this->ensureIncomingTable()) {
            return false;
        }

        $metadataJson = null;
        if ($metadata !== null) {
            $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $metadataJson = is_string($encoded) && $encoded !== '' ? $encoded : null;
        }

        $stmt = $this->mysqli->prepare(
            "UPDATE push_incoming_items
             SET publish_status = 'published',
                 published_content_id = ?,
                 published_at = NOW(),
                 publish_error = NULL,
                 publish_metadata_json = ?,
                 publish_attempts = publish_attempts + 1
             WHERE id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isi', $publishedContentId, $metadataJson, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }

    public function findPublishedIncomingItemByFingerprint(string $dataType, string $fingerprint, int $excludeId = 0): ?array
    {
        if (!$this->ensureIncomingTable()) {
            return null;
        }

        $type = $this->normalizeType($dataType);
        if ($type === null || trim($fingerprint) === '') {
            return null;
        }

        $sql = 'SELECT id, data_type, published_content_id, publish_metadata_json, content_fingerprint, published_at
                FROM push_incoming_items
                WHERE data_type = ? AND content_fingerprint = ? AND publish_status = "published"';
        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
        }
        $sql .= ' ORDER BY published_at DESC, id DESC LIMIT 1';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return null;
        }

        if ($excludeId > 0) {
            $stmt->bind_param('ssi', $type, $fingerprint, $excludeId);
        } else {
            $stmt->bind_param('ss', $type, $fingerprint);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row : null;
    }

    public function savePipelineRun(array $data): int
    {
        if (!$this->ensurePipelineRunsTable()) {
            return 0;
        }

        $actionName = $this->toNullableString($data['action_name'] ?? $data['action'] ?? null) ?? 'run-pipeline';
        $triggerSource = $this->toNullableString($data['trigger_source'] ?? null) ?? 'manual';
        $scopeType = $this->toNullableString($data['scope_type'] ?? $data['data_type'] ?? null);
        $batchLimit = max(1, min((int) ($data['batch_limit'] ?? 20), 200));
        $status = $this->toNullableString($data['status'] ?? null) ?? 'success';
        $startedAt = $this->toNullableString($data['started_at'] ?? null) ?? date('Y-m-d H:i:s');
        $finishedAt = $this->toNullableString($data['finished_at'] ?? null) ?? date('Y-m-d H:i:s');
        $durationMs = isset($data['duration_ms']) ? max(0, (int) $data['duration_ms']) : null;
        $ipAddress = $this->toNullableString($data['ip_address'] ?? null);
        $userAgent = $this->toNullableString($data['user_agent'] ?? null);
        $requestUri = $this->toNullableString($data['request_uri'] ?? null);
        $errorMessage = $this->toNullableString($data['error_message'] ?? null);

        $summary = $data['summary'] ?? [];
        $results = $data['results'] ?? [];
        $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultsJson = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $summaryJson = is_string($summaryJson) && $summaryJson !== '' ? $summaryJson : null;
        $resultsJson = is_string($resultsJson) && $resultsJson !== '' ? $resultsJson : null;

        $stmt = $this->mysqli->prepare(
            "INSERT INTO push_pipeline_runs (
                action_name,
                trigger_source,
                scope_type,
                batch_limit,
                status,
                fetched_count,
                published_count,
                failed_count,
                skipped_duplicates,
                ai_used_count,
                ai_available,
                provider_name,
                model_name,
                duration_ms,
                started_at,
                finished_at,
                request_uri,
                ip_address,
                user_agent,
                error_message,
                summary_json,
                results_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return 0;
        }

        $fetchedCount = (int) ($summary['fetched'] ?? 0);
        $publishedCount = (int) ($summary['published'] ?? 0);
        $failedCount = (int) ($summary['failed'] ?? 0);
        $skippedDuplicates = (int) ($summary['skipped_duplicates'] ?? 0);
        $aiUsedCount = (int) ($summary['ai_used_count'] ?? 0);
        $aiAvailable = isset($data['ai_available']) ? (int) (bool) $data['ai_available'] : null;
        $providerName = $this->toNullableString($data['provider_name'] ?? null);
        $modelName = $this->toNullableString($data['model_name'] ?? null);

        $stmt->bind_param(
            'sssisiiiiiississssssss',
            $actionName,
            $triggerSource,
            $scopeType,
            $batchLimit,
            $status,
            $fetchedCount,
            $publishedCount,
            $failedCount,
            $skippedDuplicates,
            $aiUsedCount,
            $aiAvailable,
            $providerName,
            $modelName,
            $durationMs,
            $startedAt,
            $finishedAt,
            $requestUri,
            $ipAddress,
            $userAgent,
            $errorMessage,
            $summaryJson,
            $resultsJson
        );

        try {
            $ok = $stmt->execute();
        } catch (Throwable $e) {
            $ok = false;
        }
        $insertId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();

        return $insertId;
    }

    public function getPipelineRuns(int $page = 1, int $limit = 20, ?string $actionName = null): array
    {
        if (!$this->ensurePipelineRunsTable()) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => max(1, $page), 'limit' => max(1, min($limit, 200))];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 200));
        $actionNorm = $this->toNullableString($actionName);
        $where = '';
        $params = [];
        $types = '';

        if ($actionNorm !== null) {
            $where = ' WHERE action_name = ?';
            $params[] = $actionNorm;
            $types = 's';
        }

        $countSql = 'SELECT COUNT(*) AS total FROM push_pipeline_runs' . $where;
        $countStmt = $this->mysqli->prepare($countSql);
        if (!$countStmt) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
        }
        if ($types !== '') {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $countRes = $countStmt->get_result();
        $countRow = $countRes ? $countRes->fetch_assoc() : null;
        $total = (int) ($countRow['total'] ?? 0);
        $countStmt->close();

        $offset = ($page - 1) * $limit;
        $sql = 'SELECT id, action_name, trigger_source, scope_type, batch_limit, status, fetched_count, published_count, failed_count, skipped_duplicates, ai_used_count, ai_available, provider_name, model_name, duration_ms, started_at, finished_at, request_uri, ip_address, user_agent, error_message, summary_json, results_json, created_at
                FROM push_pipeline_runs' . $where . ' ORDER BY started_at DESC, id DESC LIMIT ? OFFSET ?';
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return ['items' => [], 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $limit)), 'current_page' => $page, 'limit' => $limit];
        }

        $bindTypes = $types . 'ii';
        $bindParams = $params;
        $bindParams[] = $limit;
        $bindParams[] = $offset;
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $items = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['summary_json'] = json_decode((string) ($row['summary_json'] ?? 'null'), true);
                if (!is_array($row['summary_json'])) {
                    $row['summary_json'] = [];
                }
                $row['results_json'] = json_decode((string) ($row['results_json'] ?? 'null'), true);
                if (!is_array($row['results_json'])) {
                    $row['results_json'] = [];
                }
                $items[] = $row;
            }
        }
        $stmt->close();

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $limit)),
            'current_page' => $page,
            'limit' => $limit,
        ];
    }

    public function getPipelineRun(int $id): ?array
    {
        if (!$this->ensurePipelineRunsTable()) {
            return null;
        }

        $stmt = $this->mysqli->prepare(
            'SELECT id, action_name, trigger_source, scope_type, batch_limit, status, fetched_count, published_count, failed_count, skipped_duplicates, ai_used_count, ai_available, provider_name, model_name, duration_ms, started_at, finished_at, request_uri, ip_address, user_agent, error_message, summary_json, results_json, created_at
             FROM push_pipeline_runs
             WHERE id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['summary_json'] = json_decode((string) ($row['summary_json'] ?? 'null'), true);
        if (!is_array($row['summary_json'])) {
            $row['summary_json'] = [];
        }
        $row['results_json'] = json_decode((string) ($row['results_json'] ?? 'null'), true);
        if (!is_array($row['results_json'])) {
            $row['results_json'] = [];
        }

        return $row;
    }

    public function getRecentPipelineRuns(int $limit = 5): array
    {
        $result = $this->getPipelineRuns(1, $limit, null);
        return $result['items'] ?? [];
    }

    public function markIncomingItemFailed(int $id, string $error): bool
    {
        if (!$this->ensureIncomingTable()) {
            return false;
        }

        $trimmedError = trim($error);
        if ($trimmedError === '') {
            $trimmedError = 'Unknown publish error';
        }
        if (strlen($trimmedError) > 2000) {
            $trimmedError = substr($trimmedError, 0, 2000);
        }

        $stmt = $this->mysqli->prepare(
            "UPDATE push_incoming_items
             SET publish_status = 'failed',
                 publish_error = ?,
                 publish_attempts = publish_attempts + 1
             WHERE id = ?"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $trimmedError, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }

    public function getIncomingPublishStats(): array
    {
        $stats = [
            'pending' => 0,
            'published' => 0,
            'failed' => 0,
            'total' => 0,
        ];

        if (!$this->ensureIncomingTable()) {
            return $stats;
        }

        $res = $this->mysqli->query(
            "SELECT publish_status, COUNT(*) AS cnt
             FROM push_incoming_items
             GROUP BY publish_status"
        );
        if (!$res) {
            return $stats;
        }

        while ($row = $res->fetch_assoc()) {
            $status = strtolower((string) ($row['publish_status'] ?? ''));
            $cnt = (int) ($row['cnt'] ?? 0);
            if (isset($stats[$status])) {
                $stats[$status] = $cnt;
            }
            $stats['total'] += $cnt;
        }
        return $stats;
    }

    private function ensureScrapingTables(): bool
    {
        if ($this->scrapingTableChecked) {
            return $this->scrapingTableExists;
        }

        $this->scrapingTableChecked = true;

        $articleSql = "CREATE TABLE IF NOT EXISTS scraping_articles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(20) NOT NULL DEFAULT 'articles',
            source VARCHAR(120) DEFAULT NULL,
            source_key VARCHAR(160) DEFAULT NULL,
            source_url VARCHAR(768) DEFAULT NULL,
            title VARCHAR(500) DEFAULT NULL,
            category VARCHAR(160) DEFAULT NULL,
            author VARCHAR(190) DEFAULT NULL,
            brand VARCHAR(190) DEFAULT NULL,
            model VARCHAR(255) DEFAULT NULL,
            product_category VARCHAR(160) DEFAULT NULL,
            price_text VARCHAR(120) DEFAULT NULL,
            price_value DECIMAL(14,2) DEFAULT NULL,
            status_text VARCHAR(80) DEFAULT NULL,
            image_url VARCHAR(768) DEFAULT NULL,
            image_path VARCHAR(768) DEFAULT NULL,
            image_caption TEXT DEFAULT NULL,
            trigger_name VARCHAR(120) DEFAULT NULL,
            pushed_at DATETIME DEFAULT NULL,
            scraped_at DATETIME DEFAULT NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            content_fingerprint VARCHAR(64) DEFAULT NULL,
            content_type VARCHAR(30) DEFAULT NULL,
            fetch_method VARCHAR(60) DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            published_text VARCHAR(255) DEFAULT NULL,
            excerpt LONGTEXT DEFAULT NULL,
            body_text LONGTEXT DEFAULT NULL,
            tags_json LONGTEXT DEFAULT NULL,
            key_specs_json LONGTEXT DEFAULT NULL,
            specs_json LONGTEXT DEFAULT NULL,
            payload_json LONGTEXT NOT NULL,
            INDEX idx_articles_data_type (data_type),
            INDEX idx_articles_source (source),
            INDEX idx_articles_source_key (source_key),
            INDEX idx_articles_received_at (received_at),
            INDEX idx_articles_published_at (published_at),
            INDEX idx_articles_content_fingerprint (content_fingerprint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $mobileSql = "CREATE TABLE IF NOT EXISTS scraping_mobiles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            data_type VARCHAR(20) NOT NULL DEFAULT 'mobiles',
            source VARCHAR(120) DEFAULT NULL,
            source_key VARCHAR(160) DEFAULT NULL,
            source_url VARCHAR(768) DEFAULT NULL,
            title VARCHAR(500) DEFAULT NULL,
            category VARCHAR(160) DEFAULT NULL,
            author VARCHAR(190) DEFAULT NULL,
            brand VARCHAR(190) DEFAULT NULL,
            model VARCHAR(255) DEFAULT NULL,
            product_category VARCHAR(160) DEFAULT NULL,
            price_text VARCHAR(120) DEFAULT NULL,
            price_value DECIMAL(14,2) DEFAULT NULL,
            status_text VARCHAR(80) DEFAULT NULL,
            image_url VARCHAR(768) DEFAULT NULL,
            image_path VARCHAR(768) DEFAULT NULL,
            image_caption TEXT DEFAULT NULL,
            trigger_name VARCHAR(120) DEFAULT NULL,
            pushed_at DATETIME DEFAULT NULL,
            scraped_at DATETIME DEFAULT NULL,
            fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            content_fingerprint VARCHAR(64) DEFAULT NULL,
            content_type VARCHAR(30) DEFAULT NULL,
            fetch_method VARCHAR(60) DEFAULT NULL,
            published_at DATETIME DEFAULT NULL,
            published_text VARCHAR(255) DEFAULT NULL,
            excerpt LONGTEXT DEFAULT NULL,
            body_text LONGTEXT DEFAULT NULL,
            tags_json LONGTEXT DEFAULT NULL,
            key_specs_json LONGTEXT DEFAULT NULL,
            specs_json LONGTEXT DEFAULT NULL,
            payload_json LONGTEXT NOT NULL,
            INDEX idx_mobiles_data_type (data_type),
            INDEX idx_mobiles_source (source),
            INDEX idx_mobiles_source_key (source_key),
            INDEX idx_mobiles_received_at (received_at),
            INDEX idx_mobiles_published_at (published_at),
            INDEX idx_mobiles_content_fingerprint (content_fingerprint)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $createdArticles = (bool) $this->mysqli->query($articleSql);
        $createdMobiles = (bool) $this->mysqli->query($mobileSql);
        $this->scrapingTableExists = $createdArticles && $createdMobiles;
        if ($this->scrapingTableExists) {
            $this->ensureScrapingSchema();
        }

        return $this->scrapingTableExists;
    }

    private function insertScrapedArticleRow(
        array $item,
        ?string $source,
        ?string $trigger,
        ?string $pushedAt,
        ?string $receivedAt,
        string $payloadJson,
        string $fingerprint,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $imageUrl,
        ?string $imagePath
    ): int {
        $record = $this->buildScrapedRecord('articles', $item, $source, $trigger, $pushedAt, $receivedAt, $payloadJson, $fingerprint, $ipAddress, $userAgent, $imageUrl, $imagePath);
        return $this->insertScrapedRow('scraping_articles', $record);
    }

    private function insertScrapedMobileRow(
        array $item,
        ?string $source,
        ?string $trigger,
        ?string $pushedAt,
        ?string $receivedAt,
        string $payloadJson,
        string $fingerprint,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $imageUrl,
        ?string $imagePath
    ): int {
        $record = $this->buildScrapedRecord('mobiles', $item, $source, $trigger, $pushedAt, $receivedAt, $payloadJson, $fingerprint, $ipAddress, $userAgent, $imageUrl, $imagePath);
        return $this->insertScrapedRow('scraping_mobiles', $record);
    }

    private function buildScrapedRecord(
        string $dataType,
        array $item,
        ?string $source,
        ?string $trigger,
        ?string $pushedAt,
        ?string $receivedAt,
        string $payloadJson,
        string $fingerprint,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $imageUrl,
        ?string $imagePath
    ): array {
        $brand = $this->toNullableString($item['brand'] ?? null);
        $model = $this->toNullableString($item['model'] ?? null);
        $title = $this->toNullableString($item['title'] ?? $item['name'] ?? null);
        $category = $this->toNullableString($item['category'] ?? $item['productCategory'] ?? null);
        $author = $this->toNullableString($item['author'] ?? null);
        $productCategory = $this->toNullableString($item['productCategory'] ?? $item['category'] ?? null);
        $priceText = $this->toNullableString($item['price'] ?? null);
        $priceValue = $this->parsePriceValue($priceText);
        $status = $this->toNullableString($item['status'] ?? null);
        $excerpt = $this->toNullableString($item['excerpt'] ?? null);
        $bodyText = $this->toNullableString($item['bodyText'] ?? $item['body_text'] ?? null);
        $imageCaption = $this->toNullableString($item['imageCaption'] ?? $item['caption'] ?? null);
        $contentType = $this->toNullableString($item['contentType'] ?? $item['content_type'] ?? null) ?? $dataType;
        $fetchMethod = $this->toNullableString($item['fetchMethod'] ?? $item['fetch_method'] ?? null);
        $publishedAt = $this->normalizePushedAt($this->toNullableString($item['publishedAt'] ?? $item['published_at'] ?? null));
        $publishedText = $this->toNullableString($item['publishedText'] ?? $item['published_text'] ?? null);
        $scrapedAt = $this->normalizePushedAt($this->toNullableString($item['scrapedAt'] ?? $item['scraped_at'] ?? null));
        $sourceKey = $this->toNullableString($item['sourceKey'] ?? $item['source_key'] ?? null);
        $sourceUrl = $this->toNullableString($item['url'] ?? $item['sourceUrl'] ?? $item['source_url'] ?? null);
        $triggerName = $this->toNullableString($trigger);

        $tags = $this->jsonOrNull($item['tags'] ?? null);
        $keySpecs = $this->jsonOrNull($item['keySpecs'] ?? $item['key_specs'] ?? null);
        $specs = $this->jsonOrNull($item['specs'] ?? null);

        return [
            'data_type' => $dataType,
            'source' => $source,
            'source_key' => $sourceKey,
            'source_url' => $sourceUrl,
            'title' => $title,
            'category' => $category,
            'author' => $author,
            'brand' => $brand,
            'model' => $model,
            'product_category' => $productCategory,
            'price_text' => $priceText,
            'price_value' => $priceValue !== null ? (string) $priceValue : null,
            'status_text' => $status,
            'image_url' => $imageUrl,
            'image_path' => $imagePath,
            'image_caption' => $imageCaption,
            'trigger_name' => $triggerName,
            'pushed_at' => $pushedAt,
            'scraped_at' => $scrapedAt,
            'received_at' => $receivedAt,
            'fetched_at' => $receivedAt,
            'content_fingerprint' => $fingerprint,
            'content_type' => $contentType,
            'fetch_method' => $fetchMethod,
            'published_at' => $publishedAt,
            'published_text' => $publishedText,
            'excerpt' => $excerpt,
            'body_text' => $bodyText,
            'tags_json' => $tags,
            'key_specs_json' => $keySpecs,
            'specs_json' => $specs,
            'payload_json' => $payloadJson,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];
    }

    private function insertScrapedRow(string $table, array $record): int
    {
        $columns = [
            'data_type',
            'source',
            'source_key',
            'source_url',
            'title',
            'category',
            'author',
            'brand',
            'model',
            'product_category',
            'price_text',
            'price_value',
            'status_text',
            'image_url',
            'image_path',
            'image_caption',
            'trigger_name',
            'pushed_at',
            'scraped_at',
            'received_at',
            'fetched_at',
            'content_fingerprint',
            'content_type',
            'fetch_method',
            'published_at',
            'published_text',
            'excerpt',
            'body_text',
            'tags_json',
            'key_specs_json',
            'specs_json',
            'payload_json',
        ];

        $values = [];
        foreach ($columns as $column) {
            $values[] = $record[$column] ?? null;
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
        try {
            $stmt = $this->mysqli->prepare($sql);
        } catch (Throwable $e) {
            return 0;
        }
        if (!$stmt) {
            return 0;
        }

        $types = str_repeat('s', count($values));
        $bindParams = [$types];
        foreach ($values as $index => $value) {
            $bindParams[] = &$values[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);

        try {
            $ok = $stmt->execute();
        } catch (Throwable $e) {
            $stmt->close();
            return 0;
        }

        $insertId = $ok ? (int) $stmt->insert_id : 0;
        $stmt->close();

        return $insertId;
    }

    private function fetchScrapingRows(mysqli_stmt $stmt): array
    {
        $items = [];
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['payload_json'] = (string) ($row['payload_json'] ?? '');
                $items[] = $row;
            }
        }
        $stmt->close();
        return $items;
    }

    private function extractImageUrl(array $item): ?string
    {
        $candidates = [
            $item['imageUrl'] ?? null,
            $item['image_url'] ?? null,
            $item['featured_image_url'] ?? null,
            $item['image'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                foreach (['url', 'path', 'thumbnail_path', 'image_path'] as $key) {
                    if (isset($candidate[$key]) && is_scalar($candidate[$key])) {
                        $value = trim((string) $candidate[$key]);
                        if ($value !== '') {
                            return $value;
                        }
                    }
                }
                continue;
            }

            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function storeRemoteImage(?string $url, string $dataType, ?string $seedName = null): array
    {
        $url = $this->toNullableString($url);
        if ($url === null || !preg_match('#^https?://#i', $url)) {
            return ['image_path' => null, 'local_path' => null];
        }

        $download = $this->downloadRemoteBinary($url);
        if ($download === null || ($download['data'] ?? '') === '') {
            return ['image_path' => null, 'local_path' => null];
        }

        $uploadsBasePath = function_exists('brox_get_uploads_base_path')
            ? brox_get_uploads_base_path()
            : dirname(__DIR__, 2) . '/public_html/uploads';
        $uploadsBaseUrl = function_exists('brox_get_uploads_base_url')
            ? brox_get_uploads_base_url()
            : '/uploads';

        $subdir = 'scraping/' . ($dataType === 'articles' ? 'articles' : 'mobiles');
        $targetDir = rtrim($uploadsBasePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subdir);
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return ['image_path' => null, 'local_path' => null];
        }

        $ext = $this->guessImageExtension($download['mime'] ?? null, $url);
        $baseName = $seedName;
        if ($baseName === null || trim($baseName) === '') {
            $pathName = parse_url($url, PHP_URL_PATH);
            $baseName = is_string($pathName) ? pathinfo($pathName, PATHINFO_FILENAME) : '';
        }
        if (!is_string($baseName) || trim($baseName) === '') {
            $baseName = $dataType . '-image';
        }
        $filenameBase = $this->slugifyForFile($baseName);
        $hash = substr(hash('sha256', $url . '|' . microtime(true)), 0, 12);
        $filename = trim($filenameBase, '-') . '-' . date('YmdHis') . '-' . $hash . '.' . $ext;
        $localPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (@file_put_contents($localPath, $download['data']) === false) {
            return ['image_path' => null, 'local_path' => null];
        }

        $webPath = rtrim($uploadsBaseUrl, '/') . '/' . $subdir . '/' . $filename;

        return [
            'image_path' => $webPath,
            'local_path' => $localPath,
        ];
    }

    private function downloadRemoteBinary(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BroxScrapBot/1.0)',
        ]);

        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($data === false || $status < 200 || $status >= 300 || !is_string($data) || $data === '') {
            return null;
        }

        if (!is_string($mime) || $mime === '') {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($data) ?: null;
        }

        if (!is_string($mime) || stripos($mime, 'image/') !== 0) {
            return null;
        }

        return [
            'data' => $data,
            'mime' => is_string($mime) ? $mime : null,
        ];
    }

    private function guessImageExtension(?string $mime, string $url): string
    {
        $mime = strtolower(trim((string) $mime));
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp'], true)) {
                return $ext === 'jpeg' ? 'jpg' : $ext;
            }
        }

        return 'jpg';
    }

    private function slugifyForFile(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string) $text, '-');
        return $text === '' ? 'scraping-image' : $text;
    }

    private function parsePriceValue(?string $raw): ?float
    {
        $value = $this->toNullableString($raw);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^\d.,]/', '', $value);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);
        if ($normalized === '') {
            return null;
        }

        return (float) $normalized;
    }

    private function jsonOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (($trimmed[0] ?? '') === '{' || ($trimmed[0] ?? '') === '[') {
                return $trimmed;
            }
            return json_encode($trimmed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!is_array($value)) {
            return null;
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && $json !== '' ? $json : null;
    }

    public function getScrapingStats(): array
    {
        $stats = [
            'total_items' => 0,
            'total_articles' => 0,
            'total_mobiles' => 0,
            'last_received' => null,
        ];

        if (!$this->ensureScrapingTables()) {
            return $stats;
        }

        $articles = $this->mysqli->query('SELECT COUNT(*) AS total, MAX(received_at) AS last_received FROM scraping_articles');
        $mobiles = $this->mysqli->query('SELECT COUNT(*) AS total, MAX(received_at) AS last_received FROM scraping_mobiles');

        if ($articles) {
            $row = $articles->fetch_assoc();
            $stats['total_articles'] = (int) ($row['total'] ?? 0);
            $stats['last_received'] = $row['last_received'] ?? $stats['last_received'];
            $stats['total_items'] += $stats['total_articles'];
        }

        if ($mobiles) {
            $row = $mobiles->fetch_assoc();
            $stats['total_mobiles'] = (int) ($row['total'] ?? 0);
            if (($row['last_received'] ?? null) !== null) {
                $stats['last_received'] = $stats['last_received'] === null
                    ? $row['last_received']
                    : max((string) $stats['last_received'], (string) $row['last_received']);
            }
            $stats['total_items'] += $stats['total_mobiles'];
        }

        return $stats;
    }

    public function getScrapingItems(int $page = 1, int $limit = 50, ?string $filterType = null): array
    {
        if (!$this->ensureScrapingTables()) {
            return ['items' => [], 'total' => 0, 'total_pages' => 1, 'current_page' => max(1, $page), 'limit' => max(1, min($limit, 200))];
        }

        $page = max(1, $page);
        $limit = max(1, min($limit, 200));
        $offset = ($page - 1) * $limit;
        $type = $this->normalizeType($filterType);

        if ($type === 'articles') {
            $totalRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM scraping_articles');
            $total = 0;
            if ($totalRes) {
                $row = $totalRes->fetch_assoc();
                $total = (int) ($row['total'] ?? 0);
            }

            $stmt = $this->mysqli->prepare(
                'SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json
                 FROM scraping_articles
                 ORDER BY COALESCE(scraped_at, received_at) DESC, id DESC
                 LIMIT ? OFFSET ?'
            );
            if (!$stmt) {
                return ['items' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }
            $stmt->bind_param('ii', $limit, $offset);
            $items = $this->fetchScrapingRows($stmt);

            return [
                'items' => $items,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $limit)),
                'current_page' => $page,
                'limit' => $limit,
            ];
        }

        if ($type === 'mobiles') {
            $totalRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM scraping_mobiles');
            $total = 0;
            if ($totalRes) {
                $row = $totalRes->fetch_assoc();
                $total = (int) ($row['total'] ?? 0);
            }

            $stmt = $this->mysqli->prepare(
                'SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json
                 FROM scraping_mobiles
                 ORDER BY COALESCE(scraped_at, received_at) DESC, id DESC
                 LIMIT ? OFFSET ?'
            );
            if (!$stmt) {
                return ['items' => [], 'total' => $total, 'total_pages' => 1, 'current_page' => $page, 'limit' => $limit];
            }
            $stmt->bind_param('ii', $limit, $offset);
            $items = $this->fetchScrapingRows($stmt);

            return [
                'items' => $items,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $limit)),
                'current_page' => $page,
                'limit' => $limit,
            ];
        }

        $articlesCountRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM scraping_articles');
        $mobilesCountRes = $this->mysqli->query('SELECT COUNT(*) AS total FROM scraping_mobiles');
        $total = 0;
        if ($articlesCountRes) {
            $row = $articlesCountRes->fetch_assoc();
            $total += (int) ($row['total'] ?? 0);
        }
        if ($mobilesCountRes) {
            $row = $mobilesCountRes->fetch_assoc();
            $total += (int) ($row['total'] ?? 0);
        }

        $sql = '
            SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json
            FROM (
                SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json, COALESCE(scraped_at, received_at) AS sort_at
                FROM scraping_articles
                UNION ALL
                SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json, COALESCE(scraped_at, received_at) AS sort_at
                FROM scraping_mobiles
            ) AS scraping_items
            ORDER BY sort_at DESC, id DESC
            LIMIT ? OFFSET ?';

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return ['items' => [], 'total' => $total, 'total_pages' => max(1, (int) ceil($total / $limit)), 'current_page' => $page, 'limit' => $limit];
        }
        $stmt->bind_param('ii', $limit, $offset);
        $items = $this->fetchScrapingRows($stmt);

        return [
            'items' => $items,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $limit)),
            'current_page' => $page,
            'limit' => $limit,
        ];
    }

    public function getScrapingItem(string $dataType, int $id): ?array
    {
        if (!$this->ensureScrapingTables()) {
            return null;
        }

        $type = $this->normalizeType($dataType);
        if ($type === null || $id <= 0) {
            return null;
        }

        $table = $type === 'articles' ? 'scraping_articles' : 'scraping_mobiles';
        $stmt = $this->mysqli->prepare(
            'SELECT id, data_type, source, source_key, source_url, title, category, author, brand, model, product_category, price_text, price_value, status_text, image_path, image_url, image_caption, trigger_name, pushed_at, scraped_at, received_at, fetched_at, content_type, fetch_method, published_at, published_text, excerpt, body_text, tags_json, key_specs_json, specs_json, payload_json
             FROM ' . $table . '
             WHERE id = ?
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        return is_array($row) ? $row : null;
    }

    public function getScrapingItemById(int $id): ?array
    {
        if (!$this->ensureScrapingTables() || $id <= 0) {
            return null;
        }

        $item = $this->getScrapingItem('articles', $id);
        if (is_array($item)) {
            return $item;
        }

        return $this->getScrapingItem('mobiles', $id);
    }

    private function normalizeType(?string $dataType): ?string
    {
        $type = strtolower(trim((string) $dataType));
        if ($type === 'article') {
            $type = 'articles';
        }
        if ($type === 'mobile') {
            $type = 'mobiles';
        }
        if ($type === 'articals') {
            $type = 'articles';
        }

        if (!in_array($type, ['articles', 'mobiles'], true)) {
            return null;
        }

        return $type;
    }

    private function buildSelectColumns(): string
    {
        $columns = [
            'id',
            'data_type',
            'item_count',
            'data_content',
            'received_at',
            'ip_address',
            'user_agent',
        ];

        $columns[] = $this->hasColumn('source') ? 'source' : 'NULL AS source';
        $columns[] = $this->hasColumn('trigger_name') ? 'trigger_name' : 'NULL AS trigger_name';
        $columns[] = $this->hasColumn('pushed_at') ? 'pushed_at' : 'NULL AS pushed_at';
        $columns[] = $this->hasColumn('status') ? 'status' : "'received' AS status";

        return implode(', ', $columns);
    }

    private function hasColumn(string $column): bool
    {
        if ($this->columnMap === null) {
            $this->columnMap = [];
            $res = $this->mysqli->query('SHOW COLUMNS FROM push_logs');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $name = (string) ($row['Field'] ?? '');
                    if ($name !== '') {
                        $this->columnMap[$name] = true;
                    }
                }
            }
        }

        return isset($this->columnMap[$column]);
    }

    private function normalizePushedAt(?string $pushedAt): ?string
    {
        $raw = trim((string) $pushedAt);
        if ($raw === '') {
            return null;
        }

        // Fast path for already valid MySQL DATETIME format.
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return $raw;
        }

        // Normalize fractional seconds to max 6 digits for PHP datetime parsing.
        $candidate = preg_replace_callback('/\.(\d+)(?=(Z|[+\-]\d{2}:?\d{2})?$)/', static function (array $m): string {
            return '.' . substr($m[1], 0, 6);
        }, $raw);

        if (!is_string($candidate) || $candidate === '') {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($candidate);
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    private function toNullableString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }
}
