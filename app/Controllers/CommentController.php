<?php
/**
 * controllers/CommentController.php
 * 
 * Handles all comment-related routes
 * - User comment CRUD operations (API endpoints)
 * - Admin comment management (API + Template rendering)
 */

declare(strict_types = 1)
;

global $mysqli, $twig;

$commentModel = new CommentModel($mysqli);
$purifier = getPurifier();

// =====================================================================
// ROUTE GROUP: /comment (User Comment Operations - API)
// =====================================================================
$router->group('/comment', [], function ($router) use ($commentModel, $purifier) {

    /**
     * POST /comment/add
     * Add a new comment
     */
    $router->post('/add', ['middleware' => ['api_headers']], function () use ($commentModel, $purifier) {
            header('Content-Type: application/json');

            try {
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;

                $guest_name = !empty($_POST['guest_name']) ? trim($_POST['guest_name']) : null;
                $content = !empty($_POST['content']) ? trim($_POST['content']) : '';
                $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $content_type = !empty($_POST['content_type']) ? preg_replace('/[^a-z0-9_\-]/i', '', $_POST['content_type']) : 'post';
                $content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;

                $content = $purifier->purify($content);

                if (empty($content) || strlen($content) < 2) {
                    return json_response(['success' => false, 'message' => 'Comment must be at least 2 characters'], 400);
                }
                if ($content_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid content target'], 400);
                }
                if (!$user_id && empty($guest_name)) {
                    return json_response(['success' => false, 'message' => 'Guest name required for anonymous comments'], 400);
                }

                $comment_id = $commentModel->addComment($user_id, $guest_name, $content, $parent_id, $content_type, $content_id);

                if ($comment_id) {
                    // Notify owner (parent-comment owner first, otherwise content owner).
                    if ($user_id) {
                        global $mysqli;
                        $targetUserId = 0;

                        if (!empty($parent_id)) {
                            $parentComment = $commentModel->getCommentById((int)$parent_id);
                            $targetUserId = (int)($parentComment['user_id'] ?? 0);
                        } else {
                            $ownerTable = null;
                            if ($content_type === 'post') {
                                $ownerTable = 'posts';
                            } elseif ($content_type === 'page') {
                                $ownerTable = 'pages';
                            }

                            if ($ownerTable !== null) {
                                $ownerStmt = $mysqli->prepare("SELECT user_id FROM {$ownerTable} WHERE id = ? LIMIT 1");
                                if ($ownerStmt) {
                                    $ownerStmt->bind_param('i', $content_id);
                                    $ownerStmt->execute();
                                    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
                                    $targetUserId = (int)($ownerRow['user_id'] ?? 0);
                                    $ownerStmt->close();
                                }
                            }
                        }

                        if ($targetUserId > 0 && $targetUserId !== (int)$user_id) {
                            $notificationModel = new NotificationModel($mysqli);
                            $notifTitle = !empty($parent_id) ? "New Reply" : "New Comment";
                            $notifMessage = !empty($parent_id)
                                ? "Someone replied to your comment: \"" . substr($content, 0, 50) . "...\""
                                : "Someone commented on your content: \"" . substr($content, 0, 50) . "...\"";

                            $notifId = $notificationModel->create(
                                (int)$user_id,
                                $notifTitle,
                                $notifMessage,
                                "announcement",
                                [
                                    "user_id" => $targetUserId,
                                    "channels" => ["push", "in_app", "email"],
                                ]
                            );

                            if ($notifId) {
                                $notificationModel->logDelivery($notifId, $targetUserId, "sent", null, (string)$comment_id, "system", "comment");
                            }
                        }
                    }

                    logActivity("Comment Added", "comment", $comment_id, [
                        'content_type' => $content_type,
                        'content_id' => $content_id,
                        'author' => $guest_name ?? "User {$user_id}"
                    ], 'success');

                    return json_response([
                    'success' => true,
                    'message' => 'Comment posted successfully',
                    'id' => $comment_id,
                    'timestamp' => date('Y-m-d H:i:s')
                    ], 201);
                }
                else {
                    throw new Exception('Failed to insert comment');
                }
            }
            catch (Exception $e) {
                logActivity("Comment Add Failed", "comment", 0, ['error' => $e->getMessage()], 'failure');
                return json_response(['success' => false, 'message' => 'Failed to add comment'], 500);
            }
        }
        );

        /**
     * POST /comment/like
     * Like/Unlike a comment
     */
        $router->post('/like', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
                $comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;
                $guest_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                if ($comment_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Comment ID required'], 400);
                }

                $likes = $commentModel->likeComment($comment_id, $user_id, $guest_ip);

                if ($likes === false) {
                    return json_response(['success' => false, 'message' => 'Already liked'], 400);
                }
                // Notify comment owner (target) with liker as actor.
                if ($user_id) {
                    global $mysqli;
                    $comment = $commentModel->getCommentById($comment_id);
                    $commentOwnerId = (int)($comment['user_id'] ?? 0);

                    if ($commentOwnerId > 0 && $commentOwnerId !== (int)$user_id) {
                        $notificationModel = new NotificationModel($mysqli);
                        $notifId = $notificationModel->create(
                            (int)$user_id,
                            "Comment Liked",
                            "Your comment received a new like (" . $likes . " total likes).",
                            "update",
                            [
                                "user_id" => $commentOwnerId,
                                "channels" => ["push", "in_app"],
                            ]
                        );

                        if ($notifId) {
                            $notificationModel->logDelivery($notifId, $commentOwnerId, "sent", null, (string)$comment_id, "system", "comment_like");
                        }
                    }
                }

                logActivity("Comment Liked", "comment", $comment_id, ['by_user_id' => $user_id ?? 'guest'], 'success');

                return json_response(['success' => true, 'likes' => $likes], 200);
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Failed to like comment'], 500);
            }
        }
        );

        /**
     * POST /comment/edit
     * Edit user's own comment
     */
        $router->post('/edit', ['middleware' => ['api_headers']], function () use ($commentModel, $purifier) {
            header('Content-Type: application/json');

            try {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
                $comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
                $content = !empty($data['content']) ? trim($data['content']) : '';
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;

                if (!$user_id) {
                    return json_response(['success' => false, 'message' => 'Authentication required'], 401);
                }
                if ($comment_id <= 0 || empty($content) || strlen($content) < 2) {
                    return json_response(['success' => false, 'message' => 'Invalid input'], 400);
                }

                $content = $purifier->purify($content);
                $success = $commentModel->editComment($comment_id, $user_id, $content);

                if ($success) {
                    logActivity("Comment Edited", "comment", $comment_id, ['editor_id' => $user_id], 'success');
                    return json_response(['success' => true, 'message' => 'Comment updated'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to edit comment'], 403);
                }
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Error editing comment'], 500);
            }
        }
        );

        /**
     * POST /comment/delete
     * Delete user's own comment
     */
        $router->post('/delete', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
                $comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;

                if (!$user_id) {
                    return json_response(['success' => false, 'message' => 'Authentication required'], 401);
                }
                if ($comment_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment'], 400);
                }

                $success = $commentModel->deleteComment($comment_id, $user_id);

                if ($success) {
                    logActivity("Comment Deleted", "comment", $comment_id, ['deleter_id' => $user_id], 'success');
                    return json_response(['success' => true, 'message' => 'Comment deleted'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to delete comment'], 403);
                }
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Error deleting comment'], 500);
            }
        }
        );

        /**
     * POST /comment/reply/add
     * Add a reply to a comment
     */
        $router->post('/reply/add', ['middleware' => ['api_headers']], function () use ($commentModel, $purifier) {
            header('Content-Type: application/json');

            try {
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;
                $guest_name = !empty($_POST['guest_name']) ? trim($_POST['guest_name']) : null;
                $content = !empty($_POST['content']) ? trim($_POST['content']) : '';
                $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
                $content_type = !empty($_POST['content_type']) ? preg_replace('/[^a-z0-9_\-]/i', '', $_POST['content_type']) : 'post';
                $content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;

                $content = $purifier->purify($content);

                if (!$parent_id || empty($content) || $content_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid reply data'], 400);
                }
                if (!$user_id && empty($guest_name)) {
                    return json_response(['success' => false, 'message' => 'Guest name required'], 400);
                }

                $reply_id = $commentModel->addComment($user_id, $guest_name, $content, $parent_id, $content_type, $content_id);

                if ($reply_id) {
                    logActivity("Reply Added", "comment", $reply_id, ['parent_id' => $parent_id], 'success');
                    return json_response(['success' => true, 'reply_id' => $reply_id, 'timestamp' => date('Y-m-d H:i:s')], 201);
                }
                else {
                    throw new Exception('Failed to insert reply');
                }
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Failed to add reply'], 500);
            }
        }
        );

        /**
     * POST /comment/react
     * Add a reaction to a comment (emoji reactions like 👍❤️🔥😂😮😢)
     */
        $router->post('/react', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
                $comment_id = isset($data['comment_id']) ? (int)$data['comment_id'] : 0;
                $reaction = isset($data['reaction']) ? trim($data['reaction']) : '';
                $user = AuthManager::isUserAuthenticated();
                $userId = AuthManager::getCurrentUserId();
                $user_id = $user ? $userId : null;
                $guest_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

                if ($comment_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Comment ID required'], 400);
                }
                if (empty($reaction)) {
                    return json_response(['success' => false, 'message' => 'Reaction required'], 400);
                }

                // Validate reaction is a valid emoji
                $validReactions = ['👍', '❤️', '🔥', '😂', '😮', '😢', '🎉', '💯'];
                if (!in_array($reaction, $validReactions)) {
                    return json_response(['success' => false, 'message' => 'Invalid reaction'], 400);
                }

                // CommentModel ব্যবহার করে reaction যোগ করুন
                $result = $commentModel->addReaction($comment_id, $user_id, $guest_ip, $reaction);

                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'Failed to add reaction');
                }

                logActivity("Comment Reacted", "comment", $comment_id, ['reaction' => $reaction, 'by_user_id' => $user_id ?? 'guest'], 'success');

                return json_response([
                'success' => true,
                'message' => 'Reaction added',
                'reactions' => $result['reactions']
                ], 200);

            }
            catch (Exception $e) {
                logError("React error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Failed to add reaction'], 500);
            }
        }
        );

        /**
     * GET /comment/all
     * Get all comments for a content item (nested structure)
     */
        $router->get('/all', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $content_type = !empty($_GET['content_type']) ? $_GET['content_type'] : 'post';
                $content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;

                if ($content_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid content ID'], 400);
                }

                $comments = $commentModel->getComments($content_type, $content_id);

                return json_response(['success' => true, 'comments' => $comments], 200);
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Failed to fetch comments'], 500);
            }
        }
        );

        /**
     * GET /comment/top
     * Get top comments by reply count
     */
        $router->get('/top', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $content_type = !empty($_GET['content_type']) ? $_GET['content_type'] : 'post';
                $content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;
                $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 5;

                if ($content_id <= 0 || $limit <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid parameters'], 400);
                }

                $comments = $commentModel->getTopComments($content_type, $content_id, $limit);

                return json_response(['success' => true, 'comments' => $comments], 200);
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Failed to fetch top comments'], 500);
            }
        }
        );

        /**
     * GET /comment/{id}/replies
     * Get all replies for a specific comment
     */
        $router->get('/{id}/replies', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $replies = $commentModel->getReplies($id);

                return json_response(['success' => true, 'replies' => $replies], 200);
            }
            catch (Exception $e) {
                return json_response(['success' => false, 'message' => 'Failed to fetch replies'], 500);
            }
        }
        );
    });

// =====================================================================
// ROUTE GROUP: /admin/comments (Admin Comment Management)
// =====================================================================
$router->group('/admin/comments', ['middleware' => ['admin_only', 'activity_log']], function ($router) use ($commentModel, $purifier) {

    /**
     * GET /admin/comments
     * List all comments with pagination
     */
    $router->get('', function () use ($commentModel) {
            try {
                global $twig;

                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
                $search = sanitize_input($_GET['search'] ?? '');
                $sort = $_GET['sort'] ?? 'created_at';
                $order = $_GET['order'] ?? 'DESC';
                $status = $_GET['status'] ?? '';

                $filters = [];
                if (!empty($status) && in_array($status, ['pending', 'approved', 'rejected', 'hidden'])) {
                    $filters['status'] = $status;
                }

                $comments = $commentModel->getComments($page, $limit, $search, $sort, $order, $filters);
                $total = $commentModel->getCommentsCount($search, $filters);
                $totalPages = ceil($total / $limit);

                $paginationData = [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'per_page' => $limit,
                    'total' => $total,
                    'from' => ($page - 1) * $limit + 1,
                    'to' => min($page * $limit, $total),
                    'search' => $search,
                    'sort' => $sort,
                    'order' => $order,
                    'status' => $status
                ];

                $data = [
                    'comments' => $comments,
                    'pagination' => $paginationData,
                    'total_comments' => $commentModel->getPendingComments(),
                    'today_count' => $commentModel->getTodayComments(),
                    'pending_count' => $commentModel->getPendingApprovalCount()
                ];

                echo $twig->render('comments/admin_comments_manager.twig', $data);
                logActivity("View Comments List", "comment_management", 0, ['page' => $page, 'status' => $status], 'success');

            }
            catch (Exception $e) {
                logError("Admin Comments Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                echo $twig->render('error.twig', ['error' => 'Failed to load comments', 'details' => $e->getMessage()]);
            }
        }
        );

        // ========== STATIC ROUTES (MUST BE BEFORE :id ROUTES) ==========
    
        /**
     * GET /admin/comments/dashboard
     * Admin dashboard stats
     */
        $router->get('/dashboard', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $stats = [
                    'total_comments' => $commentModel->getPendingComments(),
                    'today_comments' => $commentModel->getTodayComments(),
                    'recent_comments' => $commentModel->getRecentComments(5),
                    'pending_approval' => $commentModel->getPendingApprovalCount(),
                    'last_updated' => date('Y-m-d H:i:s')
                ];

                return json_response(['success' => true, 'data' => $stats], 200);
            }
            catch (Exception $e) {
                logError("Dashboard Stats Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Failed to load dashboard stats'], 500);
            }
        }
        );

        /**
     * GET /admin/comments/detail
     * Get single comment detail (for modal)
     */
        $router->get('/detail', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $comment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

                if ($comment_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $comment = $commentModel->getCommentById($comment_id);

                if (!$comment) {
                    return json_response(['success' => false, 'message' => 'Comment not found'], 404);
                }

                // Format response
                $response = [
                    'success' => true,
                    'id' => $comment['id'],
                    'author' => $comment['guest_name'] ?? 'User #' . $comment['user_id'],
                    'content' => $comment['content'],
                    'created_at' => date('M d, Y H:i', strtotime($comment['created_at'])),
                    'likes' => $comment['likes'] ?? 0,
                    'content_type' => $comment['content_type'],
                    'content_id' => $comment['content_id']
                ];

                return json_response($response, 200);
            }
            catch (Exception $e) {
                logError("Comment Detail Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Failed to load comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/bulk-action
     * Bulk actions on comments
     */
        $router->post('/bulk-action', ['middleware' => ['api_headers']], function () use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $data = json_decode(file_get_contents('php://input'), true) ?? [];
                $action = $data['action'] ?? '';
                $comment_ids = $data['ids'] ?? [];
                $admin_id = AuthManager::getCurrentUserId();

                if (empty($action) || empty($comment_ids)) {
                    return json_response(['success' => false, 'message' => 'Invalid request'], 400);
                }

                $success_count = 0;
                $failed_count = 0;

                foreach ($comment_ids as $id) {
                    $id = (int)$id;
                    if ($id <= 0)
                        continue;

                    if ($action === 'approve') {
                        if ($commentModel->approveComment($id, $admin_id)) {
                            $success_count++;
                        }
                        else {
                            $failed_count++;
                        }
                    }
                    elseif ($action === 'delete') {
                        if ($commentModel->deleteCommentWithReplies($id)) {
                            $success_count++;
                        }
                        else {
                            $failed_count++;
                        }
                    }
                }

                logActivity("Bulk Action: {$action}", "comment_management", 0, [
                    'action' => $action,
                    'total_ids' => count($comment_ids),
                    'success' => $success_count,
                    'failed' => $failed_count
                ], 'success');

                return json_response([
                'success' => true,
                'message' => "{$success_count} comments processed successfully",
                'success_count' => $success_count,
                'failed_count' => $failed_count
                ], 200);

            }
            catch (Exception $e) {
                logError("Bulk Action Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error processing bulk action'], 500);
            }
        }
        );

        // ========== DYNAMIC ROUTES BY ID (AFTER STATIC ROUTES) ==========
    
        /**
     * POST /admin/comments/{id}/approve
     * Approve a comment
     */
        $router->post('/{id}/approve', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $success = $commentModel->approveComment($id, $admin_id);

                if ($success) {
                    logActivity("Comment Approved", "comment_management", $id, ['approved_by' => $admin_id], 'success');
                    return json_response(['success' => true, 'message' => 'Comment approved successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to approve comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Approve Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error approving comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/reject
     * Reject a comment
     */
        $router->post('/{id}/reject', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();
                $reason = !empty($_POST['reason']) ? trim($_POST['reason']) : '';

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $success = $commentModel->rejectComment($id, $admin_id, $reason);

                if ($success) {
                    logActivity("Comment Rejected", "comment_management", $id, ['rejected_by' => $admin_id, 'reason' => $reason], 'success');
                    return json_response(['success' => true, 'message' => 'Comment rejected successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to reject comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Reject Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error rejecting comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/edit
     * Admin can edit any comment (admin can edit any comment, not just their own)
     */
        $router->post('/{id}/edit', ['middleware' => ['api_headers']], function ($id) use ($commentModel, $purifier) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();
                $new_content = !empty($_POST['content']) ? trim($_POST['content']) : '';

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }
                if (empty($new_content) || strlen($new_content) < 2) {
                    return json_response(['success' => false, 'message' => 'Comment must be at least 2 characters'], 400);
                }

                $new_content = $purifier->purify($new_content);
                $success = $commentModel->adminEditComment($id, $new_content, $admin_id);

                if ($success) {
                    logActivity("Comment Edited by Admin", "comment_management", $id, ['edited_by' => $admin_id, 'new_content' => substr($new_content, 0, 100)], 'success');
                    return json_response(['success' => true, 'message' => 'Comment edited successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to edit comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Edit Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error editing comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/hide
     * Admin can hide a comment (status = 'hidden')
     */
        $router->post('/{id}/hide', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();
                $reason = !empty($_POST['reason']) ? trim($_POST['reason']) : '';

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $success = $commentModel->hideComment($id, $admin_id, $reason);

                if ($success) {
                    logActivity("Comment Hidden by Admin", "comment_management", $id, ['hidden_by' => $admin_id, 'reason' => $reason], 'success');
                    return json_response(['success' => true, 'message' => 'Comment hidden successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to hide comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Hide Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error hiding comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/unhide
     * Admin can unhide a previously hidden comment
     */
        $router->post('/{id}/unhide', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $success = $commentModel->unhideComment($id);

                if ($success) {
                    logActivity("Comment Unhidden by Admin", "comment_management", $id, ['unhidden_by' => $admin_id], 'success');
                    return json_response(['success' => true, 'message' => 'Comment unhidden successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to unhide comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Unhide Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error unhiding comment'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/reply
     * Admin can reply to any comment (reply is marked as admin reply)
     */
        $router->post('/{id}/reply', ['middleware' => ['api_headers']], function ($id) use ($commentModel, $purifier) {
            header('Content-Type: application/json');

            try {
                $parent_id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();
                $content = !empty($_POST['content']) ? trim($_POST['content']) : '';
                $content_type = !empty($_POST['content_type']) ? preg_replace('/[^a-z0-9_\-]/i', '', $_POST['content_type']) : 'post';
                $content_id = isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0;

                $content = $purifier->purify($content);

                if ($parent_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid parent comment ID'], 400);
                }
                if (empty($content) || strlen($content) < 2) {
                    return json_response(['success' => false, 'message' => 'Reply must be at least 2 characters'], 400);
                }
                if ($content_id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid content target'], 400);
                }

                $reply_id = $commentModel->adminReplyToComment($parent_id, $content, $admin_id, $content_type, $content_id);

                if ($reply_id) {
                    logActivity("Admin Reply Added", "comment_management", $reply_id, ['parent_id' => $parent_id, 'by_admin' => $admin_id], 'success');
                    return json_response(['success' => true, 'message' => 'Admin reply posted successfully', 'reply_id' => $reply_id], 201);
                }
                else {
                    throw new Exception('Failed to insert admin reply');
                }
            }
            catch (Exception $e) {
                logError("Admin Reply Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Failed to add admin reply'], 500);
            }
        }
        );

        /**
     * POST /admin/comments/{id}/delete
     * Delete a comment permanently
     */
        $router->post('/{id}/delete', ['middleware' => ['api_headers']], function ($id) use ($commentModel) {
            header('Content-Type: application/json');

            try {
                $id = (int)$id;
                $admin_id = AuthManager::getCurrentUserId();

                if ($id <= 0) {
                    return json_response(['success' => false, 'message' => 'Invalid comment ID'], 400);
                }

                $success = $commentModel->deleteCommentWithReplies($id);

                if ($success) {
                    logActivity("Comment Deleted by Admin", "comment_management", $id, ['deleted_by' => $admin_id], 'success');
                    return json_response(['success' => true, 'message' => 'Comment deleted successfully'], 200);
                }
                else {
                    return json_response(['success' => false, 'message' => 'Failed to delete comment'], 500);
                }
            }
            catch (Exception $e) {
                logError("Delete Comment Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                return json_response(['success' => false, 'message' => 'Error deleting comment'], 500);
            }
        }
        );

        /**
     * GET /admin/comments/{id}
     * View single comment detail
     */
        $router->get('/{id}', function ($id) use ($commentModel) {
            try {
                global $twig;
                $id = (int)$id;

                if ($id <= 0) {
                    http_response_code(404);
                    echo $twig->render('error.twig', ['error' => 'Comment not found']);
                    return;
                }

                $comment = $commentModel->getCommentById($id);

                if (!$comment) {
                    http_response_code(404);
                    echo $twig->render('error.twig', ['error' => 'Comment not found']);
                    return;
                }

                $replies = [];
                if (!$comment['parent_id']) {
                    $replies = $commentModel->getReplies($id);
                }

                $data = [
                    'comment' => $comment,
                    'replies' => $replies,
                    'can_edit' => true,
                    'can_delete' => true,
                    'can_approve' => true
                ];

                echo $twig->render('comments/admin_comment_detail.twig', $data);
                logActivity("View Comment Detail", "comment_management", $id, [], 'success');

            }
            catch (Exception $e) {
                logError("Admin Comment Detail Error: " . $e->getMessage(), "ERROR", ['file' => $e->getFile(), 'line' => $e->getLine()]);
                echo $twig->render('error.twig', ['error' => 'Failed to load comment']);
            }
        }
        );



    });
