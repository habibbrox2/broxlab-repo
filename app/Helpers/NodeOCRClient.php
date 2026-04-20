<?php

/**
 * Node.js OCR Service Client
 * Communicates with the unified Node server OCR routes
 */

class NodeOCRClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(?string $baseUrl = null, int $timeout = 60)
    {
        $nodeBaseUrl = $baseUrl ?? (getenv('NODE_SERVICE_URL') ?: getenv('NODE_API_URL') ?: getenv('NODEJS_SERVER_URL') ?: 'http://localhost:3000');
        $nodeBaseUrl = rtrim($nodeBaseUrl, '/');
        if (str_ends_with($nodeBaseUrl, '/api/ocr')) {
            $nodeBaseUrl = substr($nodeBaseUrl, 0, -8);
        }
        $this->baseUrl = rtrim($nodeBaseUrl, '/') . '/api/ocr';
        $this->timeout = $timeout;
    }

    /**
     * Check if Node OCR service is healthy
     */
    public function healthCheck(): array
    {
        return $this->sendRequest('GET', '/health');
    }

    /**
     * Extract text using Tesseract.js
     */
    public function extractTextTesseract(string $imageData, string $language = 'eng'): array
    {
        return $this->sendRequest('POST', '/tesseract/image', [
            'imageBase64' => $this->normalizeImageInput($imageData),
            'language' => $language,
        ]);
    }

    /**
     * Extract text using EasyOCR
     */
    public function extractTextEasyOCR(string $imageData, array $languages = ['en']): array
    {
        return $this->sendRequest('POST', '/easyocr/image', [
            'imageBase64' => $this->normalizeImageInput($imageData),
            'languages' => $languages,
        ]);
    }

    /**
     * Auto-detect best engine and extract text
     */
    public function extractTextAuto(string $imageData, string $language = 'eng', array $easyOCRLanguages = ['en']): array
    {
        return $this->sendRequest('POST', '/auto', [
            'imageBase64' => $this->normalizeImageInput($imageData),
            'language' => $language,
            'languages' => $easyOCRLanguages,
        ]);
    }

    /**
     * Extract text from file path (Tesseract)
     */
    public function extractTextFromFileTesseract(string $filePath, string $language = 'eng'): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        return $this->sendRequest('POST', '/tesseract/image', [
            'imageBase64' => base64_encode((string)file_get_contents($filePath)),
            'language' => $language,
        ]);
    }

    /**
     * Extract text from file path (EasyOCR)
     */
    public function extractTextFromFileEasyOCR(string $filePath, array $languages = ['en']): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        return $this->sendRequest('POST', '/easyocr/image', [
            'imageBase64' => base64_encode((string)file_get_contents($filePath)),
            'languages' => $languages,
        ]);
    }

    /**
     * Extract text from file path (Auto)
     */
    public function extractTextFromFileAuto(string $filePath, string $language = 'eng', array $easyOCRLanguages = ['en']): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'File not found'];
        }

        return $this->sendRequest('POST', '/auto', [
            'imageBase64' => base64_encode((string)file_get_contents($filePath)),
            'language' => $language,
            'languages' => $easyOCRLanguages,
        ]);
    }

    /**
     * Batch OCR using Tesseract
     */
    public function batchOCRTesseract(array $imageDataArray, string $language = 'eng'): array
    {
        return $this->sendRequest('POST', '/tesseract/batch', [
            'images' => $this->normalizeImages($imageDataArray),
            'language' => $language,
        ]);
    }

    /**
     * Batch OCR using EasyOCR
     */
    public function batchOCREasyOCR(array $imageDataArray, array $languages = ['en']): array
    {
        return $this->sendRequest('POST', '/easyocr/batch', [
            'images' => $this->normalizeImages($imageDataArray),
            'languages' => $languages,
        ]);
    }

    /**
     * Send request to Node service
     */
    private function sendRequest(string $method, string $endpoint, array $payload = []): array
    {
        try {
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $this->baseUrl . $endpoint,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ];

            if ($method !== 'GET') {
                $options[CURLOPT_POSTFIELDS] = json_encode($payload);
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP $httpCode"];
            }

            $result = json_decode($response, true);
            return is_array($result) ? $result : ['success' => false, 'error' => 'Invalid response'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizeImageInput(string $imageData): string
    {
        if (file_exists($imageData)) {
            return base64_encode((string)file_get_contents($imageData));
        }

        if (preg_match('/^data:image\/\w+;base64,/', $imageData)) {
            return preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
        }

        return preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $imageData)
            ? $imageData
            : base64_encode($imageData);
    }

    private function normalizeImages(array $images): array
    {
        $normalized = [];
        foreach ($images as $image) {
            if (!is_string($image) || $image === '') {
                continue;
            }

            $normalized[] = $this->normalizeImageInput($image);
        }

        return $normalized;
    }
}
