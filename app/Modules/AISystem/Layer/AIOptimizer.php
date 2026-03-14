<?php

namespace App\Modules\AISystem\Layer;

/**
 * AI Token Optimizer
 * Helps keep context size manageable by summarizing old messages or truncating safely.
 * Provides multiple token estimation methods for better accuracy.
 */
class AIOptimizer
{
    private $maxTokensLimit;
    private $estimationMethod;

    // Token estimation methods
    const METHOD_SIMPLE = 'simple';        // chars / 4 (fast, less accurate)
    const METHOD_ADVANCED = 'advanced';    // Improved algorithm
    const METHOD_CL100K = 'cl100k';        // Approximate cl100k_base (GPT-3.5/4)
    const METHOD_R50K = 'r50k';            // Approximate r50k_base (GPT-3)

    // Characters per token estimates for different methods
    private $charsPerToken = [
        self::METHOD_SIMPLE => 4.0,
        self::METHOD_ADVANCED => 3.5,  // Better estimate for English
        self::METHOD_CL100K => 4.0,    // For GPT-3.5/4 models
        self::METHOD_R50K => 4.0       // For GPT-3 models
    ];

    public function __construct(int $maxTokensLimit = 6000, string $method = self::METHOD_ADVANCED)
    {
        $this->maxTokensLimit = $maxTokensLimit;
        $this->estimationMethod = method_exists($this, 'estimateTokens' . ucfirst($method))
            ? $method
            : self::METHOD_ADVANCED;
    }

    /**
     * Optimize the message array to fit within context limits
     * 
     * @param array $messages The chat history
     * @return array The optimized chat history
     */
    public function optimize(array $messages): array
    {
        if (empty($messages)) {
            return [];
        }

        // Separate system message
        $systemMessage = null;
        if ($messages[0]['role'] === 'system') {
            $systemMessage = array_shift($messages);
        }

        // Calculate current rough token count
        $totalTokens = $this->estimateTokens($systemMessage ? $systemMessage['content'] : '');

        $optimizedMessages = [];

        // Always include the latest messages first, work backwards
        $messagesRev = array_reverse($messages);

        foreach ($messagesRev as $msg) {
            $msgTokens = $this->estimateTokens($msg['content'] ?? '');

            // If adding this message exceeds the limit, stop adding old history
            if ($totalTokens + $msgTokens > $this->maxTokensLimit) {
                break;
            }

            $totalTokens += $msgTokens;
            // Unshift to maintain chronological order
            array_unshift($optimizedMessages, $msg);
        }

        // Put the system message back at the beginning
        if ($systemMessage) {
            array_unshift($optimizedMessages, $systemMessage);
        }

        return $optimizedMessages;
    }

    /**
     * Optimize by summarizing older messages instead of truncating
     * 
     * @param array $messages The chat history
     * @param int $summaryTokenBudget Tokens to reserve for summary
     * @return array Optimized messages with summary
     */
    public function optimizeWithSummary(array $messages, int $summaryTokenBudget = 500): array
    {
        if (empty($messages)) {
            return [];
        }

        // First, try regular optimization
        $optimized = $this->optimize($messages);

        // If we still have too many messages, add a summary
        $totalTokens = $this->estimateMessagesTokens($optimized);

        if ($totalTokens > $this->maxTokensLimit) {
            // Create summary of older messages
            $messagesToSummarize = array_slice($messages, 0, -3); // Keep last 3

            if (!empty($messagesToSummarize)) {
                $summary = $this->createSummary($messagesToSummarize);
                $summaryTokens = $this->estimateTokens($summary);

                // Ensure summary fits
                if ($summaryTokens <= $summaryTokenBudget) {
                    // Replace old messages with summary
                    $recentMessages = array_slice($messages, -3);

                    $optimized = [
                        [
                            'role' => 'system',
                            'content' => ($messages[0]['role'] ?? '') === 'system'
                                ? $messages[0]['content']
                                : 'You are a helpful AI assistant.'
                        ],
                        [
                            'role' => 'system',
                            'content' => '[SUMMARY OF PREVIOUS CONVERSATION] ' . $summary
                        ]
                    ];

                    $optimized = array_merge($optimized, $recentMessages);
                }
            }
        }

        return $optimized;
    }

    /**
     * Estimate tokens for a given text
     * 
     * @param string $text Text to estimate
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        $method = 'estimateTokens' . ucfirst($this->estimationMethod);
        return call_user_func([$this, $method], $text);
    }

    /**
     * Estimate tokens using simple method (chars / 4)
     */
    private function estimateTokensSimple(string $text): int
    {
        return (int)ceil(strlen($text) / $this->charsPerToken[self::METHOD_SIMPLE]);
    }

    /**
     * Estimate tokens using advanced algorithm
     * Accounts for:
     * - Special characters and punctuation
     * - Numbers (tend to use more tokens)
     * - Whitespace patterns
     */
    private function estimateTokensAdvanced(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Count tokens using multiple approaches and average
        $approaches = [];

        // Approach 1: Basic character count
        $charCount = strlen($text);

        // Approach 2: Word count (English words avg 5 chars + space)
        $wordCount = preg_match_all('/\b\w+\b/', $text, $matches);

        // Approach 3: Token-like splitting
        $tokenLikeCount = preg_match_all('/[^\s]+/', $text, $matches);

        // Approach 4: Account for special patterns
        // URLs, emails, code blocks use more tokens
        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $text, $matches);
        $codeBlockCount = preg_match_all('/```[\s\S]*?```/', $text, $matches);

        // Calculate weighted estimate
        // Base: ~4 chars per token for plain text
        $baseEstimate = $charCount / 4;

        // Adjust for special content
        // URLs add overhead (~20 tokens per URL)
        $urlPenalty = $urlCount * 20;

        // Code blocks: ~3 chars per token (more compact)
        $codePenalty = 0;
        if ($codeBlockCount > 0) {
            $codeChars = strlen(implode('', $matches[0]));
            $codePenalty = - ($codeChars / 4); // Code is more efficient
            $baseEstimate += $codeChars / 3;  // But needs more tokens overall
        }

        // Numbers: ~3 chars per token on average
        $numberCount = preg_match_all('/\d+/', $text, $matches);
        $numberPenalty = $numberCount * 2; // Extra tokens for numbers

        $finalEstimate = $baseEstimate + $urlPenalty + $codePenalty + $numberPenalty;

        return max(1, (int)ceil($finalEstimate));
    }

    /**
     * Estimate tokens using cl100k_base approximation (GPT-3.5/4)
     */
    private function estimateTokensCl100k(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Use tiktoken-like approximation
        // This is a simplified version of the cl100k_base encoding

        // Split into tokens (approximate)
        $tokens = [];

        // Simple regex-based tokenization
        $pattern = '/(\'[s+t]+|\'[mre]+|[\p{L}\p{N}]+|[\s\p{P}]+)/u';
        preg_match_all($pattern, $text, $matches);

        return count($matches[0]);
    }

    /**
     * Estimate tokens using r50k_base approximation (GPT-3)
     */
    private function estimateTokensR50k(string $text): int
    {
        // Similar to cl100k but slightly different
        return $this->estimateTokensCl100k($text);
    }

    /**
     * Estimate total tokens for a messages array
     * 
     * @param array $messages Array of messages
     * @return int Total estimated tokens
     */
    public function estimateMessagesTokens(array $messages): int
    {
        $total = 0;

        foreach ($messages as $msg) {
            $content = is_array($msg) ? ($msg['content'] ?? '') : (string)$msg;
            $total += $this->estimateTokens($content);

            // Add overhead for message structure
            // role field + formatting (~4 tokens per message)
            $total += 4;
        }

        return $total;
    }

    /**
     * Create a simple summary of messages
     * 
     * @param array $messages Messages to summarize
     * @return string Summary text
     */
    private function createSummary(array $messages): string
    {
        $summary = [];

        foreach ($messages as $msg) {
            if (!isset($msg['content'])) {
                continue;
            }

            $role = $msg['role'] ?? 'user';
            $content = $msg['content'];

            // Truncate each message
            if (strlen($content) > 100) {
                $content = substr($content, 0, 100) . '...';
            }

            $summary[] = "{$role}: {$content}";
        }

        return implode(' | ', $summary);
    }

    /**
     * Get max tokens limit
     */
    public function getMaxTokensLimit(): int
    {
        return $this->maxTokensLimit;
    }

    /**
     * Set max tokens limit
     */
    public function setMaxTokensLimit(int $limit): void
    {
        $this->maxTokensLimit = max(1, $limit);
    }

    /**
     * Get current estimation method
     */
    public function getEstimationMethod(): string
    {
        return $this->estimationMethod;
    }

    /**
     * Set estimation method
     */
    public function setEstimationMethod(string $method): bool
    {
        if (in_array($method, [self::METHOD_SIMPLE, self::METHOD_ADVANCED, self::METHOD_CL100K, self::METHOD_R50K])) {
            $this->estimationMethod = $method;
            return true;
        }
        return false;
    }

    /**
     * Calculate how many more tokens can be added
     * 
     * @param array $messages Current messages
     * @return int Available token budget
     */
    public function getAvailableTokenBudget(array $messages): int
    {
        $used = $this->estimateMessagesTokens($messages);
        return max(0, $this->maxTokensLimit - $used);
    }

    /**
     * Check if messages need optimization
     * 
     * @param array $messages Messages to check
     * @return bool True if optimization needed
     */
    public function needsOptimization(array $messages): bool
    {
        return $this->estimateMessagesTokens($messages) > $this->maxTokensLimit;
    }
}
