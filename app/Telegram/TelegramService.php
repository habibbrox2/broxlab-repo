<?php
declare(strict_types=1);

namespace App\Telegram;

/**
 * TelegramService.php
 * Handles sending messages, documents, and API calls to Telegram Bot API.
 * Production-ready with retry logic, flood protection, and proper error handling.
 */
class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    // Retry configuration
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_BASE = 1; // seconds

    // Flood protection: max messages per minute per chat
    private const FLOOD_LIMIT_PER_MINUTE = 20;

    // Message length limits (Telegram API limits)
    private const MAX_MESSAGE_LENGTH = 4096;
    private const MAX_CAPTION_LENGTH = 1024;

    public function __construct(string $botToken = '')
    {
        $this->botToken = $botToken;
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}/";
    }

    /**
     * Send a text message with optional Markdown and inline keyboard.
     * Includes message length validation and flood protection.
     */
    public function sendMessage(string $chatId, string $text, ?array $keyboard = null): bool
    {
        // Validate message length
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 3) . '...';
            logError('Telegram message truncated (exceeded ' . self::MAX_MESSAGE_LENGTH . ' chars)', 'WARNING', [
                'chat_id' => $chatId,
                'original_length' => mb_strlen($text),
            ]);
        }

        // Check flood protection
        if (!$this->checkFloodProtection($chatId)) {
            logError('Telegram flood protection triggered', 'WARNING', ['chat_id' => $chatId]);
            return false;
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $result = $this->requestWithRetry('sendMessage', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Edit an existing message text.
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $keyboard = null): bool
    {
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 3) . '...';
        }

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $result = $this->requestWithRetry('editMessageText', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Answer a callback query (dismiss the loading spinner on buttons).
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert ? 'true' : 'false',
        ];

        $result = $this->requestWithRetry('answerCallbackQuery', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Send a document (PDF, etc.) to Telegram.
     */
    public function sendDocument(string $chatId, string $filePath, string $caption = ''): bool
    {
        if (!file_exists($filePath)) {
            logError('Telegram sendDocument: File not found', 'ERROR', ['path' => $filePath]);
            return false;
        }

        // Validate file size (max 50MB for documents)
        $fileSize = filesize($filePath);
        if ($fileSize > 50 * 1024 * 1024) {
            logError('Telegram sendDocument: File too large', 'ERROR', [
                'path' => $filePath,
                'size' => $fileSize,
            ]);
            return false;
        }

        // Validate caption length
        if (mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            $caption = mb_substr($caption, 0, self::MAX_CAPTION_LENGTH - 3) . '...';
        }

        $params = [
            'chat_id' => $chatId,
            'document' => new \CURLFile($filePath),
        ];

        if ($caption !== '') {
            $params['caption'] = $caption;
        }

        $result = $this->requestWithRetry('sendDocument', $params, true);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Get information about a Telegram file (to retrieve file_path for download).
     */
    public function getFile(string $fileId): ?array
    {
        $result = $this->requestWithRetry('getFile', ['file_id' => $fileId]);
        if (!empty($result['ok']) && !empty($result['result'])) {
            return $result['result'];
        }
        return null;
    }

    /**
     * Send a typing indicator (chat action).
     */
    public function sendTyping(string $chatId): void
    {
        $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
    }

    /**
     * Send an upload indicator.
     */
    public function sendUploadAction(string $chatId): void
    {
        $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => 'upload_document']);
    }

    /**
     * Make an API call to Telegram with retry logic and exponential backoff.
     */
    private function requestWithRetry(string $method, array $params, bool $multipart = false): array
    {
        $lastResult = ['ok' => false];

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $result = $this->request($method, $params, $multipart);

            if (!empty($result['ok'])) {
                return $result;
            }

            $lastResult = $result;

            // Check if we should retry
            $errorCode = $result['error_code'] ?? 0;
            $description = $result['description'] ?? '';

            // Don't retry on client errors (4xx) except 429 (rate limit)
            if ($errorCode >= 400 && $errorCode < 500 && $errorCode !== 429) {
                logError("Telegram API error (no retry): {$method}", 'ERROR', [
                    'error_code' => $errorCode,
                    'description' => $description,
                ]);
                return $result;
            }

            // For 429 (rate limit), wait longer
            if ($errorCode === 429) {
                $retryAfter = (int)($result['parameters']['retry_after'] ?? 5);
                logError("Telegram rate limit hit, waiting {$retryAfter}s", 'WARNING', [
                    'method' => $method,
                    'retry_after' => $retryAfter,
                ]);
                sleep($retryAfter);
                continue;
            }

            // Exponential backoff for other errors
            if ($attempt < self::MAX_RETRIES - 1) {
                $delay = self::RETRY_DELAY_BASE * pow(2, $attempt);
                logError("Telegram API retry {$attempt}/" . self::MAX_RETRIES . " for {$method}", 'WARNING', [
                    'delay' => $delay,
                    'error_code' => $errorCode,
                    'description' => $description,
                ]);
                sleep($delay);
            }
        }

        logError("Telegram API failed after " . self::MAX_RETRIES . " retries: {$method}", 'ERROR', [
            'last_result' => $lastResult,
        ]);

        return $lastResult;
    }

    /**
     * Make a single API call to Telegram.
     *
     * @param bool $multipart Use multipart/form-data (for file uploads)
     * @return array Decoded JSON response
     */
    private function request(string $method, array $params, bool $multipart = false): array
    {
        if (!function_exists('curl_init')) {
            logError('TelegramService: cURL is not available.', 'ERROR');
            return ['ok' => false, 'error_code' => 0, 'description' => 'cURL not available'];
        }

        $ch = curl_init($this->apiUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipart ? $params : http_build_query($params),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!$multipart) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            logError("TelegramService cURL error [{$method}]: {$curlErr}", 'ERROR');
            return ['ok' => false, 'error_code' => 0, 'description' => $curlErr];
        }

        $decoded = json_decode((string)$response, true);

        if (!is_array($decoded)) {
            logError("TelegramService invalid JSON response [{$method}]", 'ERROR', [
                'http_code' => $httpCode,
                'response' => substr((string)$response, 0, 500),
            ]);
            return ['ok' => false, 'error_code' => $httpCode, 'description' => 'Invalid JSON response'];
        }

        // Log non-ok responses
        if (empty($decoded['ok'])) {
            logError("Telegram API error [{$method}]", 'WARNING', [
                'error_code' => $decoded['error_code'] ?? $httpCode,
                'description' => $decoded['description'] ?? 'Unknown error',
            ]);
        }

        return $decoded;
    }

    /**
     * Simple flood protection: limit messages per chat per minute.
     */
    private function checkFloodProtection(string $chatId): bool
    {
        // This is a simple in-memory check. For production, use Redis or database.
        static $floodTracker = [];
        $now = time();
        $key = "flood_{$chatId}";

        // Clean old entries
        if (isset($floodTracker[$key])) {
            $floodTracker[$key] = array_filter(
                $floodTracker[$key],
                fn($timestamp) => $timestamp > $now - 60
            );
        } else {
            $floodTracker[$key] = [];
        }

        // Check limit
        if (count($floodTracker[$key]) >= self::FLOOD_LIMIT_PER_MINUTE) {
            return false;
        }

        // Record this message
        $floodTracker[$key][] = $now;

        return true;
    }
}
