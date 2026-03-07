<?php

declare(strict_types=1);

namespace App\Modules\AutoBlog;

/**
 * Sends AutoBlog notifications to Telegram channels.
 */
class TelegramNotifier
{
    private bool $enabled;
    private string $token;
    private string $chatId;

    public function __construct(array $config = [])
    {
        $this->enabled = (bool)($config['enabled'] ?? false);
        $this->token = $config['bot_token'] ?? '';
        $this->chatId = $config['chat_id'] ?? '';

        if (empty($this->token) || empty($this->chatId)) {
            $this->enabled = false;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Send a formatted article message.
     */
    public function sendArticle(array $article, string $template): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $text = strtr($template, [
            '{title}' => $article['title'] ?? '',
            '{excerpt}' => $article['excerpt'] ?? '',
            '{url}' => $article['url'] ?? '',
            '{source}' => $article['source'] ?? '',
        ]);

        return $this->sendMessage($text);
    }

    /**
     * Send a raw Telegram message.
     */
    public function sendMessage(string $text): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => false,
        ];

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($result === false || $status >= 400) {
            error_log("TelegramNotifier error: {$error} (HTTP {$status})");
            return false;
        }

        return true;
    }
}
