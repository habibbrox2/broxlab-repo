<?php
namespace App\Modules\AISystem\Layer;

/**
 * AI Token Optimizer
 * Helps keep context size manageable by summarizing old messages or truncating safely.
 */
class AIOptimizer
{
    private $maxTokensLimit;
    private $charsPerToken = 4; // Absolute rough estimate

    public function __construct(int $maxTokensLimit = 6000)
    {
        $this->maxTokensLimit = $maxTokensLimit;
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

    private function estimateTokens(string $text): int
    {
        return (int)ceil(strlen($text) / $this->charsPerToken);
    }
}
