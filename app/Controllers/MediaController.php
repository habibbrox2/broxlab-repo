<?php
/**
 * Media Manager Routes - Updated with Enhanced Error Handling
 * 
 * Route-driven controller for comprehensive media management
 * Pattern: Closure-based routes with middleware protection
 * 
 * Routes:
 * - GET /admin/media â†’ Media library listing
 * - GET /admin/media/upload â†’ Upload form
 * - POST /admin/media/upload â†’ Handle file upload (with JSON error responses)
 * - POST /admin/media/{id}/update â†’ Update media info
 * - POST /admin/media/{id}/delete â†’ Soft delete media
 * - GET /admin/media/{id} â†’ Media detail view
 * - GET /api/media â†’ JSON list (with search, filter, pagination)
 * - GET /api/media/{id} â†’ JSON detail
 * - GET /api/media/stats â†’ JSON statistics
 */

global $router, $twig, $mysqli;

// Initialize models and services
$mediaModel = new MediaModel($mysqli);
$mediaManager = new MediaManager($mysqli);
$uploadsPublicUrl = defined('UPLOADS_PUBLIC_URL') ? '/' . trim((string)UPLOADS_PUBLIC_URL, '/') : '/uploads';
// ============================================================================
// WEB ROUTES - Media Administration Interface
// ============================================================================

$router->group('/admin/media', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($router) use ($twig, $mediaModel, $mediaManager, $uploadsPublicUrl) {

    /**
     * GET /admin/media
     * Display media library with pagination and filters
     */
    $router->get('', function () use ($twig, $mediaModel) {
            $page = (int)($_GET['page'] ?? 1);
            $page = max(1, $page);

            $filters = [];
            if (!empty($_GET['type'])) {
                $filters['media_type'] = sanitize_input($_GET['type']);
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = sanitize_input($_GET['search']);
            }

            $data = $mediaModel->getAll($page, 20, $filters);
            $stats = $mediaModel->getStats();
            $typeStats = $mediaModel->getByMediaType();

            echo $twig->render('admin/media/library.twig', [
            'media' => $data['media'],
            'pagination' => [
            'current' => $page,
            'total' => $data['pages'],
            'per_page' => $data['limit'],
            'total_items' => $data['total']
            ],
            'filters' => $_GET,
            'stats' => $stats,
            'type_stats' => $typeStats
            ]);
        }
        );

        /**
     * GET /admin/media/upload
     * Display upload form
     */
        $router->get('/upload', function () use ($twig) {
            echo $twig->render('admin/media/upload.twig', [
            'max_file_size' => formatFileSize(52428800),
            'max_file_size_bytes' => 52428800
            ]);
        }
        );

        /**
     * POST /admin/media/upload
     * Handle file upload using unified UploadService
     * Returns JSON for AJAX requests, redirects for regular form submissions
     */
        $router->post('/upload', function () use ($mediaModel, $twig) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            $uploadStartTime = microtime(true);

            try {
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();

                if (!$user || (int)$userId <= 0) {
                    throw new Exception('User not authenticated or invalid user ID');
                }

                if (!isset($_FILES['file']) || empty($_FILES['file'])) {
                    throw new Exception('à¦•à§‹à¦¨ à¦«à¦¾à¦‡à¦² à¦¨à¦¿à¦°à§à¦¬à¦¾à¦šà¦¨ à¦•à¦°à¦¾ à¦¹à¦¯à¦¼à¦¨à¦¿à¥¤ (No file selected)');
                }

                $file = $_FILES['file'];
                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');
                $mediaType = sanitize_input($_POST['media_type'] ?? 'image');

                if (empty($title)) {
                    $title = pathinfo($file['name'], PATHINFO_FILENAME);
                }

                $validTypes = ['image', 'video', 'audio', 'document'];
                if (!in_array($mediaType, $validTypes)) {
                    throw new Exception('à¦…à¦¬à§ˆà¦§ à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦Ÿà¦¾à¦‡à¦ªà¥¤ (Invalid media type)');
                }

                logDebug('MediaController::upload - Starting upload for user ' . $userId . ', file: ' . $file['name'] . ', type: ' . $mediaType);

                // Use UploadService for unified upload
                $uploadService = new UploadService($GLOBALS['mysqli']);
                $result = $uploadService->upload($file, 'media_library', [
                    'user_id' => $userId,
                    'title' => $title,
                    'description' => $description,
                    'media_type' => $mediaType
                ]);

                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'à¦†à¦ªà¦²à§‹à¦¡ à¦¬à§à¦¯à¦°à§à¦¥ à¦¹à¦¯à¦¼à§‡à¦›à§‡à¥¤ (Upload failed)');
                }

                $uploadTime = round((microtime(true) - $uploadStartTime) * 1000, 2);
                logDebug('MediaController::upload - Upload complete! ID: ' . $result['media_id'] . ', Time: ' . $uploadTime . 'ms');

                $logDetails = ['title' => $title, 'size' => $file['size'], 'type' => $mediaType, 'original_name' => $file['name'], '_performed_by' => $userId];
                logActivity(
                    'Media Uploaded',
                    'media',
                    $result['media_id'],
                    $logDetails,
                    'success'
                );

                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(200);
                    echo json_encode([
                    'success' => true,
                    'message' => 'à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦†à¦ªà¦²à§‹à¦¡ à¦•à¦°à¦¾ à¦¹à¦¯à¦¼à§‡à¦›à§‡! (Media uploaded successfully!)',
                    'media_id' => $result['media_id'],
                    'redirect' => '/admin/media'
                    ]);
                    exit;
                }

                showMessage('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦†à¦ªà¦²à§‹à¦¡ à¦•à¦°à¦¾ à¦¹à¦¯à¦¼à§‡à¦›à§‡! (Media uploaded successfully!)', 'success');
                header('Location: /admin/media');
                exit;

            }
            catch (Exception $e) {
                $errorMsg = $e->getMessage();
                $uploadTime = round((microtime(true) - $uploadStartTime) * 1000, 2);

                logError('MediaController::upload - FAILED! Error: ' . $errorMsg . ', Time: ' . $uploadTime . 'ms, User: ' . $userId, "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);

                logActivity(
                    'Media Upload Failed',
                    'media',
                    null,
                ['error' => $errorMsg, 'file' => $_FILES['file']['name'] ?? 'unknown', 'user_id' => $userId],
                    'failure'
                );

                if ($isAjax) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode([
                    'success' => false,
                    'error' => $errorMsg,
                    'file' => $_FILES['file']['name'] ?? 'unknown'
                    ]);
                    exit;
                }

                showMessage('à¦†à¦ªà¦²à§‹à¦¡ à¦¬à§à¦¯à¦°à§à¦¥: ' . $errorMsg, 'error');
                header('Location: /admin/media/upload');
                exit;
            }
        }
        );

        /**
     * GET /admin/media/{id}
     * View media details with error handling
     */
        $router->get('/{id}', function ($id) use ($twig, $mediaModel, $uploadsPublicUrl) {
            try {
                $mediaId = (int)$id;

                if ($mediaId <= 0) {
                    throw new Exception('à¦…à¦¬à§ˆà¦§ à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦†à¦‡à¦¡à¦¿à¥¤ (Invalid media ID)');
                }

                $media = $mediaModel->getById($mediaId);

                if (!$media) {
                    throw new Exception('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦ªà¦¾à¦“à¦¯à¦¼à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (Media not found)');
                }

                echo $twig->render('admin/media/detail.twig', [
                'media' => $media,
                'media_url' => $uploadsPublicUrl . '/media/' . $media['file_path'],
                'thumbnail_url' => $media['thumbnail_path'] ? $uploadsPublicUrl . '/media/' . $media['thumbnail_path'] : null
                ]);
            }
            catch (Exception $e) {
                showMessage('à¦¤à§à¦°à§à¦Ÿà¦¿: ' . $e->getMessage(), 'error');
                header('Location: /admin/media');
                exit;
            }
        }
        );

        /**
     * POST /admin/media/{id}/update
     * Update media title and description with error handling
     */
        $router->post('/{id}/update', function ($id) use ($mediaModel) {
            try {
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                if (!$user || (int)$userId <= 0) {
                    throw new Exception('User not authenticated or invalid user ID');
                }

                $mediaId = (int)$id;

                if ($mediaId <= 0) {
                    throw new Exception('à¦…à¦¬à§ˆà¦§ à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦†à¦‡à¦¡à¦¿à¥¤ (Invalid media ID)');
                }

                if (!$mediaModel->exists($mediaId)) {
                    throw new Exception('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦ªà¦¾à¦“à¦¯à¦¼à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (Media not found)');
                }

                $title = sanitize_input($_POST['title'] ?? '');
                $description = sanitize_input($_POST['description'] ?? '');

                if (empty($title)) {
                    throw new Exception('à¦¶à¦¿à¦°à§‹à¦¨à¦¾à¦® à¦–à¦¾à¦²à¦¿ à¦¹à¦¤à§‡ à¦ªà¦¾à¦°à§‡ à¦¨à¦¾à¥¤ (Title cannot be empty)');
                }

                $data = [
                    'title' => $title,
                    'description' => $description
                ];

                if (!$mediaModel->update($mediaId, $data)) {
                    throw new Exception('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦†à¦ªà¦¡à§‡à¦Ÿ à¦•à¦°à¦¤à§‡ à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to update media)');
                }

                $data['_performed_by'] = $userId;
                logActivity('Media Updated', 'media', $mediaId, $data, 'success');
                showMessage('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦†à¦ªà¦¡à§‡à¦Ÿ à¦•à¦°à¦¾ à¦¹à¦¯à¦¼à§‡à¦›à§‡! (Media updated successfully!)', 'success');
            }
            catch (Exception $e) {
                $failureDetails = ['error' => $e->getMessage(), 'user_id' => $userId ?? null];
                logActivity('Update Media Failed', 'media', $mediaId ?? 0, $failureDetails, 'failure');
                showMessage('à¦†à¦ªà¦¡à§‡à¦Ÿ à¦¬à§à¦¯à¦°à§à¦¥: ' . $e->getMessage(), 'error');
            }

            header('Location: /admin/media/' . ($mediaId ?? 0));
            exit;
        }
        );

        /**
     * POST /admin/media/{id}/delete
     * Soft delete media with error handling
     */
        $router->post('/{id}/delete', function ($id) use ($mediaModel) {
            try {
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                if (!$user || (int)$userId <= 0) {
                    throw new Exception('User not authenticated or invalid user ID');
                }

                $mediaId = (int)$id;

                if ($mediaId <= 0) {
                    throw new Exception('à¦…à¦¬à§ˆà¦§ à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦†à¦‡à¦¡à¦¿à¥¤ (Invalid media ID)');
                }

                if (!$mediaModel->exists($mediaId)) {
                    throw new Exception('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦ªà¦¾à¦“à¦¯à¦¼à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (Media not found)');
                }

                if (!$mediaModel->softDelete($mediaId)) {
                    throw new Exception('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦®à§à¦›à¦¤à§‡ à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to delete media)');
                }

                $details = ['action' => 'soft_delete', '_performed_by' => $userId];
                logActivity('Media Deleted', 'media', $mediaId, $details, 'success');
                showMessage('à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦¸à¦«à¦²à¦­à¦¾à¦¬à§‡ à¦®à§à¦›à§‡ à¦¦à§‡à¦“à¦¯à¦¼à¦¾ à¦¹à¦¯à¦¼à§‡à¦›à§‡! (Media deleted successfully!)', 'success');
            }
            catch (Exception $e) {
                $failureDetails = ['error' => $e->getMessage(), 'user_id' => $userId ?? null];
                logActivity('Delete Media Failed', 'media', $mediaId ?? 0, $failureDetails, 'failure');
                showMessage('à¦®à§‹à¦›à¦¾à¦° à¦¬à§à¦¯à¦°à§à¦¥: ' . $e->getMessage(), 'error');
            }

            header('Location: /admin/media');
            exit;
        }
        );
    });

// ============================================================================
// API ROUTES - JSON Media Endpoints
// ============================================================================

$router->group('/api/media', ['middleware' => ['auth', 'admin_only']], function ($router) use ($mediaModel, $uploadsPublicUrl) {

    /**
     * GET /api/media
     * JSON list of media with pagination and filtering
     * Query params: page, limit, type, search
     */
    $router->get('', function () use ($mediaModel) {
            try {
                $page = (int)($_GET['page'] ?? 1);
                $limit = min((int)($_GET['limit'] ?? 20), 100);
                $page = max(1, $page);
                $limit = max(1, $limit);

                $filters = [];
                if (!empty($_GET['type'])) {
                    $type = sanitize_input($_GET['type']);
                    $validTypes = ['image', 'video', 'audio', 'document'];
                    if (in_array($type, $validTypes)) {
                        $filters['media_type'] = $type;
                    }
                }
                if (!empty($_GET['search'])) {
                    $search = sanitize_input($_GET['search']);
                    if (strlen($search) >= 2) {
                        $filters['search'] = $search;
                    }
                }

                $data = $mediaModel->getAll($page, $limit, $filters);

                return json_response([
                'success' => true,
                'media' => $data['media'],
                'pagination' => [
                'page' => $data['page'],
                'limit' => $data['limit'],
                'total' => $data['total'],
                'pages' => $data['pages']
                ]
                ]);
            }
            catch (Exception $e) {
                return json_response([
                'success' => false,
                'error' => 'à¦¡à§‡à¦Ÿà¦¾ à¦ªà§à¦¨à¦°à§à¦¦à§à¦§à¦¾à¦° à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to retrieve data)',
                'message' => $e->getMessage()
                ], 500);
            }
        }
        );

        /**
     * GET /api/media/{id}
     * Get single media details with error handling
     */
        $router->get('/{id}', function ($id) use ($mediaModel, $uploadsPublicUrl) {
            try {
                $mediaId = (int)$id;

                if ($mediaId <= 0) {
                    return json_response([
                    'success' => false,
                    'error' => 'à¦…à¦¬à§ˆà¦§ à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦†à¦‡à¦¡à¦¿à¥¤ (Invalid media ID)'
                    ], 400);
                }

                $media = $mediaModel->getById($mediaId);

                if (!$media) {
                    return json_response([
                    'success' => false,
                    'error' => 'à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦ªà¦¾à¦“à¦¯à¦¼à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (Media not found)'
                    ], 404);
                }

                return json_response([
                'success' => true,
                'media' => $media,
                'media_url' => $uploadsPublicUrl . '/media/' . $media['file_path'],
                'thumbnail_url' => $media['thumbnail_path'] ? $uploadsPublicUrl . '/media/' . $media['thumbnail_path'] : null
                ]);
            }
            catch (Exception $e) {
                return json_response([
                'success' => false,
                'error' => 'à¦®à¦¿à¦¡à¦¿à¦¯à¦¼à¦¾ à¦ªà§à¦¨à¦°à§à¦¦à§à¦§à¦¾à¦° à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to retrieve media)',
                'message' => $e->getMessage()
                ], 500);
            }
        }
        );

        /**
     * GET /api/media/stats
     * Get media statistics with error handling
     */
        $router->get('/stats', function () use ($mediaModel) {
            try {
                $stats = $mediaModel->getStats();
                $typeStats = $mediaModel->getByMediaType();

                if (!$stats) {
                    throw new Exception('à¦ªà¦°à¦¿à¦¸à¦‚à¦–à§à¦¯à¦¾à¦¨ à¦ªà§à¦¨à¦°à§à¦¦à§à¦§à¦¾à¦° à¦•à¦°à¦¾ à¦¯à¦¾à¦¯à¦¼à¦¨à¦¿à¥¤ (Failed to retrieve statistics)');
                }

                return json_response([
                'success' => true,
                'stats' => $stats,
                'by_type' => $typeStats
                ]);
            }
            catch (Exception $e) {
                return json_response([
                'success' => false,
                'error' => 'à¦ªà¦°à¦¿à¦¸à¦‚à¦–à§à¦¯à¦¾à¦¨ à¦ªà§à¦¨à¦°à§à¦¦à§à¦§à¦¾à¦° à¦¬à§à¦¯à¦°à§à¦¥à¥¤ (Failed to retrieve statistics)',
                'message' => $e->getMessage()
                ], 500);
            }
        }
        );
    });
