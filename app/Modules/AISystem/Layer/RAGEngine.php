<?php

/**
 * RAGEngine - Retrieval-Augmented Generation Engine
 * 
 * Provides semantic search capabilities using keyword matching and
 * integrates with the AI Knowledge Base for context-aware responses.
 */

require_once __DIR__ . '/../../Models/AIKnowledge.php';

class RAGEngine
{
    private $mysqli;
    private $knowledgeModel;
    private $embeddingModel;

    // Maximum tokens to use from retrieved context
    const MAX_CONTEXT_TOKENS = 2000;
    const MAX_RETRIEVAL_RESULTS = 5;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->knowledgeModel = new AIKnowledge($mysqli);
    }

    /**
     * Retrieve relevant knowledge for a given query
     * 
     * @param string $query The user's question/query
     * @param array $options Search options (limit, categories, etc.)
     * @return array Retrieved context documents
     */
    public function retrieve(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? self::MAX_RETRIEVAL_RESULTS;
        $categories = $options['categories'] ?? null;

        // First, try semantic search using embeddings if available
        $embeddingResults = $this->semanticSearch($query, $limit);

        // Also do keyword-based search as fallback/enhancement
        $keywordResults = $this->keywordSearch($query, $limit, $categories);

        // Merge and deduplicate results
        $merged = $this->mergeResults($embeddingResults, $keywordResults, $limit);

        return $merged;
    }

    /**
     * Semantic search using embeddings
     * Uses cosine similarity if embeddings exist
     */
    private function semanticSearch(string $query, int $limit): array
    {
        // Try to generate embedding for query
        $queryEmbedding = $this->generateEmbedding($query);

        if (!$queryEmbedding) {
            return [];
        }

        // Get all knowledge items with embeddings
        $items = $this->knowledgeModel->list(100, 0, null, true);

        $scored = [];
        foreach ($items as $item) {
            if (!empty($item['embedding'])) {
                $similarity = $this->cosineSimilarity(
                    $queryEmbedding,
                    json_decode($item['embedding'], true)
                );
                $item['similarity'] = $similarity;
                $item['search_type'] = 'semantic';
                $scored[] = $item;
            }
        }

        // Sort by similarity and return top results
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Keyword-based search
     */
    private function keywordSearch(string $query, int $limit, ?array $categories = null): array
    {
        // Extract keywords from query
        $keywords = $this->extractKeywords($query);

        if (empty($keywords)) {
            return [];
        }

        // Get all active knowledge items
        $items = $this->knowledgeModel->list(100, 0, null, true);

        $scored = [];
        foreach ($items as $item) {
            $score = 0;
            $content = strtolower($item['content'] ?? '');
            $title = strtolower($item['title'] ?? '');

            foreach ($keywords as $keyword) {
                // Title matches are weighted higher
                if (stripos($title, $keyword) !== false) {
                    $score += 3;
                }
                // Content matches
                if (stripos($content, $keyword) !== false) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $item['keyword_score'] = $score;
                $item['search_type'] = 'keyword';
                $scored[] = $item;
            }
        }

        // Sort by score and return
        usort($scored, fn($a, $b) => $b['keyword_score'] <=> $a['keyword_score']);

        return array_slice($scored, 0, $limit);
    }

    /**
     * Merge semantic and keyword results, deduplicating
     */
    private function mergeResults(array $semantic, array $keyword, int $limit): array
    {
        $seen = [];
        $merged = [];

        // First add semantic results (higher quality)
        foreach ($semantic as $item) {
            $id = $item['id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $merged[] = $item;
            }
        }

        // Then add keyword results
        foreach ($keyword as $item) {
            $id = $item['id'];
            if (!isset($seen[$id]) && count($merged) < $limit) {
                $seen[$id] = true;
                $merged[] = $item;
            }
        }

        return array_slice($merged, 0, $limit);
    }

    /**
     * Extract keywords from query
     */
    private function extractKeywords(string $query): array
    {
        // Remove common stop words
        $stopWords = [
            'the',
            'a',
            'an',
            'and',
            'or',
            'but',
            'in',
            'on',
            'at',
            'to',
            'for',
            'of',
            'with',
            'by',
            'from',
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
            'shall',
            'can',
            'need',
            'what',
            'which',
            'who',
            'whom',
            'this',
            'that',
            'these',
            'those',
            'am',
            'your',
            'you',
            'i',
            'we',
            'they',
            'he',
            'she',
            'it',
            'how',
            'why',
            'when',
            'where',
            'all',
            'each',
            'every',
            'both',
            'few',
            'more',
            'most',
            'other',
            'some',
            'such',
            'no',
            'nor',
            'not',
            'only',
            'own',
            'same',
            'so',
            'than',
            'too',
            'very',
            'just',
            'also',
            'now',
            'here'
        ];

        // Clean and tokenize
        $query = strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query));
        $tokens = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out stop words and short tokens
        $keywords = array_filter($tokens, fn($t) => strlen($t) > 2 && !in_array($t, $stopWords));

        // Return unique keywords
        return array_values(array_unique($keywords));
    }

    /**
     * Generate embedding for text using available AI providers
     * Falls back to simple hash-based representation if no provider available
     */
    private function generateEmbedding(string $text): ?array
    {
        // Try to use OpenAI or other embedding providers
        // This is a simplified version - in production, integrate with actual API

        // For now, generate a simple deterministic embedding
        // In production, replace with actual embedding API call
        return $this->simpleEmbedding($text);
    }

    /**
     * Simple hash-based embedding for fallback
     * Not semantic but provides deterministic results
     */
    private function simpleEmbedding(string $text): array
    {
        $tokens = preg_split('/\s+/', strtolower($text));
        $embedding = array_fill(0, 128, 0.0);

        foreach ($tokens as $i => $token) {
            $hash = crc32($token);
            for ($j = 0; $j < 128; $j++) {
                $embedding[$j] += (($hash >> ($j % 32)) & 1) ? 1.0 : -0.1;
            }
        }

        // Normalize
        $norm = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        if ($norm > 0) {
            $embedding = array_map(fn($x) => $x / $norm, $embedding);
        }

        return $embedding;
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }

    /**
     * Build context string from retrieved documents
     */
    public function buildContext(array $documents): string
    {
        if (empty($documents)) {
            return '';
        }

        $context = "## Relevant Knowledge Base Articles:\n\n";

        foreach ($documents as $i => $doc) {
            $title = htmlspecialchars($doc['title'] ?? 'Untitled');
            $content = $doc['content'] ?? '';
            $category = $doc['category'] ?? 'general';

            // Truncate content if too long
            if (strlen($content) > 500) {
                $content = substr($content, 0, 500) . '...';
            }

            $context .= "### [" . ($i + 1) . "] {$title} (Category: {$category})\n";
            $context .= $content . "\n\n";
        }

        return $context;
    }

    /**
     * Augment prompt with RAG context
     */
    public function augmentPrompt(string $userQuery, string $systemPrompt): string
    {
        $documents = $this->retrieve($userQuery);

        if (empty($documents)) {
            return $systemPrompt;
        }

        $context = $this->buildContext($documents);

        $augmented = $systemPrompt . "\n\n" . $context;

        // Check token limit (rough estimate: 1 token ≈ 4 characters)
        $maxChars = self::MAX_CONTEXT_TOKENS * 4;
        if (strlen($augmented) > $maxChars) {
            $augmented = substr($augmented, 0, $maxChars) . "...\n\n[Context truncated]";
        }

        return $augmented;
    }

    /**
     * Store embedding for a knowledge item
     */
    public function storeEmbedding(int $knowledgeId, string $content): bool
    {
        $embedding = $this->generateEmbedding($content);

        if (!$embedding) {
            return false;
        }

        // Update the knowledge item with embedding
        // Note: This requires adding an 'embedding' column to the table
        $json = json_encode($embedding);

        try {
            // Check if column exists, if not add it
            $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN embedding JSON DEFAULT NULL");
        } catch (Exception $e) {
            // Column might already exist
        }

        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET embedding = ? WHERE id = ?");
        $stmt->bind_param('si', $json, $knowledgeId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Re-index all knowledge base items with embeddings
     */
    public function reindexAll(): array
    {
        $items = $this->knowledgeModel->list(1000, 0, null, false);

        $indexed = 0;
        $failed = 0;

        foreach ($items as $item) {
            $content = ($item['title'] ?? '') . ' ' . ($item['content'] ?? '');
            if ($this->storeEmbedding($item['id'], $content)) {
                $indexed++;
            } else {
                $failed++;
            }
        }

        return [
            'total' => count($items),
            'indexed' => $indexed,
            'failed' => $failed
        ];
    }
}
