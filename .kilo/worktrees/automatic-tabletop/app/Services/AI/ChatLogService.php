<?php
/**
 * ChatLogService - Error log handling, usage logging, and OCR extraction
 * Extracted from AISystemController.php for modularity
 */

class ChatLogService
{
    /**
     * Resolve path to error log file
     */
    public static function resolveErrorLogPath(): string
    {
        if (defined('BASE_PATH')) {
            return rtrim((string)BASE_PATH, "/\\") . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.log';
        }
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.log';
    }

    /**
     * Read last N lines from a file efficiently
     */
    public static function readLastLines(string $path, int $n): array
    {
        $lines = [];
        $fp = @fopen($path, 'r');
        if (!$fp) return $lines;

        @fseek($fp, 0, SEEK_END);
        $pos = (int)@ftell($fp);
        $buffer = '';
        $lineCount = 0;

        while ($pos > 0 && $lineCount < $n) {
            $chunk = min(4096, $pos);
            $pos -= $chunk;
            @fseek($fp, $pos);
            $buffer = (string)@fread($fp, $chunk) . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        @fclose($fp);
        $all = explode("\n", $buffer);
        if (end($all) === '') array_pop($all);
        return array_slice($all, -$n);
    }

    /**
     * Redact secrets from log lines
     */
    public static function redactSecrets(string $line): string
    {
        $patterns = [
            '/(authorization\s*[:=]\s*)([^\s,;]+)/i',
            '/(api[_-]?key\s*[:=]\s*)([^\s,;]+)/i',
            '/(token\s*[:=]\s*)([^\s,;]+)/i',
            '/(password\s*[:=]\s*)([^\s,;]+)/i',
            '/(DB_PASS\s*[:=]\s*)([^\s,;]+)/i',
        ];
        foreach ($patterns as $p) {
            $line = preg_replace($p, '$1[REDACTED]', $line) ?? $line;
        }
        return $line;
    }

    /**
     * Select recent errors from log lines
     */
    public static function selectRecentErrors(array $lines, int $limit = 20): array
    {
        $out = [];
        foreach (array_reverse($lines) as $line) {
            $line = trim((string)$line);
            if ($line === '') continue;
            $u = strtoupper($line);
            $match = str_contains($u, '[ERROR]') || str_contains($u, '[CRITICAL]') || str_contains($u, '[WARNING]')
                || str_contains($u, 'PHP FATAL') || str_contains($u, 'PHP WARNING') || str_contains($u, 'PHP ERROR');
            if (!$match) continue;
            $line = self::redactSecrets($line);
            if (strlen($line) > 800) {
                $line = substr($line, 0, 800) . '…';
            }
            $out[] = $line;
            if (count($out) >= $limit) break;
        }
        return array_reverse($out);
    }

    /**
     * Build recent conversation text for context
     */
    public static function buildRecentConversationText(array $messages, int $max = 10): string
    {
        $slice = array_slice($messages, -$max);
        $parts = [];
        foreach ($slice as $m) {
            $role = (string)($m['role'] ?? '');
            $content = ChatMessageService::extractText($m['content'] ?? '');
            if ($content === '') continue;
            $label = $role === 'assistant' ? 'Assistant' : 'User';
            $parts[] = $label . ': ' . $content;
        }
        return implode("\n", $parts);
    }

    /**
     * Log AI usage to database
     */
    public static function logUsage(mysqli $mysqli, string $provider, string $model, array $usage, string $status, ?string $error, ?int $userId, array $metadata): void
    {
        $promptTokens = (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $completionTokens = (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        $totalTokens = (int)($usage['total_tokens'] ?? ($promptTokens + $completionTokens));
        $cost = (float)($usage['cost'] ?? 0);
        $requestType = 'chat';
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);

        $stmt = $mysqli->prepare("
            INSERT INTO ai_usage_logs
            (provider_name, model_name, prompt_tokens, completion_tokens, total_tokens, cost, request_type, status, error_message, user_id, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'ssiiidsssis',
            $provider,
            $model,
            $promptTokens,
            $completionTokens,
            $totalTokens,
            $cost,
            $requestType,
            $status,
            $error,
            $userId,
            $metadataJson
        );

        try {
            $stmt->execute();
        } catch (\Exception $e) {
            aiErrorLog('AI usage logging failed: ' . $e->getMessage());
        }
        $stmt->close();
    }

    /**
     * Extract OCR text from images for admin assistant
     */
    public static function extractOCRForAdmin(array $imageRefs): string
    {
        if (empty($imageRefs)) {
            return '';
        }

        $ocrTexts = [];
        require_once dirname(__DIR__, 1) . '/Services/OCRService.php';
        $ocr = new OCRService();

        foreach ($imageRefs as $ref) {
            try {
                $imageData = null;
                if ($ref['is_base64'] ?? false) {
                    $imageData = $ref['url'];
                } elseif (isset($ref['file_path']) && file_exists($ref['file_path'])) {
                    $imageData = base64_encode(file_get_contents($ref['file_path']));
                } elseif ($ref['is_url'] ?? false) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $ref['url'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_FOLLOWLOCATION => true
                    ]);
                    $imageData = curl_exec($ch);
                    curl_close($ch);
                    if ($imageData) {
                        $imageData = base64_encode($imageData);
                    }
                }

                if ($imageData) {
                    $result = $ocr->extractTextFromImage($imageData, ['language' => 'eng']);
                    if (!empty($result['success']) && !empty($result['text'])) {
                        $fileName = $ref['name'] ?? 'Image';
                        $engine = $result['engine'] ?? 'OCR Service';
                        $ocrTexts[] = "**OCR from $fileName** ($engine):\n" . trim($result['text']);
                    } else {
                        aiErrorLog('Admin OCR extraction failed for ' . ($ref['name'] ?? 'Image') . ': ' . ($result['error'] ?? 'Unknown error'));
                    }
                }
            } catch (\Exception $e) {
                aiErrorLog("Admin OCR extraction failed: " . $e->getMessage());
            }
        }

        return implode("\n\n", $ocrTexts);
    }

    /**
     * Get provider model info with remote fetch support
     */
    public static function getProviderModels(AIProvider $aiProvider, string $providerName, array $providers): array
    {
        foreach ($providers as $provider) {
            if (($provider['provider_name'] ?? '') !== $providerName) {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($providerName);
                $models = $config['models'] ?? [];
            }
            if (in_array($providerName, ['fireworks', 'openrouter'], true)) {
                $remote = $aiProvider->fetchRemoteModels($providerName);
                if (!empty($remote)) {
                    $models = $remote;
                }
            }
            return $models;
        }

        $config = AIProvider::getProviderConfig($providerName);
        $models = $config['models'] ?? [];
        if (in_array($providerName, ['fireworks', 'openrouter'], true)) {
            $remote = $aiProvider->fetchRemoteModels($providerName);
            if (!empty($remote)) {
                $models = $remote;
            }
        }
        return $models;
    }
}
