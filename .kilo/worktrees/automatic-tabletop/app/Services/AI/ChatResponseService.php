<?php
/**
 * ChatResponseService - Handles JSON and SSE streaming responses
 * Extracted from AISystemController.php for modularity
 */

class ChatResponseService
{
    /**
     * Send JSON response
     */
    public static function sendJson(array $payload, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send SSE streaming response
     */
    public static function streamContent(string $content, array $meta = []): void
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);

        if (!empty($meta)) {
            echo 'data: ' . json_encode(['meta' => $meta], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }

        $chunkSize = 200;
        if (function_exists('mb_strlen')) {
            $length = mb_strlen($content, 'UTF-8');
            for ($i = 0; $i < $length; $i += $chunkSize) {
                $chunk = mb_substr($content, $i, $chunkSize, 'UTF-8');
                echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                flush();
            }
        } else {
            foreach (str_split($content, $chunkSize) as $chunk) {
                echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
                @ob_flush();
                flush();
            }
        }

        echo "data: [DONE]\n\n";
        @ob_flush();
        flush();
    }

    /**
     * Derive HTTP status from AI response
     */
    public static function deriveHttpStatus(array $response, int $default = 502): int
    {
        if (!empty($response['http_code']) && is_int($response['http_code'])) {
            $code = $response['http_code'];
            if (in_array($code, [400, 401, 402, 403, 404, 429], true)) {
                return $code;
            }
            if ($code >= 500) {
                return 502;
            }
        }

        if (!empty($response['error_code']) && $response['error_code'] === 'provider_incomplete') {
            return 400;
        }

        if (!empty($response['error']) && str_contains((string)$response['error'], 'API key not configured')) {
            return 400;
        }

        if (!empty($response['error_type']) && $response['error_type'] === 'network') {
            return 502;
        }

        return $default;
    }
}
