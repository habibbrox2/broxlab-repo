<?php
/**
 * ChatSessionService - Session state resolution and conversation management
 * Extracted from AISystemController.php for modularity
 */

class ChatSessionService
{
    /**
     * Resolve session state from input
     */
    public static function resolveSessionState(array $input, bool $isAdmin): array
    {
        $context = $isAdmin ? 'admin' : 'public';
        $userId = $isAdmin ? (AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null)) : null;
        $guestToken = !$isAdmin ? (string)($input['visitorToken'] ?? '') : '';
        $guestToken = $guestToken !== '' ? $guestToken : null;
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

    /**
     * Build session payload with conversation history
     */
    public static function buildSessionPayload(AIChatModel $chatModel, array $sessionState, ?int $conversationId = null): array
    {
        $conversation = null;
        $context = (string)($sessionState['context'] ?? 'public');
        $userId = isset($sessionState['user_id']) ? (int)$sessionState['user_id'] : null;
        $guestToken = $sessionState['guest_token'] ?? null;
        $sessionKey = $sessionState['session_key'] ?? null;

        if ($conversationId) {
            $conversation = $chatModel->getConversationByIdForActor($conversationId, $userId, $guestToken, $context);
        }
        if (!$conversation && $sessionKey !== null) {
            $conversation = $chatModel->getConversationForSession($userId, $guestToken, $sessionKey, $context);
        }

        $messages = [];
        if ($conversation && !empty($conversation['id'])) {
            $messages = $chatModel->getMessages((int)$conversation['id']);
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

    /**
     * Get or create conversation (admin)
     */
    public static function getOrCreateAdminConversation(
        AIChatModel $chatModel,
        int $actorUserId,
        ?string $sessionKey,
        string $contextType,
        ?int $requestedConvId = null
    ): ?int {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $device = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent ?? '')) {
            $device = preg_match('/iPad/i', $userAgent ?? '') ? 'Tablet' : 'Mobile';
        }
        $location = 'Unknown';

        if ($requestedConvId && $requestedConvId > 0) {
            $conversation = $chatModel->getConversationByIdForActor($requestedConvId, $actorUserId, null, $contextType);
            if ($conversation && ($conversation['status'] ?? 'open') === 'open') {
                return (int)$conversation['id'];
            }
        }

        return $chatModel->getOrCreateConversation($actorUserId, null, $ipAddress, $device, $location, $userAgent, $sessionKey, $contextType);
    }

    /**
     * Get or create conversation (public guest)
     */
    public static function getOrCreatePublicConversation(
        AIChatModel $chatModel,
        string $actorGuestToken,
        ?string $sessionKey,
        string $contextType,
        ?int $requestedConvId = null
    ): ?int {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $device = 'Desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent ?? '')) {
            $device = preg_match('/iPad/i', $userAgent ?? '') ? 'Tablet' : 'Mobile';
        }
        $location = 'Unknown';

        if ($requestedConvId && $requestedConvId > 0) {
            $conversation = $chatModel->getConversationByIdForActor($requestedConvId, null, $actorGuestToken, $contextType);
            if ($conversation && ($conversation['status'] ?? 'open') === 'open') {
                return (int)$conversation['id'];
            }
        }

        return $chatModel->getOrCreateConversation(null, $actorGuestToken, $ipAddress, $device, $location, $userAgent, $sessionKey, $contextType);
    }
}
