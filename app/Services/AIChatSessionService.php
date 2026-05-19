<?php

namespace App\Services;

use AIChatModel;
use AuthManager;
use mysqli;

class AIChatSessionService
{
    private mysqli $mysqli;
    private AIChatModel $chatModel;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->chatModel = new AIChatModel($mysqli);
    }

    public function resolveSessionState(array $input, bool $isAdmin): array
    {
        $context = $isAdmin ? 'admin' : 'public';
        $userId = null;

        if ($isAdmin) {
            $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
        }

        $guestToken = null;
        if (!$isAdmin) {
            $rawGuestToken = trim((string)($input['visitorToken'] ?? ''));
            $guestToken = $rawGuestToken !== '' ? $rawGuestToken : null;
        }

        $sessionKey = trim((string)($input['session_key'] ?? $input['sessionKey'] ?? ''));
        if ($sessionKey === '') {
            $sessionKey = null;
        }

        $sessionImageKey = null;
        if ($sessionKey !== null) {
            $sessionImageKey = $context . '_session_' . $sessionKey;
        } elseif ($userId) {
            $sessionImageKey = $context . '_user_' . (int)$userId;
        } elseif ($guestToken !== null) {
            $sessionImageKey = $context . '_visitor_' . $guestToken;
        }

        return [
            'context' => $context,
            'user_id' => $userId ? (int)$userId : null,
            'guest_token' => $guestToken,
            'session_key' => $sessionKey,
            'image_context_key' => $sessionImageKey,
        ];
    }

    public function buildSessionPayload(array $sessionState, ?int $conversationId = null): array
    {
        $context = (string)($sessionState['context'] ?? 'public');
        $userId = isset($sessionState['user_id']) ? (int)$sessionState['user_id'] : null;
        $guestToken = $sessionState['guest_token'] ?? null;
        $sessionKey = $sessionState['session_key'] ?? null;

        $conversation = null;
        if ($conversationId) {
            $conversation = $this->chatModel->getConversationByIdForActor($conversationId, $userId, $guestToken, $context);
        }

        if (!$conversation && $sessionKey !== null) {
            $conversation = $this->chatModel->getConversationForSession($userId, $guestToken, $sessionKey, $context);
        }

        $messages = [];
        if ($conversation && !empty($conversation['id'])) {
            $messages = $this->chatModel->getMessages((int)$conversation['id']);
        }

        return [
            'success' => true,
            'session_key' => $sessionKey,
            'conversation' => $conversation,
            'conversation_id' => $conversation['id'] ?? null,
            'status' => $conversation['status'] ?? null,
            'title' => $conversation['title'] ?? null,
            'messages' => $messages,
        ];
    }
}
