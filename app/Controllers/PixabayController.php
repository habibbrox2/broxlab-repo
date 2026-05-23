<?php

/**
 * Pixabay Controller - Image Search & Download
 * ============================================================================
 * Routes:
 *   GET  /api/pixabay/search          ?q=...&page=...&per_page=...
 *   POST /api/pixabay/download-image   { image_url, alt_text? }
 */

$router->get('/api/pixabay/search', function () {
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
        $perPage = min(50, max(3, (int)($_GET['per_page'] ?? 20)));
        $apiKey = getenv('PIXABAY_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            $apiKey = $_ENV['PIXABAY_API_KEY'] ?? $_SERVER['PIXABAY_API_KEY'] ?? '';
        }

        if (empty($apiKey)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'PIXABAY_API_KEY not configured. Add to .env', 'error_code' => 'missing_api_key']);
            exit;
        }

        $cache = null;
        if (class_exists('\App\Modules\AISystem\UnifiedCache')) {
            $cache = \App\Modules\AISystem\UnifiedCache::getInstance();
        }

        $cacheKey = 'pixabay-search:' . md5(strtolower($query) . ':' . $page . ':' . $perPage);
        if ($cache) {
            $cached = $cache->get($cacheKey, \App\Modules\AISystem\UnifiedCache::CATEGORY_RESPONSE, 86400);
            if ($cached !== false) {
                echo $cached;
                exit;
            }
        }

        $url = 'https://pixabay.com/api/?key=' . urlencode($apiKey)
            . '&q=' . urlencode($query)
            . '&per_page=' . $perPage
            . '&page=' . $page
            . '&image_type=photo'
            . '&safesearch=true'
            . '&lang=en';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'BroxLab/1.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException('Pixabay API network error: ' . $error);
        }

        if ($httpCode !== 200) {
            $decoded = json_decode($response, true);
            $errMsg = isset($decoded['error']) ? $decoded['error'] : (isset($decoded['message']) ? $decoded['message'] : "HTTP {$httpCode}");
            throw new RuntimeException('Pixabay API error: ' . $errMsg);
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['hits'])) {
            throw new RuntimeException('Invalid Pixabay API response');
        }

        $images = [];
        foreach ($data['hits'] as $hit) {
            $images[] = [
                'id' => $hit['id'] ?? 0,
                'width' => $hit['imageWidth'] ?? 0,
                'height' => $hit['imageHeight'] ?? 0,
                'tags' => $hit['tags'] ?? '',
                'alt' => $hit['tags'] ?? 'Pixabay image',
                'photographer' => $hit['user'] ?? '',
                'photographer_url' => isset($hit['user']) && isset($hit['user_id']) ? 'https://pixabay.com/users/' . urlencode($hit['user']) . '-' . intval($hit['user_id']) . '/' : '',
                'page_url' => $hit['pageURL'] ?? '',
                'src' => [
                    'preview' => $hit['previewURL'] ?? '',
                    'small' => $hit['webformatURL'] ?? '',
                    'medium' => $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '',
                    'large' => $hit['fullHDURL'] ?? $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '',
                    'original' => $hit['imageURL'] ?? $hit['fullHDURL'] ?? $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '',
                ],
                'user_image_url' => $hit['userImageURL'] ?? '',
            ];
        }

        $output = json_encode([
            'success' => true,
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'total_results' => $data['totalHits'] ?? 0,
            'has_more' => (($data['totalHits'] ?? 0) > ($page * $perPage)),
            'images' => $images,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($cache && $output !== false) {
            $cache->set($cacheKey, $output, \App\Modules\AISystem\UnifiedCache::CATEGORY_RESPONSE, 86400, ['pixabay']);
        }

        echo $output;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'pixabay_search_error']);
    }

    exit;
});

$router->post('/api/pixabay/download-image', function () {
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

        if (!preg_match('#^https://(?:cdn\.pixabay\.com|pixabay\.com)/#i', $imageUrl)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Only Pixabay image URLs allowed', 'error_code' => 'invalid_url']);
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
        $filename = 'pixabay-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        global $mysqli;

        if (class_exists('MediaManager')) {
            if (!isset($mysqli) || !$mysqli instanceof mysqli) {
                $mysqli = null;
            }
            $mm = new MediaManager($mysqli);
            $result = $mm->saveFromBinary($imageData, $filename, [
                'alt_text' => $altText ?: 'Image from Pixabay',
                'source' => 'pixabay',
                'source_url' => $imageUrl,
            ]);
            if ($result && !empty($result['url'])) {
                echo json_encode([
                    'success' => true,
                    'url' => $result['url'],
                    'thumbnail_url' => $result['thumbnail_url'] ?? $result['url'],
                    'filename' => $filename,
                    'alt_text' => $altText ?: 'Image from Pixabay',
                    'width' => $result['width'] ?? 0,
                    'height' => $result['height'] ?? 0,
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
            'alt_text' => $altText ?: 'Image from Pixabay',
            'message' => 'Image saved (fallback)',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'error_code' => 'pixabay_download_error']);
    }

    exit;
});
