<?php

/**
 * OCRController.php
 * Handles OCR operations using OCR.space API
 * Web hosting compatible - no system dependencies
 */

require_once __DIR__ . '/../Helpers/OCRService.php';
require_once __DIR__ . '/../Models/AppSettings.php';

// Initialize OCR service
$ocrService = new OCRService();



// ---------------- OCR API ROUTES ----------------

// Test route
$router->get('/test-ocr', function () {
    echo json_encode(['message' => 'OCR Controller is working']);
    exit;
});

$router->get('/api/ocr/health', function () use ($ocrService) {
    try {
        $usageInfo = $ocrService->getUsageInfo();

        aiChatSendJson([
            'status' => 'healthy',
            'ocr_available' => $ocrService->isAvailable(),
            'available_languages' => $ocrService->getAvailableLanguages(),
            'service_type' => 'ocr_space_api',
            'usage_info' => $usageInfo,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        aiChatSendJson([
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Extract text from image
$router->post('/api/ocr/image', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['image'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'Image data is required'
            ], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->extractTextFromImage($input['image'], $options);

        aiChatSendJson($result);
    } catch (Exception $e) {
        aiChatSendJson([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Extract text from PDF
$router->post('/api/ocr/pdf', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['pdf'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'PDF data is required'
            ], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->extractTextFromPDF($input['pdf'], $options);

        aiChatSendJson($result);
    } catch (Exception $e) {
        aiChatSendJson([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Process batch OCR
$router->post('/api/ocr/batch', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['files'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'Files array is required'
            ], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->processBatch($input['files'], $options);

        aiChatSendJson($result);
    } catch (Exception $e) {
        aiChatSendJson([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Handle file upload OCR (alternative to base64)
$router->post('/api/ocr/upload', function () use ($ocrService) {
    try {
        if (!isset($_FILES['file'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'File upload is required'
            ], 400);
            return;
        }

        $file = $_FILES['file'];
        $filePath = $file['tmp_name'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'pdf'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'Unsupported file type'
            ], 400);
            return;
        }

        $fileData = file_get_contents($filePath);
        $base64Data = base64_encode($fileData);

        $options = $_POST['options'] ?? [];
        $options = is_string($options) ? json_decode($options, true) : $options;
        $options = is_array($options) ? $options : [];

        if ($fileType === 'pdf') {
            $result = $ocrService->extractTextFromPDF($base64Data, $options);
        } else {
            $result = $ocrService->extractTextFromImage($base64Data, $options);
        }

        aiChatSendJson($result);
    } catch (Exception $e) {
        aiChatSendJson([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Legacy route support (for backward compatibility)
$router->get('/ocr-api.php/health', function () use ($ocrService) {
    try {
        $usageInfo = $ocrService->getUsageInfo();

        aiChatSendJson([
            'status' => 'healthy',
            'ocr_available' => $ocrService->isAvailable(),
            'available_languages' => $ocrService->getAvailableLanguages(),
            'service_type' => 'ocr_space_api',
            'usage_info' => $usageInfo,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        aiChatSendJson([
            'status' => 'error',
            'error' => $e->getMessage()
        ], 500);
    }
});

$router->post('/ocr-api.php/ocr/image', function () use ($ocrService) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['image'])) {
            aiChatSendJson([
                'success' => false,
                'error' => 'Image data is required'
            ], 400);
            return;
        }

        $options = $input['options'] ?? [];
        $result = $ocrService->extractTextFromImage($input['image'], $options);

        aiChatSendJson($result);
    } catch (Exception $e) {
        aiChatSendJson([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
