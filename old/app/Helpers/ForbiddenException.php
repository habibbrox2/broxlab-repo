<?php
/**
 * ForbiddenException.php
 * 
 * Standardized exception for authorization failures.
 * Used across middleware, controllers, and services to provide
 * consistent 403 Forbidden responses.
 * 
 * @package BroxLab
 * @version 1.0.0
 */

class ForbiddenException extends Exception {
    
    /**
     * HTTP status code
     */
    protected int $httpCode = 403;
    
    /**
     * Error code for API responses
     */
    protected string $errorCode = 'FORBIDDEN';
    
    /**
     * Additional context data
     */
    protected array $context = [];
    
    /**
     * @param string $message Human-readable error message
     * @param string $errorCode Machine-readable error code
     * @param array $context Additional debug context (never exposed to client)
     * @param int $httpCode HTTP status code
     */
    public function __construct(
        string $message = 'Access denied',
        string $errorCode = 'FORBIDDEN',
        array $context = [],
        int $httpCode = 403
    ) {
        parent::__construct($message, $httpCode);
        $this->errorCode = $errorCode;
        $this->context = $context;
        $this->httpCode = $httpCode;
    }
    
    /**
     * Get the error code for API responses
     */
    public function getErrorCode(): string {
        return $this->errorCode;
    }
    
    /**
     * Get context data for logging
     */
    public function getContext(): array {
        return $this->context;
    }
    
    /**
     * Render the exception as a JSON response
     */
    public function render(): void {
        http_response_code($this->httpCode);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ]);
        exit;
    }
    
    /**
     * Render the exception as an HTML error page
     */
    public function renderHtml(): void {
        http_response_code($this->httpCode);
        
        if (function_exists('renderError')) {
            renderError($this->httpCode, $this->getMessage());
        } else {
            echo '<h1>403 Forbidden</h1><p>' . htmlspecialchars($this->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }
        exit;
    }
    
    /**
     * Convenience method — send the right format based on request type
     */
    public function respond(): void {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isApiRequest = (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0)
            || (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
            || (strpos($accept, 'application/json') !== false);
        
        if ($isApiRequest) {
            $this->render();
        } else {
            $this->renderHtml();
        }
    }
}
