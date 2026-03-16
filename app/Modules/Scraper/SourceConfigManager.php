<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * SourceConfigManager.php
 * Manages multiple scraping sources with custom CSS selectors
 * Supports different source types: RSS, HTML, JSON, API
 */
class SourceConfigManager
{
    private array $sources = [];
    private array $defaultSelectors = [];
    private ?\mysqli $mysqli = null;

    // Source types
    public const TYPE_RSS = 'rss';
    public const TYPE_HTML = 'html';
    public const TYPE_JSON = 'json';
    public const TYPE_API = 'api';

    // Pagination types
    public const PAGINATION_NONE = 'none';
    public const PAGINATION_LINK = 'link';
    public const PAGINATION_NUMBER = 'number';
    public const PAGINATION_SCROLL = 'scroll';
    public const PAGINATION_BUTTON = 'button';

    public function __construct(array $config = [])
    {
        $this->defaultSelectors = $config['default_selectors'] ?? [];
        $this->sources = $config['sources'] ?? [];
    }

    /**
     * Set database connection
     */
    public function setDatabase(\mysqli $mysqli): self
    {
        $this->mysqli = $mysqli;
        return $this;
    }

    /**
     * Add a source configuration
     */
    public function addSource(array $source): self
    {
        $source += [
            'id' => null,
            'name' => '',
            'url' => '',
            'type' => self::TYPE_HTML,
            'category_id' => null,
            'selectors' => [],
            'pagination' => [
                'type' => self::PAGINATION_NONE,
                'selector' => '',
                'pattern' => '',
                'max_pages' => 10,
            ],
            'proxy' => [
                'enabled' => false,
                'provider' => '',
            ],
            'rate_limit' => [
                'min_delay' => 1000,
                'max_delay' => 3000,
            ],
            'is_active' => true,
            'fetch_interval' => 3600,
        ];

        $key = $this->getSourceKey($source['url']);
        $this->sources[$key] = $source;

        return $this;
    }

    /**
     * Load sources from database
     */
    public function loadFromDatabase(): array
    {
        if (!$this->mysqli) {
            return [];
        }

        $sql = "SELECT * FROM autocontent_sources WHERE is_active = 1 ORDER BY id DESC";
        $result = $this->mysqli->query($sql);

        $loadedSources = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $source = $this->parseSourceFromDb($row);
                $key = $this->getSourceKey($source['url']);
                $loadedSources[$key] = $source;
            }
            $result->free();
        }

        $this->sources = array_merge($this->sources, $loadedSources);
        
        return $loadedSources;
    }

    /**
     * Parse database row to source config
     */
    private function parseSourceFromDb(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'url' => $row['url'],
            'type' => $row['type'],
            'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
            'selectors' => [
                'list_container' => $row['selector_list_container'] ?? '',
                'list_item' => $row['selector_list_item'] ?? '',
                'list_title' => $row['selector_list_title'] ?? '',
                'list_date' => $row['selector_list_date'] ?? '',
                'list_url' => $row['selector_list_url'] ?? '',
                'title' => $row['selector_title'] ?? '',
                'content' => $row['selector_content'] ?? '',
                'image' => $row['selector_image'] ?? '',
                'excerpt' => $row['selector_excerpt'] ?? '',
                'date' => $row['selector_date'] ?? '',
                'author' => $row['selector_author'] ?? '',
            ],
            'pagination' => [
                'type' => $row['pagination_type'] ?? self::PAGINATION_NONE,
                'selector' => $row['pagination_selector'] ?? '',
                'pattern' => $row['pagination_pattern'] ?? '',
                'max_pages' => (int)($row['max_pages'] ?? 10),
            ],
            'proxy' => [
                'enabled' => (bool)($row['proxy_enabled'] ?? false),
                'provider' => $row['proxy_provider'] ?? '',
            ],
            'rate_limit' => [
                'min_delay' => (int)($row['min_delay'] ?? 1000),
                'max_delay' => (int)($row['max_delay'] ?? 3000),
            ],
            'is_active' => (bool)$row['is_active'],
            'fetch_interval' => (int)($row['fetch_interval'] ?? 3600),
            'last_fetch' => $row['last_fetch'] ?? null,
        ];
    }

    /**
     * Get source by URL
     */
    public function getSource(string $url): ?array
    {
        $key = $this->getSourceKey($url);
        return $this->sources[$key] ?? null;
    }

    /**
     * Get source by ID
     */
    public function getSourceById(int $id): ?array
    {
        foreach ($this->sources as $source) {
            if (($source['id'] ?? null) === $id) {
                return $source;
            }
        }
        return null;
    }

    /**
     * Get all sources
     */
    public function getAllSources(): array
    {
        return $this->sources;
    }

    /**
     * Get active sources only
     */
    public function getActiveSources(): array
    {
        return array_filter($this->sources, fn($s) => $s['is_active']);
    }

    /**
     * Get sources by type
     */
    public function getSourcesByType(string $type): array
    {
        return array_filter($this->sources, fn($s) => $s['type'] === $type);
    }

    /**
     * Get selectors for a source
     */
    public function getSelectors(string $url): array
    {
        $source = $this->getSource($url);
        
        if ($source && !empty($source['selectors'])) {
            return $source['selectors'];
        }
        
        // Return default selectors
        return $this->getDefaultSelectors();
    }

    /**
     * Get default selectors
     */
    public function getDefaultSelectors(): array
    {
        return [
            'title' => ['h1', 'article h1', '.post-title', '.entry-title', '.article-title', 'head title'],
            'content' => ['article', '.post-content', '.article-content', '.entry-content', '.content', 'main', '.post-body'],
            'image' => ['meta[property="og:image"]', 'meta[name="twitter:image"]', 'article img', '.post-thumbnail img'],
            'author' => ['.author-name', '.byline', '[rel="author"]', '.article-author'],
            'date' => ['time[datetime]', '.published-at', '.post-date', 'meta[property="article:published_time"]'],
            'excerpt' => ['meta[name="description"]', 'meta[property="og:description"]', '.excerpt', '.article-excerpt'],
        ];
    }

    /**
     * Set selectors for a source
     */
    public function setSelectors(string $url, array $selectors): self
    {
        $key = $this->getSourceKey($url);
        
        if (isset($this->sources[$key])) {
            $this->sources[$key]['selectors'] = array_merge(
                $this->sources[$key]['selectors'],
                $selectors
            );
        }
        
        return $this;
    }

    /**
     * Get pagination config for a source
     */
    public function getPaginationConfig(string $url): array
    {
        $source = $this->getSource($url);
        
        return $source['pagination'] ?? [
            'type' => self::PAGINATION_NONE,
            'selector' => '',
            'pattern' => '',
            'max_pages' => 10,
        ];
    }

    /**
     * Get proxy config for a source
     */
    public function getProxyConfig(string $url): array
    {
        $source = $this->getSource($url);
        
        return $source['proxy'] ?? [
            'enabled' => false,
            'provider' => '',
        ];
    }

    /**
     * Get rate limit config for a source
     */
    public function getRateLimitConfig(string $url): array
    {
        $source = $this->getSource($url);
        
        return $source['rate_limit'] ?? [
            'min_delay' => 1000,
            'max_delay' => 3000,
        ];
    }

    /**
     * Update last fetch time
     */
    public function updateLastFetch(string $url): bool
    {
        if (!$this->mysqli) {
            return false;
        }

        $source = $this->getSource($url);
        if (!$source || !($source['id'] ?? null)) {
            return false;
        }

        $stmt = $this->mysqli->prepare(
            "UPDATE autocontent_sources SET last_fetch = NOW() WHERE id = ?"
        );
        $stmt->bind_param('i', $source['id']);
        $result = $stmt->execute();
        $stmt->close();

        if ($result && isset($this->sources[$this->getSourceKey($url)])) {
            $this->sources[$this->getSourceKey($url)]['last_fetch'] = date('Y-m-d H:i:s');
        }

        return $result;
    }

    /**
     * Create source in database
     */
    public function createSource(array $data): ?int
    {
        if (!$this->mysqli) {
            return null;
        }

        $source = $this->parseSourceFromDb($data);
        $source['selectors'] = $data['selectors'] ?? [];
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO autocontent_sources (
                name, url, type, category_id,
                selector_list_container, selector_list_item,
                selector_list_title, selector_list_date, selector_list_url,
                selector_title, selector_content, selector_image,
                selector_excerpt, selector_date, selector_author,
                pagination_type, pagination_selector, pagination_pattern,
                proxy_enabled, proxy_provider,
                fetch_interval, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->bind_param(
            "sssissssssssssssissi",
            $source['name'],
            $source['url'],
            $source['type'],
            $source['category_id'],
            $source['selectors']['list_container'] ?? '',
            $source['selectors']['list_item'] ?? '',
            $source['selectors']['list_title'] ?? '',
            $source['selectors']['list_date'] ?? '',
            $source['selectors']['list_url'] ?? '',
            $source['selectors']['title'] ?? '',
            $source['selectors']['content'] ?? '',
            $source['selectors']['image'] ?? '',
            $source['selectors']['excerpt'] ?? '',
            $source['selectors']['date'] ?? '',
            $source['selectors']['author'] ?? '',
            $source['pagination']['type'],
            $source['pagination']['selector'],
            $source['pagination']['pattern'],
            $source['proxy']['enabled'],
            $source['proxy']['provider'],
            $source['fetch_interval'],
            $source['is_active']
        );

        $result = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        if ($result) {
            $source['id'] = (int)$insertId;
            $this->sources[$this->getSourceKey($source['url'])] = $source;
        }

        return $result ? $insertId : null;
    }

    /**
     * Update source
     */
    public function updateSource(int $id, array $data): bool
    {
        if (!$this->mysqli) {
            return false;
        }

        $fields = [];
        $values = [];
        $types = '';

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE autocontent_sources SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            $this->loadFromDatabase();
        }

        return $result;
    }

    /**
     * Delete source
     */
    public function deleteSource(int $id): bool
    {
        if (!$this->mysqli) {
            return false;
        }

        $stmt = $this->mysqli->prepare("DELETE FROM autocontent_sources WHERE id = ?");
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            // Remove from local cache
            foreach ($this->sources as $key => $source) {
                if (($source['id'] ?? null) === $id) {
                    unset($this->sources[$key]);
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Check if source needs to be fetched
     */
    public function needsFetch(string $url): bool
    {
        $source = $this->getSource($url);
        
        if (!$source || !($source['is_active'] ?? true)) {
            return false;
        }

        $lastFetch = $source['last_fetch'] ?? null;
        
        if (!$lastFetch) {
            return true;
        }

        $interval = $source['fetch_interval'] ?? 3600;
        $lastFetchTime = strtotime($lastFetch);
        
        return (time() - $lastFetchTime) >= $interval;
    }

    /**
     * Get sources needing fetch
     */
    public function getSourcesNeedingFetch(): array
    {
        return array_filter($this->sources, fn($s) => $this->needsFetch($s['url']));
    }

    /**
     * Generate source key from URL
     */
    private function getSourceKey(string $url): string
    {
        return md5(strtolower(trim($url)));
    }

    /**
     * Export sources as array
     */
    public function export(): array
    {
        return $this->sources;
    }

    /**
     * Import sources from array
     */
    public function import(array $sources): self
    {
        $this->sources = [];
        foreach ($sources as $source) {
            $this->addSource($source);
        }
        return $this;
    }

    /**
     * Get source statistics
     */
    public function getStats(): array
    {
        $stats = [
            'total' => count($this->sources),
            'active' => count($this->getActiveSources()),
            'by_type' => [],
            'needs_fetch' => count($this->getSourcesNeedingFetch()),
        ];

        foreach ($this->sources as $source) {
            $type = $source['type'] ?? 'unknown';
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
        }

        return $stats;
    }
}
