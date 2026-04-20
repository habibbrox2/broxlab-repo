<?php

/**
 * OCR Service - Hybrid Approach
 * Primary: Node.js OCR service (Tesseract.js on unified server port 3000)
 * Fallback: OCR.space API (web hosting compatible)
 */

class OCRService
{
    private string $ocrSpaceApiKey;
    private int $timeout;
    private string $nodeOcrUrl;
    private array $supportedLanguages = ['eng', 'ben', 'eng+ben'];

    public function __construct(string $apiKey = null, int $timeout = 30, string $nodeOcrUrl = null)
    {
        $this->ocrSpaceApiKey = $apiKey ?: getenv('OCR_SPACE_API_KEY') ?: 'K81289438988957'; // Paid tier API key
        $this->timeout = $timeout;
        // Prefer the unified Node service URL and keep legacy fallbacks for compatibility
        $nodeBaseUrl = $nodeOcrUrl ?: (getenv('NODE_SERVICE_URL') ?: (getenv('OCR_SERVICE_URL') ?: (getenv('OCR_API_URL') ?: 'http://localhost:3000')));
        $nodeBaseUrl = rtrim($nodeBaseUrl, '/');
        if (str_ends_with($nodeBaseUrl, '/api/ocr')) {
            $nodeBaseUrl = substr($nodeBaseUrl, 0, -8);
        }
        $this->nodeOcrUrl = rtrim($nodeBaseUrl, '/');
    }

    /**
     * Check if OCR service is available
     */
    public function isAvailable(): bool
    {
        return function_exists('curl_init');
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * Check if Node.js OCR service is healthy
     */
    private function isNodeOcrHealthy(): bool
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->nodeOcrUrl . '/api/ocr/health',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $isHealthy = ($httpCode === 200);
            if ($isHealthy) {
                error_log("Node.js OCR service is healthy at {$this->nodeOcrUrl}");
            } else {
                error_log("Node.js OCR health check failed: HTTP $httpCode, URL: {$this->nodeOcrUrl}");
            }
            return $isHealthy;
        } catch (Exception $e) {
            error_log("Node.js OCR health check error: " . $e->getMessage() . ", URL: {$this->nodeOcrUrl}");
            return false;
        }
    }

    /**
     * Extract text using Node.js OCR service (Tesseract.js)
     */
    private function extractTextViaNodeOcr(string $imageData, array $options = []): array
    {
        try {
            error_log('Attempting Node.js OCR extraction');

            // Prepare payload
            $payload = [
                'image' => $imageData,
                'lang' => $options['language'] ?? 'eng'
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->nodeOcrUrl . '/api/ocr/tesseract/image',
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
                error_log("Node.js OCR cURL error: $error");
                return [
                    'success' => false,
                    'error' => 'Node.js OCR connection failed: ' . $error
                ];
            }

            if ($httpCode !== 200) {
                error_log("Node.js OCR HTTP error: $httpCode. Response: $response");
                return [
                    'success' => false,
                    'error' => "Node.js OCR HTTP $httpCode"
                ];
            }

            if (!$response) {
                error_log("Node.js OCR returned empty response");
                return [
                    'success' => false,
                    'error' => 'Node.js OCR returned empty response'
                ];
            }

            $result = json_decode($response, true);
            if (!$result) {
                error_log("Failed to decode Node.js OCR response: $response");
                return [
                    'success' => false,
                    'error' => 'Failed to parse Node.js OCR response'
                ];
            }

            if (empty($result['success'])) {
                error_log("Node.js OCR returned success=false: " . ($result['error'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Node.js OCR processing failed'
                ];
            }

            if (empty($result['text'])) {
                error_log("Node.js OCR returned empty text");
                return [
                    'success' => false,
                    'error' => 'Node.js OCR extracted no text'
                ];
            }

            error_log("Node.js OCR successfully extracted text (" . strlen($result['text']) . " chars)");
            return [
                'success' => true,
                'text' => $result['text'],
                'confidence' => $result['confidence'] ?? 0,
                'engine' => 'tesseract.js (Node.js)',
                'language' => $options['language'] ?? 'eng'
            ];
        } catch (Exception $e) {
            error_log("Node.js OCR exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from PDF using the unified Node.js OCR service
     */
    private function extractTextViaNodePdf(string $pdfData, array $options = []): array
    {
        try {
            $payload = [
                'pdfBase64' => $pdfData,
                'language' => $options['language'] ?? 'eng'
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->nodeOcrUrl . '/api/ocr/pdf/extract',
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

            if ($error || $httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => $error ?: "Node.js OCR HTTP $httpCode"
                ];
            }

            $result = json_decode($response, true);
            if (empty($result['success']) || empty($result['text'])) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Node.js OCR PDF processing failed'
                ];
            }

            return [
                'success' => true,
                'text' => $result['text'],
                'engine' => 'pdf-parse (Node.js)'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract text from image - Primary: Node.js OCR, Fallback: OCR.space API
     */
    public function extractTextFromImage(string $imageData, array $options = []): array
    {
        try {
            // Normalize image data: always keep as base64 string
            if (preg_match('/^data:image\\/[^;]+;base64,/', $imageData)) {
                // If it has data: prefix, remove it and keep base64
                $imageData = preg_replace('/^data:image\\/[^;]+;base64,/', '', $imageData);
            } elseif (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $imageData)) {
                // If it's binary, encode to base64
                $imageData = base64_encode($imageData);
            }
            // Now $imageData is guaranteed to be base64 string

            if (!$imageData) {
                return [
                    'success' => false,
                    'error' => 'Invalid image data'
                ];
            }

            // **PRIMARY: Try Node.js OCR service first** ✅
            if ($this->isNodeOcrHealthy()) {
                $nodeResult = $this->extractTextViaNodeOcr($imageData, $options);
                if ($nodeResult['success'] && !empty($nodeResult['text'])) {
                    return $nodeResult;
                }
                // Log Node.js failure but continue to fallback
                error_log('Node.js OCR attempt failed, falling back to OCR.space: ' . ($nodeResult['error'] ?? 'Unknown error'));
            }

            // **FALLBACK: Use OCR.space API** 🔄
            error_log('Using fallback OCR.space API for text extraction');

            // Decode base64 to binary
            $imageBinary = base64_decode($imageData);
            if ($imageBinary === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid base64 image data'
                ];
            }

            $originalSizeKB = strlen($imageBinary) / 1024;
            error_log("Image size before processing: {$originalSizeKB} KB");

            // Check and compress image if it exceeds 900KB (leaving 100KB buffer for OCR.space 1MB limit)
            $imageSizeKB = $originalSizeKB;
            $compressedBinary = $imageBinary;

            if ($imageSizeKB > 900) {
                // Try compression at 85% quality
                $compressed85 = $this->compressImage($imageBinary, 85);
                $size85 = strlen($compressed85) / 1024;
                error_log("Image compressed at 85% quality: {$size85} KB");

                if ($size85 > 0 && $size85 <= 950) {
                    $compressedBinary = $compressed85;
                    $imageSizeKB = $size85;
                } else {
                    // Try compression at 70% quality
                    $compressed70 = $this->compressImage($imageBinary, 70);
                    $size70 = strlen($compressed70) / 1024;
                    error_log("Image compressed at 70% quality: {$size70} KB");

                    if ($size70 > 0 && $size70 <= 950) {
                        $compressedBinary = $compressed70;
                        $imageSizeKB = $size70;
                    } else {
                        // Try resizing
                        $resized = $this->resizeImage($imageBinary, 0.8);
                        $sizeResized = strlen($resized) / 1024;
                        error_log("Image resized to 80%: {$sizeResized} KB");

                        if ($sizeResized > 0) {
                            $compressedBinary = $resized;
                            $imageSizeKB = $sizeResized;
                        }
                    }
                }
            }

            error_log("Final image size before upload: {$imageSizeKB} KB");

            // Validate final size
            if ($imageSizeKB > 1024) {
                return [
                    'success' => false,
                    'error' => 'Image too large even after compression (' . round($imageSizeKB, 1) . ' KB). Please use a smaller image.'
                ];
            }

            // Prepare form data for OCR.space API
            $postData = [
                'apikey' => $this->ocrSpaceApiKey,
                'language' => $options['language'] ?? 'eng',
                'isOverlayRequired' => 'false',
                'detectOrientation' => 'true',
                'scale' => 'true'
            ];

            // Create temp file for upload with binary data
            $tempFile = tempnam(sys_get_temp_dir(), 'ocr_');
            $written = file_put_contents($tempFile, $compressedBinary);
            if ($written === false) {
                error_log("Failed to write to temp file: $tempFile");
                return [
                    'success' => false,
                    'error' => 'Failed to write image to temporary file'
                ];
            }

            $tempFileSize = filesize($tempFile);
            error_log("Temp file created at $tempFile, size: {$tempFileSize} bytes");

            // Verify file exists and has content
            if (!file_exists($tempFile) || $tempFileSize === 0) {
                error_log("Temp file verification failed - file may be empty or missing");
                @unlink($tempFile);
                return [
                    'success' => false,
                    'error' => 'Temp file validation failed'
                ];
            }

            try {
                $postData['file'] = new CURLFile($tempFile, 'image/jpeg', 'ocr.jpg');
            } catch (Exception $e) {
                error_log("CURLFile creation failed: " . $e->getMessage());
                @unlink($tempFile);
                return [
                    'success' => false,
                    'error' => 'Failed to prepare file for upload: ' . $e->getMessage()
                ];
            }

            // Call OCR.space API with cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL for OCR.space
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BroxLab/1.0');

            error_log("Sending request to OCR.space API with file size: {$tempFileSize} bytes");
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Clean up temp file immediately
            @unlink($tempFile);

            if ($curlError) {
                error_log("OCR.space cURL error: $curlError");
                return [
                    'success' => false,
                    'error' => 'OCR.space API error: ' . $curlError
                ];
            }

            if ($httpCode !== 200) {
                error_log("OCR.space HTTP error: $httpCode. Response length: " . strlen($response) . " chars");
                error_log("Response preview: " . substr($response, 0, 500));
                return [
                    'success' => false,
                    'error' => 'OCR.space API HTTP ' . $httpCode . ' - ' . substr($response, 0, 100)
                ];
            }

            if (empty($response)) {
                error_log("OCR.space returned empty response");
                return [
                    'success' => false,
                    'error' => 'OCR.space returned empty response'
                ];
            }

            $data = json_decode($response, true);
            if (!$data) {
                error_log("Failed to decode JSON response from OCR.space. Response: " . substr($response, 0, 500));
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response from OCR.space'
                ];
            }

            error_log("OCR.space response: " . json_encode(['IsErroredOnProcessing' => $data['IsErroredOnProcessing'] ?? null, 'ErrorMessage' => $data['ErrorMessage'] ?? null, 'ParsedText_len' => strlen($data['ParsedText'] ?? '')]));

            if ($data['IsErroredOnProcessing'] === true) {
                $errorMsg = is_array($data['ErrorMessage']) ? implode(', ', $data['ErrorMessage']) : ($data['ErrorMessage'] ?? 'Unknown error');
                error_log("OCR.space processing error: $errorMsg");
                return [
                    'success' => false,
                    'error' => 'OCR.space API error: ' . $errorMsg
                ];
            }

            $text = trim($data['ParsedText'] ?? '');
            if (empty($text)) {
                error_log("OCR.space returned empty text. Full response: " . json_encode($data));
                return [
                    'success' => false,
                    'error' => 'No text extracted from image (OCR returned empty result)'
                ];
            }

            error_log("OCR.space successfully extracted text (" . strlen($text) . " chars)");
            return [
                'success' => true,
                'text' => $text,
                'engine' => 'OCR.space'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Compress image using GD library - returns binary data
     */
    private function compressImage(string $imageBinary, int $quality = 80): string
    {
        try {
            if (!extension_loaded('gd')) {
                error_log("GD extension not loaded, returning original image");
                return $imageBinary; // Return original if GD not available
            }

            $image = @imagecreatefromstring($imageBinary);
            if (!$image) {
                error_log("Failed to create image from binary data (imagecreatefromstring returned false)");
                return $imageBinary; // Return original if can't create image
            }

            // Capture JPEG output to string
            ob_start();
            $jpegged = @imagejpeg($image, null, $quality);
            $compressed = ob_get_clean();
            @imagedestroy($image);

            if (!$jpegged || !$compressed || strlen($compressed) === 0) {
                error_log("imagejpeg failed or returned empty output at quality {$quality}");
                return $imageBinary;
            }

            return $compressed;
        } catch (Exception $e) {
            error_log("Image compression failed: " . $e->getMessage());
            return $imageBinary;
        }
    }

    /**
     * Resize image to reduce file size - returns binary data
     */
    private function resizeImage(string $imageBinary, float $scale = 0.8): string
    {
        try {
            if (!extension_loaded('gd')) {
                error_log("GD extension not loaded, cannot resize image");
                return $imageBinary;
            }

            $image = @imagecreatefromstring($imageBinary);
            if (!$image) {
                error_log("Failed to create image from binary data in resizeImage");
                return $imageBinary;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            error_log("Resizing image from {$width}x{$height} to {$newWidth}x{$newHeight}");

            // Prevent image from being too small
            if ($newWidth < 100 || $newHeight < 100) {
                $newWidth = max(100, $newWidth);
                $newHeight = max(100, $newHeight);
            }

            $resized = @imagecreatetruecolor($newWidth, $newHeight);
            if (!$resized) {
                error_log("Failed to create resized image canvas");
                @imagedestroy($image);
                return $imageBinary;
            }

            @imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            ob_start();
            $jpegOk = @imagejpeg($resized, null, 85);
            $result = ob_get_clean();
            @imagedestroy($image);
            @imagedestroy($resized);

            if (!$jpegOk || !$result || strlen($result) === 0) {
                error_log("imagejpeg failed in resizeImage");
                return $imageBinary;
            }

            return $result;
            @imagedestroy($resized);

            return ($result && strlen($result) > 0) ? $result : $imageBinary;
        } catch (Exception $e) {
            error_log("Image resize failed: " . $e->getMessage());
            return $imageBinary;
        }
    }

    /**
     * Extract text from PDF (convert to images first, then OCR)
     */
    public function extractTextFromPDF(string $pdfData, array $options = []): array
    {
        try {
            if ($this->isNodeOcrHealthy()) {
                $pdfData = preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pdfData)
                    ? $pdfData
                    : base64_encode($pdfData);

                $nodeResult = $this->extractTextViaNodePdf($pdfData, $options);
                if ($nodeResult['success']) {
                    return $nodeResult;
                }
            }

            return [
                'success' => false,
                'error' => 'PDF processing not available in web hosting environment. Please convert PDF to images first.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process batch of files
     */
    public function processBatch(array $files, array $options = []): array
    {
        $results = [];

        foreach ($files as $index => $file) {
            $result = $this->extractTextFromImage($file, $options);
            $results[] = [
                'index' => $index,
                'result' => $result
            ];
        }

        return [
            'success' => true,
            'results' => $results
        ];
    }

    /**
     * Call OCR.space API
     */
    private function callOCRspaceAPI(array $postData): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.ocr.space/parse/image',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: PHP-OCR-Service/1.0'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'API request failed: ' . $error
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'API returned HTTP ' . $httpCode
            ];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid API response'
            ];
        }

        // Check for API errors
        if (!empty($result['ErrorMessage'])) {
            return [
                'success' => false,
                'error' => 'OCR.space API error: ' . implode(', ', $result['ErrorMessage'])
            ];
        }

        // Extract text from response
        if (!empty($result['ParsedResults']) && !empty($result['ParsedResults'][0]['ParsedText'])) {
            return [
                'success' => true,
                'text' => trim($result['ParsedResults'][0]['ParsedText']),
                'language' => $postData['language'] ?? 'eng',
                'source' => 'ocr_space_api'
            ];
        }

        return [
            'success' => false,
            'error' => 'No text found in image'
        ];
    }

    /**
     * Get API usage info (for OCR.space)
     */
    public function getUsageInfo(): array
    {
        // OCR.space doesn't provide usage info in free tier
        return [
            'success' => true,
            'info' => 'Using OCR.space free tier (unlimited requests, watermark may be added)',
            'limits' => [
                'max_file_size' => '1MB',
                'rate_limit' => 'None (free tier)',
                'accuracy' => 'Good for clear text'
            ]
        ];
    }
}
