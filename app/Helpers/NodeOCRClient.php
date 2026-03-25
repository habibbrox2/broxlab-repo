<?php

/**
 * Node.js OCR Service Client
 * Communicates with Node.js OCR service (Tesseract.js + EasyOCR)
 */

class NodeOCRClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $baseUrl = null, int $timeout = 60)
    {
        $this->baseUrl = $baseUrl ?: 'http://localhost:7020';
        $this->timeout = $timeout;
    }

    /**
     * Check if Node OCR service is healthy
     */
    public function healthCheck(): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . '/health',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($response, true) ?: ['success' => false];
            }

            return ['success' => false, 'error' => 'Service unavailable'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract text using Tesseract.js
     */
    public function extractTextTesseract(string $imageData, string $language = 'eng'): array
    {
        return $this->sendRequest('/ocr/tesseract/image', [
            'image' => $imageData,
            'language' => $language
        ]);
    }

    /**
     * Extract text using EasyOCR
     */
    public function extractTextEasyOCR(string $imageData, array $languages = ['en']): array
    {
        return $this->sendRequest('/ocr/easyocr/image', [
            'image' => $imageData,
            'languages' => $languages
        ]);
    }

    /**
     * Auto-detect best engine and extract text
     */
    public function extractTextAuto(string $imageData, string $language = 'eng', array $easyOCRLanguages = ['en']): array
    {
        return $this->sendRequest('/ocr/auto', [
            'image' => $imageData,
            'language' => $language,
            'languages' => $easyOCRLanguages
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

        return $this->sendFileRequest('/ocr/tesseract/image', $filePath, [
            'language' => $language
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

        return $this->sendFileRequest('/ocr/easyocr/image', $filePath, [
            'languages' => $languages
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

        return $this->sendFileRequest('/ocr/auto', $filePath, [
            'language' => $language,
            'languages' => $easyOCRLanguages
        ]);
    }

    /**
     * Batch OCR using Tesseract
     */
    public function batchOCRTesseract(array $imageDataArray, string $language = 'eng'): array
    {
        // Create temp files
        $tempFiles = [];
        foreach ($imageDataArray as $imageData) {
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            if ($imageData['startsWith']('data:')) {
                $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            }
            file_put_contents($tempFile, base64_decode($imageData));
            $tempFiles[] = $tempFile;
        }

        $result = $this->sendMultiFileRequest('/ocr/tesseract/batch', $tempFiles, [
            'language' => $language
        ]);

        // Cleanup
        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return $result;
    }

    /**
     * Batch OCR using EasyOCR
     */
    public function batchOCREasyOCR(array $imageDataArray, array $languages = ['en']): array
    {
        // Create temp files
        $tempFiles = [];
        foreach ($imageDataArray as $imageData) {
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            if (strpos($imageData, 'data:') === 0) {
                $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            }
            file_put_contents($tempFile, base64_decode($imageData));
            $tempFiles[] = $tempFile;
        }

        $result = $this->sendMultiFileRequest('/ocr/easyocr/batch', $tempFiles, [
            'languages' => $languages
        ]);

        // Cleanup
        foreach ($tempFiles as $file) {
            @unlink($file);
        }

        return $result;
    }

    /**
     * Send JSON request to Node service
     */
    private function sendRequest(string $endpoint, array $payload): array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP $httpCode"];
            }

            $result = json_decode($response, true);
            return $result ?: ['success' => false, 'error' => 'Invalid response'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send file request to Node service
     */
    private function sendFileRequest(string $endpoint, string $filePath, array $extraData = []): array
    {
        try {
            $ch = curl_init();

            $postData = $extraData;
            $postData['file'] = new CURLFile($filePath);

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP $httpCode"];
            }

            $result = json_decode($response, true);
            return $result ?: ['success' => false, 'error' => 'Invalid response'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send multiple files request
     */
    private function sendMultiFileRequest(string $endpoint, array $filePaths, array $extraData = []): array
    {
        try {
            $ch = curl_init();

            $postData = $extraData;
            foreach ($filePaths as $index => $filePath) {
                $postData["files[$index]"] = new CURLFile($filePath);
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->baseUrl . $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'error' => $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "HTTP $httpCode"];
            }

            $result = json_decode($response, true);
            return $result ?: ['success' => false, 'error' => 'Invalid response'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
