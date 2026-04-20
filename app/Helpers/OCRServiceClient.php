<?php

/**
 * OCR Service Client for PHP
 * Integrates with PHP OCR API for text extraction
 */

class OCRServiceClient
{
    private string $baseUrl;
    private int $timeout;
    private $curlHandle;

    public function __construct(string $baseUrl = null, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl ?: getenv('NODE_SERVICE_URL') ?: getenv('NODE_API_URL') ?: getenv('NODEJS_SERVER_URL') ?: 'http://localhost:3000', '/');
        if (str_ends_with($this->baseUrl, '/api/ocr')) {
            $this->baseUrl = substr($this->baseUrl, 0, -8);
        }
        $this->timeout = $timeout;
        $this->curlHandle = null;
    }

    /**
     * Initialize cURL handle
     */
    private function initCurl(): void
    {
        if ($this->curlHandle === null) {
            $this->curlHandle = curl_init();
            curl_setopt_array($this->curlHandle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);
        }
    }

    /**
     * Make HTTP request
     */
    private function makeRequest(string $method, string $endpoint, array $data = null): array
    {
        $this->initCurl();

        $url = rtrim($this->baseUrl, '/') . $endpoint;

        curl_setopt($this->curlHandle, CURLOPT_URL, $url);
        curl_setopt($this->curlHandle, CURLOPT_CUSTOMREQUEST, $method);

        if ($data !== null) {
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($this->curlHandle);
        $httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
        $error = curl_error($this->curlHandle);

        if ($error) {
            return [
                'success' => false,
                'error' => $error
            ];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response'
            ];
        }

        return $result;
    }

    /**
     * Check if OCR service is healthy
     */
    public function healthCheck(): array
    {
        return $this->makeRequest('GET', '/api/ocr/health');
    }

    /**
     * Extract text from image
     */
    public function extractTextFromImage(string $imageData, array $options = []): array
    {
        // Handle file path
        if (file_exists($imageData)) {
            $imageData = base64_encode(file_get_contents($imageData));
        }

        // Ensure it's base64
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $imageData)) {
            $imageData = base64_encode($imageData);
        }

        $payload = [
            'image' => $imageData,
            'options' => [
                'language' => $options['language'] ?? 'eng+ben',
                'preprocess' => $options['preprocess'] ?? true
            ]
        ];

        return $this->makeRequest('POST', '/api/ocr/image', $payload);
    }

    /**
     * Extract text from PDF
     */
    public function extractTextFromPDF(string $pdfData, array $options = []): array
    {
        // Handle file path
        if (file_exists($pdfData)) {
            $pdfData = base64_encode(file_get_contents($pdfData));
        }

        // Ensure it's base64
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pdfData)) {
            $pdfData = base64_encode($pdfData);
        }

        $payload = [
            'pdf' => $pdfData,
            'options' => [
                'language' => $options['language'] ?? 'eng+ben',
                'dpi' => $options['dpi'] ?? 300
            ]
        ];

        return $this->makeRequest('POST', '/api/ocr/pdf', $payload);
    }

    /**
     * Process multiple files in batch
     */
    public function processBatch(array $files, array $options = []): array
    {
        $processedFiles = [];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $processedFiles[] = base64_encode(file_get_contents($file));
            } elseif (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $file)) {
                $processedFiles[] = $file; // Already base64
            } else {
                $processedFiles[] = base64_encode($file); // Raw data
            }
        }

        $payload = [
            'files' => $processedFiles,
            'options' => [
                'type' => $options['type'] ?? 'image',
                'language' => $options['language'] ?? 'eng+ben'
            ]
        ];

        return $this->makeRequest('POST', '/api/ocr/batch', $payload);
    }

    /**
     * Extract text from file by path
     */
    public function extractTextFromFile(string $filePath, array $options = []): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'File not found: ' . $filePath
            ];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['pdf'])) {
            return $this->extractTextFromPDF($filePath, $options);
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif'])) {
            return $this->extractTextFromImage($filePath, $options);
        } else {
            return [
                'success' => false,
                'error' => 'Unsupported file type: ' . $extension
            ];
        }
    }

    /**
     * Extract text with OCR.space API fallback
     */
    public function extractTextWithFallback(string $imageData, array $options = []): array
    {
        // Primary method is now OCR.space API
        return $this->extractTextFromImage($imageData, $options);
    }

    /**
     * Clean up cURL handle
     */
    public function __destruct()
    {
        if ($this->curlHandle !== null) {
            curl_close($this->curlHandle);
        }
    }
}
