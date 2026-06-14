<?php

namespace App\Modules\AISystem\Layer;

/**
 * Correlation ID Manager
 * 
 * Provides correlation ID generation and propagation for request tracing
 * across all AI system components.
 * 
 * v2026 - Observability Pillar
 */
class CorrelationId
{
    private const HEADER_NAME = 'X-Correlation-ID';
    private const HEADER_NAME_SHORT = 'X-Corr-ID';

    /**
     * Get correlation ID from current request
     * 
     * @return string Correlation ID
     */
    public static function getCurrent(): string
    {
        // Check headers
        $correlationId = $_SERVER['HTTP_X_CORRELATION_ID'] ??
            $_SERVER['HTTP_X_CORR_ID'] ??
            $_SERVER['HTTP_X_REQUEST_ID'] ??
            null;

        if ($correlationId) {
            return $correlationId;
        }

        // Check session
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['ai_correlation_id'])) {
            return $_SESSION['ai_correlation_id'];
        }

        // Generate new ID
        return self::generate();
    }

    /**
     * Generate a new correlation ID
     * 
     * @return string UUID-like correlation ID
     */
    public static function generate(): string
    {
        // Generate UUID-like ID: corr_<timestamp>_<random>
        return 'corr_' . bin2hex(random_bytes(8));
    }

    /**
     * Set correlation ID for current request
     * 
     * @param string|null $id Correlation ID (generates new if null)
     * @return string The correlation ID
     */
    public static function set(?string $id = null): string
    {
        $correlationId = $id ?? self::generate();

        // Store in session for persistence
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['ai_correlation_id'] = $correlationId;
        }

        return $correlationId;
    }

    /**
     * Create a child correlation ID for sub-requests
     * 
     * @param string|null $parentId Parent correlation ID
     * @return string Child correlation ID
     */
    public static function createChild(?string $parentId = null): string
    {
        $parent = $parentId ?? self::getCurrent();
        return $parent . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    /**
     * Get correlation ID from input (header or body)
     * 
     * @return string|null Correlation ID or null
     */
    public static function getFromInput(): ?string
    {
        // Check header
        $fromHeader = $_SERVER['HTTP_X_CORRELATION_ID'] ??
            $_SERVER['HTTP_X_CORR_ID'] ?? null;
        if ($fromHeader) {
            return $fromHeader;
        }

        // Check JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['correlation_id'])) {
            return $input['correlation_id'];
        }

        return null;
    }

    /**
     * Add correlation ID to response headers
     * 
     * @param string $correlationId Correlation ID
     */
    public static function addToResponse(string $correlationId): void
    {
        header(self::HEADER_NAME . ': ' . $correlationId);
    }

    /**
     * Create a logging context array with correlation ID
     * 
     * @param array $context Additional context
     * @return array Context with correlation ID
     */
    public static function loggingContext(array $context = []): array
    {
        return array_merge($context, [
            'correlation_id' => self::getCurrent(),
            'timestamp' => date('c'),
            'trace_id' => self::getCurrent() // For OpenTelemetry compatibility
        ]);
    }

    /**
     * Get all headers for propagation to sub-requests
     * 
     * @return array Headers to propagate
     */
    public static function getPropagationHeaders(): array
    {
        return [
            self::HEADER_NAME => self::getCurrent()
        ];
    }

    /**
     * Middleware handler for correlation ID
     * Call at the start of each request
     * 
     * @return string Correlation ID
     */
    public static function middleware(): string
    {
        // Try to get existing ID
        $correlationId = self::getFromInput() ?? self::getCurrent();

        // Set for this request
        self::set($correlationId);

        // Add to response
        self::addToResponse($correlationId);

        return $correlationId;
    }
}
