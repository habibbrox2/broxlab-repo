<?php

// controllers/AIChatController.php

global $mysqli;

$aiConversationModel = new AIConversation($mysqli);
$aiMessageModel = new AIMessage($mysqli);

// ==================== USER AI CHAT ROUTES ====================

// POST /api/ai/chat/stream
$router->post('/api/ai/chat/stream', ['middleware' => ['csrf', 'auth']], function () use ($aiConversationModel, $aiMessageModel) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversationId'] ?? null;

        if (!$message) {
            throw new \Exception('Message is required');
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception('User not authenticated');
        }

        if (!$conversationId) {
            $conversation = $aiConversationModel->create([
                'user_id' => $userId,
                'title' => substr($message, 0, 50),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $conversationId = $conversation['id'] ?? null;
        }

        if (!$conversationId) {
            throw new \Exception('Failed to create conversation');
        }

        $aiMessageModel->create([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        echo "data: " . json_encode(['status' => 'ok']) . "\n\n";
        flush();
    } catch (\Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /api/ai/chat
$router->post('/api/ai/chat', ['middleware' => ['csrf', 'auth']], function () use ($aiConversationModel, $aiMessageModel) {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim($input['message'] ?? '');
        $conversationId = $input['conversationId'] ?? null;

        if (!$message) {
            throw new \Exception('Message is required');
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception('User not authenticated');
        }

        if (!$conversationId) {
            $conversation = $aiConversationModel->create([
                'user_id' => $userId,
                'title' => substr($message, 0, 50),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $conversationId = $conversation['id'] ?? null;
        }

        if (!$conversationId) {
            throw new \Exception('Failed to create conversation');
        }

        $aiMessageModel->create([
            'conversation_id' => $conversationId,
            'role' => 'user',
            'content' => $message,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        echo json_encode([
            'success' => true,
            'data' => [
                'conversationId' => $conversationId,
                'message' => $message,
            ],
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /api/ai/export
$router->post('/api/ai/export', ['middleware' => ['csrf', 'auth']], function () use ($aiConversationModel, $aiMessageModel) {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = $input['conversationId'] ?? null;
        $format = $input['format'] ?? 'json';

        if (!$conversationId) {
            throw new \Exception('Conversation ID is required');
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception('User not authenticated');
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'format' => $format,
                'conversationId' => $conversationId,
            ],
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// GET /api/ai/search
$router->get('/api/ai/search', ['middleware' => ['auth']], function () use ($aiMessageModel) {
    header('Content-Type: application/json');

    try {
        $query = sanitize_input($_GET['q'] ?? '');
        $limit = (int)($_GET['limit'] ?? 10);

        if (strlen($query) < 2) {
            throw new \Exception('Query must be at least 2 characters');
        }

        $limit = min(max($limit, 1), 100);
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            throw new \Exception('User not authenticated');
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'query' => $query,
                'results' => [],
                'count' => 0,
            ],
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /api/ai/tag
$router->post('/api/ai/tag', ['middleware' => ['csrf', 'auth']], function () use ($aiConversationModel) {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = $input['conversationId'] ?? null;
        $tags = $input['tags'] ?? [];

        if (!$conversationId) {
            throw new \Exception('Conversation ID is required');
        }

        if (!is_array($tags) || count($tags) === 0) {
            throw new \Exception('Tags must be a non-empty array');
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            throw new \Exception('User not authenticated');
        }

        echo json_encode([
            'success' => true,
            'data' => ['tags' => $tags],
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /api/ai/command/execute
$router->post('/api/ai/command/execute', ['middleware' => ['csrf', 'auth']], function () {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $commandName = trim($input['command'] ?? '');
        $params = $input['params'] ?? [];

        $commandName = preg_replace('/[^a-z-]/', '', strtolower($commandName));

        if (!$commandName) {
            throw new \Exception('Command name is required');
        }

        echo json_encode([
            'success' => true,
            'command' => $commandName,
            'data' => $params,
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /api/ai/tool/execute
$router->post('/api/ai/tool/execute', ['middleware' => ['csrf', 'auth']], function () {
    header('Content-Type: application/json');

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $toolName = trim($input['tool'] ?? '');
        $params = $input['params'] ?? [];

        if (!$toolName) {
            throw new \Exception('Tool name is required');
        }

        echo json_encode([
            'success' => true,
            'tool' => $toolName,
            'data' => $params,
        ]);
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// GET /api/ai/health
$router->get('/api/ai/health', ['middleware' => ['auth']], function () {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'timestamp' => time()]);
});

// GET /api/ai/models
$router->get('/api/ai/models', ['middleware' => ['auth']], function () {
    header('Content-Type: application/json');
    echo json_encode(['models' => [], 'count' => 0]);
});

// GET /api/ai/commands
$router->get('/api/ai/commands', ['middleware' => ['auth']], function () {
    header('Content-Type: application/json');
    echo json_encode([
        'commands' => ['summarize', 'analyze-logs', 'check-security', 'health-check', 'web-search', 'generate-report']
    ]);
});

// GET /api/ai/tools
$router->get('/api/ai/tools', ['middleware' => ['auth']], function () {
    header('Content-Type: application/json');
    echo json_encode([
        'tools' => ['calculate', 'scrape', 'search', 'extract-entities', 'translate']
    ]);
});
