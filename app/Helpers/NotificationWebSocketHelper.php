<?php

/**
 * NotificationWebSocketHelper - WebSocket broadcast utilities
 *
 * Handles broadcasting notifications via WebSocket connections.
 *
 * @package Broxbhai
 */

class NotificationWebSocketHelper
{
    /**
     * WebSocket server URL for broadcasting notifications
     */
    private const WEBSOCKET_URL = 'http://localhost:3003';

    /**
     * Broadcast notification to users via WebSocket
     *
     * @param int|array $recipientUserIds User ID or array of user IDs
     * @param array $notificationData Notification data with keys: id, title, message, type, action_url, created_at
     * @param array $channels Channels to broadcast through (e.g., ['websocket'])
     * @return bool Success status
     * @throws Exception
     */
    public static function broadcastNotification($recipientUserIds, $notificationData, $channels = ['websocket'])
    {
        // Only process if websocket channel is requested
        if (!in_array('websocket', $channels)) {
            return true;
        }

        // Ensure recipientUserIds is an array
        if (!is_array($recipientUserIds)) {
            $recipientUserIds = [$recipientUserIds];
        }

        // Prepare broadcast payload
        $payload = [
            'type' => 'broadcast',
            'userIds' => $recipientUserIds,
            'notification' => $notificationData
        ];

        try {
            // Attempt to send to WebSocket server
            return self::sendToWebSocketServer($payload);
        } catch (Exception $e) {
            // Log but don't throw - WebSocket broadcast is optional
            error_log('[NotificationWebSocketHelper] Broadcast error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payload to WebSocket server via HTTP
     *
     * @param array $payload Data to broadcast
     * @return bool Success status
     * @throws Exception
     */
    private static function sendToWebSocketServer($payload)
    {
        $ch = curl_init();

        if ($ch === false) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => self::WEBSOCKET_URL . '/broadcast',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("WebSocket server returned HTTP $httpCode");
        }

        return true;
    }

    /**
     * Test WebSocket server connectivity
     *
     * @return bool
     */
    public static function testConnection()
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => self::WEBSOCKET_URL . '/health',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }
}
