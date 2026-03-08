<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * EnhancedDuplicateChecker.php
 * Advanced duplicate detection using multiple algorithms:
 * - URL exact match
 * - Title similarity (Levenshtein, Jaro-Winkler)
 * - Content hashing (SimHash, MD5)
 * - Semantic similarity
 */
class EnhancedDuplicateChecker
{
    private ?\mysqli $mysqli = null;
    private float $titleThreshold = 0.85;
    private float $contentThreshold = 0.80;
    private array $stopWords = [];
    private bool $useSimHash = true;
    private int $simHashBits = 64;

    public function __construct(\mysqli $mysqli, array $config = [])
    {
        $this->mysqli = $mysqli;
        $this->titleThreshold = $config['title_threshold'] ?? 0.85;
        $this->contentThreshold = $config['content_threshold'] ?? 0.80;
        $this->useSimHash = $config['use_simhash'] ?? true;
        $this->simHashBits = $config['simhash_bits'] ?? 64;
        
        $this->stopWords = $this->getDefaultStopWords();
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
     * Check for duplicates using all methods
     */
    public function checkDuplicate(array $data): array
    {
        $result = [
            'is_duplicate' => false,
            'reason' => null,
            'existing_id' => null,
            'existing_title' => null,
            'similarity' => 0,
            'method' => null,
        ];

        // 1. Check exact URL match
        if (!empty($data['url'])) {
            $urlCheck = $this->checkUrlExists($data['url'], $data['id'] ?? null);
            if ($urlCheck['exists']) {
                return array_merge($result, [
                    'is_duplicate' => true,
                    'reason' => 'URL already exists',
                    'existing_id' => $urlCheck['id'],
                    'method' => 'url_exact',
                ]);
            }
        }

        // 2. Check title similarity
        if (!empty($data['title'])) {
            $titleCheck = $this->checkTitleSimilarity($data['title'], $data['id'] ?? null);
            if ($titleCheck['is_similar']) {
                return array_merge($result, [
                    'is_duplicate' => true,
                    'reason' => "Title is {$titleCheck['similarity']}% similar to existing article",
                    'existing_id' => $titleCheck['id'],
                    'existing_title' => $titleCheck['title'],
                    'similarity' => $titleCheck['similarity'],
                    'method' => 'title_similarity',
                ]);
            }
        }

        // 3. Check content using SimHash
        if (!empty($data['content'])) {
            $contentCheck = $this->checkContentSimilarity($data['content'], $data['id'] ?? null);
            if ($contentCheck['is_similar']) {
                return array_merge($result, [
                    'is_duplicate' => true,
                    'reason' => "Content is {$contentCheck['similarity']}% similar to existing article",
                    'existing_id' => $contentCheck['id'],
                    'existing_title' => $contentCheck['title'],
                    'similarity' => $contentCheck['similarity'],
                    'method' => 'content_simhash',
                ]);
            }
        }

        // 4. Check exact content hash
        if (!empty($data['content'])) {
            $hashCheck = $this->checkContentHash($data['content'], $data['id'] ?? null);
            if ($hashCheck['exists']) {
                return array_merge($result, [
                    'is_duplicate' => true,
                    'reason' => 'Exact content already exists',
                    'existing_id' => $hashCheck['id'],
                    'method' => 'content_exact',
                ]);
            }
        }

        return $result;
    }

    /**
     * Check if URL exists
     */
    public function checkUrlExists(string $url, ?int $excludeId = null): array
    {
        $sql = "SELECT id, title FROM autocontent_articles WHERE url = ?";
        $params = [$url];
        $types = 's';

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'exists' => true,
                'id' => (int)$row['id'],
                'title' => $row['title'],
            ];
        }

        $stmt->close();
        return ['exists' => false];
    }

    /**
     * Check title similarity
     */
    public function checkTitleSimilarity(string $title, ?int $excludeId = null): array
    {
        // Get all titles (with limit for performance)
        $sql = "SELECT id, title FROM autocontent_articles WHERE 1=1";
        $params = [];
        $types = '';

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $sql .= " ORDER BY id DESC LIMIT 1000";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $normalizedTitle = $this->normalizeText($title);

        while ($row = $result->fetch_assoc()) {
            $existingTitle = $row['title'] ?? '';
            
            // Calculate multiple similarity metrics
            $levenshtein = $this->levenshteinSimilarity($normalizedTitle, $this->normalizeText($existingTitle));
            $jaro = $this->jaroWinklerSimilarity($normalizedTitle, $this->normalizeText($existingTitle));
            $token = $this->tokenSimilarity($normalizedTitle, $this->normalizeText($existingTitle));
            
            // Use the best similarity score
            $similarity = max($levenshtein, $jaro, $token);

            if ($similarity >= $this->titleThreshold) {
                $stmt->close();
                return [
                    'is_similar' => true,
                    'id' => (int)$row['id'],
                    'title' => $existingTitle,
                    'similarity' => round($similarity * 100),
                ];
            }
        }

        $stmt->close();
        return ['is_similar' => false];
    }

    /**
     * Check content similarity using SimHash
     */
    public function checkContentSimilarity(string $content, ?int $excludeId = null): array
    {
        if (!$this->useSimHash) {
            return ['is_similar' => false];
        }

        // Calculate SimHash for current content
        $currentHash = $this->calculateSimHash($content);

        // Get articles with pre-computed hashes
        $sql = "SELECT id, title, simhash FROM autocontent_articles WHERE simhash IS NOT NULL AND simhash != ''";
        $params = [];
        $types = '';

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $sql .= " ORDER BY id DESC LIMIT 500";

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $existingHash = $row['simhash'] ?? '';
            
            if (empty($existingHash)) {
                continue;
            }

            // Calculate Hamming distance
            $distance = $this->hammingDistance($currentHash, $existingHash);
            $similarity = 1 - ($distance / $this->simHashBits);

            if ($similarity >= $this->contentThreshold) {
                $stmt->close();
                return [
                    'is_similar' => true,
                    'id' => (int)$row['id'],
                    'title' => $row['title'],
                    'similarity' => round($similarity * 100),
                ];
            }
        }

        $stmt->close();
        return ['is_similar' => false];
    }

    /**
     * Check exact content hash
     */
    public function checkContentHash(string $content, ?int $excludeId = null): array
    {
        $hash = md5($this->normalizeText($content));

        $sql = "SELECT id, title FROM autocontent_articles WHERE content_hash = ?";
        $params = [$hash];
        $types = 's';

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $stmt->close();
            return [
                'exists' => true,
                'id' => (int)$row['id'],
                'title' => $row['title'],
            ];
        }

        $stmt->close();
        return ['exists' => false];
    }

    /**
     * Calculate SimHash for content
     */
    public function calculateSimHash(string $text): string
    {
        // Normalize and tokenize
        $tokens = $this->tokenize($text);
        
        if (empty($tokens)) {
            return str_repeat('0', $this->simHashBits);
        }

        // Initialize hash array
        $v = array_fill(0, $this->simHashBits, 0);

        // Process each token
        foreach ($tokens as $token) {
            $tokenHash = $this->hashToken($token);
            
            for ($i = 0; $i < $this->simHashBits; $i++) {
                if (isset($tokenHash[$i]) && $tokenHash[$i] === '1') {
                    $v[$i]++;
                } else {
                    $v[$i]--;
                }
            }
        }

        // Convert to binary string
        $hash = '';
        foreach ($v as $bit) {
            $hash .= $bit >= 0 ? '1' : '0';
        }

        return $hash;
    }

    /**
     * Hash a token to binary string
     */
    private function hashToken(string $token): string
    {
        $hash = md5($token);
        $binary = '';
        
        for ($i = 0; $i < strlen($hash); $i++) {
            $binary .= str_pad(decbin(hexdec($hash[$i])), 4, '0', STR_PAD_LEFT);
        }
        
        return substr($binary, 0, $this->simHashBits);
    }

    /**
     * Calculate Hamming distance between two hashes
     */
    public function hammingDistance(string $hash1, string $hash2): int
    {
        $distance = 0;
        $length = min(strlen($hash1), strlen($hash2));
        
        for ($i = 0; $i < $length; $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $distance++;
            }
        }
        
        return $distance;
    }

    /**
     * Calculate Levenshtein similarity
     */
    public function levenshteinSimilarity(string $s1, string $s2): float
    {
        if ($s1 === $s2) {
            return 1.0;
        }
        
        $len1 = strlen($s1);
        $len2 = strlen($s2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        
        $distance = levenshtein($s1, $s2);
        $maxLen = max($len1, $len2);
        
        return 1 - ($distance / $maxLen);
    }

    /**
     * Calculate Jaro-Winkler similarity
     */
    public function jaroWinklerSimilarity(string $s1, string $s2): float
    {
        $jaro = $this->jaroSimilarity($s1, $s2);
        
        // Calculate common prefix (up to 4 chars)
        $prefix = 0;
        for ($i = 0; $i < min(4, strlen($s1), strlen($s2)); $i++) {
            if ($s1[$i] === $s2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }
        
        // Calculate Jaro-Winkler
        return $jaro + ($prefix * 0.1 * (1 - $jaro));
    }

    /**
     * Calculate Jaro similarity
     */
    private function jaroSimilarity(string $s1, string $s2): float
    {
        $len1 = strlen($s1);
        $len2 = strlen($s2);
        
        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }
        
        $matchDistance = (int)floor(max($len1, $len2) / 2) - 1;
        
        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;
        
        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);
            
            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $s1[$i] !== $s2[$j]) {
                    continue;
                }
                
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }
        
        if ($matches === 0) {
            return 0.0;
        }
        
        // Count transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) {
                continue;
            }
            
            while (!$s2Matches[$k]) {
                $k++;
            }
            
            if ($s1[$i] !== $s2[$k]) {
                $transpositions++;
            }
            
            $k++;
        }
        
        return (
            ($matches / $len1) +
            ($matches / $len2) +
            (($matches - $transpositions / 2) / $matches)
        ) / 3;
    }

    /**
     * Calculate token-based similarity (Jaccard)
     */
    public function tokenSimilarity(string $s1, string $s2): float
    {
        $tokens1 = array_unique(explode(' ', $s1));
        $tokens2 = array_unique(explode(' ', $s2));
        
        if (empty($tokens1) || empty($tokens2)) {
            return 0.0;
        }
        
        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));
        
        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Normalize text for comparison
     */
    public function normalizeText(string $text): string
    {
        // Remove HTML
        $text = strip_tags($text);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove stop words
        $words = explode(' ', $text);
        $words = array_diff($words, $this->stopWords);
        
        return implode(' ', $words);
    }

    /**
     * Tokenize text into words
     */
    private function tokenize(string $text): array
    {
        // Normalize
        $text = $this->normalizeText($text);
        
        // Split into tokens
        $tokens = preg_split('/[^a-z0-9]+/', $text);
        
        // Filter short tokens and stop words
        return array_filter($tokens, fn($t) => strlen($t) > 2 && !in_array($t, $this->stopWords));
    }

    /**
     * Get default stop words
     */
    private function getDefaultStopWords(): array
    {
        return [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
            'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
            'could', 'should', 'may', 'might', 'must', 'can', 'this', 'that',
            'these', 'those', 'it', 'its', 'they', 'them', 'their', 'we', 'us',
            'our', 'you', 'your', 'he', 'she', 'him', 'her', 'his', 'i', 'me',
            'my', 'not', 'no', 'so', 'just', 'about', 'also', 'more', 'most',
            'some', 'any', 'all', 'each', 'every', 'both', 'few', 'other',
            'such', 'then', 'than', 'very', 'only', 'own', 'same', 'after',
            'before', 'here', 'there', 'when', 'where', 'why', 'how', 'what',
        ];
    }

    /**
     * Set similarity thresholds
     */
    public function setThresholds(float $title, float $content): self
    {
        $this->titleThreshold = $title;
        $this->contentThreshold = $content;
        return $this;
    }

    /**
     * Enable/disable SimHash
     */
    public function setSimHashEnabled(bool $enabled): self
    {
        $this->useSimHash = $enabled;
        return $this;
    }

    /**
     * Save hash to database for article
     */
    public function saveHash(int $articleId, string $content): bool
    {
        $simHash = $this->calculateSimHash($content);
        $contentHash = md5($this->normalizeText($content));

        $stmt = $this->mysqli->prepare(
            "UPDATE autocontent_articles SET simhash = ?, content_hash = ? WHERE id = ?"
        );
        $stmt->bind_param('ssi', $simHash, $contentHash, $articleId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Find potential duplicates for all articles
     */
    public function findAllDuplicates(int $limit = 50): array
    {
        $sql = "SELECT a1.id as id1, a1.title as title1, a1.url as url1,
                       a2.id as id2, a2.title as title2, a2.url as url2,
                       a1.simhash as hash1, a2.simhash as hash2
                FROM autocontent_articles a1
                INNER JOIN autocontent_articles a2 ON a1.id < a2.id
                WHERE a1.simhash IS NOT NULL AND a2.simhash IS NOT NULL
                ORDER BY a1.id DESC
                LIMIT ?";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $duplicates = [];

        while ($row = $result->fetch_assoc()) {
            $distance = $this->hammingDistance($row['hash1'], $row['hash2']);
            $similarity = 1 - ($distance / $this->simHashBits);

            if ($similarity >= $this->contentThreshold) {
                $duplicates[] = [
                    'article1' => [
                        'id' => $row['id1'],
                        'title' => $row['title1'],
                        'url' => $row['url1'],
                    ],
                    'article2' => [
                        'id' => $row['id2'],
                        'title' => $row['title2'],
                        'url' => $row['url2'],
                    ],
                    'similarity' => round($similarity * 100) . '%',
                    'hamming_distance' => $distance,
                ];
            }
        }

        $stmt->close();
        return $duplicates;
    }
}
