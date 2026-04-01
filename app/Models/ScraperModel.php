<?php

namespace App\Models;

use Exception;

class ScraperModel
{
    private $mysqli;

    public function __construct($mysqli = null)
    {
        global $mysqli;
        $this->mysqli = $mysqli ?: $mysqli;
    }

    public function getMysqli()
    {
        return $this->mysqli;
    }

    // Sources methods
    public function getAllSources()
    {
        $sql = "SELECT * FROM web_scraping_sources ORDER BY created_at DESC";
        $result = $this->mysqli->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSourceById($id)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_sources WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getSourceByUrl(string $url)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_sources WHERE url = ? LIMIT 1");
        $stmt->bind_param("s", $url);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createSource($data)
    {
        $sql = "INSERT INTO web_scraping_sources
                (name, url, type, category_id, selectors, advance_config, presets, fetch_interval, content_type, scrape_depth, use_browser, max_pages, delay, pagination_type, pagination_selector, pagination_pattern, proxy_enabled, proxy_provider, proxy_config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->mysqli->prepare($sql);
        $selectors = isset($data['selectors']) ? json_encode($data['selectors']) : null;
        $advance_config = isset($data['advance_config']) ? json_encode($data['advance_config']) : null;
        $presets = isset($data['presets']) ? json_encode($data['presets']) : null;

        $stmt->bind_param(
            "ssissssisiiissssiss",
            $data['name'],
            $data['url'],
            $data['type'],
            $data['category_id'],
            $selectors,
            $advance_config,
            $presets,
            $data['fetch_interval'],
            $data['content_type'],
            $data['scrape_depth'],
            $data['use_browser'],
            $data['max_pages'],
            $data['delay'],
            $data['pagination_type'],
            $data['pagination_selector'],
            $data['pagination_pattern'],
            $data['proxy_enabled'],
            $data['proxy_provider'],
            $data['proxy_config']
        );

        if ($stmt->execute()) {
            return $this->mysqli->insert_id;
        }

        return false;
    }

    // Articles methods
    public function saveArticle($data)
    {
        $sql = "INSERT INTO web_scraping_articles
                (source_id, url, title, content, excerpt, author, image_url, published_at, status, content_hash, categories_json, tags_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                title = VALUES(title), content = VALUES(content), excerpt = VALUES(excerpt),
                author = VALUES(author), image_url = VALUES(image_url), published_at = VALUES(published_at),
                status = VALUES(status), updated_at = NOW()";

        $stmt = $this->mysqli->prepare($sql);
        $categories_json = isset($data['categories']) ? json_encode($data['categories']) : null;
        $tags_json = isset($data['tags']) ? json_encode($data['tags']) : null;

        $stmt->bind_param(
            "isssssssssss",
            $data['source_id'],
            $data['url'],
            $data['title'],
            $data['content'],
            $data['excerpt'],
            $data['author'],
            $data['image_url'],
            $data['published_at'],
            $data['status'],
            $data['content_hash'],
            $categories_json,
            $tags_json
        );

        return $stmt->execute();
    }

    // Queue methods
    public function addToQueue($data)
    {
        $sql = "INSERT INTO web_scraping_queue
                (source_id, queue_type, url, url_hash, priority, depth)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE priority = GREATEST(priority, VALUES(priority))";

        $stmt = $this->mysqli->prepare($sql);
        $url_hash = hash('sha256', $data['url']);

        $stmt->bind_param(
            "isssii",
            $data['source_id'],
            $data['queue_type'],
            $data['url'],
            $url_hash,
            $data['priority'] ?? 5,
            $data['depth'] ?? 0
        );

        return $stmt->execute();
    }

    public function getPendingQueueItems($queue_type = null, $limit = 10)
    {
        $sql = "SELECT * FROM web_scraping_queue WHERE status = 'pending'";
        $params = [];
        $types = "";

        if ($queue_type) {
            $sql .= " AND queue_type = ?";
            $params[] = $queue_type;
            $types .= "s";
        }

        $sql .= " ORDER BY priority DESC, created_at ASC LIMIT ?";
        $params[] = $limit;
        $types .= "i";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Jobs methods
    public function createJob($data)
    {
        $sql = "INSERT INTO web_scraping_jobs (source_id, job_type, priority) VALUES (?, ?, ?)";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("isi", $data['source_id'], $data['job_type'], $data['priority'] ?? 5);
        $stmt->execute();
        return $this->mysqli->insert_id;
    }

    public function updateJobStatus($job_id, $status, $stats = [])
    {
        $fields = ["status = ?"];
        $types = "s";
        $params = [$status];

        if ($status === 'running') {
            $fields[] = "started_at = NOW()";
            $fields[] = "completed_at = NULL";
        } elseif ($status === 'pending') {
            $fields[] = "started_at = NULL";
            $fields[] = "completed_at = NULL";
        } elseif (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $fields[] = "completed_at = NOW()";
        }

        $statColumns = [
            'items_found' => 'i',
            'items_saved' => 'i',
            'items_failed' => 'i',
            'avg_response_time' => 'd',
            'total_response_time' => 'd',
        ];

        foreach ($statColumns as $key => $type) {
            if (array_key_exists($key, $stats)) {
                $fields[] = "{$key} = ?";
                $params[] = $stats[$key];
                $types .= $type;
            }
        }

        if (array_key_exists('error_message', $stats)) {
            $fields[] = "error_message = ?";
            $params[] = $stats['error_message'];
            $types .= "s";
        }

        if (array_key_exists('result_data', $stats)) {
            $fields[] = "result_data = ?";
            $params[] = $stats['result_data'] !== null ? json_encode($stats['result_data']) : null;
            $types .= "s";
        }

        $sql = "UPDATE web_scraping_jobs SET " . implode(", ", $fields) . " WHERE id = ?";
        $params[] = $job_id;
        $types .= "i";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    public function updateJobResult(int $jobId, string $status, array $payload = []): bool
    {
        $stats = [
            'items_found' => $payload['items_found'] ?? 0,
            'items_saved' => $payload['items_saved'] ?? 0,
            'items_failed' => $payload['items_failed'] ?? 0,
            'avg_response_time' => $payload['avg_response_time'] ?? null,
            'total_response_time' => $payload['total_response_time'] ?? null,
            'error_message' => $payload['error_message'] ?? null,
            'result_data' => $payload['result_data'] ?? null
        ];

        return $this->updateJobStatus($jobId, $status, $stats);
    }

    public function fetchNextPendingJob(): ?array
    {
        try {
            $this->mysqli->begin_transaction();

            $sql = "SELECT * FROM web_scraping_jobs
                    WHERE status = 'pending'
                    ORDER BY priority DESC, created_at ASC
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->mysqli->prepare($sql);
            $stmt->execute();
            $job = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$job) {
                $this->mysqli->commit();
                return null;
            }

            $updateStmt = $this->mysqli->prepare("UPDATE web_scraping_jobs SET status = 'running', started_at = NOW(), completed_at = NULL WHERE id = ? AND status = 'pending'");
            $updateStmt->bind_param("i", $job['id']);
            $updateStmt->execute();
            $affected = $updateStmt->affected_rows;
            $updateStmt->close();

            if ($affected === 0) {
                $this->mysqli->rollback();
                return null;
            }

            $this->mysqli->commit();
            $job['status'] = 'running';
            $job['started_at'] = date('Y-m-d H:i:s');
            return $job;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return null;
        }
    }

    // Logs methods
    public function saveLog($data)
    {
        $sql = "INSERT INTO web_scraping_logs (source_id, job_id, log_type, level, message, details) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($sql);
        $details = isset($data['details']) ? json_encode($data['details']) : null;
        $jobId = $data['job_id'] ?? null;
        $logType = $data['log_type'] ?? 'scrape_attempt';
        $level = $data['level'] ?? 'info';
        $stmt->bind_param(
            "isssss",
            $data['source_id'],
            $jobId,
            $logType,
            $level,
            $data['message'],
            $details
        );
        return $stmt->execute();
    }

    // Stats methods
    public function saveStats($data)
    {
        $sql = "INSERT INTO web_scraping_stats (source_id, stat_type, stat_date, metrics)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE metrics = VALUES(metrics), updated_at = NOW()";

        $stmt = $this->mysqli->prepare($sql);
        $metrics = json_encode($data['metrics']);
        $stmt->bind_param("isss", $data['source_id'], $data['stat_type'], $data['stat_date'], $metrics);
        return $stmt->execute();
    }

    // Seen URLs methods
    public function isUrlSeen($url, $source_id = null)
    {
        $sql = "SELECT id FROM web_scraping_seen_urls WHERE url_hash = ?";
        $params = [hash('sha256', $url)];
        $types = "s";

        if ($source_id) {
            $sql .= " AND source_id = ?";
            $params[] = $source_id;
            $types .= "i";
        }

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }

    public function markUrlSeen($url, $source_id = null)
    {
        $url_hash = hash('sha256', $url);
        $sql = "INSERT INTO web_scraping_seen_urls (url_hash, canonical_url, source_id)
                VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen = NOW()";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssi", $url_hash, $url, $source_id);
        return $stmt->execute();
    }

    // Settings methods
    public function getSetting($key)
    {
        $stmt = $this->mysqli->prepare("SELECT setting_value FROM web_scraping_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? $result['setting_value'] : null;
    }

    public function setSetting($key, $value)
    {
        $sql = "INSERT INTO web_scraping_settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ss", $key, $value);
        return $stmt->execute();
    }

    public function getSettingsCount()
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM web_scraping_settings");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['total'] ?? 0);
    }

    public function getSettingsPaginated($offset, $limit)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_settings ORDER BY setting_key ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Mobiles methods
    public function saveMobile($data)
    {
        $sql = "INSERT INTO web_scraping_mobiles
                (source_id, source_url, title, price, brand, model, image_url, specifications, release_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                price = VALUES(price), specifications = VALUES(specifications), status = VALUES(status), updated_at = NOW()";

        $stmt = $this->mysqli->prepare($sql);
        $specs = isset($data['specifications']) ? json_encode($data['specifications']) : null;

        $stmt->bind_param(
            "isssssssss",
            $data['source_id'],
            $data['source_url'],
            $data['title'],
            $data['price'],
            $data['brand'],
            $data['model'],
            $data['image_url'],
            $specs,
            $data['release_date'],
            $data['status']
        );

        return $stmt->execute();
    }

    public function getArticles($page = 1, $limit = 20, $status = null, $sourceFilter = null, $search = null, $contentType = null, $categoryFilter = null)
    {
        $offset = ($page - 1) * $limit;

        // First, get total count for pagination
        $countSql = "SELECT COUNT(*) as total FROM web_scraping_articles a
                     LEFT JOIN web_scraping_sources s ON a.source_id = s.id WHERE 1=1";
        $countParams = [];
        $countTypes = "";

        if ($status) {
            $countSql .= " AND a.status = ?";
            $countParams[] = $status;
            $countTypes .= "s";
        }

        if ($sourceFilter) {
            $countSql .= " AND a.source_id = ?";
            $countParams[] = $sourceFilter;
            $countTypes .= "i";
        }

        if ($search) {
            $countSql .= " AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
            $searchTerm = "%$search%";
            $countParams = array_merge($countParams, [$searchTerm, $searchTerm, $searchTerm]);
            $countTypes .= "sss";
        }

        if ($contentType) {
            $countSql .= " AND a.content_type = ?";
            $countParams[] = $contentType;
            $countTypes .= "s";
        }

        if ($categoryFilter) {
            $countSql .= " AND s.category_id = ?";
            $countParams[] = $categoryFilter;
            $countTypes .= "i";
        }

        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();
        $total = $totalResult['total'];
        $totalPages = ceil($total / $limit);

        // Now get the actual articles with pagination
        $sql = "SELECT a.*, s.name as source_name FROM web_scraping_articles a
                LEFT JOIN web_scraping_sources s ON a.source_id = s.id WHERE 1=1";

        $params = [];
        $types = "";

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($sourceFilter) {
            $sql .= " AND a.source_id = ?";
            $params[] = $sourceFilter;
            $types .= "i";
        }

        if ($search) {
            $sql .= " AND (a.title LIKE ? OR a.content LIKE ? OR a.excerpt LIKE ?)";
            $searchTerm = "%$search%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
            $types .= "sss";
        }

        if ($contentType) {
            $sql .= " AND a.content_type = ?";
            $params[] = $contentType;
            $types .= "s";
        }

        if ($categoryFilter) {
            $sql .= " AND s.category_id = ?";
            $params[] = $categoryFilter;
            $types .= "i";
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'articles' => $articles,
            'pagination' => [
                'total' => (int)$total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => (int)$totalPages,
                'current_page' => (int)$page,
                'per_page' => (int)$limit,
                'total_pages' => (int)$totalPages
            ]
        ];
    }

    public function getArticleById($id)
    {
        $stmt = $this->mysqli->prepare("
            SELECT a.*, s.name as source_name
            FROM web_scraping_articles a
            LEFT JOIN web_scraping_sources s ON a.source_id = s.id
            WHERE a.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function deleteArticle($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getCategories()
    {
        $sql = "SELECT * FROM web_scraping_categories ORDER BY name ASC";
        $result = $this->mysqli->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getCategoryById($id)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getCategoryTableData(): array
    {
        $sql = "
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description,
                c.is_active,
                c.`order`,
                COUNT(DISTINCT s.id) as sources_count,
                COUNT(a.id) as articles_count
            FROM web_scraping_categories c
            LEFT JOIN web_scraping_sources s ON s.category_id = c.id
            LEFT JOIN web_scraping_articles a ON a.source_id = s.id
            GROUP BY c.id
            ORDER BY c.`order` ASC, c.name ASC
        ";

        $result = $this->mysqli->query($sql);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'is_active' => (bool)$row['is_active'],
                'order' => (int)$row['order'],
                'sources_count' => (int)$row['sources_count'],
                'articles_count' => (int)$row['articles_count'],
            ];
        }

        return $rows;
    }

    public function getArticleByUrl(string $url)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_articles WHERE url = ? LIMIT 1");
        $stmt->bind_param("s", $url);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function getMobileByUrl(string $url)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_mobiles WHERE source_url = ? LIMIT 1");
        $stmt->bind_param("s", $url);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function createCategory($data)
    {
        $stmt = $this->mysqli->prepare("INSERT INTO web_scraping_categories (name, description, parent_id, is_active, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssii", $data['name'], $data['description'], $data['parent_id'] ?? null, $data['is_active'] ?? 1);
        $result = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();
        return $result ? (int)$insertId : 0;
    }

    public function updateCategory($id, $data)
    {
        $stmt = $this->mysqli->prepare("UPDATE web_scraping_categories SET name = ?, description = ?, parent_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssiii", $data['name'], $data['description'], $data['parent_id'] ?? null, $data['is_active'] ?? 1, $id);
        return $stmt->execute();
    }

    public function deleteCategory($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getJobs($page = 1, $limit = 50, $status = null, $sourceId = null)
    {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT j.*, s.name as source_name
                FROM web_scraping_jobs j
                LEFT JOIN web_scraping_sources s ON j.source_id = s.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($status) {
            $sql .= " AND j.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        if ($sourceId) {
            $sql .= " AND j.source_id = ?";
            $params[] = $sourceId;
            $types .= "i";
        }

        $sql .= " ORDER BY j.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM web_scraping_jobs j WHERE 1=1";
        $countParams = [];
        $countTypes = "";

        if ($status) {
            $countSql .= " AND j.status = ?";
            $countParams[] = $status;
            $countTypes .= "s";
        }

        if ($sourceId) {
            $countSql .= " AND j.source_id = ?";
            $countParams[] = $sourceId;
            $countTypes .= "i";
        }

        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];

        return [
            'jobs' => $jobs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    public function getJobById($id)
    {
        $stmt = $this->mysqli->prepare("
            SELECT j.*, s.name as source_name
            FROM web_scraping_jobs j
            LEFT JOIN web_scraping_sources s ON j.source_id = s.id
            WHERE j.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getMobiles($page = 1, $limit = 20, $sourceId = null, $search = null)
    {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT m.*, s.name as source_name
                FROM web_scraping_mobiles m
                LEFT JOIN web_scraping_sources s ON m.source_id = s.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($sourceId) {
            $sql .= " AND m.source_id = ?";
            $params[] = $sourceId;
            $types .= "i";
        }

        if ($search) {
            $sql .= " AND (m.name LIKE ? OR m.brand LIKE ? OR m.model LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $mobiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM web_scraping_mobiles m WHERE 1=1";
        $countParams = [];
        $countTypes = "";

        if ($sourceId) {
            $countSql .= " AND m.source_id = ?";
            $countParams[] = $sourceId;
            $countTypes .= "i";
        }

        if ($search) {
            $countSql .= " AND (m.name LIKE ? OR m.brand LIKE ? OR m.model LIKE ?)";
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
            $countTypes .= "sss";
        }

        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];

        return [
            'mobiles' => $mobiles,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    public function getMobileById($id)
    {
        $stmt = $this->mysqli->prepare("
            SELECT m.*, s.name as source_name
            FROM web_scraping_mobiles m
            LEFT JOIN web_scraping_sources s ON m.source_id = s.id
            WHERE m.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function deleteMobile($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_mobiles WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getSeenUrls($page = 1, $limit = 50, $sourceId = null, $search = null)
    {
        $offset = ($page - 1) * $limit;

        $sql = "SELECT u.*, s.name as source_name
                FROM web_scraping_seen_urls u
                LEFT JOIN web_scraping_sources s ON u.source_id = s.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($sourceId) {
            $sql .= " AND u.source_id = ?";
            $params[] = $sourceId;
            $types .= "i";
        }

        if ($search) {
            $sql .= " AND u.canonical_url LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }

        $sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $urls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM web_scraping_seen_urls u WHERE 1=1";
        $countParams = [];
        $countTypes = "";

        if ($sourceId) {
            $countSql .= " AND u.source_id = ?";
            $countParams[] = $sourceId;
            $countTypes .= "i";
        }

        if ($search) {
            $countSql .= " AND u.canonical_url LIKE ?";
            $countParams[] = "%$search%";
            $countTypes .= "s";
        }

        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];

        return [
            'urls' => $urls,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ];
    }

    public function deleteSeenUrl($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_seen_urls WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getAllSettings($page = 1, $perPage = 20)
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM web_scraping_settings";
        $countResult = $this->mysqli->query($countSql);
        $total = $countResult ? (int)$countResult->fetch_assoc()['total'] : 0;

        // Get paginated results
        $sql = "SELECT * FROM web_scraping_settings ORDER BY setting_key ASC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $perPage, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $settings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return [
            'settings' => $settings,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    public function updateSource($id, $data)
    {
        $sql = "UPDATE web_scraping_sources SET
                name = ?, url = ?, type = ?, category_id = ?, selectors = ?, advance_config = ?,
                presets = ?, fetch_interval = ?, content_type = ?, scrape_depth = ?,
                use_browser = ?, max_pages = ?, delay = ?, pagination_type = ?,
                pagination_selector = ?, pagination_pattern = ?, proxy_enabled = ?,
                proxy_provider = ?, proxy_config = ?, is_active = ?
                WHERE id = ?";

        $stmt = $this->mysqli->prepare($sql);
        $selectors = isset($data['selectors']) ? json_encode($data['selectors']) : null;
        $advance_config = isset($data['advance_config']) ? json_encode($data['advance_config']) : null;
        $presets = isset($data['presets']) ? json_encode($data['presets']) : null;

        $stmt->bind_param(
            "sssissssisiiisssisssi",
            $data['name'],
            $data['url'],
            $data['type'],
            $data['category_id'],
            $selectors,
            $advance_config,
            $presets,
            $data['fetch_interval'],
            $data['content_type'],
            $data['scrape_depth'],
            $data['use_browser'],
            $data['max_pages'],
            $data['delay'],
            $data['pagination_type'],
            $data['pagination_selector'],
            $data['pagination_pattern'],
            $data['proxy_enabled'],
            $data['proxy_provider'],
            $data['proxy_config'],
            $data['is_active'],
            $id
        );

        return $stmt->execute();
    }

    public function deleteSource($id)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_sources WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function toggleSourceStatus($id, $isActive)
    {
        $stmt = $this->mysqli->prepare("UPDATE web_scraping_sources SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $isActive, $id);
        return $stmt->execute();
    }

    public function toggleAllSources($isActive)
    {
        $stmt = $this->mysqli->prepare("UPDATE web_scraping_sources SET is_active = ?");
        $stmt->bind_param("i", $isActive);
        return $stmt->execute();
    }

    public function getActiveSources()
    {
        $sql = "SELECT * FROM web_scraping_sources WHERE is_active = 1 ORDER BY name ASC";
        $result = $this->mysqli->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getPendingJobs($limit = 50)
    {
        $sql = "SELECT j.*, s.name as source_name
                FROM web_scraping_jobs j
                LEFT JOIN web_scraping_sources s ON j.source_id = s.id
                WHERE j.status IN ('pending', 'running')
                ORDER BY j.created_at DESC LIMIT ?";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getOverallStats()
    {
        $stats = [];

        // Sources count
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM web_scraping_sources");
        $stats['total_sources'] = $result->fetch_assoc()['count'];

        // Active sources
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM web_scraping_sources WHERE is_active = 1");
        $stats['active_sources'] = $result->fetch_assoc()['count'];

        // Articles count
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM web_scraping_articles");
        $stats['total_articles'] = $result->fetch_assoc()['count'];

        // Queue stats
        $result = $this->mysqli->query("SELECT status, COUNT(*) as count FROM web_scraping_queue GROUP BY status");
        $queueStats = [];
        while ($row = $result->fetch_assoc()) {
            $queueStats[$row['status']] = $row['count'];
        }
        $stats['queue'] = $queueStats;

        // Jobs stats
        $result = $this->mysqli->query("SELECT status, COUNT(*) as count FROM web_scraping_jobs GROUP BY status");
        $jobStats = [];
        while ($row = $result->fetch_assoc()) {
            $jobStats[$row['status']] = $row['count'];
        }
        $stats['jobs'] = $jobStats;

        return $stats;
    }

    public function getCollectedDataSummary(): array
    {
        $statusCounts = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        $result = $this->mysqli->query("SELECT status, COUNT(*) as count FROM web_scraping_articles GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = strtolower($row['status']);
                if (isset($statusCounts[$key])) {
                    $statusCounts[$key] = (int)$row['count'];
                }
            }
            $result->free();
        }

        $totalArticlesResult = $this->mysqli->query("SELECT COUNT(*) as total FROM web_scraping_articles");
        $totalArticles = $totalArticlesResult ? (int)$totalArticlesResult->fetch_assoc()['total'] : 0;

        $contentTypes = [];
        $result = $this->mysqli->query("SELECT source_content_type, COUNT(*) as count FROM web_scraping_articles GROUP BY source_content_type");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $type = $row['source_content_type'] ?: 'unknown';
                $contentTypes[$type] = (int)$row['count'];
            }
            $result->free();
        }

        $publishedResult = $this->mysqli->query("SELECT COUNT(*) as count FROM web_scraping_articles WHERE status = 'completed' AND published_at IS NOT NULL");
        $publishedCount = $publishedResult ? (int)$publishedResult->fetch_assoc()['count'] : 0;

        $lastPublishedResult = $this->mysqli->query("SELECT MAX(published_at) as last_published FROM web_scraping_articles WHERE published_at IS NOT NULL");
        $lastPublished = $lastPublishedResult ? $lastPublishedResult->fetch_assoc()['last_published'] : null;

        $categories = [];
        $sql = "
            SELECT c.id, c.name, c.slug, c.is_active, c.`order`, COUNT(a.id) as articles_count
            FROM web_scraping_categories c
            LEFT JOIN web_scraping_sources s ON s.category_id = c.id
            LEFT JOIN web_scraping_articles a ON a.source_id = s.id
            GROUP BY c.id
            ORDER BY c.`order` ASC, c.name ASC
        ";
        $result = $this->mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'slug' => $row['slug'] ?? '',
                    'is_active' => (bool)$row['is_active'],
                    'articles_count' => (int)$row['articles_count']
                ];
            }
            $result->free();
        }

        return [
            'total' => $totalArticles,
            'statuses' => array_merge(['all' => $totalArticles], $statusCounts),
            'content_types' => $contentTypes,
            'published' => $publishedCount,
            'last_published_at' => $lastPublished,
            'categories' => $categories
        ];
    }

    public function getLogSummary(array $filters = []): array
    {
        $where = '1=1';
        $params = [];
        $types = '';

        if (!empty($filters['source_id'])) {
            $where .= ' AND source_id = ?';
            $params[] = $filters['source_id'];
            $types .= 'i';
        }

        if (!empty($filters['level'])) {
            $where .= ' AND level = ?';
            $params[] = $filters['level'];
            $types .= 's';
        }

        $levels = [
            'error' => 0,
            'warning' => 0,
            'info' => 0,
            'debug' => 0
        ];

        $stmt = $this->mysqli->prepare("SELECT level, COUNT(*) as count FROM web_scraping_logs WHERE $where GROUP BY level");
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $lvl = strtolower($row['level']);
            if (array_key_exists($lvl, $levels)) {
                $levels[$lvl] = (int)$row['count'];
            }
        }
        $stmt->close();

        $latestStmt = $this->mysqli->prepare("SELECT MAX(created_at) as latest FROM web_scraping_logs WHERE $where");
        if ($params) {
            $latestStmt->bind_param($types, ...$params);
        }
        $latestStmt->execute();
        $latestResult = $latestStmt->get_result()->fetch_assoc();
        $latestStmt->close();

        $total = array_sum($levels);

        return [
            'levels' => $levels,
            'total' => $total,
            'latest_timestamp' => $latestResult['latest'] ?? null
        ];
    }

    public function getSettingsForApi(int $limit = 100): array
    {
        $limit = max(5, min(200, $limit));
        $total = $this->getSettingsCount();

        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_settings ORDER BY updated_at DESC LIMIT ?");
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return [
            'settings' => $settings,
            'total' => (int)$total,
            'latest_updated_at' => $settings[0]['updated_at'] ?? null
        ];
    }

    public function getLogs($filters = [], $page = 1, $limit = 100)
    {
        // Calculate offset
        $offset = ($page - 1) * $limit;

        // First, get total count
        $countSql = "SELECT COUNT(*) as total
                     FROM web_scraping_logs l
                     LEFT JOIN web_scraping_sources s ON l.source_id = s.id
                     LEFT JOIN web_scraping_jobs j ON l.job_id = j.id
                     WHERE 1=1";

        $countParams = [];
        $countTypes = "";

        if (isset($filters['source_id']) && $filters['source_id']) {
            $countSql .= " AND l.source_id = ?";
            $countParams[] = $filters['source_id'];
            $countTypes .= "i";
        }

        if (isset($filters['level']) && $filters['level']) {
            $countSql .= " AND l.level = ?";
            $countParams[] = $filters['level'];
            $countTypes .= "s";
        }

        if (isset($filters['type']) && $filters['type']) {
            $countSql .= " AND l.log_type = ?";
            $countParams[] = $filters['type'];
            $countTypes .= "s";
        }

        $countStmt = $this->mysqli->prepare($countSql);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();
        $total = $totalResult['total'];

        // Calculate total pages
        $pages = ceil($total / $limit);

        // Now get the actual logs with pagination
        $sql = "SELECT l.*, s.name as source_name, j.job_type
                FROM web_scraping_logs l
                LEFT JOIN web_scraping_sources s ON l.source_id = s.id
                LEFT JOIN web_scraping_jobs j ON l.job_id = j.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if (isset($filters['source_id']) && $filters['source_id']) {
            $sql .= " AND l.source_id = ?";
            $params[] = $filters['source_id'];
            $types .= "i";
        }

        if (isset($filters['level']) && $filters['level']) {
            $sql .= " AND l.level = ?";
            $params[] = $filters['level'];
            $types .= "s";
        }

        if (isset($filters['type']) && $filters['type']) {
            $sql .= " AND l.log_type = ?";
            $params[] = $filters['type'];
            $types .= "s";
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return [
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages
        ];
    }

    public function deleteOldLogs($days)
    {
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        return $stmt->affected_rows;
    }

    public function getJobLogs($jobId)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_logs WHERE job_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $jobId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getLogById($id)
    {
        $stmt = $this->mysqli->prepare("
            SELECT l.*, s.name as source_name, j.job_type
            FROM web_scraping_logs l
            LEFT JOIN web_scraping_sources s ON l.source_id = s.id
            LEFT JOIN web_scraping_jobs j ON l.job_id = j.id
            WHERE l.id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getSourcesByType($type)
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM web_scraping_sources WHERE type = ? ORDER BY name ASC");
        $stmt->bind_param("s", $type);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getStats($sourceId = null, $days = 30)
    {
        $sql = "SELECT s.source_id, src.name as source_name, s.stat_type, s.stat_date, s.metrics
                FROM web_scraping_stats s
                LEFT JOIN web_scraping_sources src ON s.source_id = src.id
                WHERE s.stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";

        $params = [$days];
        $types = "i";

        if ($sourceId) {
            $sql .= " AND s.source_id = ?";
            $params[] = $sourceId;
            $types .= "i";
        }

        $sql .= " ORDER BY s.stat_date DESC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $stats = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['metrics'] = json_decode($row['metrics'], true);
            $stats[] = $row;
        }

        return $stats;
    }

    public function getScrapeHistory($sourceId, $limit = 50)
    {
        $stmt = $this->mysqli->prepare("
            SELECT l.*, j.job_type, j.status as job_status
            FROM web_scraping_logs l
            LEFT JOIN web_scraping_jobs j ON l.job_id = j.id
            WHERE l.source_id = ? AND l.log_type = 'scrape_attempt'
            ORDER BY l.created_at DESC LIMIT ?
        ");
        $stmt->bind_param("ii", $sourceId, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createSetting($key, $value)
    {
        // Since this table uses setting_key as primary key, we use INSERT ... ON DUPLICATE KEY UPDATE
        $stmt = $this->mysqli->prepare("
            INSERT INTO web_scraping_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
        ");
        $stmt->bind_param("ss", $key, $value);
        return $stmt->execute();
    }

    public function updateSetting($key, $value)
    {
        // Since this table uses setting_key as primary key, we update by key
        $stmt = $this->mysqli->prepare("
            UPDATE web_scraping_settings
            SET setting_value = ?, updated_at = NOW()
            WHERE setting_key = ?
        ");
        $stmt->bind_param("ss", $value, $key);
        return $stmt->execute();
    }

    public function deleteSetting($key)
    {
        // Since this table uses setting_key as primary key, we delete by key directly
        $stmt = $this->mysqli->prepare("DELETE FROM web_scraping_settings WHERE setting_key = ?");
        $stmt->bind_param("s", $key);
        return $stmt->execute() && $stmt->affected_rows > 0;
    }

    public function createPreset(array $payload): bool
    {
        $sql = "INSERT INTO web_scraping_presets (`key`, `name`, `description`, `content_type`, `selectors`, `advance_config`, `is_default`, `is_active`)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $selectors = $payload['selectors'] ?? null;
        $advance = $payload['advance_config'] ?? null;
        $isDefault = !empty($payload['is_default']) ? 1 : 0;

        $stmt->bind_param(
            "ssssssi",
            $payload['key'],
            $payload['name'],
            $payload['description'],
            $payload['content_type'],
            $selectors,
            $advance,
            $isDefault
        );

        $result = $stmt->execute();
        $stmt->close();

        return (bool)$result;
    }
}
