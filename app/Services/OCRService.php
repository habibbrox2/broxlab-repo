<?php

/**
 * OCR Service - PHP-only approach
 * Primary: OCR.space API for image OCR
 * Optional PDF support via local pdftotext if installed
 */

class OCRService
{
    private string $ocrSpaceApiKey;
    private int $timeout;
    private array $supportedLanguages = ['eng', 'ben', 'eng+ben'];

    public function __construct(string $apiKey = null, int $timeout = 30)
    {
        $this->ocrSpaceApiKey = $apiKey ?: getenv('OCR_SPACE_API_KEY') ?: '';
        $this->timeout = $timeout;
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
     * Check whether pdftotext is available in the current environment
     */
    private function canUsePdftotext(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $output = [];
        $returnVar = 1;
        @exec('pdftotext -v 2>&1', $output, $returnVar);

        if ($returnVar === 0) {
            return true;
        }

        return stripos(implode(' ', $output), 'pdftotext') !== false;
    }


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

            // Use OCR.space API for image extraction
            aiErrorLog('Using OCR.space API for text extraction');

            // Decode base64 to binary
            $imageBinary = base64_decode($imageData);
            if ($imageBinary === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid base64 image data'
                ];
            }

            $originalSizeKB = strlen($imageBinary) / 1024;
            aiErrorLog("Image size before processing: {$originalSizeKB} KB");

            // Check and compress image if it exceeds 900KB (leaving 100KB buffer for OCR.space 1MB limit)
            $imageSizeKB = $originalSizeKB;
            $compressedBinary = $imageBinary;

            if ($imageSizeKB > 900) {
                // Try compression at 85% quality
                $compressed85 = $this->compressImage($imageBinary, 85);
                $size85 = strlen($compressed85) / 1024;
                aiErrorLog("Image compressed at 85% quality: {$size85} KB");

                if ($size85 > 0 && $size85 <= 950) {
                    $compressedBinary = $compressed85;
                    $imageSizeKB = $size85;
                } else {
                    // Try compression at 70% quality
                    $compressed70 = $this->compressImage($imageBinary, 70);
                    $size70 = strlen($compressed70) / 1024;
                    aiErrorLog("Image compressed at 70% quality: {$size70} KB");

                    if ($size70 > 0 && $size70 <= 950) {
                        $compressedBinary = $compressed70;
                        $imageSizeKB = $size70;
                    } else {
                        // Try resizing
                        $resized = $this->resizeImage($imageBinary, 0.8);
                        $sizeResized = strlen($resized) / 1024;
                        aiErrorLog("Image resized to 80%: {$sizeResized} KB");

                        if ($sizeResized > 0) {
                            $compressedBinary = $resized;
                            $imageSizeKB = $sizeResized;
                        }
                    }
                }
            }

            aiErrorLog("Final image size before upload: {$imageSizeKB} KB");

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
                aiErrorLog("Failed to write to temp file: $tempFile");
                return [
                    'success' => false,
                    'error' => 'Failed to write image to temporary file'
                ];
            }

            $tempFileSize = filesize($tempFile);
            aiErrorLog("Temp file created at $tempFile, size: {$tempFileSize} bytes");

            // Verify file exists and has content
            if (!file_exists($tempFile) || $tempFileSize === 0) {
                aiErrorLog("Temp file verification failed - file may be empty or missing");
                @unlink($tempFile);
                return [
                    'success' => false,
                    'error' => 'Temp file validation failed'
                ];
            }

            try {
                $postData['file'] = new CURLFile($tempFile, 'image/jpeg', 'ocr.jpg');
            } catch (Exception $e) {
                aiErrorLog("CURLFile creation failed: " . $e->getMessage());
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

            aiErrorLog("Sending request to OCR.space API with file size: {$tempFileSize} bytes");
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Clean up temp file immediately
            @unlink($tempFile);

            if ($curlError) {
                aiErrorLog("OCR.space cURL error: $curlError");
                return [
                    'success' => false,
                    'error' => 'OCR.space API error: ' . $curlError
                ];
            }

            if ($httpCode !== 200) {
                aiErrorLog("OCR.space HTTP error: $httpCode. Response length: " . strlen($response) . " chars");
                aiErrorLog("Response preview: " . substr($response, 0, 500));
                return [
                    'success' => false,
                    'error' => 'OCR.space API HTTP ' . $httpCode . ' - ' . substr($response, 0, 100)
                ];
            }

            if (empty($response)) {
                aiErrorLog("OCR.space returned empty response");
                return [
                    'success' => false,
                    'error' => 'OCR.space returned empty response'
                ];
            }

            $data = json_decode($response, true);
            if (!$data) {
                aiErrorLog("Failed to decode JSON response from OCR.space. Response: " . substr($response, 0, 500));
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response from OCR.space'
                ];
            }

            aiErrorLog("OCR.space response: " . json_encode(['IsErroredOnProcessing' => $data['IsErroredOnProcessing'] ?? null, 'ErrorMessage' => $data['ErrorMessage'] ?? null, 'ParsedText_len' => strlen($data['ParsedText'] ?? '')]));

            if ($data['IsErroredOnProcessing'] === true) {
                $errorMsg = is_array($data['ErrorMessage']) ? implode(', ', $data['ErrorMessage']) : ($data['ErrorMessage'] ?? 'Unknown error');
                aiErrorLog("OCR.space processing error: $errorMsg");
                return [
                    'success' => false,
                    'error' => 'OCR.space API error: ' . $errorMsg
                ];
            }

            $text = trim($data['ParsedText'] ?? '');
            if (empty($text)) {
                aiErrorLog("OCR.space returned empty text. Full response: " . json_encode($data));
                return [
                    'success' => false,
                    'error' => 'No text extracted from image (OCR returned empty result)'
                ];
            }

            aiErrorLog("OCR.space successfully extracted text (" . strlen($text) . " chars)");
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
                aiErrorLog("GD extension not loaded, returning original image");
                return $imageBinary; // Return original if GD not available
            }

            $image = @imagecreatefromstring($imageBinary);
            if (!$image) {
                aiErrorLog("Failed to create image from binary data (imagecreatefromstring returned false)");
                return $imageBinary; // Return original if can't create image
            }

            // Capture JPEG output to string
            ob_start();
            $jpegged = @imagejpeg($image, null, $quality);
            $compressed = ob_get_clean();
            @imagedestroy($image);

            if (!$jpegged || !$compressed || strlen($compressed) === 0) {
                aiErrorLog("imagejpeg failed or returned empty output at quality {$quality}");
                return $imageBinary;
            }

            return $compressed;
        } catch (Exception $e) {
            aiErrorLog("Image compression failed: " . $e->getMessage());
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
                aiErrorLog("GD extension not loaded, cannot resize image");
                return $imageBinary;
            }

            $image = @imagecreatefromstring($imageBinary);
            if (!$image) {
                aiErrorLog("Failed to create image from binary data in resizeImage");
                return $imageBinary;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);

            aiErrorLog("Resizing image from {$width}x{$height} to {$newWidth}x{$newHeight}");

            // Prevent image from being too small
            if ($newWidth < 100 || $newHeight < 100) {
                $newWidth = max(100, $newWidth);
                $newHeight = max(100, $newHeight);
            }

            $resized = @imagecreatetruecolor($newWidth, $newHeight);
            if (!$resized) {
                aiErrorLog("Failed to create resized image canvas");
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
                aiErrorLog("imagejpeg failed in resizeImage");
                return $imageBinary;
            }

            return $result;
            @imagedestroy($resized);

            return ($result && strlen($result) > 0) ? $result : $imageBinary;
        } catch (Exception $e) {
            aiErrorLog("Image resize failed: " . $e->getMessage());
            return $imageBinary;
        }
    }

    /**
     * Extract text from PDF (convert to images first, then OCR)
     */
    public function extractTextFromPDF(string $pdfData, array $options = []): array
    {
        try {
            $pdfData = preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $pdfData)
                ? $pdfData
                : base64_encode($pdfData);

            if ($this->canUsePdftotext()) {
                $tempPdf = tempnam(sys_get_temp_dir(), 'ocr_pdf_');
                if ($tempPdf === false) {
                    return ['success' => false, 'error' => 'Failed to create temporary file for PDF'];
                }

                file_put_contents($tempPdf, base64_decode($pdfData));
                $outputTxt = $tempPdf . '.txt';
                $command = escapeshellcmd('pdftotext') . ' ' . escapeshellarg($tempPdf) . ' ' . escapeshellarg($outputTxt);
                exec($command . ' 2>&1', $execOutput, $returnVar);

                if ($returnVar !== 0 || !file_exists($outputTxt)) {
                    @unlink($tempPdf);
                    @unlink($outputTxt);
                    return [
                        'success' => false,
                        'error' => 'PDF text extraction failed using pdftotext: ' . implode(' ', $execOutput)
                    ];
                }

                $text = trim((string)file_get_contents($outputTxt));
                @unlink($tempPdf);
                @unlink($outputTxt);

                if ($text === '') {
                    return ['success' => false, 'error' => 'PDF extracted no text'];
                }

                return [
                    'success' => true,
                    'text' => $text,
                    'engine' => 'pdftotext'
                ];
            }

            return [
                'success' => false,
                'error' => 'PDF processing is not available. Install pdftotext or convert PDF pages to images first.'
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
