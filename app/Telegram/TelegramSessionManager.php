<?php
declare(strict_types = 1)
;

namespace App\Telegram;

use mysqli;

/**
 * TelegramSessionManager.php
 * Manages user states for multi-step Telegram conversations.
 */
class TelegramSessionManager
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS telegram_sessions (
            chat_id VARCHAR(50) PRIMARY KEY,
            state VARCHAR(50) NOT NULL,
            data TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->mysqli->query($sql);
    }

    public function getState(string $chatId): ?string
    {
        $stmt = $this->mysqli->prepare("SELECT state FROM telegram_sessions WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row['state'] ?? null;
    }

    public function getData(string $chatId): array
    {
        $stmt = $this->mysqli->prepare("SELECT data FROM telegram_sessions WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return isset($row['data']) ? json_decode($row['data'], true) : [];
    }

    public function setState(string $chatId, string $state, array $data = []): void
    {
        $dataJson = json_encode($data);
        $stmt = $this->mysqli->prepare("INSERT INTO telegram_sessions (chat_id, state, data) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)");
        $stmt->bind_param("sss", $chatId, $state, $dataJson);
        $stmt->execute();
    }

    public function clear(string $chatId): void
    {
        $stmt = $this->mysqli->prepare("DELETE FROM telegram_sessions WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
    }
}
