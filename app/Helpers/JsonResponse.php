<?php
declare(strict_types=1);

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
    // Ensure no further output is sent.
    exit;
}
