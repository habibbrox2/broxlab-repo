<?php
/**
 * Pexels Controller - AI Image Search & Download
 * ============================================================================
 * Routes:
 *   GET  /api/pexels/search          ?q=...&page=...
 *   POST /api/pexels/download-image   { image_url, alt_text? }
 */

$router->get('/api/pexels/search', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $query = trim((string)($_GET['q'] ?? ''));
        if ($query === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Search query is required', 'error_code' => 'missing_query']);
            exit;
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(20, max(1, (int)($_GET['per_page'] ?? 15)));
        $apiKey = getenv('PEXELS_API_KEY');

        if (empty($apiKey)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'PEXELS_API_KEY not configured. Add to .env', 'error_code' => 'missing_api_key']);
            exit;
        }

        $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . $perPage . '&page=' . $page;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $apiKey, 'User-Agent: BroxLab/1.0'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('Pexels API network error: ' . $error);
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $errMsg = isset($decoded['error']) ? $decoded['error'] : (isset($decoded['message']) ? $decoded['message'] : "HTTP {$httpCode}");
            throw new RuntimeException("Pexels API error: {$errMsg}");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['photos'])) {
            throw new RuntimeException('Invalid Pexels API response');
        }

        $photos = [];
        foreach ($data['photos'] as $photo) {
            $photos[] = [
                'id' => $photo['id'] ?? 0,
                'width' => $photo['width'] ?? 0,
                'height' => $photo['height'] ?? 0,
                'alt' => $photo['alt'] ?? '',
                'photographer' => $photo['photographer'] ?? '',
                'photographer_url' => $photo['photographer_url'] ?? '',
                'url' => $photo['url'] ?? '',
                'src' => [
                    'original' => $photo['src']['original'] ?? '',
                    'large' => $photo['src']['large'] ?? '',
                    'large2x' => $photo['src']['large2x'] ?? '',
                    'medium' => $photo['src']['medium'] ?? '',
                    'small' => $photo['src']['small'] ?? '',
                    'tiny' => $photo['src']['tiny'] ?? '',
                ],
            ];
        }

        echo json_encode([
            'success' => true,
            'query' => $query,
            'page' => $data['page'] ?? 1,
            'per_page' => $data['per_page'] ?? $perPage,
            'total_results' => $data['total_results'] ?? 0,
            'has_more' => ($data['next_page'] ?? null) !== null,
            'photos' => $photos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'pexels_search_error']);
    }

    exit;
});

$router->post('/api/pexels/download-image', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $imageUrl = trim((string)($input['image_url'] ?? $_POST['image_url'] ?? ''));
        $altText = trim((string)($input['alt_text'] ?? $_POST['alt_text'] ?? ''));

        if ($imageUrl === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'image_url is required', 'error_code' => 'missing_image_url']);
            exit;
        }

        if (!preg_match('#^https://images\.pexels\.com/photos/#i', $imageUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Only Pexels image URLs allowed', 'error_code' => 'invalid_url']);
            exit;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $imageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'BroxLab/1.0',
        ]);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200 || !$imageData) {
            $errMsg = $error ? $error : "HTTP {$httpCode}";
            throw new RuntimeException('Failed to download image: ' . $errMsg);
        }

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $ext = isset($extMap[$contentType]) ? $extMap[$contentType] : 'jpg';
        $filename = 'pexels-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        if (class_exists('MediaManager')) {
            $mm = new MediaManager();
            $result = $mm->saveFromBinary($imageData, $filename, [
                'alt_text' => $altText ?: 'Image from Pexels',
                'source' => 'pexels',
                'source_url' => $imageUrl,
            ]);
            if ($result && !empty($result['url'])) {
                echo json_encode([
                    'success' => true,
                    'url' => $result['url'],
                    'thumbnail_url' => isset($result['thumbnail_url']) ? $result['thumbnail_url'] : $result['url'],
                    'filename' => $filename,
                    'alt_text' => $altText ?: 'Image from Pexels',
                    'width' => isset($result['width']) ? $result['width'] : 0,
                    'height' => isset($result['height']) ? $result['height'] : 0,
                    'message' => 'Image saved successfully',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        $yearMonth = date('Y/m');
        $baseDir = defined('UPLOADS_MEDIA_DIR') ? UPLOADS_MEDIA_DIR : (dirname(__DIR__, 1) . '/public_html/uploads/media');
        $targetDir = rtrim($baseDir, '/\\') . '/' . $yearMonth;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $targetPath = $targetDir . '/' . $filename;
        $written = file_put_contents($targetPath, $imageData);
        if ($written === false) {
            throw new RuntimeException('Failed to save image to disk');
        }
        $publicUrl = '/uploads/media/' . $yearMonth . '/' . $filename;

        echo json_encode([
            'success' => true,
            'url' => $publicUrl,
            'thumbnail_url' => $publicUrl,
            'filename' => $filename,
            'alt_text' => $altText ?: 'Image from Pexels',
            'message' => 'Image saved (fallback)',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'pexels_download_error']);
    }

    exit;
});
