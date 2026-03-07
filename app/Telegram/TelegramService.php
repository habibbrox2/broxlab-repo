<?php
declare(strict_types=1);

namespace App\Telegram;

/**
 * TelegramService.php
 * Handles sending messages, documents, and API calls to Telegram Bot API.
 */
class TelegramService
{
    private string $botToken;
    private string $apiUrl;

    public function __construct(string $botToken = '')
    {
        $this->botToken = $botToken;
        $this->apiUrl   = "https://api.telegram.org/bot{$botToken}/";
    }

    /**
     * Send a text message with optional Markdown and inline keyboard.
     */
    public function sendMessage(string $chatId, string $text, ?array $keyboard = null): bool
    {
        $params = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $result = $this->request('sendMessage', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Edit an existing message text.
     */
    public function editMessageText(string $chatId, int $messageId, string $text, ?array $keyboard = null): bool
    {
        $params = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ];

        if ($keyboard !== null) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        $result = $this->request('editMessageText', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Answer a callback query (dismiss the loading spinner on buttons).
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        $params = [
            'callback_query_id' => $callbackQueryId,
            'text'              => $text,
            'show_alert'        => $showAlert ? 'true' : 'false',
        ];
        $result = $this->request('answerCallbackQuery', $params);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Send a document (PDF, etc.) to Telegram.
     */
    public function sendDocument(string $chatId, string $filePath, string $caption = ''): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $params = [
            'chat_id'  => $chatId,
            'document' => new \CURLFile($filePath),
        ];

        if ($caption !== '') {
            $params['caption'] = $caption;
        }

        $result = $this->request('sendDocument', $params, true);
        return (bool)($result['ok'] ?? false);
    }

    /**
     * Get information about a Telegram file (to retrieve file_path for download).
     */
    public function getFile(string $fileId): ?array
    {
        $result = $this->request('getFile', ['file_id' => $fileId]);
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
     * Make an API call to Telegram.
     *
     * @param bool $multipart Use multipart/form-data (for file uploads)
     * @return array Decoded JSON response
     */
    private function request(string $method, array $params, bool $multipart = false): array
    {
        if (!function_exists('curl_init')) {
            error_log('TelegramService: cURL is not available.');
            return ['ok' => false];
        }

        $ch = curl_init($this->apiUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $multipart ? $params : http_build_query($params),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if (!$multipart) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }

        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("TelegramService cURL error [{$method}]: {$curlErr}");
            return ['ok' => false];
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? $decoded : ['ok' => false];
    }
}
