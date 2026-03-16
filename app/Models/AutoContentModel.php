<?php

/**
 * AutoContentModel.php
 * Model for AI Auto Content - handles scraping sources and scraped articles
 */

declare(strict_types=1);

class AutoContentModel
{
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // ================== SOURCE MANAGEMENT ==================

    /**
     * Get all sources
     */
    public function getAllSources(): array
    {
        $sql = "SELECT s.*, c.name as category_name 
                FROM autocontent_sources s 
                LEFT JOIN categories c ON s.category_id = c.id 
                ORDER BY s.id DESC";
        $result = $this->mysqli->query($sql);

        $sources = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sources[] = $row;
            }
            $result->free();
        }
        return $sources;
    }

    /**
     * Get source by ID
     */
    public function getSourceById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM autocontent_sources WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $source = $result->fetch_assoc();
            $stmt->close();
            return $source;
        }
        $stmt->close();
        return null;
    }

    /**
     * Create a new source
     */
    public function createSource(array $data): int
    {
        // Ensure tables and columns exist
        $this->ensureTablesExist();

        // Check which columns exist in the database
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources");
        $existingColumns = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
            $result->free();
        }

        // Build INSERT query based on existing columns
        $columns = [];
        $placeholders = [];
        $values = [];
        $types = '';

        // Always add name, url, type (these always exist)
        $columns[] = 'name';
        $placeholders[] = '?';
        $values[] = sanitize_input($data['name'] ?? '');
        $types .= 's';

        $columns[] = 'url';
        $placeholders[] = '?';
        $values[] = sanitize_input($data['url'] ?? '');
        $types .= 's';

        $columns[] = 'type';
        $placeholders[] = '?';
        $values[] = sanitize_input($data['type'] ?? 'rss');
        $types .= 's';

        $columns[] = 'category_id';
        $placeholders[] = '?';
        $catId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $values[] = $catId;
        $types .= 'i';

        // Add website preset key if column exists
        if (in_array('website_preset_key', $existingColumns)) {
            $columns[] = 'website_preset_key';
            $placeholders[] = '?';
            $values[] = sanitize_input($data['website_preset_key'] ?? '');
            $types .= 's';
        }

        $columns[] = 'fetch_interval';
        $placeholders[] = '?';
        $fetchInt = isset($data['fetch_interval']) ? (int)$data['fetch_interval'] : 3600;
        $values[] = $fetchInt;
        $types .= 'i';

        $columns[] = 'is_active';
        $placeholders[] = '?';
        $isActive = isset($data['is_active']) ? 1 : 0;
        $values[] = $isActive;
        $types .= 'i';

        // created_at is a literal NOW(), not a placeholder
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';

        // Add content_type if column exists
        if (in_array('content_type', $existingColumns)) {
            $columns[] = 'content_type';
            $placeholders[] = '?';
            $values[] = sanitize_input($data['content_type'] ?? 'articles');
            $types .= 's';
        }

        // Add scrape_depth if column exists
        if (in_array('scrape_depth', $existingColumns)) {
            $columns[] = 'scrape_depth';
            $placeholders[] = '?';
            $values[] = isset($data['scrape_depth']) ? (int)$data['scrape_depth'] : 1;
            $types .= 'i';
        }

        // Add use_browser if column exists
        if (in_array('use_browser', $existingColumns)) {
            $columns[] = 'use_browser';
            $placeholders[] = '?';
            $values[] = isset($data['use_browser']) ? 1 : 0;
            $types .= 'i';
        }

        // Add max_pages if column exists
        if (in_array('max_pages', $existingColumns)) {
            $columns[] = 'max_pages';
            $placeholders[] = '?';
            $values[] = isset($data['max_pages']) ? (int)$data['max_pages'] : 50;
            $types .= 'i';
        }

        // Add delay if column exists
        if (in_array('delay', $existingColumns)) {
            $columns[] = 'delay';
            $placeholders[] = '?';
            $values[] = isset($data['delay']) ? (int)$data['delay'] : 2;
            $types .= 'i';
        }

        // Add selector columns if they exist
        $selectorColumns = [
            'selector_list_container',
            'selector_list_item',
            'selector_list_title',
            'selector_list_link',
            'selector_list_date',
            'selector_list_image',
            'selector_title',
            'selector_content',
            'selector_image',
            'selector_excerpt',
            'selector_date',
            'selector_author',
            'selector_pagination',
            'selector_read_more',
            'selector_category',
            'selector_tags',
            'selector_video',
            'selector_audio',
            'selector_source_url'
        ];

        foreach ($selectorColumns as $col) {
            if (in_array($col, $existingColumns)) {
                $columns[] = $col;
                $placeholders[] = '?';
                $values[] = sanitize_input($data[$col] ?? '');
                $types .= 's';
            }
        }

        $sql = "INSERT INTO autocontent_sources (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        return (int)$insertId;
    }

    /**
     * Update a source
     */
    public function updateSource(int $id, array $data): bool
    {
        // Ensure tables exist first (this adds columns if missing)
        $this->ensureTablesExist();

        // Check which columns exist in the database
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources");
        $existingColumns = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $existingColumns[] = $row['Field'];
            }
            $result->free();
        }

        // Build UPDATE query based on existing columns
        $setParts = [];
        $values = [];
        $types = '';

        // Basic fields
        $setParts[] = 'name = ?';
        $values[] = sanitize_input($data['name'] ?? '');
        $types .= 's';

        $setParts[] = 'url = ?';
        $values[] = sanitize_input($data['url'] ?? '');
        $types .= 's';

        $setParts[] = 'type = ?';
        $values[] = sanitize_input($data['type'] ?? 'rss');
        $types .= 's';

        $setParts[] = 'category_id = ?';
        $catId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $values[] = $catId;
        $types .= 'i';

        // Add website preset key if column exists
        if (in_array('website_preset_key', $existingColumns)) {
            $setParts[] = 'website_preset_key = ?';
            $values[] = sanitize_input($data['website_preset_key'] ?? '');
            $types .= 's';
        }

        $setParts[] = 'fetch_interval = ?';
        $fetchInt = isset($data['fetch_interval']) ? (int)$data['fetch_interval'] : 3600;
        $values[] = $fetchInt;
        $types .= 'i';

        $setParts[] = 'is_active = ?';
        $isActive = isset($data['is_active']) ? 1 : 0;
        $values[] = $isActive;
        $types .= 'i';

        // Add content_type if column exists
        if (in_array('content_type', $existingColumns)) {
            $setParts[] = 'content_type = ?';
            $values[] = sanitize_input($data['content_type'] ?? 'articles');
            $types .= 's';
        }

        // Add scrape_depth if column exists
        if (in_array('scrape_depth', $existingColumns)) {
            $setParts[] = 'scrape_depth = ?';
            $values[] = isset($data['scrape_depth']) ? (int)$data['scrape_depth'] : 1;
            $types .= 'i';
        }

        // Add use_browser if column exists
        if (in_array('use_browser', $existingColumns)) {
            $setParts[] = 'use_browser = ?';
            $values[] = isset($data['use_browser']) ? 1 : 0;
            $types .= 'i';
        }

        // Add max_pages if column exists
        if (in_array('max_pages', $existingColumns)) {
            $setParts[] = 'max_pages = ?';
            $values[] = isset($data['max_pages']) ? (int)$data['max_pages'] : 50;
            $types .= 'i';
        }

        // Add delay if column exists
        if (in_array('delay', $existingColumns)) {
            $setParts[] = 'delay = ?';
            $values[] = isset($data['delay']) ? (int)$data['delay'] : 2;
            $types .= 'i';
        }

        // Add selector columns if they exist
        $selectorColumns = [
            'selector_list_container',
            'selector_list_item',
            'selector_list_title',
            'selector_list_link',
            'selector_list_date',
            'selector_list_image',
            'selector_title',
            'selector_content',
            'selector_image',
            'selector_excerpt',
            'selector_date',
            'selector_author',
            'selector_pagination',
            'selector_read_more',
            'selector_category',
            'selector_tags',
            'selector_video',
            'selector_audio',
            'selector_source_url'
        ];

        foreach ($selectorColumns as $col) {
            if (in_array($col, $existingColumns)) {
                $setParts[] = "$col = ?";
                $values[] = sanitize_input($data[$col] ?? '');
                $types .= 's';
            }
        }

        // Add id to values
        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE autocontent_sources SET " . implode(', ', $setParts) . " WHERE id = ?";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Ensure selector columns exist in the database
     */
    private function ensureSelectorColumns(): void
    {
        $columns = [
            // List page selectors
            'selector_list_container' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_item' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_title' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_link' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_date' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_image' => 'VARCHAR(500) DEFAULT ""',
            // Detail page selectors
            'selector_title' => 'VARCHAR(500) DEFAULT ""',
            'selector_content' => 'VARCHAR(500) DEFAULT ""',
            'selector_image' => 'VARCHAR(500) DEFAULT ""',
            'selector_excerpt' => 'VARCHAR(500) DEFAULT ""',
            'selector_date' => 'VARCHAR(500) DEFAULT ""',
            'selector_author' => 'VARCHAR(500) DEFAULT ""',
            // Additional selectors
            'selector_pagination' => 'VARCHAR(500) DEFAULT ""',
            'selector_read_more' => 'VARCHAR(500) DEFAULT ""',
            'selector_category' => 'VARCHAR(500) DEFAULT ""',
            'selector_tags' => 'VARCHAR(500) DEFAULT ""',
            'selector_video' => 'VARCHAR(500) DEFAULT ""',
            'selector_audio' => 'VARCHAR(500) DEFAULT ""',
            'selector_source_url' => 'VARCHAR(500) DEFAULT ""'
        ];

        foreach ($columns as $column => $definition) {
            $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE '$column'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN $column $definition");
            }
        }
    }

    /**
     * Ensure new columns exist for sitemap support
     */
    private function ensureNewColumns(): void
    {
        // Add content_type column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'content_type'");
        if (!$result || $result->num_rows === 0) {
            // Column doesn't exist, create it
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN content_type ENUM('articles', 'pages', 'mobiles', 'services') DEFAULT 'articles' AFTER type");
        }
        // If column exists, do nothing - don't try to modify it

        // Add max_pages column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'max_pages'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN max_pages INT DEFAULT 50 AFTER fetch_interval");
        }

        // Add delay column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'delay'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN delay INT DEFAULT 2 AFTER max_pages");
        }

        // Add scrape_depth column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'scrape_depth'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN scrape_depth INT DEFAULT 1 AFTER content_type");
        }

        // Add use_browser column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'use_browser'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN use_browser TINYINT(1) DEFAULT 0 AFTER scrape_depth");
        }
    }

    /**
     * Ensure type column has all required ENUM values (rss, xml, html, api, scrape)
     * This fixes the "Data truncated for column 'type'" error
     */
    private function ensureTypeColumnEnum(): void
    {
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'type'");
        if ($result && $result->num_rows > 0) {
            $column = $result->fetch_assoc();
            // Check if both 'scrape' AND 'xml' are missing
            $hasScrape = strpos($column['Type'], 'scrape') !== false;
            $hasXml = strpos($column['Type'], 'xml') !== false;

            if (!$hasScrape || !$hasXml) {
                // First check what's in the current ENUM
                preg_match("/ENUM\('([^']+)'\)/", $column['Type'], $matches);
                $currentValues = $matches ? explode("','", $matches[1]) : [];

                // Build new ENUM with all required values
                $requiredValues = ['rss', 'xml', 'html', 'api', 'scrape'];
                $newValues = array_unique(array_merge($currentValues, $requiredValues));
                $enumList = implode("','", $newValues);

                $this->mysqli->query("ALTER TABLE autocontent_sources MODIFY COLUMN type ENUM('$enumList') DEFAULT 'rss'");
            }
        }
    }

    /**
     * Ensure content_type column has all required ENUM values
     */
    private function ensureContentTypeEnum(): void
    {
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'content_type'");
        if ($result && $result->num_rows > 0) {
            $column = $result->fetch_assoc();
            // Check if all required values exist
            $hasArticles = strpos($column['Type'], 'articles') !== false;
            $hasPages = strpos($column['Type'], 'pages') !== false;
            $hasMobiles = strpos($column['Type'], 'mobiles') !== false;
            $hasServices = strpos($column['Type'], 'services') !== false;

            if (!$hasArticles || !$hasPages || !$hasMobiles || !$hasServices) {
                // First check what's in the current ENUM
                preg_match("/ENUM\('([^']+)'\)/", $column['Type'], $matches);
                $currentValues = $matches ? explode("','", $matches[1]) : [];

                // Build new ENUM with all required values
                $requiredValues = ['articles', 'pages', 'mobiles', 'services'];
                $newValues = array_unique(array_merge($currentValues, $requiredValues));
                $enumList = implode("','", $newValues);

                $this->mysqli->query("ALTER TABLE autocontent_sources MODIFY COLUMN content_type ENUM('$enumList') DEFAULT 'articles'");
            }
        }
    }

    /**
     * Delete a source
     */
    public function deleteSource(int $id): bool
    {
        // First delete associated articles
        $this->mysqli->query("DELETE FROM autocontent_articles WHERE source_id = " . (int)$id);

        $stmt = $this->mysqli->prepare("DELETE FROM autocontent_sources WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Toggle source active status
     */
    public function toggleSourceStatus(int $id): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE autocontent_sources SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Update last fetched time
     */
    public function updateLastFetched(int $id): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE autocontent_sources SET last_fetched_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Get active sources
     */
    public function getActiveSources(): array
    {
        $sql = "SELECT * FROM autocontent_sources WHERE is_active = 1 ORDER BY id";
        $result = $this->mysqli->query($sql);

        $sources = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $sources[] = $row;
            }
            $result->free();
        }
        return $sources;
    }

    // ================== ARTICLE MANAGEMENT ==================

    /**
     * Get all articles with filters
     */
    public function getArticles(int $page = 1, int $limit = 20, string $status = '', string $sourceFilter = '', string $search = ''): array
    {
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];

        if (!empty($status)) {
            $where .= " AND a.status = ?";
            $params[] = $status;
        }

        if (!empty($sourceFilter)) {
            $where .= " AND a.source_id = ?";
            $params[] = (int)$sourceFilter;
        }

        if (!empty($search)) {
            $where .= " AND (a.title LIKE ? OR a.original_title LIKE ? OR a.ai_title LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        $sql = "SELECT a.*, s.name as source_name, s.url as source_url 
                FROM autocontent_articles a 
                LEFT JOIN autocontent_sources s ON a.source_id = s.id 
                WHERE {$where} 
                ORDER BY a.id DESC 
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->mysqli->prepare($sql);

        // Build type string
        $types = str_repeat('s', count($params) - 2) . 'ii';
        $stmt->bind_param($types, ...$params);

        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Add backward compatibility for legacy field names
                if (!isset($row['title']) && isset($row['original_title'])) {
                    $row['title'] = $row['original_title'];
                }
                if (!isset($row['content']) && isset($row['original_content'])) {
                    $row['content'] = $row['original_content'];
                }
                $articles[] = $row;
            }
            $result->free();
        }
        $stmt->close();

        return $articles;
    }

    /**
     * Get article by ID
     */
    public function getArticleById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT a.*, s.name as source_name FROM autocontent_articles a LEFT JOIN autocontent_sources s ON a.source_id = s.id WHERE a.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $article = $result->fetch_assoc();
            $stmt->close();

            // Add backward compatibility for legacy field names
            if (!isset($article['title']) && isset($article['original_title'])) {
                $article['title'] = $article['original_title'];
            }
            if (!isset($article['content']) && isset($article['original_content'])) {
                $article['content'] = $article['original_content'];
            }
            if (!isset($article['excerpt']) && isset($article['original_excerpt'])) {
                $article['excerpt'] = $article['original_excerpt'];
            }
            if (!isset($article['author']) && isset($article['original_author'])) {
                $article['author'] = $article['original_author'];
            }

            return $article;
        }
        $stmt->close();
        return null;
    }

    /**
     * Create a new article
     */
    public function createArticle(array $data): int
    {
        // Support both original_ prefixed fields and legacy fields
        $title = $data['title'] ?? $data['original_title'] ?? '';
        $content = $data['content'] ?? $data['original_content'] ?? '';
        $excerpt = $data['excerpt'] ?? $data['original_excerpt'] ?? '';
        $author = $data['author'] ?? $data['original_author'] ?? '';

        // Ensure UTF-8 encoding for Bengali/Unicode characters
        $title = mb_convert_encoding($title, 'UTF-8', 'UTF-8');
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $excerpt = mb_convert_encoding(mb_substr($excerpt, 0, 200), 'UTF-8', 'UTF-8');
        $author = mb_convert_encoding($author, 'UTF-8', 'UTF-8');

        $stmt = $this->mysqli->prepare("
            INSERT INTO autocontent_articles (source_id, url, title, content, excerpt, author, image_url, published_at, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $sourceId = (int)$data['source_id'];
        $url = sanitize_input($data['url'] ?? '');
        $imageUrl = sanitize_input($data['image_url'] ?? $data['featured_image'] ?? '');
        $publishedAt = sanitize_input($data['published_at'] ?? date('Y-m-d H:i:s'));
        $status = sanitize_input($data['status'] ?? 'collected');

        // Set charset for this connection to utf8mb4
        $this->mysqli->set_charset('utf8mb4');

        $stmt->bind_param("issssssss", $sourceId, $url, $title, $content, $excerpt, $author, $imageUrl, $publishedAt, $status);
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        return (int)$insertId;
    }

    /**
     * Update article status
     */
    public function updateArticleStatus(int $id, string $status): bool
    {
        $stmt = $this->mysqli->prepare("UPDATE autocontent_articles SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Update article content (after AI processing)
     */
    public function updateArticleContent(int $id, string $processedContent, string $aiSummary): bool
    {
        $stmt = $this->mysqli->prepare("
            UPDATE autocontent_articles 
            SET content = ?, ai_summary = ?, status = 'processed', updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $processedContent, $aiSummary, $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Update article with AI enhanced content
     */
    public function updateArticleWithAi(int $id, string $aiTitle, string $aiContent, string $aiExcerpt, int $seoScore, int $wordCount): bool
    {
        // First check if seo_score column exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_articles LIKE 'seo_score'");
        $hasSeoScore = $result && $result->num_rows > 0;

        if ($hasSeoScore) {
            $stmt = $this->mysqli->prepare("
                UPDATE autocontent_articles 
                SET ai_title = ?, ai_content = ?, ai_excerpt = ?, 
                    seo_score = ?, word_count = ?, 
                    status = 'processed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sssiii", $aiTitle, $aiContent, $aiExcerpt, $seoScore, $wordCount, $id);
        } else {
            // Fallback for older schema without seo_score
            $stmt = $this->mysqli->prepare("
                UPDATE autocontent_articles 
                SET ai_title = ?, ai_content = ?, ai_excerpt = ?, 
                    word_count = ?, 
                    status = 'processed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("sssii", $aiTitle, $aiContent, $aiExcerpt, $wordCount, $id);
        }

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Delete article
     */
    public function deleteArticle(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM autocontent_articles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Get article count by status
     */
    public function getArticleCountByStatus(string $status = ''): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM autocontent_articles";
        if (!empty($status)) {
            $sql .= " WHERE status = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("s", $status);
        } else {
            $sql .= " GROUP BY status";
            $stmt = $this->mysqli->prepare($sql);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $counts = [
            'collected' => 0,
            'processing' => 0,
            'processed' => 0,
            'approved' => 0,
            'published' => 0,
            'failed' => 0,
            'rejected' => 0,
            'total' => 0
        ];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $counts[$row['status']] = (int)$row['count'];
                $counts['total'] += (int)$row['count'];
            }
            $result->free();
        }
        $stmt->close();

        return $counts;
    }

    /**
     * Get articles collected today
     */
    public function getArticlesCollectedToday(): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $result->free();
        }
        $stmt->close();

        return $count;
    }

    /**
     * Get articles processed today
     */
    public function getArticlesProcessedToday(): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE status IN ('processed', 'approved', 'published') AND DATE(updated_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $result->free();
        }
        $stmt->close();

        return $count;
    }

    /**
     * Get articles published today
     */
    public function getArticlesPublishedToday(): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE status = 'published' AND DATE(updated_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $result->free();
        }
        $stmt->close();

        return $count;
    }

    /**
     * Get articles failed today
     */
    public function getArticlesFailedToday(): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE status = 'failed' AND DATE(updated_at) = CURDATE()");
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $result->free();
        }
        $stmt->close();

        return $count;
    }

    /**
     * Get average SEO score
     */
    public function getAverageSeoScore(): float
    {
        // Check if seo_score column exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_articles LIKE 'seo_score'");
        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $stmt = $this->mysqli->prepare("SELECT AVG(seo_score) as avg_score FROM autocontent_articles WHERE seo_score > 0");
        $stmt->execute();
        $result = $stmt->get_result();

        $avg = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $avg = $row['avg_score'] ? round((float)$row['avg_score'], 1) : 0;
            $result->free();
        }
        $stmt->close();

        return $avg;
    }

    /**
     * Get approved articles count
     */
    public function getApprovedCount(): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE status = 'approved'");
        $stmt->execute();
        $result = $stmt->get_result();

        $count = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];
            $result->free();
        }
        $stmt->close();

        return $count;
    }

    /**
     * Check if article URL already exists
     */
    public function articleUrlExists(string $url, int $sourceId): bool
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM autocontent_articles WHERE url = ? AND source_id = ?");
        $stmt->bind_param("si", $url, $sourceId);
        $stmt->execute();
        $result = $stmt->get_result();

        $exists = false;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $exists = (int)$row['count'] > 0;
            $result->free();
        }
        $stmt->close();

        return $exists;
    }

    // ================== SETTINGS MANAGEMENT ==================

    /**
     * Get all settings
     */
    public function getSettings(): array
    {
        $sql = "SELECT setting_key, setting_value FROM autocontent_settings";
        $result = $this->mysqli->query($sql);

        $settings = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            $result->free();
        }

        // Check if AI key is set
        $aiKeySet = !empty($settings['ai_key']);

        // Set defaults
        $defaults = [
            'ai_endpoint' => '',
            'ai_model' => 'gpt-4o-mini',
            'ai_key' => '',
            'ai_key_set' => $aiKeySet,
            'autocontent_enabled' => '0',
            'auto_collect' => '0',
            'auto_process' => '0',
            'auto_publish' => '0',
            'max_articles_per_source' => '10',
            'publish_status' => 'published'
        ];

        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }

        // Override ai_key_set with computed value
        $settings['ai_key_set'] = $aiKeySet;

        return $settings;
    }

    /**
     * Save a setting
     */
    public function saveSetting(string $key, string $value): bool
    {
        $stmt = $this->mysqli->prepare("
            INSERT INTO autocontent_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->bind_param("sss", $key, $value, $value);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Save multiple settings
     */
    public function saveSettings(array $settings): bool
    {
        foreach ($settings as $key => $value) {
            if (!$this->saveSetting($key, (string)$value)) {
                return false;
            }
        }
        return true;
    }

    // ================== STATISTICS ==================

    /**
     * Get dashboard statistics
     */
    public function getStats(): array
    {
        $statusCounts = $this->getArticleCountByStatus();

        return [
            'collected' => $statusCounts['collected'],
            'processing' => $statusCounts['processing'],
            'processed' => $statusCounts['processed'],
            'published' => $statusCounts['published'],
            'failed' => $statusCounts['failed'],
            'total' => $statusCounts['total'],
            'collected_today' => $this->getArticlesCollectedToday(),
            'processed_today' => $this->getArticlesProcessedToday(),
            'published_today' => $this->getArticlesPublishedToday(),
            'failed_today' => $this->getArticlesFailedToday(),
            'avg_seo_score' => $this->getAverageSeoScore(),
            'approved' => $this->getApprovedCount(),
            'active_sources' => count($this->getActiveSources())
        ];
    }

    /**
     * Get recent articles for dashboard
     */
    public function getRecentArticles(int $limit = 15): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT a.*, s.name as source_name 
            FROM autocontent_articles a 
            LEFT JOIN autocontent_sources s ON a.source_id = s.id 
            ORDER BY a.id DESC 
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Add backward compatibility
                if (!isset($row['title']) && isset($row['original_title'])) {
                    $row['title'] = $row['original_title'];
                }
                if (!isset($row['content']) && isset($row['original_content'])) {
                    $row['content'] = $row['original_content'];
                }
                $articles[] = $row;
            }
            $result->free();
        }
        $stmt->close();

        return $articles;
    }

    /**
     * Get articles by status for processing
     */
    public function getArticlesByStatus(string $status, int $limit = 10): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT a.*, s.name as source_name, s.url as source_url 
            FROM autocontent_articles a 
            LEFT JOIN autocontent_sources s ON a.source_id = s.id 
            WHERE a.status = ? 
            ORDER BY a.id ASC 
            LIMIT ?
        ");
        $stmt->bind_param("si", $status, $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $articles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Add backward compatibility
                if (!isset($row['title']) && isset($row['original_title'])) {
                    $row['title'] = $row['original_title'];
                }
                if (!isset($row['content']) && isset($row['original_content'])) {
                    $row['content'] = $row['original_content'];
                }
                $articles[] = $row;
            }
            $result->free();
        }
        $stmt->close();

        return $articles;
    }

    /**
     * Retry failed articles
     */
    public function retryFailedArticles(int $limit = 10): int
    {
        $stmt = $this->mysqli->prepare("
            UPDATE autocontent_articles 
            SET status = 'collected', updated_at = NULL 
            WHERE status = 'failed' 
            ORDER BY id ASC 
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Get all categories for dropdown
     */
    public function getCategories(): array
    {
        $sql = "SELECT id, name FROM categories ORDER BY name";
        $result = $this->mysqli->query($sql);

        $categories = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            $result->free();
        }
        return $categories;
    }

    /**
     * Ensure database tables exist
     */
    public function ensureTablesExist(): bool
    {
        // First ensure the type column has all required ENUM values
        $this->ensureTypeColumnEnum();

        // Also ensure content_type column has all required ENUM values
        $this->ensureContentTypeEnum();

        // Create sources table
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS autocontent_sources (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                type ENUM('rss', 'html', 'api', 'scrape') DEFAULT 'rss',
                category_id INT DEFAULT NULL,
                website_preset_key VARCHAR(50) DEFAULT '',
                selectors TEXT,
                fetch_interval INT DEFAULT 3600,
                is_active TINYINT(1) DEFAULT 1,
                last_fetched_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active),
                INDEX idx_last_fetched (last_fetched_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Migrate existing table: ensure all ENUM values exist for 'type' column
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'type'");
        if ($result && $result->num_rows > 0) {
            $column = $result->fetch_assoc();
            // Check if 'scrape' or 'xml' is missing and add it
            if (strpos($column['Type'], 'scrape') === false || strpos($column['Type'], 'xml') === false) {
                // First check what's in the current ENUM
                preg_match("/ENUM\('([^']+)'\)/", $column['Type'], $matches);
                $currentValues = $matches ? explode("','", $matches[1]) : [];

                // Build new ENUM with all required values
                $requiredValues = ['rss', 'xml', 'html', 'api', 'scrape'];
                $newValues = array_unique(array_merge($currentValues, $requiredValues));
                $enumList = implode("','", $newValues);

                $this->mysqli->query("ALTER TABLE autocontent_sources MODIFY COLUMN type ENUM('$enumList') DEFAULT 'rss'");
            }
        }

        // Migrate existing table: add individual selector columns if not exists
        $selectorColumns = [
            // Detail page selectors
            'selector_title' => 'VARCHAR(500) DEFAULT ""',
            'selector_content' => 'VARCHAR(500) DEFAULT ""',
            'selector_image' => 'VARCHAR(500) DEFAULT ""',
            'selector_excerpt' => 'VARCHAR(500) DEFAULT ""',
            'selector_date' => 'VARCHAR(500) DEFAULT ""',
            'selector_author' => 'VARCHAR(500) DEFAULT ""',
            // List page selectors
            'selector_list_container' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_item' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_title' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_link' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_date' => 'VARCHAR(500) DEFAULT ""',
            'selector_list_image' => 'VARCHAR(500) DEFAULT ""',
            // Additional selectors
            'selector_pagination' => 'VARCHAR(500) DEFAULT ""',
            'selector_read_more' => 'VARCHAR(500) DEFAULT ""',
            'selector_category' => 'VARCHAR(500) DEFAULT ""',
            'selector_tags' => 'VARCHAR(500) DEFAULT ""',
            'selector_video' => 'VARCHAR(500) DEFAULT ""',
            'selector_audio' => 'VARCHAR(500) DEFAULT ""',
            'selector_source_url' => 'VARCHAR(500) DEFAULT ""'
        ];

        foreach ($selectorColumns as $column => $definition) {
            $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE '$column'");
            if (!$result || $result->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN $column $definition");
            }
        }

        // Add new sitemap columns if not exist
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'content_type'");
        if (!$result || $result->num_rows === 0) {
            // Column doesn't exist, add it
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN content_type ENUM('articles', 'pages', 'mobiles', 'services') DEFAULT 'articles'");
        }
        // If column exists, do nothing - don't try to modify it

        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'max_pages'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN max_pages INT DEFAULT 50");
        }

        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'delay'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN delay INT DEFAULT 2");
        }

        // Add scrape_depth column
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'scrape_depth'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN scrape_depth INT DEFAULT 1");
        }

        // Add use_browser column
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'use_browser'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN use_browser TINYINT(1) DEFAULT 0");
        }

        // Add website preset key column if not exists
        $result = $this->mysqli->query("SHOW COLUMNS FROM autocontent_sources LIKE 'website_preset_key'");
        if (!$result || $result->num_rows === 0) {
            $this->mysqli->query("ALTER TABLE autocontent_sources ADD COLUMN website_preset_key VARCHAR(50) DEFAULT ''");
        }

        // Create articles table
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS autocontent_articles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                source_id INT NOT NULL,
                title VARCHAR(500) NOT NULL,
                url TEXT NOT NULL,
                content LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                excerpt TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                image_url TEXT,
                author VARCHAR(255),
                published_at DATETIME,
                status ENUM('collected', 'processing', 'processed', 'published', 'failed') DEFAULT 'collected',
                ai_summary TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_source (source_id),
                INDEX idx_status (status),
                INDEX idx_published (published_at),
                UNIQUE KEY unique_source_url (source_id, url(255))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Create settings table
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS autocontent_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Create website presets table
        $this->mysqli->query("
            CREATE TABLE IF NOT EXISTS autocontent_website_presets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                preset_key VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                selector_list_container TEXT,
                selector_list_item TEXT,
                selector_list_title TEXT,
                selector_list_link TEXT,
                selector_list_date TEXT,
                selector_list_image TEXT,
                selector_title TEXT,
                selector_content TEXT,
                selector_image TEXT,
                selector_excerpt TEXT,
                selector_date TEXT,
                selector_author TEXT,
                selector_pagination TEXT,
                selector_read_more TEXT,
                selector_category TEXT,
                selector_tags TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insert default presets if table is empty
        $result = $this->mysqli->query("SELECT COUNT(*) as cnt FROM autocontent_website_presets");
        if ($result && $result->fetch_assoc()['cnt'] == 0) {
            $this->insertDefaultPresets();
        }

        return true;
    }

    /**
     * Insert default website presets
     */
    private function insertDefaultPresets(): void
    {
        $presets = [
            [
                'key' => 'prothomalo',
                'name' => 'Prothom Alo',
                'list_container' => 'body',
                'list_item' => '.wide-story-card, .news_with_item',
                'list_title' => 'h3.headline-title a.title-link',
                'list_date' => 'time.published-at, time.published-time',
                'title' => 'h1.IiRps, h1[data-title-0]',
                'content' => '.story-element.story-element-text',
                'image' => 'meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => 'time[datetime]',
                'author' => '.author-name, .contributor-name'
            ],
            [
                'key' => 'bdnews24',
                'name' => 'BD News 24',
                'list_container' => '#data-wrapper',
                'list_item' => '.SubCat-wrapper, .col-md-3',
                'list_title' => 'h5 a, .SubcatList-detail h5 a',
                'list_date' => '.publish-time, span.publish-time',
                'title' => '.details-title h1, h1',
                'content' => '#contentDetails, .details-brief',
                'image' => '.details-img img, .details-img picture img',
                'excerpt' => '.details-title h2, h2.shoulder-text',
                'date' => '.pub-up .pub, .pub-up span:first-child',
                'author' => '.author-name-wrap .author, .detail-author-name .author'
            ],
            [
                'key' => 'bbc',
                'name' => 'BBC Bangla',
                'list_container' => '.content--list',
                'list_item' => '.media-list__item',
                'list_title' => 'h3 a, .media__title a',
                'list_date' => 'time',
                'title' => 'h1',
                'content' => 'article',
                'image' => 'meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => 'time[datetime]',
                'author' => '.byline__name'
            ],
            [
                'key' => 'ittefaq',
                'name' => 'Ittefaq',
                'list_container' => '.news-list, .category-news',
                'list_item' => '.news-item, .category-item',
                'list_title' => 'h2 a, h3 a, .news-title a',
                'list_date' => '.date, time, .publish-date',
                'title' => 'h1, .article-title',
                'content' => '.article-content, .news-content',
                'image' => '.article-img img, meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => '.publish-date, time',
                'author' => '.author, .writer'
            ],
            [
                'key' => 'jugantor',
                'name' => 'Jugantor',
                'list_container' => '.news_list, .cat_news',
                'list_item' => '.news_item, .cat_item',
                'list_title' => 'h2 a, h3 a',
                'list_date' => '.date, time',
                'title' => 'h1, .title',
                'content' => '.details, .content',
                'image' => 'meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => '.date, time',
                'author' => '.writer, .author'
            ],
            [
                'key' => 'kalerkhobor',
                'name' => 'Kaler Khobor',
                'list_container' => '.latest-news, .news-list',
                'list_item' => '.news-item, .item',
                'list_title' => 'h3 a, h4 a',
                'list_date' => '.date, time',
                'title' => 'h1, .headline',
                'content' => '.news-details, .content',
                'image' => 'meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => '.date, time',
                'author' => '.author, .writer'
            ],
            [
                'key' => 'dailystar',
                'name' => 'The Daily Star',
                'list_container' => '.news-feed, .latest-news',
                'list_item' => '.news-card, .news-item',
                'list_title' => 'h3 a, .headline a',
                'list_date' => '.date, time',
                'title' => 'h1, .article-headline',
                'content' => '.article-body, .content',
                'image' => 'meta[property="og:image"]',
                'excerpt' => 'meta[name="description"]',
                'date' => '.publish-date, time',
                'author' => '.author-name, .byline'
            ]
        ];

        $stmt = $this->mysqli->prepare("
            INSERT INTO autocontent_website_presets 
            (preset_key, name, selector_list_container, selector_list_item, selector_list_title, 
             selector_list_date, selector_title, selector_content, selector_image, 
             selector_excerpt, selector_date, selector_author)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($presets as $p) {
            $stmt->bind_param(
                'ssssssssssss',
                $p['key'],
                $p['name'],
                $p['list_container'],
                $p['list_item'],
                $p['list_title'],
                $p['list_date'],
                $p['title'],
                $p['content'],
                $p['image'],
                $p['excerpt'],
                $p['date'],
                $p['author']
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    /**
     * Get all active website presets
     */
    public function getWebsitePresets(): array
    {
        $sql = "SELECT * FROM autocontent_website_presets WHERE is_active = 1 ORDER BY name";
        $result = $this->mysqli->query($sql);

        $presets = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $presets[] = $row;
            }
            $result->free();
        }
        return $presets;
    }

    /**
     * Get a specific website preset by key
     */
    public function getWebsitePresetByKey(string $key): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT * FROM autocontent_website_presets WHERE preset_key = ? AND is_active = 1");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $preset = $result->fetch_assoc();
            $stmt->close();
            return $preset;
        }
        $stmt->close();
        return null;
    }

    /**
     * Save a website preset
     */
    public function saveWebsitePreset(array $data): int
    {
        if (isset($data['id']) && $data['id'] > 0) {
            // Update existing
            $stmt = $this->mysqli->prepare("
                UPDATE autocontent_website_presets SET
                    name = ?, preset_key = ?,
                    selector_list_container = ?, selector_list_item = ?,
                    selector_list_title = ?, selector_list_link = ?,
                    selector_list_date = ?, selector_list_image = ?,
                    selector_title = ?, selector_content = ?,
                    selector_image = ?, selector_excerpt = ?,
                    selector_date = ?, selector_author = ?,
                    selector_pagination = ?, selector_read_more = ?,
                    selector_category = ?, selector_tags = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'sssssssssssssssssi',
                $data['name'],
                $data['preset_key'],
                $data['selector_list_container'],
                $data['selector_list_item'],
                $data['selector_list_title'],
                $data['selector_list_link'],
                $data['selector_list_date'],
                $data['selector_list_image'],
                $data['selector_title'],
                $data['selector_content'],
                $data['selector_image'],
                $data['selector_excerpt'],
                $data['selector_date'],
                $data['selector_author'],
                $data['selector_pagination'],
                $data['selector_read_more'],
                $data['selector_category'],
                $data['selector_tags'],
                $data['id']
            );
            $stmt->execute();
            $stmt->close();
            return $data['id'];
        } else {
            // Insert new
            $stmt = $this->mysqli->prepare("
                INSERT INTO autocontent_website_presets
                (preset_key, name, selector_list_container, selector_list_item,
                 selector_list_title, selector_list_link, selector_list_date, selector_list_image,
                 selector_title, selector_content, selector_image, selector_excerpt,
                 selector_date, selector_author, selector_pagination, selector_read_more,
                 selector_category, selector_tags)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'ssssssssssssssssss',
                $data['preset_key'],
                $data['name'],
                $data['selector_list_container'],
                $data['selector_list_item'],
                $data['selector_list_title'],
                $data['selector_list_link'],
                $data['selector_list_date'],
                $data['selector_list_image'],
                $data['selector_title'],
                $data['selector_content'],
                $data['selector_image'],
                $data['selector_excerpt'],
                $data['selector_date'],
                $data['selector_author'],
                $data['selector_pagination'],
                $data['selector_read_more'],
                $data['selector_category'],
                $data['selector_tags']
            );
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        }
    }

    /**
     * Delete a website preset
     */
    public function deleteWebsitePreset(int $id): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM autocontent_website_presets WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }
}
