<?php

namespace App\Modules\AISystem\Layer;

/**
 * Streaming Engine
 * Wrapper logic for handling API streaming with proper error handling,
 * progress tracking, and reconnection support.
 */
class StreamingEngine
{
    private $bufferSize = 4096;
    private $enableLogging = false;
    private $logFile;
    private $onProgress;
    private $onError;

    public function __construct()
    {
        // Setup logging
        $this->enableLogging = getenv('STREAM_LOGGING') ?? false;
        if ($this->enableLogging) {
            $this->logFile = __DIR__ . '/../../../storage/logs/streaming.log';
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
        }
    }

    /**
     * Set progress callback
     */
    public function onProgress(callable $callback): self
    {
        $this->onProgress = $callback;
        return $this;
    }

    /**
     * Set error callback
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;
        return $this;
    }

    /**
     * Stream response with full error handling
     * 
     * @param callable $apiStreamFunction Function that returns API stream
     * @return array Result with status and data
     */
    public static function streamResponse(callable $apiStreamFunction)
    {
        $instance = new self();
        return $instance->executeStream($apiStreamFunction);
    }

    /**
     * Execute the streaming process
     */
    private function executeStream(callable $apiStreamFunction): array
    {
        $startTime = microtime(true);
        $bytesReceived = 0;
        $chunksReceived = 0;

        try {
            // Disable output buffering
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            // Set appropriate headers
            $this->setHeaders();

            // Execute the API call
            $result = $apiStreamFunction(function ($chunk) use (&$bytesReceived, &$chunksReceived) {
                $this->processChunk($chunk, $bytesReceived, $chunksReceived);
            });

            // Send completion message
            $this->sendCompletion($chunksReceived, $bytesReceived, $startTime);

            return [
                'success' => true,
                'chunks' => $chunksReceived,
                'bytes' => $bytesReceived,
                'duration' => microtime(true) - $startTime
            ];
        } catch (\Exception $e) {
            $this->handleError($e, $startTime);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'chunks_received' => $chunksReceived,
                'bytes_received' => $bytesReceived,
                'duration' => microtime(true) - $startTime
            ];
        } catch (\Throwable $e) {
            $this->handleError($e, $startTime);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'chunks_received' => $chunksReceived,
                'bytes_received' => $bytesReceived,
                'duration' => microtime(true) - $startTime
            ];
        }
    }

    /**
     * Set HTTP headers for streaming
     */
    private function setHeaders(): void
    {
        // Prevent caching
        header('Cache-Control: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        // Streaming headers
        header('Content-Type: text/event-stream');
        header('Transfer-Encoding: chunked');
        header('Connection: keep-alive');

        // CORS headers (adjust as needed)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: X-Stream-Chunks, X-Stream-Bytes');

        // Custom headers for progress tracking
        header('X-Stream-Status: streaming');

        // Flush headers immediately
        @flush();

        flush();
    }

    /**
     * Process a single chunk of the stream
     */
    private function processChunk($chunk, int &$bytesReceived, int &$chunksReceived): void
    {
        if (!isset($chunk['choices'][0]['delta']['content'])) {
            return;
        }

        $content = $chunk['choices'][0]['delta']['content'];
        $contentLength = strlen($content);

        // Send the chunk
        $data = json_encode([
            'text' => $content,
            'chunk' => $chunksReceived,
            'timestamp' => time()
        ]);

        echo "data: {$data}\n\n";

        $bytesReceived += $contentLength;
        $chunksReceived++;

        // Trigger progress callback
        if ($this->onProgress) {
            call_user_func($this->onProgress, [
                'chunk' => $chunksReceived,
                'bytes' => $bytesReceived,
                'content' => $content
            ]);
        }

        // Flush output
        if (function_exists('flush')) {
            flush();
        }
    }

    /**
     * Send completion message
     */
    private function sendCompletion(int $chunks, int $bytes, float $startTime): void
    {
        $duration = microtime(true) - $startTime;

        // Send done message
        echo "data: " . json_encode([
            'done' => true,
            'chunks' => $chunks,
            'bytes' => $bytes,
            'duration' => round($duration, 3)
        ]) . "\n\n";

        // Send custom done message for SSE
        echo "data: [DONE]\n\n";

        // Add final stats header
        header('X-Stream-Chunks: ' . $chunks);
        header('X-Stream-Bytes: ' . $bytes);
        header('X-Stream-Duration: ' . round($duration, 3));
        header('X-Stream-Status: completed');

        flush();

        $this->log('COMPLETED', "Stream completed: {$chunks} chunks, {$bytes} bytes");
    }

    /**
     * Handle errors during streaming
     */
    private function handleError($error, float $startTime): void
    {
        $duration = microtime(true) - $startTime;
        $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
        $errorClass = get_class($error);

        // Log the error
        $this->log('ERROR', "{$errorClass}: {$errorMessage}");

        // Send error to client
        $errorData = json_encode([
            'error' => true,
            'message' => $errorMessage,
            'type' => $errorClass,
            'duration' => round($duration, 3)
        ]);

        echo "data: {$errorData}\n\n";
        echo "data: [DONE]\n\n";
        flush();

        // Trigger error callback
        if ($this->onError) {
            call_user_func($this->onError, [
                'message' => $errorMessage,
                'type' => $errorClass,
                'duration' => $duration
            ]);
        }
    }

    /**
     * Log streaming events
     */
    private function log(string $level, string $message): void
    {
        if (!$this->enableLogging || !$this->logFile) {
            return;
        }

        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]) . "\n";

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Create a streaming response wrapper with retry support
     * 
     * @param callable $apiStreamFunction The API call function
     * @param int $maxRetries Maximum retry attempts
     * @param array $retryDelays Delay between retries in seconds
     * @return array Result
     */
    public static function streamWithRetry(
        callable $apiStreamFunction,
        int $maxRetries = 3,
        array $retryDelays = [1, 2, 5]
    ): array {
        $instance = new self();

        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 0) {
                // Wait before retry
                $delay = $retryDelays[min($attempt - 1, count($retryDelays) - 1)];
                $instance->log('RETRY', "Attempt {$attempt} after {$delay}s delay");
                sleep($delay);
            }

            $result = $instance->executeStream($apiStreamFunction);

            if ($result['success']) {
                return $result;
            }

            $lastError = $result['error'];

            // Check if error is retryable
            if (!$instance->isRetryableError($lastError)) {
                break;
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded: ' . $lastError,
            'attempts' => $maxRetries + 1
        ];
    }

    /**
     * Check if an error is retryable
     */
    private function isRetryableError(string $error): bool
    {
        $retryablePatterns = [
            'timeout',
            'connection',
            'network',
            'temporary',
            '503',
            '502',
            '429', // Rate limit
            'rate_limit'
        ];

        $lowerError = strtolower($error);

        foreach ($retryablePatterns as $pattern) {
            if (strpos($lowerError, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stream to a file instead of stdout (for testing)
     */
    public static function streamToFile(callable $apiStreamFunction, string $filePath): array
    {
        $instance = new self();

        try {
            $result = $apiStreamFunction(function ($chunk) use ($filePath, $instance) {
                if (!isset($chunk['choices'][0]['delta']['content'])) {
                    return;
                }

                $content = $chunk['choices'][0]['delta']['content'];
                file_put_contents($filePath, $content, FILE_APPEND | LOCK_EX);
            });

            return [
                'success' => true,
                'file' => $filePath,
                'size' => filesize($filePath)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
