<?php

/**
 * RAGEngine - Retrieval-Augmented Generation Engine
 * 
 * Provides semantic search capabilities using keyword matching and
 * integrates with the AI Knowledge Base for context-aware responses.
 */

// Load AIKnowledge model from the application's Models directory
require_once dirname(__DIR__, 3) . '/Models/AIKnowledge.php';

class RAGEngine
{
    private $mysqli;
    private $knowledgeModel;
    private $embeddingModel;
    private static $embeddingColumnChecked = false;

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
     * Uses Node.js service (sentence-transformers) for actual semantic embeddings
     */
    private function generateEmbedding(string $text): ?array
    {
        // Prefer multi-provider PHP-native embedding (OpenAI, Ollama, Cohere, etc.)
        $embedding = $this->generateEmbeddingMultiProvider($text, 'openai');

        if ($embedding !== null) {
            return $embedding;
        }

        // Final fallback to simple deterministic embedding
        return $this->simpleEmbedding($text);
    }

    /**
     * Generate embeddings using multiple AI providers
     * Tries each provider in order until one succeeds
     * 
     * @param string $text Text to embed
     * @param string $preferredProvider Preferred provider (openai, anthropic, ollama, etc.)
     * @return array|null Embedding vector or null if all fail
     */
    public function generateEmbeddingMultiProvider(string $text, string $preferredProvider = 'openai'): ?array
    {
        $providers = [];

        // Add preferred provider first
        if (!empty($preferredProvider)) {
            $providers[] = $preferredProvider;
        }

        // Add fallback providers
        $fallbacks = ['openai', 'anthropic', 'ollama', 'cohere', 'voyage'];
        foreach ($fallbacks as $fb) {
            if (!in_array($fb, $providers)) {
                $providers[] = $fb;
            }
        }

        foreach ($providers as $provider) {
            $embedding = $this->generateEmbeddingForProvider($text, $provider);
            if ($embedding !== null) {
                return $embedding;
            }
        }

        // Final fallback to simple embedding
        return $this->simpleEmbedding($text);
    }

    /**
     * Generate embedding for a specific provider
     * 
     * @param string $text Text to embed
     * @param string $provider Provider name
     * @return array|null Embedding or null if provider unavailable
     */
    private function generateEmbeddingForProvider(string $text, string $provider): ?array
    {
        $embeddingModel = null;
        $apiKey = null;

        switch ($provider) {
            case 'openai':
                $embeddingModel = 'text-embedding-3-small';
                $apiKey = $this->getApiKey('openai');
                break;
            case 'cohere':
                $embeddingModel = 'embed-english-v3.0';
                $apiKey = $this->getApiKey('cohere');
                break;
            case 'voyage':
                $embeddingModel = 'voyage-2';
                $apiKey = $this->getApiKey('voyage');
                break;
            case 'ollama':
                return $this->generateEmbeddingOllama($text);
            case 'anthropic':
                // Anthropic doesn't have embedding API, skip
                return null;
            default:
                return null;
        }

        if (empty($apiKey)) {
            return null;
        }

        return $this->callEmbeddingAPI($provider, $embeddingModel, $apiKey, $text);
    }

    /**
     * Get API key for a provider
     */
    private function getApiKey(string $provider): ?string
    {
        $aiProvider = new \AIProvider($this->mysqli);
        $settings = $aiProvider->getSettings();

        $keyMap = [
            'openai' => 'openai_api_key',
            'anthropic' => 'anthropic_api_key',
            'cohere' => 'cohere_api_key',
            'voyage' => 'voyage_api_key',
            'ollama' => null // Ollama doesn't need API key (local)
        ];

        $key = $keyMap[$provider] ?? null;
        if ($key && !empty($settings[$key])) {
            return $settings[$key];
        }

        return null;
    }

    /**
     * Call embedding API for a specific provider
     */
    private function callEmbeddingAPI(string $provider, string $model, string $apiKey, string $text): ?array
    {
        $endpoints = [
            'openai' => 'https://api.openai.com/v1/embeddings',
            'cohere' => 'https://api.cohere.ai/v1/embed',
            'voyage' => 'https://api.voyageai.com/v1/embeddings'
        ];

        $url = $endpoints[$provider] ?? null;
        if (!$url) {
            return null;
        }

        $headers = [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ];

        $payload = [];
        switch ($provider) {
            case 'openai':
                $payload = [
                    'model' => $model,
                    'input' => $text
                ];
                break;
            case 'cohere':
                $payload = [
                    'model' => $model,
                    'texts' => [$text]
                ];
                break;
            case 'voyage':
                $payload = [
                    'model' => $model,
                    'input' => $text
                ];
                break;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);

        // Parse response based on provider
        switch ($provider) {
            case 'openai':
                return $data['data'][0]['embedding'] ?? null;
            case 'cohere':
                return $data['embeddings'][0] ?? null;
            case 'voyage':
                return $data['data'][0]['embedding'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Generate embedding using Ollama (local)
     */
    private function generateEmbeddingOllama(string $text): ?array
    {
        $aiProvider = new \AIProvider($this->mysqli);
        $ollamaProvider = $aiProvider->getByName('ollama');

        if (!$ollamaProvider || empty($ollamaProvider['base_url'])) {
            return null;
        }

        $baseUrl = rtrim($ollamaProvider['base_url'], '/');
        $url = $baseUrl . '/api/embeddings';

        // Get embedding model from settings
        $settings = $aiProvider->getSettings();
        $model = $settings['ollama_embedding_model'] ?? 'nomic-embed-text';

        $payload = [
            'model' => $model,
            'prompt' => $text
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['embedding'] ?? null;
    }

    /**
     * Re-index all knowledge items with embeddings from any available provider
     * 
     * @param string $preferredProvider Preferred provider for embeddings
     * @return array Results with success count and errors
     */
    public function reindexAllWithProvider(string $preferredProvider = 'openai'): array
    {
        $items = $this->knowledgeModel->list(1000, 0, null, false);
        $success = 0;
        $errors = [];

        foreach ($items as $item) {
            $text = $item['title'] . ' ' . $item['content'];
            $embedding = $this->generateEmbeddingMultiProvider($text, $preferredProvider);

            if ($embedding !== null) {
                $this->knowledgeModel->updateEmbedding($item['id'], json_encode($embedding));
                $success++;
            } else {
                $errors[] = 'Failed to generate embedding for item #' . $item['id'];
            }
        }

        return [
            'total' => count($items),
            'success' => $success,
            'errors' => $errors
        ];
    }

    // Node.js embedding path removed — PHP multi-provider embedding used instead

    /**
     * Simple hash-based embedding for fallback
     * Not semantic but provides deterministic results
     * NOTE: all-MiniLM-L6-v2 produces 384-dimension embeddings
     */
    private function simpleEmbedding(string $text): array
    {
        $tokens = preg_split('/\s+/', strtolower($text));
        // Use 384 dimensions to match sentence-transformers/all-MiniLM-L6-v2
        $embedding = array_fill(0, 384, 0.0);

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

        // Only attempt ALTER TABLE once (avoids repeated exceptions)
        if (!self::$embeddingColumnChecked) {
            $checkResult = $this->mysqli->query("SHOW COLUMNS FROM ai_knowledge_base LIKE 'embedding'");
            if ($checkResult && $checkResult->num_rows === 0) {
                $this->mysqli->query("ALTER TABLE ai_knowledge_base ADD COLUMN embedding JSON DEFAULT NULL");
            }
            self::$embeddingColumnChecked = true;
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

    /**
     * Process uploaded file (PDF or image) and extract text for indexing/querying
     * @param array $file PHP file upload array ($_FILES)
     * @return array{success: bool, text: string, error?: string}
     */
    public function processFile(array $file): array
    {
        if (empty($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'text' => '', 'error' => 'File upload error'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $tempPath = $file['tmp_name'];

        try {
            switch ($extension) {
                case 'pdf':
                    return $this->extractTextFromPDF($tempPath);
                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'gif':
                case 'webp':
                    return $this->extractTextFromImage($tempPath);
                case 'txt':
                case 'md':
                case 'csv':
                    $text = file_get_contents($tempPath);
                    return ['success' => true, 'text' => $text];
                default:
                    return ['success' => false, 'text' => '', 'error' => "Unsupported file type: $extension"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'text' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract text from PDF using Node.js or fallback to pdftotext command
     * @param string $pdfPath Path to PDF file
     * @return array{success: bool, text: string, error?: string}
     */
    public function extractTextFromPDF(string $pdfPath): array
    {
        // Prefer PHP PDF parser library when available (smalot/pdfparser)
        if (class_exists('\\Smalot\\PdfParser\\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($pdfPath);
                $text = trim($pdf->getText());
                if (!empty($text)) {
                    return ['success' => true, 'text' => $text];
                }
            } catch (Exception $e) {
                // Continue to system fallback
                aiErrorLog('Smalot PDF parser failed: ' . $e->getMessage());
            }
        }

        // Fallback: basic PDF text extraction using pdftotext command (poppler)
        $tempText = sys_get_temp_dir() . '/pdf_extract_' . uniqid() . '.txt';
        $cmd = "pdftotext -layout \"" . escapeshellcmd($pdfPath) . "\" \"" . escapeshellcmd($tempText) . "\" 2>&1";

        @exec($cmd, $output, $return);

        if (file_exists($tempText)) {
            $text = file_get_contents($tempText);
            @unlink($tempText);

            if (!empty($text)) {
                return ['success' => true, 'text' => $text];
            }
        }

        return ['success' => false, 'text' => '', 'error' => 'Could not extract text from PDF'];
    }

    // Node.js PDF extraction removed — rely on system pdftotext or PHP libraries

    /**
     * Extract text from image using OCR.space API (web hosting compatible)
     * @param string $imagePath Path to image file
     * @return array{success: bool, text: string, error?: string}
     */
    public function extractTextFromImage(string $imagePath): array
    {
        try {
            // Read image file and encode as base64
            if (!file_exists($imagePath)) {
                return ['success' => false, 'text' => '', 'error' => 'Image file not found'];
            }

            $imageData = base64_encode(file_get_contents($imagePath));

            // Use OCR.space API
            $postData = [
                'apikey' => 'K81289438988957', // Paid tier API key
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true'
            ];

            // Create temp file for upload
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            file_put_contents($tempFile, base64_decode($imageData));
            // Detect image MIME type from the temp file
            $detectedMime = @mime_content_type($tempFile);
            $mimeTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            $ext = strtolower(pathinfo($tempFile, PATHINFO_EXTENSION));
            $mime = ($detectedMime && in_array($detectedMime, $mimeTypes, true)) ? $detectedMime : ($mimeTypes[$ext] ?? 'image/png');
            $ext = array_search($mime, $mimeTypes);
            $filename = $ext ? 'ocr.' . $ext : 'ocr.png';
            $postData['file'] = new CURLFile($tempFile, $mime, $filename);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.ocr.space/parse/image',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            @unlink($tempFile);

            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                if (!empty($result['ParsedResults'][0]['ParsedText'])) {
                    $text = trim($result['ParsedResults'][0]['ParsedText']);
                    if (!empty($text)) {
                        return ['success' => true, 'text' => $text];
                    }
                }
                if (!empty($result['ErrorMessage'])) {
                    return ['success' => false, 'text' => '', 'error' => implode(', ', $result['ErrorMessage'])];
                }
            }

            return ['success' => false, 'text' => '', 'error' => 'OCR API request failed'];
        } catch (Exception $e) {
            return ['success' => false, 'text' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Multimodal query - accept text, image, or PDF as query input
     * @param string $query User query
     * @param array|null $file Optional file (PDF/image) to include in query
     * @return string Extracted text from file + query
     */
    public function prepareMultimodalQuery(string $query, ?array $file = null): string
    {
        $queryText = $query;

        // If file provided, extract text from it and combine with query
        if (!empty($file) && $file['error'] === UPLOAD_ERR_OK) {
            $extracted = $this->processFile($file);

            if ($extracted['success'] && !empty($extracted['text'])) {
                $queryText = "Query: " . $query . "\n\nDocument content:\n" . $extracted['text'];
            }
        }

        return $queryText;
    }
}
