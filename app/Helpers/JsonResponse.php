<?php
declare(strict_types=1);

namespace App\Helpers {
    class JsonResponse
    {
        /**
         * Send a success response with optional data.
         *
         * @param array $payload
         * @param int $status
         */
        public static function success(array $payload = [], int $status = 200): void
        {
            if (!isset($payload['success'])) {
                $payload['success'] = true;
            }

            jsonResponse($payload, $status);
        }

        /**
         * Send an error response.
         *
         * @param string|array $message
         * @param int $status
         */
        public static function error($message, int $status = 400): void
        {
            $payload = [
                'success' => false,
            ];

            if (is_array($message)) {
                $payload = array_merge($payload, $message);
            } else {
                $payload['error'] = $message;
            }

            jsonResponse($payload, $status);
        }
    }
}

namespace {
    if (!function_exists('jsonResponse')) {
        /**
         * Sends a JSON response with the appropriate headers and HTTP status code.
         *
         * Usage: jsonResponse(['success'=>true,'data'=>...], 200);
         */
        function jsonResponse(array $data, int $status = 200): void
        {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data);
            exit;
        }
    }
}
