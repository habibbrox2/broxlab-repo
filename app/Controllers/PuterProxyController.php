<?php

/**
 * controllers/PuterProxyController.php
 *
 * Simple proxy to forward requests from the browser to Puter's API.
 *
 * This is used to avoid CORS issues and allow the browser to call Puter
 * via a same-origin endpoint (e.g. `/__/puter/*`).
 *
 * Optionally, you can define a server-side token to avoid requiring users
 * to sign in via the Puter popup.
 *
 * Add to your .env:
 *   PUTER_API_PROXY_TOKEN=your_server_side_puter_token
 *
 * Routes:
 *   ANY /__/puter/*   -> proxies to https://api.puter.com/*
 */

// Make $router available within this controller
global $router;

$proxyPuterHandler = function () {
    try {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];
        if (!in_array($method, $allowedMethods, true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            echo 'Method Not Allowed';
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $basePath = '/__/puter';
        $path = preg_replace('#^' . preg_quote($basePath, '#') . '#', '', $requestUri);
        if ($path === false || $path === '') {
            $path = '/';
        }

        // A simple health check endpoint to verify the proxy is reachable.
        if (preg_match('#^/health/?$#', $path)) {
            logDebug('Puter proxy health check', [
                'method' => $method,
                'path' => $path,
                'query' => $_SERVER['QUERY_STRING'] ?? '',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'proxy' => true,
                'path' => $path,
                'timestamp' => date(DATE_ATOM),
            ]);
            return;
        }

        $publicOnlyMode = filter_var($_ENV['PUTER_PROXY_PUBLIC_ONLY'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($publicOnlyMode && preg_match('#^/rao(?:/)?$#', $path)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'proxy' => true,
                'endpoint' => 'rao',
                'public_only' => true,
                'token_source' => 'public-only',
            ]);
            return;
        }

        // A deeper proxy test endpoint that verifies the Puter API is reachable and auth works.
        if (preg_match('#^/(?:test|whoami)(?:/)?$#', $path)) {
            $token = trim($_ENV['PUTER_API_PROXY_TOKEN'] ?? '');
            $tokenSource = 'env';

            // If no server token is configured, allow the client to supply one via Authorization header
            if ($token === '' && function_exists('getallheaders')) {
                $headers = getallheaders();
                foreach ($headers as $hName => $hValue) {
                    if (strtolower($hName) === 'authorization' && stripos($hValue, 'bearer ') === 0) {
                        $token = trim(substr($hValue, 7));
                        $tokenSource = 'header';
                        break;
                    }
                }
            }

            // In public-only mode, allow the whoami/test endpoint to run without a token.
            if ($token === '' && $publicOnlyMode) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'proxy' => true,
                    'endpoint' => 'whoami',
                    'http_code' => 200,
                    'token_source' => 'public-only',
                    'body' => [
                        'anonymous' => true,
                        'public_only' => true,
                    ],
                ]);
                return;
            }

            if ($token === '' && !$publicOnlyMode) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'PUTER_API_PROXY_TOKEN is not configured and no Authorization header was provided',
                ]);
                return;
            }

            $whoamiUrl = 'https://api.puter.com/whoami';
            $ch = curl_init($whoamiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            if ($response === false) {
                $err = curl_error($ch);
                logError("Puter proxy test request failed: {$err}", 'ERROR', [
                    'target_url' => $whoamiUrl,
                    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                http_response_code(502);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Bad Gateway', 'details' => $err]);
                return;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $responseBody = substr($response, $headerSize);
            $payload = json_decode($responseBody, true);

            logDebug('Puter proxy test response', [
                'http_code' => $httpCode,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                'token_source' => $tokenSource,
                'parsed' => is_array($payload),
            ]);

            header('Content-Type: application/json');
            $success = $httpCode >= 200 && $httpCode < 300;
            $result = [
                'success' => $success,
                'proxy' => true,
                'endpoint' => 'whoami',
                'http_code' => $httpCode,
                'token_source' => $tokenSource,
                'body' => $payload ?? $responseBody,
            ];

            if (!$success && $httpCode === 401) {
                $result['error'] = 'Unauthorized: invalid/expired token (check PUTER_API_PROXY_TOKEN or Authorization header)';
            }

            echo json_encode($result);
            return;
        }

        $targetUrl = 'https://api.puter.com' . $path;
        if (!empty($_SERVER['QUERY_STRING'])) {
            $targetUrl .= '?' . $_SERVER['QUERY_STRING'];
        }

        $body = file_get_contents('php://input');

        $headers = [];
        $debugHeaders = [];
        $clientAuthHeader = null;

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $lower = strtolower($name);

                // Allow forwarding of common headers, but avoid forwarding hop-by-hop headers.
                if (in_array($lower, ['content-type', 'accept', 'user-agent', 'x-requested-with', 'origin', 'referer'], true)) {
                    $headers[] = "{$name}: {$value}";
                }

                // Capture a few headers for debugging (mask auth)
                if (in_array($lower, ['content-type', 'accept', 'user-agent', 'origin', 'referer', 'authorization'], true)) {
                    $debugValue = $value;
                    if ($lower === 'authorization' && stripos($value, 'bearer ') === 0) {
                        $debugValue = 'Bearer [REDACTED]';
                    }
                    $debugHeaders[$name] = $debugValue;
                }

                if ($lower === 'authorization' && stripos($value, 'bearer ') === 0) {
                    $clientAuthHeader = trim($value);
                }
            }
        }

        $useProxyToken = filter_var($_ENV['PUTER_API_PROXY_FORCE_TOKEN'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $envToken = trim($_ENV['PUTER_API_PROXY_TOKEN'] ?? '');

        // Public-only mode: explicitly strip any Authorization header.
        if ($publicOnlyMode) {
            // do nothing (deliberately remove auth headers)
        } elseif ($clientAuthHeader) {
            $headers[] = "Authorization: {$clientAuthHeader}";
        } elseif ($useProxyToken && $envToken !== '') {
            $headers[] = 'Authorization: Bearer ' . $envToken;
            $debugHeaders['Authorization'] = 'Bearer [SERVER_TOKEN]';
        }

        logDebug('Puter proxy forwarding request', [
            'method' => $method,
            'path' => $path,
            'query' => $_SERVER['QUERY_STRING'] ?? '',
            'target_url' => $targetUrl ?? null,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
            'headers' => $debugHeaders
        ]);

        $ch = curl_init($targetUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            logError("Puter proxy request failed: {$err}", 'ERROR', [
                'target_url' => $targetUrl,
                'method' => $method,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            http_response_code(502);
            echo 'Bad Gateway';
            return;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        logDebug('Puter proxy response received', [
            'target_url' => $targetUrl,
            'http_code' => $httpCode,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        // Forward response headers (except hop-by-hop headers)
        $blocked = [
            'transfer-encoding',
            'connection',
            'keep-alive',
            'proxy-authenticate',
            'proxy-authorization',
            'te',
            'trailers',
            'upgrade',
            'content-encoding',
            'content-length',
            'set-cookie',
        ];

        foreach (preg_split("/\r\n|\n|\r/", $responseHeaders) as $line) {
            if (trim($line) === '' || stripos($line, 'HTTP/') === 0) {
                continue;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $name = trim($name);
            $value = trim($value);
            if ($name === '') {
                continue;
            }
            if (in_array(strtolower($name), $blocked, true)) {
                continue;
            }
            header("{$name}: {$value}");
        }

        http_response_code($httpCode);
        echo $responseBody;
    } catch (Throwable $e) {
        logError('Puter proxy exception: ' . $e->getMessage());
        http_response_code(500);
        echo 'Internal Server Error';
    }
};

// Register proxy routes
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}/{b}', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}/{b}/{c}', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}/{b}/{c}/{d}', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}/{b}/{c}/{d}/{e}', $proxyPuterHandler);
$router->match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], '/__/puter/{a}/{b}/{c}/{d}/{e}/{f}', $proxyPuterHandler);
