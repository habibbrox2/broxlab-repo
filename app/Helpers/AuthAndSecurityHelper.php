<?php

if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

if (!function_exists('getRequestData')) {
    function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }
}

if (!function_exists('normalizeAuthRedirectPath')) {
    function normalizeAuthRedirectPath($rawPath): string
    {
        $path = trim((string)$rawPath);
        if ($path === '') {
            return '';
        }

        if (!isValidInternalPath($path)) {
            return '';
        }

        $normalizedPath = strtolower((string)(parse_url($path, PHP_URL_PATH) ?? ''));
        $blockedPaths = [
            '/login',
            '/register',
            '/logout',
            '/forgot-password',
            '/reset-password',
            '/verify-2fa',
            '/send-verification-email'
        ];

        if (in_array($normalizedPath, $blockedPaths, true) || strpos($normalizedPath, '/__/auth') === 0) {
            return '';
        }

        return $path;
    }
}

if (!function_exists('getInternalRefererPath')) {
    function getInternalRefererPath(): string
    {
        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer === '') {
            return '';
        }

        $parsed = parse_url($referer);
        if (!is_array($parsed)) {
            return '';
        }

        $refererHost = strtolower((string)($parsed['host'] ?? ''));
        $currentHostRaw = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        $currentHost = '';
        if ($currentHostRaw !== '') {
            $currentHost = strtolower((string)parse_url('http://' . $currentHostRaw, PHP_URL_HOST));
        }
        if ($refererHost !== '' && $currentHost !== '' && $refererHost !== $currentHost) {
            return '';
        }

        $path = (string)($parsed['path'] ?? '');
        if ($path === '') {
            return '';
        }

        $query = isset($parsed['query']) && $parsed['query'] !== ''
            ? '?' . $parsed['query']
            : '';

        return $path . $query;
    }
}

if (!function_exists('resolveAuthRedirectPath')) {
    function resolveAuthRedirectPath(array $requestData = [], bool $allowRefererFallback = false): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $candidates = [
            $requestData['oauth_redirect'] ?? null,
            $requestData['redirect'] ?? null,
            $requestData['next'] ?? null,
            $_GET['oauth_redirect'] ?? null,
            $_GET['redirect'] ?? null,
            $_GET['next'] ?? null
        ];

        foreach ($candidates as $candidate) {
            $safe = normalizeAuthRedirectPath($candidate);
            if ($safe !== '') {
                $_SESSION['post_login_redirect'] = $safe;
                return $safe;
            }
        }

        $sessionRedirect = normalizeAuthRedirectPath($_SESSION['post_login_redirect'] ?? '');
        if ($sessionRedirect !== '') {
            $_SESSION['post_login_redirect'] = $sessionRedirect;
            return $sessionRedirect;
        }

        if ($allowRefererFallback) {
            $refererRedirect = normalizeAuthRedirectPath(getInternalRefererPath());
            if ($refererRedirect !== '') {
                $_SESSION['post_login_redirect'] = $refererRedirect;
                return $refererRedirect;
            }
        }

        return '';
    }
}

if (!function_exists('generateBase32Secret')) {
    function generateBase32Secret($length = 32)
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $base32chars[random_int(0, 31)];
        }

        return $secret;
    }
}

if (!function_exists('generateBackupCodes')) {
    function generateBackupCodes($count = 10)
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = '';
            for ($j = 0; $j < 3; $j++) {
                $code .= sprintf('%04X', random_int(0, 65535));
                if ($j < 2) {
                    $code .= '-';
                }
            }
            $codes[] = $code;
        }

        return $codes;
    }
}
