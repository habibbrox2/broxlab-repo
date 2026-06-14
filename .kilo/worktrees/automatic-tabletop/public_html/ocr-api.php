<?php

/**
 * OCR API Server - PHP-based REST API for OCR operations
 * No Python dependencies - uses Tesseract directly
 */

require_once dirname(__DIR__, 1) . '/app/Services/OCRService.php';

// Set headers for CORS and JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize OCR service
$ocr = new OCRService();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/ocr-api.php', '', $path); // Remove script name if needed

// Route handling
try {
    switch ($path) {
        case '/health':
            if ($method === 'GET') {
                $usageInfo = $ocr->getUsageInfo();
                echo json_encode([
                    'status' => 'healthy',
                    'ocr_available' => $ocr->isAvailable(),
                    'available_languages' => $ocr->getAvailableLanguages(),
                    'service_type' => 'ocr_space_api',
                    'usage_info' => $usageInfo,
                    'timestamp' => date('c')
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/ocr/image':
            if ($method === 'POST') {
                handleImageOCR($ocr);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/ocr/pdf':
            if ($method === 'POST') {
                handlePdfOCR($ocr);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/ocr/batch':
            if ($method === 'POST') {
                handleBatchOCR($ocr);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle image OCR request
 */
function handleImageOCR(OCRService $ocr): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['image'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Image data is required']);
        return;
    }

    $options = $input['options'] ?? [];
    $result = $ocr->extractTextFromImage($input['image'], $options);

    echo json_encode($result);
}

/**
 * Handle PDF OCR request
 */
function handlePdfOCR(OCRService $ocr): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['pdf'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'PDF data is required']);
        return;
    }

    $options = $input['options'] ?? [];
    $result = $ocr->extractTextFromPDF($input['pdf'], $options);

    echo json_encode($result);
}

/**
 * Handle batch OCR request
 */
function handleBatchOCR(OCRService $ocr): void
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['files'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Files array is required']);
        return;
    }

    $options = $input['options'] ?? [];
    $result = $ocr->processBatch($input['files'], $options);

    echo json_encode($result);
}

/**
 * Handle file upload OCR request (alternative to base64)
 */
function handleFileUploadOCR(OCRService $ocr): void
{
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File upload is required']);
        return;
    }

    $file = $_FILES['file'];
    $filePath = $file['tmp_name'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'bmp', 'tiff', 'tif', 'pdf'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
        return;
    }

    $fileData = file_get_contents($filePath);
    $base64Data = base64_encode($fileData);

    $options = $_POST['options'] ?? [];
    $options = is_string($options) ? json_decode($options, true) : $options;
    $options = is_array($options) ? $options : [];

    if ($fileType === 'pdf') {
        $result = $ocr->extractTextFromPDF($base64Data, $options);
    } else {
        $result = $ocr->extractTextFromImage($base64Data, $options);
    }

    echo json_encode($result);
}

// If this script is run directly, start a simple server
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Check if running from command line
    if (php_sapi_name() === 'cli') {
        echo "OCR API Server\n";
        echo "Usage: php ocr-api.php\n";
        echo "Then access via: http://localhost:8000/ocr-api.php/health\n";
        echo "\nStarting server on port 8000...\n";

        // Simple built-in server for testing
        $command = 'php -S localhost:8000 ' . __FILE__;
        passthru($command);
    }
}
