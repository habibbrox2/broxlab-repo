<?php

namespace App\Modules\AISystem\Layer;

/**
 * Semantic Cache
 * 
 * Extends UnifiedCache with semantic similarity matching using embeddings.
 * Uses Redis for vector storage and similarity search.
 * 
 * v2026 - AI Capability Upgrade Pillar
 */
class SemanticCache
{
    private ?object $redis = null;
    private float $similarityThreshold = 0.93;
    private string $cacheKeyPrefix = 'semantic_cache:';
    private int $defaultTtl = 3600;
    private bool $redisAvailable = false;

    // Embedding dimension for common models
    private int $embeddingDim = 1536; // OpenAI ada-002

    public function __construct(array $config = [])
    {
        $this->similarityThreshold = $config['similarity_threshold'] ?? 0.93;
        $this->defaultTtl = $config['ttl'] ?? 3600;
        $this->embeddingDim = $config['embedding_dim'] ?? 1536;

        $this->initRedis();
    }

    /**
     * Initialize Redis connection
     */
    private function initRedis(): void
    {
        if (class_exists('Redis')) {
            try {
                $host = getenv('REDIS_HOST') ?: '127.0.0.1';
                $port = getenv('REDIS_PORT') ?: 6379;

                $this->redis = new \Redis();
                $this->redis->connect($host, $port);

                if ($password = getenv('REDIS_PASSWORD')) {
                    $this->redis->auth($password);
                }

                $this->redisAvailable = true;
            } catch (\Exception $e) {
                $this->redisAvailable = false;
            }
        }
    }

    /**
     * Check if semantic cache is available
     */
    public function isAvailable(): bool
    {
        return $this->redisAvailable;
    }

    /**
     * Get cached response using semantic similarity
     * 
     * @param array $embedding Query embedding
     * @param string $provider Provider name
     * @return array|null Cached response or null
     */
    public function getByEmbedding(array $embedding, string $provider = 'default'): ?array
    {
        if (!$this->redisAvailable) {
            return null;
        }

        try {
            // Search for similar embeddings
            $results = $this->findSimilarEmbeddings($embedding, $provider);

            if (empty($results)) {
                return null;
            }

            // Get the best match
            $bestMatch = $results[0];

            if ($bestMatch['score'] >= $this->similarityThreshold) {
                // Get full cached data
                $cached = $this->redis->hGetAll($this->cacheKeyPrefix . $provider . ':' . $bestMatch['key']);

                if ($cached && isset($cached['data'])) {
                    return [
                        'data' => json_decode($cached['data'], true),
                        'similarity' => $bestMatch['score'],
                        'cached_key' => $bestMatch['key'],
                        'from_cache' => true
                    ];
                }
            }

            return null;
        } catch (\Exception $e) {
            error_log('[SemanticCache] Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store response with embedding
     * 
     * @param array $embedding Query embedding
     * @param mixed $data Response data to cache
     * @param string $provider Provider name
     * @param int|null $ttl TTL in seconds
     * @return string Cache key
     */
    public function setByEmbedding(array $embedding, $data, string $provider = 'default', ?int $ttl = null): string
    {
        if (!$this->redisAvailable) {
            return '';
        }

        $cacheKey = $this->generateCacheKey($embedding, $provider);
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            // Store the embedding as a sorted set member
            $this->redis->zAdd(
                $this->cacheKeyPrefix . $provider . ':embeddings',
                $this->arrayToScore($embedding),
                $cacheKey
            );

            // Store the full data
            $this->redis->hMSet($this->cacheKeyPrefix . $provider . ':' . $cacheKey, [
                'data' => json_encode($data),
                'embedding' => json_encode($embedding),
                'created_at' => time(),
                'access_count' => 0,
                'last_access' => time()
            ]);

            // Set TTL
            $this->redis->expire($this->cacheKeyPrefix . $provider . ':' . $cacheKey, $ttl);
            $this->redis->expire($this->cacheKeyPrefix . $provider . ':embeddings', $ttl);

            // Cleanup old entries if cache is large
            $this->cleanupOldEntries($provider, 100);

            return $cacheKey;
        } catch (\Exception $e) {
            error_log('[SemanticCache] Set error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Find similar embeddings using cosine similarity
     * 
     * @param array $embedding Query embedding
     * @param string $provider Provider name
     * @param int $limit Number of results
     * @return array Similar entries with scores
     */
    private function findSimilarEmbeddings(array $embedding, string $provider = 'default', int $limit = 5): array
    {
        if (!$this->redisAvailable) {
            return [];
        }

        $results = [];

        try {
            // Get all embeddings in the set
            $allKeys = $this->redis->zRange(
                $this->cacheKeyPrefix . $provider . ':embeddings',
                0,
                -1,
                true
            );

            if (empty($allKeys)) {
                return [];
            }

            // Calculate similarity for each
            $queryScore = $this->arrayToScore($embedding);

            foreach ($allKeys as $key => $storedScore) {
                // Retrieve stored embedding
                $storedData = $this->redis->hGet(
                    $this->cacheKeyPrefix . $provider . ':' . $key,
                    'embedding'
                );

                if (!$storedData) continue;

                $storedEmbedding = json_decode($storedData, true);

                // Calculate cosine similarity
                $similarity = $this->cosineSimilarity($embedding, $storedEmbedding);

                $results[] = [
                    'key' => $key,
                    'score' => $similarity
                ];
            }

            // Sort by similarity descending
            usort($results, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            return array_slice($results, 0, $limit);
        } catch (\Exception $e) {
            error_log('[SemanticCache] Search error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert embedding array to a sortable score
     * Simple hash for sorting purposes
     */
    private function arrayToScore(array $embedding): float
    {
        // Use first few dimensions as a simple hash
        $hash = 0;
        for ($i = 0; $i < min(10, count($embedding)); $i++) {
            $hash += $embedding[$i] * pow(1000, $i);
        }
        return $hash;
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0 || $normB === 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Generate a cache key from embedding
     */
    private function generateCacheKey(array $embedding, string $provider): string
    {
        $hash = hash('sha256', json_encode($embedding));
        return $provider . '_' . substr($hash, 0, 16) . '_' . time();
    }

    /**
     * Cleanup old entries to prevent unbounded growth
     */
    private function cleanupOldEntries(string $provider, int $maxEntries = 100): void
    {
        try {
            $count = $this->redis->zCard($this->cacheKeyPrefix . $provider . ':embeddings');

            if ($count > $maxEntries) {
                // Remove oldest entries
                $toRemove = $this->redis->zRange(
                    $this->cacheKeyPrefix . $provider . ':embeddings',
                    0,
                    $count - $maxEntries - 1
                );

                foreach ($toRemove as $key) {
                    $this->redis->del($this->cacheKeyPrefix . $provider . ':' . $key);
                }

                $this->redis->zDeleteRangeByRank(
                    $this->cacheKeyPrefix . $provider . ':embeddings',
                    0,
                    $count - $maxEntries - 1
                );
            }
        } catch (\Exception $e) {
            // Silently ignore cleanup errors
        }
    }

    /**
     * Invalidate cache for a provider
     */
    public function invalidate(string $provider = 'default'): bool
    {
        if (!$this->redisAvailable) {
            return false;
        }

        try {
            $keys = $this->redis->keys($this->cacheKeyPrefix . $provider . ':*');
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(string $provider = 'default'): array
    {
        if (!$this->redisAvailable) {
            return ['available' => false];
        }

        try {
            $count = $this->redis->zCard($this->cacheKeyPrefix . $provider . ':embeddings');

            return [
                'available' => true,
                'provider' => $provider,
                'entries' => $count,
                'similarity_threshold' => $this->similarityThreshold,
                'default_ttl' => $this->defaultTtl
            ];
        } catch (\Exception $e) {
            return ['available' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate embedding for text (using simple hash as placeholder)
     * In production, this would call an embedding API
     */
    public function generateEmbedding(string $text): array
    {
        // Simple hash-based embedding for demonstration
        // In production, replace with actual embedding API call
        $hash = md5($text);
        $embedding = [];

        for ($i = 0; $i < $this->embeddingDim; $i++) {
            $embedding[] = (ord($hash[$i % strlen($hash)]) / 255) * 2 - 1;
        }

        return $embedding;
    }
}
