<?php

/**
 * KB Self-Healer
 * 
 * Monitors and improves Knowledge Base quality automatically
 * Can use either PHP AI (AgentClient) or Node.js AI service
 * 
 * Features:
 * - Quality scoring and monitoring
 * - Auto-improvement of low-quality entries
 * - Outdated content detection
 * - Duplicate detection and merging
 * - Usage-based content suggestions
 */

require_once __DIR__ . '/../Modules/AISystem/AgentClient.php';

class KBSelfHealer
{
    private $mysqli;
    private $agentClient;
    private $nodeJsUrl;
    private $useNodeJs;
    private $autoImprove;
    private $qualityThreshold;
    private $lookbackDays;

    /**
     * @param mysqli $mysqli Database connection
     * @param array $options Configuration options
     */
    public function __construct($mysqli, $options = [])
    {
        $this->mysqli = $mysqli;
        $this->agentClient = new AgentClient($mysqli);

        // Configuration
        $this->useNodeJs = $options['useNodeJs'] ?? (getenv('KB_USE_NODEJS') === 'true');
        $this->nodeJsUrl = $options['nodeJsUrl'] ?? (getenv('NODEJS_AI_SERVER_URL') ?: (getenv('APP_URL') ?: 'http://localhost:3001'));
        $this->autoImprove = $options['autoImprove'] ?? (getenv('KB_AUTO_IMPROVE') === 'true');
        $this->qualityThreshold = $options['qualityThreshold'] ?? 50;
        $this->lookbackDays = $options['lookbackDays'] ?? 30;
    }

    /**
     * Run KB health check
     * @return array Health check results
     */
    public function runHealthCheck(): array
    {
        // Get all KB entries
        $entries = $this->getKBEntries();

        $results = [
            'total' => count($entries),
            'healthy' => 0,
            'needsImprovement' => 0,
            'outdated' => 0,
            'duplicates' => 0,
            'improved' => 0,
            'errors' => []
        ];

        // Analyze each entry
        foreach ($entries as $entry) {
            try {
                $analysis = $this->analyzeEntry($entry);

                if ($analysis['qualityScore'] >= $this->qualityThreshold) {
                    $results['healthy']++;
                } elseif ($analysis['isOutdated']) {
                    $results['outdated']++;
                    if ($this->autoImprove) {
                        $this->improveEntry($entry, $analysis);
                        $results['improved']++;
                    }
                } else {
                    $results['needsImprovement']++;
                    if ($this->autoImprove) {
                        $this->improveEntry($entry, $analysis);
                        $results['improved']++;
                    }
                }
            } catch (Exception $error) {
                $results['errors'][] = [
                    'id' => $entry['id'],
                    'error' => $error->getMessage()
                ];
            }
        }

        // Check for duplicates
        $duplicates = $this->detectDuplicates($entries);
        $results['duplicates'] = count($duplicates);

        logActivity("KB Health Check", "knowledge_base", 0, $results, 'info');

        return $results;
    }

    /**
     * Get all KB entries
     */
    private function getKBEntries(): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT * FROM ai_knowledge_base ORDER BY updated_at DESC"
        );
        $stmt->execute();

        $result = $stmt->get_result();
        $entries = [];

        while ($row = $result->fetch_assoc()) {
            $entries[] = $row;
        }

        return $entries;
    }

    /**
     * Analyze a KB entry
     */
    private function analyzeEntry(array $entry): array
    {
        $analysis = [
            'qualityScore' => 0,
            'isOutdated' => false,
            'issues' => [],
            'suggestions' => []
        ];

        // Check content length
        $contentLength = strlen($entry['content'] ?? '');

        if ($contentLength < 50) {
            $analysis['issues'][] = 'Content too short';
            $analysis['qualityScore'] += 10;
        } elseif ($contentLength > 5000) {
            $analysis['issues'][] = 'Content too long - consider splitting';
            $analysis['qualityScore'] += 30;
        } else {
            $analysis['qualityScore'] += 40;
        }

        // Check title
        $titleLength = strlen($entry['title'] ?? '');
        if ($titleLength < 5) {
            $analysis['issues'][] = 'Title missing or too short';
            $analysis['qualityScore'] += 20;
        } else {
            $analysis['qualityScore'] += 20;
        }

        // Check category
        if (empty($entry['category'])) {
            $analysis['issues'][] = 'Missing category';
            $analysis['qualityScore'] += 15;
        } else {
            $analysis['qualityScore'] += 15;
        }

        // Check if outdated
        if (!empty($entry['updated_at'])) {
            $updatedDate = new DateTime($entry['updated_at']);
            $daysSinceUpdate = (time() - $updatedDate->getTimestamp()) / (60 * 60 * 24);

            if ($daysSinceUpdate > $this->lookbackDays * 2) {
                $analysis['isOutdated'] = true;
                $analysis['issues'][] = "Content is " . floor($daysSinceUpdate) . " days old";
            } elseif ($daysSinceUpdate > $this->lookbackDays) {
                $analysis['qualityScore'] -= 10;
            }
        }

        // Check formatting
        $content = $entry['content'] ?? '';
        if (strpos($content, "\n\n") !== false || strpos($content, '•') !== false || strpos($content, '- ') !== false) {
            $analysis['qualityScore'] += 15;
        } else {
            $analysis['issues'][] = 'No formatting detected';
        }

        // Check tags
        if (!empty($entry['tags'])) {
            $analysis['qualityScore'] += 10;
        } else {
            $analysis['issues'][] = 'No tags/keywords';
        }

        // Generate suggestions
        if ($analysis['qualityScore'] < $this->qualityThreshold) {
            $analysis['suggestions'] = $this->generateSuggestions($analysis['issues'], $entry);
        }

        return $analysis;
    }

    /**
     * Generate improvement suggestions
     */
    private function generateSuggestions(array $issues, array $entry): array
    {
        $suggestions = [];

        if (in_array('Content too short', $issues)) {
            $suggestions[] = 'Expand content with more details and examples';
        }
        if (in_array('Content too long', $issues)) {
            $suggestions[] = 'Split into multiple entries or summarize key points';
        }
        if (in_array('Title missing or too short', $issues)) {
            $suggestions[] = 'Improve title to be more descriptive (5+ words)';
        }
        if (in_array('No formatting detected', $issues)) {
            $suggestions[] = 'Add bullet points, headers, or structured formatting';
        }
        if (in_array('No tags/keywords', $issues)) {
            $suggestions[] = 'Add relevant tags for better searchability';
        }

        return $suggestions;
    }

    /**
     * Improve a KB entry using AI
     */
    private function improveEntry(array $entry, array $analysis): bool
    {
        $prompt = $this->buildImprovePrompt($entry, $analysis);

        try {
            if ($this->useNodeJs) {
                $improved = $this->callNodeJsAI('/api/ai/chat', [
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'provider' => 'auto'
                ]);

                $result = json_decode($improved['content'] ?? '{}', true);
            } else {
                $messages = [['role' => 'user', 'content' => $prompt]];
                $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);
                $result = json_decode($response, true);
            }

            // Update entry if we got valid results
            if (!empty($result['title']) && !empty($result['content'])) {
                return $this->updateKBEntry($entry['id'], [
                    'title' => $result['title'],
                    'content' => $result['content'],
                    'category' => $result['category'] ?? $entry['category'],
                    'tags' => $result['tags'] ?? $entry['tags']
                ]);
            }

            return false;
        } catch (Exception $error) {
            logError("KB improve failed for entry " . $entry['id'] . ": " . $error->getMessage());
            return false;
        }
    }

    /**
     * Build improvement prompt
     */
    private function buildImprovePrompt(array $entry, array $analysis): string
    {
        return "You are a Knowledge Base expert. Improve the following KB entry.

Current Issues: " . implode(', ', $analysis['issues']) . "

Original Title: {$entry['title']}
Original Content: {$entry['content']}
Category: " . ($entry['category'] ?? 'General') . "

Provide improved version as JSON:
{
  \"title\": \"Improved title\",
  \"content\": \"Improved content\",
  \"category\": \"Category\",
  \"tags\": [\"tag1\", \"tag2\"]
}

Respond only with valid JSON.";
    }

    /**
     * Update KB entry
     */
    private function updateKBEntry(int $id, array $data): bool
    {
        $tags = is_array($data['tags'] ?? null) ? json_encode($data['tags']) : ($data['tags'] ?? null);

        $stmt = $this->mysqli->prepare(
            "UPDATE ai_knowledge_base 
             SET title = ?, content = ?, category = ?, tags = ?, quality_score = 100, updated_at = NOW()
             WHERE id = ?"
        );

        $stmt->bind_param('ssssi', $data['title'], $data['content'], $data['category'], $tags, $id);

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            logActivity("KB Entry Auto-Improved", "knowledge_base", $id, ['title' => $data['title']], 'success');
        }

        return $result;
    }

    /**
     * Detect duplicate entries
     */
    private function detectDuplicates(array $entries): array
    {
        $duplicates = [];
        $titles = [];

        foreach ($entries as $entry) {
            $normalizedTitle = strtolower(trim($entry['title'] ?? ''));

            if (isset($titles[$normalizedTitle])) {
                $duplicates[] = [
                    'entry1' => $titles[$normalizedTitle],
                    'entry2' => $entry['id'],
                    'reason' => 'Similar title'
                ];
            } else {
                $titles[$normalizedTitle] = $entry['id'];
            }
        }

        return $duplicates;
    }

    /**
     * Merge duplicate entries
     */
    public function mergeDuplicates(int $keepId, int $removeId): bool
    {
        // Get content from both entries
        $stmt = $this->mysqli->prepare("SELECT content FROM ai_knowledge_base WHERE id = ?");
        $stmt->bind_param('i', $keepId);
        $stmt->execute();
        $keepContent = $stmt->get_result()->fetch_assoc()['content'] ?? '';
        $stmt->close();

        $stmt = $this->mysqli->prepare("SELECT content FROM ai_knowledge_base WHERE id = ?");
        $stmt->bind_param('i', $removeId);
        $stmt->execute();
        $removeContent = $stmt->get_result()->fetch_assoc()['content'] ?? '';
        $stmt->close();

        // Merge content (keep both, just append)
        $mergedContent = $keepContent . "\n\n---\n\n" . $removeContent;

        // Update keep entry
        $stmt = $this->mysqli->prepare("UPDATE ai_knowledge_base SET content = ? WHERE id = ?");
        $stmt->bind_param('si', $mergedContent, $keepId);
        $result = $stmt->execute();
        $stmt->close();

        // Delete duplicate
        if ($result) {
            $stmt = $this->mysqli->prepare("DELETE FROM ai_knowledge_base WHERE id = ?");
            $stmt->bind_param('i', $removeId);
            $stmt->execute();
            $stmt->close();

            logActivity("KB Duplicates Merged", "knowledge_base", $keepId, [
                'kept' => $keepId,
                'removed' => $removeId
            ], 'success');
        }

        return $result ?? false;
    }

    /**
     * Get content suggestions based on usage
     */
    public function getSuggestions(): array
    {
        // Get unsuccessful searches from logs or feedback
        $stmt = $this->mysqli->query(
            "SELECT query, COUNT(*) as count 
             FROM ai_knowledge_usage 
             WHERE successful = 0 
             GROUP BY query 
             ORDER BY count DESC 
             LIMIT 10"
        );

        $unsuccessfulQueries = [];
        while ($row = $stmt->fetch_assoc()) {
            $unsuccessfulQueries[] = $row['query'];
        }

        $suggestions = [];

        foreach (array_slice($unsuccessfulQueries, 0, 5) as $query) {
            $suggestion = $this->generateContentSuggestion($query);
            if ($suggestion) {
                $suggestions[] = $suggestion;
            }
        }

        return $suggestions;
    }

    /**
     * Generate content suggestion for a query
     */
    private function generateContentSuggestion(string $query): ?array
    {
        $prompt = "A user searched for \"$query\" but found no results. 
Suggest a KB entry title and outline.

Provide JSON:
{
  \"suggestedTitle\": \"Title\",
  \"suggestedContent\": \"Outline\",
  \"suggestedCategory\": \"Category\",
  \"suggestedTags\": [\"tag1\"]
}";

        try {
            if ($this->useNodeJs) {
                $response = $this->callNodeJsAI('/api/ai/chat', [
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'provider' => 'auto'
                ]);

                $result = json_decode($response['content'] ?? '{}', true);
            } else {
                $messages = [['role' => 'user', 'content' => $prompt]];
                $response = $this->agentClient->chat($messages, 'openrouter', null, null, false);
                $result = json_decode($response, true);
            }

            if (!empty($result['suggestedTitle'])) {
                return [
                    'query' => $query,
                    'suggestedTitle' => $result['suggestedTitle'],
                    'suggestedContent' => $result['suggestedContent'],
                    'suggestedCategory' => $result['suggestedCategory'],
                    'suggestedTags' => $result['suggestedTags'],
                    'generatedAt' => date('Y-m-d H:i:s')
                ];
            }

            return null;
        } catch (Exception $error) {
            return null;
        }
    }

    /**
     * Add suggested content to KB
     */
    public function addSuggestedContent(array $suggestion): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO ai_knowledge_base (title, content, category, tags, quality_score, created_at, updated_at)
             VALUES (?, ?, ?, ?, 50, NOW(), NOW())"
        );

        $tags = json_encode($suggestion['suggestedTags'] ?? []);

        $stmt->bind_param(
            'ssss',
            $suggestion['suggestedTitle'],
            $suggestion['suggestedContent'],
            $suggestion['suggestedCategory'],
            $tags
        );

        $result = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        if ($result) {
            logActivity("KB Suggestion Added", "knowledge_base", $insertId, [
                'title' => $suggestion['suggestedTitle']
            ], 'success');
        }

        return $insertId ?: null;
    }

    /**
     * Call Node.js AI API
     */
    private function callNodeJsAI(string $endpoint, array $data): array
    {
        $ch = curl_init($this->nodeJsUrl . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Node.js API returned status $httpCode");
        }

        $result = json_decode($response, true);

        return $result['content'] ?? $result;
    }

    /**
     * Get KB statistics
     */
    public function getStats(): array
    {
        $stats = [];

        // Total entries
        $result = $this->mysqli->query("SELECT COUNT(*) as total FROM ai_knowledge_base");
        $stats['total'] = $result->fetch_assoc()['total'] ?? 0;

        // Average quality
        $result = $this->mysqli->query("SELECT AVG(quality_score) as avg_quality FROM ai_knowledge_base WHERE quality_score IS NOT NULL");
        $stats['avgQuality'] = round($result->fetch_assoc()['avg_quality'] ?? 0, 1);

        // Low quality entries
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM ai_knowledge_base WHERE quality_score < ?");
        $stmt->bind_param('i', $this->qualityThreshold);
        $stmt->execute();
        $stats['lowQuality'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt->close();

        // Outdated entries
        $days = $this->lookbackDays;
        $stmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as count FROM ai_knowledge_base 
             WHERE updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $stats['outdated'] = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
        $stmt->close();

        // Categories
        $result = $this->mysqli->query(
            "SELECT category, COUNT(*) as count FROM ai_knowledge_base GROUP BY category ORDER BY count DESC"
        );
        $stats['categories'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['categories'][$row['category']] = $row['count'];
        }

        return $stats;
    }
}
