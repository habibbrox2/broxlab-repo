<?php

/**
 * Notification WebSocket Helper
 * Handles communication with the WebSocket server to push real-time notifications
 */

class NotificationWebSocketHelper
{
    private static $wsServerUrl = null;
    private static $timeout = 5; // seconds

    /**
     * Get WebSocket server URL from environment or use default
     */
    private static function getWsServerUrl(): string
    {
        if (self::$wsServerUrl === null) {
            self::$wsServerUrl = getenv('WEBSOCKET_SERVER_URL') ?: 'http://localhost:3003';
        }
        return self::$wsServerUrl;
    }

    /**
     * Send notification to user(s) via WebSocket
     * 
     * @param int|array $userId - Single user ID or array of user IDs
     * @param array $notification - Notification data {id, title, message, type, action_url, created_at}
     * @param array $channels - Channels to use (['websocket', 'push', 'email'])
     * @return array - Results of the broadcast
     */
    public static function broadcastNotification($userId, $notification, $channels = ['websocket'])
    {
        if (!in_array('websocket', $channels)) {
            return ['success' => false, 'error' => 'WebSocket channel not enabled'];
        }

        try {
            $userIds = is_array($userId) ? $userId : [$userId];

            $payload = [
                'userIds' => $userIds,
                'notification' => [
                    'id' => (int)($notification['id'] ?? 0),
                    'title' => $notification['title'] ?? 'Notification',
                    'message' => $notification['message'] ?? '',
                    'type' => $notification['type'] ?? 'info',
                    'action_url' => $notification['action_url'] ?? '#',
                    'created_at' => $notification['created_at'] ?? date('Y-m-d H:i:s')
                ],
                'channels' => $channels
            ];

            $response = self::sendRequest('/api/notifications/broadcast', $payload);
            return $response;
        } catch (Exception $e) {
            error_log('[NotificationWebSocketHelper] Error broadcasting notification: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send batch notifications via WebSocket
     * 
     * @param array $notifications - Array of {userId, notification}
     * @return array - Results of the batch broadcast
     */
    public static function batchBroadcastNotifications($notifications)
    {
        try {
            $payload = [
                'notifications' => array_map(function ($item) {
                    return [
                        'userId' => (int)$item['userId'],
                        'notification' => [
                            'id' => (int)($item['notification']['id'] ?? 0),
                            'title' => $item['notification']['title'] ?? 'Notification',
                            'message' => $item['notification']['message'] ?? '',
                            'type' => $item['notification']['type'] ?? 'info',
                            'action_url' => $item['notification']['action_url'] ?? '#',
                            'created_at' => $item['notification']['created_at'] ?? date('Y-m-d H:i:s')
                        ]
                    ];
                }, $notifications)
            ];

            $response = self::sendRequest('/api/notifications/batch-broadcast', $payload);
            return $response;
        } catch (Exception $e) {
            error_log('[NotificationWebSocketHelper] Error batch broadcasting notifications: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get WebSocket server stats
     * 
     * @return array - Server stats or error
     */
    public static function getServerStats()
    {
        try {
            $response = self::sendRequest('/api/notifications/stats', [], 'GET');
            return $response;
        } catch (Exception $e) {
            error_log('[NotificationWebSocketHelper] Error getting server stats: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send HTTP request to WebSocket server
     * 
     * @param string $endpoint - API endpoint path
     * @param array $data - Request data (for POST)
     * @param string $method - HTTP method (GET, POST)
     * @return array - Decoded response
     */
    private static function sendRequest($endpoint, $data = [], $method = 'POST')
    {
        $url = self::getWsServerUrl() . $endpoint;

        $options = [
            'http' => [
                'method' => $method,
                'header' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'timeout' => self::$timeout,
                'ignore_errors' => true
            ]
        ];

        if ($method === 'POST' && !empty($data)) {
            $options['http']['content'] = json_encode($data);
        }

        $context = stream_context_create($options);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $error = error_get_last();
                throw new Exception($error['message'] ?? 'Failed to reach WebSocket server');
            }

            $decoded = json_decode($response, true);
            return $decoded ?: ['success' => false, 'error' => 'Invalid response from WebSocket server'];
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Check if WebSocket server is available
     * 
     * @return bool
     */
    public static function isServerAvailable()
    {
        try {
            $url = self::getWsServerUrl() . '/notifications-health';
            $options = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => 2,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            return $response !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Set custom WebSocket server URL
     * 
     * @param string $url - Server URL (e.g., http://localhost:3003)
     */
    public static function setServerUrl($url)
    {
        self::$wsServerUrl = rtrim($url, '/');
    }

    /**
     * Set request timeout
     * 
     * @param int $seconds
     */
    public static function setTimeout($seconds)
    {
        self::$timeout = max(1, (int)$seconds);
    }
}
