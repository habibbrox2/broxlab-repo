<?php

/**
 * WebSocket Configuration Helper
 *
 * Provides application-level websocket configuration values from the environment.
 *
 * @package BroxLab
 */

if (!function_exists('getWebSocketServerUrl')) {
    function getWebSocketServerUrl(): string
    {
        $url = null;

        if (array_key_exists('WEBSOCKET_SERVER_URL', $_ENV) && $_ENV['WEBSOCKET_SERVER_URL'] !== null) {
            $url = trim((string)$_ENV['WEBSOCKET_SERVER_URL']);
        }

        if ($url === null && array_key_exists('WEBSOCKET_SERVER_URL', $_SERVER) && $_SERVER['WEBSOCKET_SERVER_URL'] !== null) {
            $url = trim((string)$_SERVER['WEBSOCKET_SERVER_URL']);
        }

        if ($url === null) {
            $value = getenv('WEBSOCKET_SERVER_URL');
            if ($value !== false) {
                $url = trim((string)$value);
            }
        }

        if ($url === null || $url === '') {
            return 'http://localhost:3003';
        }

        return $url;
    }
}
