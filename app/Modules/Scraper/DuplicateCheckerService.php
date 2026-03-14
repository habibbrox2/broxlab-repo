<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

use mysqli;

/**
 * DuplicateCheckerService.php
 * Checks for duplicate content in the Auto Content articles
 */
class DuplicateCheckerService
{
    private mysqli $mysqli;
    private float $similarityThreshold;

    public function __construct(mysqli $mysqli, float $threshold = 0.8)
    {
        $this->mysqli = $mysqli;
        $this->similarityThreshold = $threshold;
    }

    /**
     * Check if URL already exists
     */
    public function urlExists(string $url, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM autocontent_articles WHERE url = ?";
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

        $exists = false;
        if ($result && $row = $result->fetch_assoc()) {
            $exists = (int)$row['count'] > 0;
        }

        $stmt->close();
        return $exists;
    }

    /**
     * Check if title already exists
     */
    public function titleExists(string $title, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM autocontent_articles WHERE title = ?";
        $params = [$title];
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

        $exists = false;
        if ($result && $row = $result->fetch_assoc()) {
            $exists = (int)$row['count'] > 0;
        }

        $stmt->close();
        return $exists;
    }

    /**
     * Check if content is similar to existing articles
     */
    public function isDuplicate(string $content, ?int $excludeId = null): ?array
    {
        // Simple exact match check
        $sql = "SELECT id, title, original_content FROM autocontent_articles WHERE 1=1";
        $params = [];
        $types = '';

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $contentHash = $this->normalizeContent($content);

        while ($row = $result->fetch_assoc()) {
            $existingContent = $row['original_content'] ?? '';
            $similarity = $this->calculateSimilarity($content, $existingContent);

            if ($similarity >= $this->similarityThreshold) {
                $stmt->close();
                return [
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'similarity' => $similarity
                ];
            }
        }

        $stmt->close();
        return null;
    }

    /**
     * Calculate similarity between two texts (0-1)
     */
    public function calculateSimilarity(string $text1, string $text2): float
    {
        if (empty($text1) || empty($text2)) {
            return 0.0;
        }

        // Simple word-based similarity
        $words1 = array_unique(str_word_count(strtolower($this->normalizeContent($text1)), 1));
        $words2 = array_unique(str_word_count(strtolower($this->normalizeContent($text2)), 1));

        if (empty($words1) || empty($words2)) {
            return 0.0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_unique(array_merge($words1, $words2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Normalize content for comparison
     */
    private function normalizeContent(string $content): string
    {
        // Remove HTML tags
        $content = strip_tags($content);

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Convert to lowercase
        $content = strtolower(trim($content));

        // Remove common words
        $commonWords = [
            'the',
            'a',
            'an',
            'is',
            'are',
            'was',
            'were',
            'be',
            'been',
            'being',
            'have',
            'has',
            'had',
            'do',
            'does',
            'did',
            'will',
            'would',
            'could',
            'should',
            'may',
            'might',
            'must',
            'can',
            'to',
            'of',
            'in',
            'for',
            'on',
            'with',
            'at',
            'by',
            'from',
            'and',
            'or',
            'but',
            'if',
            'then',
            'so',
            'as',
            'that',
            'this'
        ];

        $words = explode(' ', $content);
        $words = array_diff($words, $commonWords);

        return implode(' ', $words);
    }

    /**
     * Full duplicate check - combines URL, title, and content
     */
    public function checkDuplicate(array $data): array
    {
        $result = [
            'is_duplicate' => false,
            'reason' => null,
            'existing_id' => null,
            'existing_title' => null
        ];

        // Check URL
        if (!empty($data['url']) && $this->urlExists($data['url'], $data['id'] ?? null)) {
            $result['is_duplicate'] = true;
            $result['reason'] = 'URL already exists';
            return $result;
        }

        // Check title
        if (!empty($data['title']) && $this->titleExists($data['title'], $data['id'] ?? null)) {
            $result['is_duplicate'] = true;
            $result['reason'] = 'Title already exists';
            return $result;
        }

        // Check content similarity
        if (!empty($data['content'])) {
            $similar = $this->isDuplicate($data['content'], $data['id'] ?? null);
            if ($similar) {
                $result['is_duplicate'] = true;
                $result['reason'] = 'Content is ' . round($similar['similarity'] * 100) . '% similar to existing article';
                $result['existing_id'] = $similar['id'];
                $result['existing_title'] = $similar['title'];
                return $result;
            }
        }

        return $result;
    }

    /**
     * Get all potential duplicates for review
     */
    public function getPotentialDuplicates(): array
    {
        $sql = "SELECT a1.id as id1, a1.title as title1, a1.url as url1,
                       a2.id as id2, a2.title as title2, a2.url as url2
                FROM autocontent_articles a1
                INNER JOIN autocontent_articles a2 ON a1.id < a2.id
                WHERE a1.status != 'published' AND a2.status != 'published'
                ORDER BY a1.id DESC
                LIMIT 50";

        $result = $this->mysqli->query($sql);

        $duplicates = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $similarity = $this->calculateSimilarity(
                    $row['title1'] ?? '',
                    $row['title2'] ?? ''
                );

                if ($similarity >= 0.7) {
                    $duplicates[] = [
                        'article1' => ['id' => $row['id1'], 'title' => $row['title1'], 'url' => $row['url1']],
                        'article2' => ['id' => $row['id2'], 'title' => $row['title2'], 'url' => $row['url2']],
                        'similarity' => round($similarity * 100) . '%'
                    ];
                }
            }
        }

        return $duplicates;
    }
}
