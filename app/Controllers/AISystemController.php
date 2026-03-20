<?php

/**
 * AISystemController.php
 * Handles AI provider configuration routes in the Admin Panel.
 * All database operations are handled by AIProvider model.
 */

$aiProviderPath = realpath(__DIR__ . '/../Models/AIProvider.php');
require_once $aiProviderPath ?: (__DIR__ . '/../Models/AIProvider.php');
require_once __DIR__ . '/../Models/AppSettings.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';
require_once __DIR__ . '/../Helpers/ToolRegistry.php';
require_once __DIR__ . '/../Helpers/ToolDefinitions.php';
require_once __DIR__ . '/../Models/AIChatModel.php';
require_once __DIR__ . '/../Models/AuthManager.php';
require_once __DIR__ . '/../Models/UploadService.php';

function aiChatSendJson(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function aiChatStreamContent(string $content, array $meta = []): void
{
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('output_buffering', 'off');
    @ini_set('zlib.output_compression', '0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @ob_implicit_flush(true);

    if (!empty($meta)) {
        echo 'data: ' . json_encode(['meta' => $meta], JSON_UNESCAPED_UNICODE) . "\n\n";
        @ob_flush();
        flush();
    }

    $chunkSize = 200;
    if (function_exists('mb_strlen')) {
        $length = mb_strlen($content, 'UTF-8');
        for ($i = 0; $i < $length; $i += $chunkSize) {
            $chunk = mb_substr($content, $i, $chunkSize, 'UTF-8');
            echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }
    } else {
        foreach (str_split($content, $chunkSize) as $chunk) {
            echo 'data: ' . json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }
    }

    echo "data: [DONE]\n\n";
    @ob_flush();
    flush();
}

function aiChatExtractText($content): string
{
    if (is_string($content)) {
        return trim($content);
    }
    if (!is_array($content)) {
        return '';
    }
    $parts = [];
    foreach ($content as $part) {
        if (!is_array($part)) {
            continue;
        }
        if (($part['type'] ?? '') !== 'text') {
            continue;
        }
        $text = $part['text'] ?? '';
        if (is_string($text) && trim($text) !== '') {
            $parts[] = trim($text);
        }
    }
    return trim(implode("\n", $parts));
}

function aiChatNormalizeMessages($messages, int $maxMessages, int $maxChars, ?string &$error = null): array
{
    if (!is_array($messages)) {
        $error = 'Messages array is required';
        return [];
    }

    $out = [];
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $role = $msg['role'] ?? '';
        if (!in_array($role, ['user', 'assistant'], true)) {
            continue;
        }
        $content = $msg['content'] ?? '';
        $normalizedContent = null;
        $contentLen = 0;
        if (is_string($content)) {
            $content = trim($content);
            if ($content === '') {
                continue;
            }
            $normalizedContent = $content;
            $contentLen = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        } elseif (is_array($content)) {
            $parts = [];
            $textLen = 0;
            foreach ($content as $part) {
                if (!is_array($part)) {
                    continue;
                }
                $type = $part['type'] ?? '';
                if ($type === 'text') {
                    $text = $part['text'] ?? '';
                    if (!is_string($text)) {
                        continue;
                    }
                    $text = trim($text);
                    if ($text === '') {
                        continue;
                    }
                    $parts[] = ['type' => 'text', 'text' => $text];
                    $textLen += function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                    continue;
                }
                if ($type === 'image_url') {
                    $image = $part['image_url'] ?? [];
                    $url = $image['url'] ?? '';
                    if (!is_string($url) || trim($url) === '') {
                        continue;
                    }
                    $artifact = ['url' => trim($url)];
                    if (!empty($image['name']) && is_string($image['name'])) {
                        $artifact['name'] = trim($image['name']);
                    }
                    if (!empty($image['mime']) && is_string($image['mime'])) {
                        $artifact['mime'] = trim($image['mime']);
                    }
                    if (isset($image['size']) && (is_int($image['size']) || is_numeric($image['size']))) {
                        $artifact['size'] = (int)$image['size'];
                    }
                    $parts[] = ['type' => 'image_url', 'image_url' => $artifact];
                }
            }
            if (empty($parts)) {
                continue;
            }
            $normalizedContent = $parts;
            $contentLen = $textLen;
        } else {
            continue;
        }
        $len = $contentLen;
        if ($len > $maxChars) {
            $error = 'Message too long';
            return [];
        }
        $out[] = ['role' => $role, 'content' => $normalizedContent];
    }

    if (empty($out)) {
        $error = 'No valid messages';
        return [];
    }

    if (count($out) > $maxMessages) {
        $out = array_slice($out, -$maxMessages);
    }

    return $out;
}

function aiChatLastUserMessage(array $messages): string
{
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if (($messages[$i]['role'] ?? '') === 'user') {
            return aiChatExtractText($messages[$i]['content'] ?? '');
        }
    }
    return '';
}

function aiChatExtractImageReferences(array $messages): array
{
    $refs = [];
    foreach ($messages as $msg) {
        if (!is_array($msg) || empty($msg['content']) || !is_array($msg['content'])) {
            continue;
        }
        foreach ($msg['content'] as $part) {
            if (!is_array($part) || ($part['type'] ?? '') !== 'image_url') {
                continue;
            }
            $image = $part['image_url'] ?? [];
            $url = $image['url'] ?? null;
            
            // Support multiple image input formats:
            // 1. URL: "https://..."
            // 2. Base64: "data:image/png;base64,..."
            // 3. File ID: "file-id-from-upload"
            if (!$url) {
                continue;
            }
            
            // Extract detail level (low, high, original, auto) - default to "high" for better analysis
            $detail = $image['detail'] ?? 'high';
            if (!in_array($detail, ['low', 'high', 'original', 'auto'], true)) {
                $detail = 'high';
            }
            
            $refs[] = [
                'url' => $url,
                'name' => $image['name'] ?? null,
                'mime' => $image['mime'] ?? null,
                'size' => isset($image['size']) ? (int)$image['size'] : null,
                'detail' => $detail,
                'is_base64' => strpos($url, 'data:') === 0,
                'is_url' => strpos($url, 'http') === 0
            ];
        }
    }
    return $refs;
}

function aiChatMergeImageReferences(array $existing, array $incoming): array
{
    $merged = [];
    $seen = [];
    foreach (array_merge($existing, $incoming) as $ref) {
        if (!is_array($ref) || empty($ref['url'])) {
            continue;
        }
        $url = (string)$ref['url'];
        if (isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $merged[] = [
            'url' => $url,
            'name' => $ref['name'] ?? null,
            'mime' => $ref['mime'] ?? null,
            'size' => isset($ref['size']) ? (int)$ref['size'] : null,
            'detail' => $ref['detail'] ?? 'high',
            'is_base64' => $ref['is_base64'] ?? (strpos($url, 'data:') === 0),
            'is_url' => $ref['is_url'] ?? (strpos($url, 'http') === 0)
        ];
    }
    return $merged;
}

function aiChatHasImageContent(array $messages): bool
{
    foreach ($messages as $msg) {
        if (!is_array($msg) || empty($msg['content']) || !is_array($msg['content'])) {
            continue;
        }
        foreach ($msg['content'] as $part) {
            if (!is_array($part) || ($part['type'] ?? '') !== 'image_url') {
                continue;
            }
            $url = $part['image_url']['url'] ?? null;
            if (is_string($url) && trim($url) !== '') {
                return true;
            }
        }
    }
    return false;
}

function aiChatAppendImageContext(string $prompt, array $imageRefs): string
{
    if (empty($imageRefs)) {
        return $prompt;
    }

    $lines = ["\n\n[IMAGE CONTEXT]"];
    foreach ($imageRefs as $img) {
        $line = '- ' . ($img['name'] ? ($img['name'] . ': ') : 'Image: ') . ($img['url'] ?? '');
        $metaParts = [];
        if (!empty($img['mime'])) {
            $metaParts[] = $img['mime'];
        }
        if (!empty($img['size'])) {
            $metaParts[] = $img['size'] . ' bytes';
        }
        if (!empty($img['detail'])) {
            $metaParts[] = 'detail: ' . $img['detail'];
        }
        if (!empty($metaParts)) {
            $line .= ' (' . implode(', ', $metaParts) . ')';
        }
        $lines[] = $line;
    }

    return $prompt . "\n" . implode("\n", $lines);
}

/**
 * Build vision-compatible message content with proper image format
 * Supports: URLs, Base64 data URLs, and file references
 * 
 * @param array $messages Original messages
 * @param array $imageRefs Extracted image references
 * @return array Messages formatted for vision models
 */
function aiChatBuildVisionMessages(array $messages, array $imageRefs): array
{
    if (empty($imageRefs)) {
        return $messages;
    }
    
    $builtMessages = [];
    
    foreach ($messages as $msg) {
        if (!is_array($msg)) {
            $builtMessages[] = $msg;
            continue;
        }
        
        $role = $msg['role'] ?? 'user';
        $content = $msg['content'] ?? '';
        
        // If content is a string and there are images, convert to multimodal format
        if (is_string($content) && !empty($imageRefs) && $role === 'user') {
            $newContent = [];
            
            // Add text part
            if (!empty(trim($content))) {
                $newContent[] = [
                    'type' => 'text',
                    'text' => $content
                ];
            }
            
            // Add image parts
            foreach ($imageRefs as $img) {
                $imageData = [
                    'url' => $img['url'],
                    'detail' => $img['detail'] ?? 'high'
                ];
                
                // Add name if available
                if (!empty($img['name'])) {
                    $imageData['name'] = $img['name'];
                }
                
                $newContent[] = [
                    'type' => 'image_url',
                    'image_url' => $imageData
                ];
            }
            
            $builtMessages[] = [
                'role' => $role,
                'content' => $newContent
            ];
        } else {
            // Pass through as-is (already in multimodal format)
            $builtMessages[] = $msg;
        }
    }
    
    return $builtMessages;
}

/**
 * Check if a model supports vision/images
 */
function aiChatSupportsVision(?string $model): bool
{
    if (empty($model)) {
        return true; // Default to allowing
    }
    
    $modelLower = strtolower($model);
    
    // Known vision models
    $visionIndicators = [
        'vision', 'gpt-4o', 'gpt-4-vision', 'gpt-4-turbo',
        'claude-3', 'claude-3.5', 'claude-3.7',
        'gemini', 'llama-3.2', 'qwen2-vl', 'pixtral',
        'minimax', 'deepseek-vl'
    ];
    
    foreach ($visionIndicators as $indicator) {
        if (strpos($modelLower, $indicator) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Encode a local image file as base64 data URL
 * Supports: PNG, JPEG, WEBP, GIF
 * 
 * @param string $filePath Path to the image file
 * @return string|null Base64 data URL or null on failure
 */
function aiChatEncodeImageBase64(string $filePath): ?string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return null;
    }
    
    $mimeTypes = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif'
    ];
    
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$ext] ?? 'image/png';
    
    $data = file_get_contents($filePath);
    if ($data === false) {
        return null;
    }
    
    $base64 = base64_encode($data);
    return "data:{$mime};base64,{$base64}";
}

/**
 * Download and encode a remote image as base64
 * 
 * @param string $url Remote image URL
 * @return string|null Base64 data URL or null on failure
 */
function aiChatEncodeRemoteImage(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'follow_location' => 1,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]
    ]);
    
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return null;
    }
    
    // Detect MIME type from content
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($data);
    
    if (strpos($mime, 'image/') !== 0) {
        return null;
    }
    
    $base64 = base64_encode($data);
    return "data:{$mime};base64,{$base64}";
}

function aiChatParseSlashCommand(string $text): ?array
{
    $text = trim($text);
    if ($text === '' || $text[0] !== '/') {
        return null;
    }
    if (!preg_match('/^\/([a-zA-Z0-9_-]+)(?:\s+(.*))?$/', $text, $m)) {
        return null;
    }
    return [
        'cmd' => strtolower($m[1]),
        'args' => trim((string)($m[2] ?? '')),
    ];
}

function aiChatResolveErrorLogPath(): string
{
    if (defined('BASE_PATH')) {
        return rtrim((string)BASE_PATH, "/\\") . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.log';
    }
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'errors.log';
}

function aiChatReadLastLines(string $path, int $n): array
{
    $lines = [];
    $fp = @fopen($path, 'r');
    if (!$fp) return $lines;

    @fseek($fp, 0, SEEK_END);
    $pos = (int)@ftell($fp);
    $buffer = '';
    $lineCount = 0;

    while ($pos > 0 && $lineCount < $n) {
        $chunk = min(4096, $pos);
        $pos -= $chunk;
        @fseek($fp, $pos);
        $buffer = (string)@fread($fp, $chunk) . $buffer;
        $lineCount = substr_count($buffer, "\n");
    }

    @fclose($fp);
    $all = explode("\n", $buffer);
    if (end($all) === '') array_pop($all);
    return array_slice($all, -$n);
}

function aiChatRedactSecrets(string $line): string
{
    $patterns = [
        '/(authorization\s*[:=]\s*)([^\s,;]+)/i',
        '/(api[_-]?key\s*[:=]\s*)([^\s,;]+)/i',
        '/(token\s*[:=]\s*)([^\s,;]+)/i',
        '/(password\s*[:=]\s*)([^\s,;]+)/i',
        '/(DB_PASS\s*[:=]\s*)([^\s,;]+)/i',
    ];
    foreach ($patterns as $p) {
        $line = preg_replace($p, '$1[REDACTED]', $line) ?? $line;
    }
    return $line;
}

function aiChatSelectRecentErrors(array $lines, int $limit = 20): array
{
    $out = [];
    foreach (array_reverse($lines) as $line) {
        $line = trim((string)$line);
        if ($line === '') continue;
        $u = strtoupper($line);
        $match = str_contains($u, '[ERROR]') || str_contains($u, '[CRITICAL]') || str_contains($u, '[WARNING]')
            || str_contains($u, 'PHP FATAL') || str_contains($u, 'PHP WARNING') || str_contains($u, 'PHP ERROR');
        if (!$match) continue;
        $line = aiChatRedactSecrets($line);
        if (strlen($line) > 800) {
            $line = substr($line, 0, 800) . '…';
        }
        $out[] = $line;
        if (count($out) >= $limit) break;
    }
    return array_reverse($out);
}

function aiChatBuildRecentConversationText(array $messages, int $max = 10): string
{
    $slice = array_slice($messages, -$max);
    $parts = [];
    foreach ($slice as $m) {
        $role = (string)($m['role'] ?? '');
        $content = aiChatExtractText($m['content'] ?? '');
        if ($content === '') continue;
        $label = $role === 'assistant' ? 'Assistant' : 'User';
        $parts[] = $label . ': ' . $content;
    }
    return implode("\n", $parts);
}

function aiChatSelectFallbackProvider(AIProvider $aiProvider, string $currentProvider, array $settings): ?array
{
    $active = $aiProvider->getActive();
    foreach ($active as $provider) {
        $name = $provider['provider_name'] ?? '';
        if ($name === '' || $name === $currentProvider) {
            continue;
        }
        if (!$aiProvider->hasApiKey($name)) {
            continue;
        }

        $models = $provider['supported_models'] ?? [];
        if (empty($models)) {
            $config = AIProvider::getProviderConfig($name);
            $models = $config['models'] ?? [];
        }

        $defaultModel = $settings['default_model'] ?? '';
        $model = ($defaultModel !== '' && isset($models[$defaultModel]))
            ? $defaultModel
            : array_key_first($models);

        if (!$model) {
            continue;
        }

        return [
            'provider' => $name,
            'model' => $model
        ];
    }

    return null;
}

function aiChatLogUsage(mysqli $mysqli, string $provider, string $model, array $usage, string $status, ?string $error, ?int $userId, array $metadata): void
{
    $promptTokens = (int)($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
    $completionTokens = (int)($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
    $totalTokens = (int)($usage['total_tokens'] ?? ($promptTokens + $completionTokens));
    $cost = (float)($usage['cost'] ?? 0);
    $requestType = 'chat';
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);

    $stmt = $mysqli->prepare("
        INSERT INTO ai_usage_logs
        (provider_name, model_name, prompt_tokens, completion_tokens, total_tokens, cost, request_type, status, error_message, user_id, metadata)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        return;
    }

    $stmt->bind_param(
        'ssiiidsssis',
        $provider,
        $model,
        $promptTokens,
        $completionTokens,
        $totalTokens,
        $cost,
        $requestType,
        $status,
        $error,
        $userId,
        $metadataJson
    );

    // Execute with error handling - don't let logging failure break the chat
    try {
        $stmt->execute();
    } catch (\Exception $e) {
        // Log silently but don't break the chat functionality
        error_log('AI usage logging failed: ' . $e->getMessage());
    }
    $stmt->close();
}

function aiSystemGetProviderModels(AIProvider $aiProvider, string $providerName, array $providers): array
{
    foreach ($providers as $provider) {
        if (($provider['provider_name'] ?? '') !== $providerName) {
            continue;
        }
        $models = $provider['supported_models'] ?? [];
        if (empty($models)) {
            $config = AIProvider::getProviderConfig($providerName);
            $models = $config['models'] ?? [];
        }
        if ($providerName === 'fireworks') {
            $remote = $aiProvider->fetchRemoteModels($providerName);
            if (!empty($remote)) {
                $models = $remote;
            }
        }
        return $models;
    }

    $config = AIProvider::getProviderConfig($providerName);
    $models = $config['models'] ?? [];
    if ($providerName === 'fireworks') {
        $remote = $aiProvider->fetchRemoteModels($providerName);
        if (!empty($remote)) {
            $models = $remote;
        }
    }
    return $models;
}

function aiSystemResolveModel(AIProvider $aiProvider, string $providerName, string $selectedModel, array $providers, string $defaultModel = ''): string
{
    $models = aiSystemGetProviderModels($aiProvider, $providerName, $providers);
    if (!empty($selectedModel) && isset($models[$selectedModel])) {
        return $selectedModel;
    }
    if (!empty($defaultModel) && isset($models[$defaultModel])) {
        return $defaultModel;
    }

    return (string)array_key_first($models);
}
function aiChatHandleRequest(array $input, mysqli $mysqli, bool $isAdmin, bool $allowOverrides): void
{
    $aiProvider = new AIProvider($mysqli);
    $chatModel = new AIChatModel($mysqli);
    $providers = $aiProvider->getActive();
    $settings = $aiProvider->getSettings();

    $maxMessages = $isAdmin ? 40 : 20;
    $maxChars = $isAdmin ? 8000 : 4000;
    $error = null;
    $messages = aiChatNormalizeMessages($input['messages'] ?? null, $maxMessages, $maxChars, $error);
    if ($error) {
        aiChatSendJson(['success' => false, 'error' => $error], 400);
        return;
    }

    // Track image context across a session so the assistant can reference previous images
    $sessionImageKey = null;
    if ($isAdmin) {
        $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
        if ($userId) {
            $sessionImageKey = 'user_' . (int)$userId;
        }
    } else {
        $visitorToken = $input['visitorToken'] ?? null;
        if ($visitorToken) {
            $sessionImageKey = 'visitor_' . (string)$visitorToken;
        }
    }

    // Determine retention threshold (number of messages before clearing stored images)
    $maxMessages = (int)($settings['image_context_max_messages'] ?? 10);
    if ($maxMessages <= 0) {
        $maxMessages = 10;
    }

    $imageRefs = aiChatExtractImageReferences($messages);
    if ($sessionImageKey) {
        if (!isset($_SESSION['ai_image_context']) || !is_array($_SESSION['ai_image_context'])) {
            $_SESSION['ai_image_context'] = [];
        }

        $stored = $_SESSION['ai_image_context'][$sessionImageKey] ?? ['images' => [], 'message_count' => 0];
        if (!is_array($stored)) {
            $stored = ['images' => [], 'message_count' => 0];
        }

        $storedImages = $stored['images'] ?? [];
        $storedCount = (int)($stored['message_count'] ?? 0);

        // Merge new image refs into stored images
        $merged = aiChatMergeImageReferences($storedImages, $imageRefs);
        // Keep only the most recent 10 images
        if (count($merged) > 10) {
            $merged = array_slice($merged, -10);
        }

        // Only increment message count when the user contributes new input (text or images).
        // This prevents idle polling or system prompts from counting toward image context retention.
        $lastUserMessage = aiChatLastUserMessage($messages);
        if ($lastUserMessage !== '' || !empty($imageRefs)) {
            $storedCount++;
        }

        // Clear context when threshold exceeded
        if ($storedCount >= $maxMessages) {
            $merged = [];
            $storedCount = 0;
        }

        $_SESSION['ai_image_context'][$sessionImageKey] = [
            'images' => $merged,
            'message_count' => $storedCount
        ];
        $imageRefs = $merged;
    }

    $hasImageContent = !empty($imageRefs);

    $stream = !empty($input['stream']);
    $contextType = $isAdmin ? 'admin' : 'public';
    $contextData = $input['context'] ?? null;

    $systemPrompt = PromptLoader::getSystemPrompt($contextType, $mysqli);
    if ($contextData && is_array($contextData)) {
        $systemPrompt .= "\n\n[USER CONTEXT]\n";
        foreach ($contextData as $key => $val) {
            if (is_scalar($val)) {
                $systemPrompt .= ucfirst((string)$key) . ": $val\n";
            }
        }
    }

    $lastUserMessage = aiChatLastUserMessage($messages);

    // Admin-only slash commands routing via ToolRegistry
    $cmd = ($isAdmin && $lastUserMessage !== '') ? ToolRegistry::parseCommand($lastUserMessage) : null;
    
    // Backward compatibility: map old command names to new ones
    $commandAliases = [
        'diagnostics' => 'get_system_health',
        'db-query' => 'query_database',
        'db_query' => 'query_database',
        'table-stats' => 'get_table_stats',
        'table_stats' => 'get_table_stats',
        'analyze-logs' => 'analyze_error_logs',
        'analyze_logs' => 'analyze_error_logs',
        'summarize' => 'summarize_text',
        'cache-stats' => 'get_cache_stats',
        'cache_stats' => 'get_cache_stats',
        'user-stats' => 'get_user_stats',
        'user_stats' => 'get_user_stats',
        'content-stats' => 'get_content_stats',
        'content_stats' => 'get_content_stats',
        'help' => 'list_tools',
    ];
    
    if ($cmd) {
        // Resolve command alias
        $originalCmd = $cmd['cmd'];
        $cmd['cmd'] = $commandAliases[$cmd['cmd']] ?? $cmd['cmd'];
        
        // Execute tool via registry
        $toolResult = ToolRegistry::execute($cmd['cmd'], $cmd['args'], $mysqli);

        if (!$toolResult['success']) {
            $errorMsg = $toolResult['error'] ?? 'Tool execution failed';
            $errorCode = $toolResult['error_code'] ?? 'tool_error';

            // Provide helpful error for unknown tools
            if ($errorCode === 'tool_not_found') {
                $availableTools = array_map(fn($t) => '/'.$t['name'], ToolRegistry::listTools());
                $errorMsg = "Unknown command: /{$originalCmd}\n\nAvailable: " . implode(', ', $availableTools);
            }

            aiChatSendJson([
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode
            ], 400);
            return;
        }

        // Command mode disables KB + image context
        $imageRefs = [];
        $hasImageContent = false;

        // Format tool output for AI to present to user
        $toolOutput = json_encode($toolResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $execTime = $toolResult['execution_time_ms'] ?? 0;
        $cached = !empty($toolResult['cached']) ? ' (cached)' : '';
        $callId = $cmd['call_id'] ?? 'call_' . bin2hex(random_bytes(8));

        $systemPrompt .= "\n\n[TOOL CALL]\nTool: {$cmd['cmd']}\nCall ID: {$callId}\nExecution: {$execTime}ms{$cached}\n\nPresent these results to the user in a clear, formatted way:\n\n{$toolOutput}";
        $messages = [
            ['role' => 'user', 'content' => "Execute tool: /{$originalCmd} " . ($cmd['raw_args'] ?? '')]
        ];
        $lastUserMessage = $messages[0]['content'];
    }

    if ($lastUserMessage !== '') {
        // Skip KB context in slash-command mode to keep output deterministic
        $kbContext = $cmd ? '' : PromptLoader::getKnowledgeContext($lastUserMessage, $mysqli);
        if ($kbContext) {
            $systemPrompt .= "\n\n" . $kbContext;
        }
    }

    // Add image context to system prompt so non-multimodal providers are aware of visual inputs
    if (!empty($imageRefs)) {
        $systemPrompt = aiChatAppendImageContext($systemPrompt, $imageRefs);
    }

    array_unshift($messages, ['role' => 'system', 'content' => $systemPrompt]);

    $effective = $aiProvider->getEffectiveProvider();
    $provider = $effective['provider_name'] ?? 'openrouter';
    $model = $settings['default_model'] ?? 'openrouter/auto';
    if (!$isAdmin) {
        $frontendProvider = $settings['frontend_provider'] ?? 'openrouter';
        $activeNames = array_values(array_filter(array_map(fn($p) => $p['provider_name'] ?? '', $providers)));
        if (!in_array($frontendProvider, $activeNames, true)) {
            $frontendProvider = $activeNames[0] ?? $frontendProvider;
        }
        $provider = $frontendProvider;
        $model = aiSystemResolveModel(
            $aiProvider,
            $frontendProvider,
            (string)($settings['frontend_model'] ?? ''),
            $providers,
            (string)($settings['default_model'] ?? '')
        );
    } else {
        $backendProvider = $settings['backend_provider'] ?? ($settings['default_provider'] ?? $provider);
        $provider = $backendProvider;
        $model = aiSystemResolveModel(
            $aiProvider,
            $backendProvider,
            (string)($settings['backend_model'] ?? ''),
            $providers,
            (string)($settings['default_model'] ?? '')
        );

        // If Ollama is active and reachable, prefer it for admin chat only
        $ollamaProvider = $aiProvider->getByName('ollama');
        if ($ollamaProvider && !empty($ollamaProvider['is_active'])) {
            $ollamaModels = $aiProvider->fetchRemoteModels('ollama');
            if (!empty($ollamaModels)) {
                if ((int)($ollamaProvider['sort_order'] ?? 0) !== 0) {
                    $aiProvider->update((int)$ollamaProvider['id'], ['sort_order' => 0]);
                }
                $provider = 'ollama';
                $model = (string)array_key_first($ollamaModels);
            }
        }
    }

    if ($allowOverrides) {
        if (!empty($input['provider']) && is_string($input['provider'])) {
            $provider = $input['provider'];
        }
        if (!empty($input['model']) && is_string($input['model'])) {
            $model = $input['model'];
        }
    }

    $options = [];
    if ($allowOverrides && isset($input['options']) && is_array($input['options'])) {
        $options = $input['options'];
    }
    $options['max_tokens'] = isset($options['max_tokens'])
        ? (int)$options['max_tokens']
        : (int)($settings['max_tokens'] ?? 4000);
    $options['temperature'] = isset($options['temperature'])
        ? (float)$options['temperature']
        : (float)($settings['temperature'] ?? 0.7);

    $startTime = microtime(true);
    $convId = null;
    $userMessageId = null;
    $assistantMessageId = null;
    if (!$isAdmin) {
        $visitorToken = $input['visitorToken'] ?? null;
        if ($visitorToken) {
            // Get visitor info
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $device = 'Desktop';
            if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent ?? '')) {
                $device = 'Mobile';
                if (preg_match('/iPad/i', $userAgent ?? '')) {
                    $device = 'Tablet';
                }
            }
            // Simple location (can be enhanced with geo-ip service)
            $location = 'Unknown';

            $convId = $chatModel->getOrCreateConversation(null, $visitorToken, $ipAddress, $device, $location, $userAgent);
            if ($convId && $lastUserMessage !== '') {
                $userMessageId = $chatModel->addMessage($convId, 'user', $lastUserMessage);
            }
        }
    } else {
        $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
        if ($userId) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $device = 'Desktop';
            if (preg_match('/Mobile|Android|iPhone|iPad/i', $userAgent ?? '')) {
                $device = 'Mobile';
                if (preg_match('/iPad/i', $userAgent ?? '')) {
                    $device = 'Tablet';
                }
            }
            $location = 'Unknown';

            $convId = $chatModel->getOrCreateConversation((int)$userId, null, $ipAddress, $device, $location, $userAgent);
            if ($convId && $lastUserMessage !== '') {
                $userMessageId = $chatModel->addMessage($convId, 'user', $lastUserMessage);
            }
        }
    }

    $enableFallback = $settings['enable_fallback'] ?? true;
    $fallbackUsed = false;
    $response = null;

    $orderedProviders = [];
    if (!empty($provider)) {
        $orderedProviders[] = $provider;
    }
    foreach ($providers as $p) {
        $name = $p['provider_name'] ?? '';
        if ($name === '' || in_array($name, $orderedProviders, true)) {
            continue;
        }
        $orderedProviders[] = $name;
    }

    // Determine multimodal capability per provider/model when images are present.
    $providerModelMultimodal = [];
    if (!empty($hasImageContent)) {
        foreach ($orderedProviders as $name) {
            $selectedModel = ($name === $provider) ? $model : '';
            $resolvedModel = aiSystemResolveModel(
                $aiProvider,
                $name,
                (string)$selectedModel,
                $providers,
                (string)($settings['default_model'] ?? '')
            );
            $providerModelMultimodal[$name] = $resolvedModel
                ? $aiProvider->modelSupportsMultimodal($name, $resolvedModel)
                : false;
        }

        // Prefer providers/models that support multimodal content.
        usort($orderedProviders, function ($a, $b) use ($providerModelMultimodal) {
            $aMulti = $providerModelMultimodal[$a] ?? false;
            $bMulti = $providerModelMultimodal[$b] ?? false;
            if ($aMulti === $bMulti) {
                return 0;
            }
            return $aMulti ? -1 : 1;
        });
    }

    $hasUsableProvider = false;
    $lastError = null;
    $primaryProvider = $provider;
    $primaryModel = $model;

    foreach ($orderedProviders as $name) {
        if (!$aiProvider->hasApiKey($name)) {
            continue;
        }
        $selectedModel = '';
        if ($name === $primaryProvider) {
            $selectedModel = $primaryModel;
        }
        $resolvedModel = aiSystemResolveModel(
            $aiProvider,
            $name,
            (string)$selectedModel,
            $providers,
            (string)($settings['default_model'] ?? '')
        );
        if ($resolvedModel === '') {
            continue;
        }

        $hasUsableProvider = true;
        $provider = $name;
        $model = $resolvedModel;
        if ($name !== $primaryProvider) {
            $fallbackUsed = true;
        }
        $response = $aiProvider->callAPI($provider, $model, $messages, $options);

        if (!empty($response['success'])) {
            break;
        }

        $lastError = $response['error'] ?? 'AI error';
        if (!$enableFallback) {
            break;
        }
    }

    if (empty($response['success'])) {
        if (!$hasUsableProvider) {
            if (!$isAdmin) {
                $errorPayload = [
                    'success' => false,
                    'error' => 'No available AI providers',
                    'error_code' => 'no_providers'
                ];
                aiChatSendJson($errorPayload, 503);
                return;
            }
            $response = ['success' => false, 'error' => 'No available AI providers'];
        } elseif (!$isAdmin) {
            $errorPayload = [
                'success' => false,
                'error' => $lastError ?? 'AI error',
                'error_code' => 'providers_failed'
            ];
            $status = (isset($lastError) && str_contains((string)$lastError, 'API key not configured')) ? 400 : 502;
            aiChatSendJson($errorPayload, $status);
            return;
        }
    }
    if ($convId && !empty($response['success'])) {
        $aiText = $response['content'] ?? '';
        if ($aiText !== '') {
            $assistantMessageId = $chatModel->addMessage((int)$convId, 'assistant', $aiText);
        }
    }

    $latencyMs = (int)round((microtime(true) - $startTime) * 1000);
    $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
    $usage = $response['usage'] ?? [];
    $status = !empty($response['success']) ? 'success' : 'failed';
    $errorMessage = $response['error'] ?? null;
    aiChatLogUsage($mysqli, $provider, $model, $usage, $status, $errorMessage, $userId ? (int)$userId : null, [
        'context' => $contextType,
        'latency_ms' => $latencyMs,
        'fallback_used' => $fallbackUsed,
        'stream' => (bool)$stream,
        'has_image_content' => (bool)$hasImageContent
    ]);

    if ($stream) {
        if (empty($response['success'])) {
            $status = (isset($response['error']) && str_contains($response['error'], 'API key not configured')) ? 400 : 502;
            aiChatSendJson([
                'success' => false,
                'error' => $response['error'] ?? 'AI error',
                'error_code' => $response['error_code'] ?? null
            ], $status);
            return;
        }
        aiChatStreamContent($response['content'] ?? '', [
            'conversation_id' => $convId,
            'message_id' => $assistantMessageId,
            'user_message_id' => $userMessageId
        ]);
        return;
    }

    if (!empty($response['success'])) {
        aiChatSendJson([
            'success' => true,
            'content' => $response['content'] ?? '',
            'conversation_id' => $convId,
            'message_id' => $assistantMessageId,
            'usage' => $response['usage'] ?? []
        ]);
        return;
    }

    $status = (isset($response['error']) && str_contains($response['error'], 'API key not configured')) ? 400 : 502;
    aiChatSendJson([
        'success' => false,
        'error' => $response['error'] ?? 'AI error',
        'error_code' => $response['error_code'] ?? null
    ], $status);
}



// ==================== GET /admin/ai-system ====================
$router->get('/admin/ai-system', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $aiProvider = new AIProvider($mysqli);

    $providers = $aiProvider->getAll();
    // Remove Puter provider from UI/config management - Puter is only used client-side as a fallback.
    $providers = array_filter($providers, fn($p) => ($p['provider_name'] ?? '') !== 'puter');
    // Ensure Ollama shows first in the admin providers table.
    $providers = array_values($providers);
    usort($providers, function ($a, $b) {
        $aIsOllama = ($a['provider_name'] ?? '') === 'ollama';
        $bIsOllama = ($b['provider_name'] ?? '') === 'ollama';
        if ($aIsOllama === $bIsOllama) {
            return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
        }
        return $aIsOllama ? -1 : 1;
    });

    $settings = $aiProvider->getSettings();
    // Ensure frontend provider never returns the old Puter option
    if (($settings['frontend_provider'] ?? '') === 'puter-js' || ($settings['frontend_provider'] ?? '') === 'puter') {
        $settings['frontend_provider'] = 'openrouter';
    }

    $defaultProvider = $aiProvider->getDefault();
    if ($defaultProvider && ($defaultProvider['provider_name'] ?? '') === 'puter') {
        $defaultProvider = $aiProvider->getEffectiveProvider();
    }

    $providerConfigs = AIProvider::getAllProviderConfigs();

    // Determine where OpenRouter key comes from (DB vs environment).
    $openrouterDbKey = $settings['openrouter_api_key'] ?? '';
    $settings['openrouter_key_source'] = !empty($openrouterDbKey) ? 'db' : 'none';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin'],
        ['label' => 'AI SYSTEM', 'url' => '/admin/ai-system']
    ];

    echo $twig->render('admin/ai/system.twig', [
        'title' => 'AI SYSTEM',
        'breadcrumbs' => $breadcrumbs,
        'providers' => $providers,
        'settings' => $settings,
        'default_provider' => $defaultProvider,
        'provider_configs' => $providerConfigs,
        'csrf_token' => generateCsrfToken()
    ]);
});

// ==================== GET /api/ai-system/frontend ====================
// NOTE: API routes are centralized in app/Routes/AISystemRoutes.php.
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
$router->get('/api/ai-system/frontend', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();

    $openrouterDbKey = $settings['openrouter_api_key'] ?? '';

    $openrouterKeySource = !empty($openrouterDbKey) ? 'db' : 'none';

    // Ensure frontend provider never returns a Puter option (Puter is only used as a pure frontend fallback)
    $frontendProvider = $settings['frontend_provider'] ?? 'openrouter';
    if ($frontendProvider === 'puter-js' || $frontendProvider === 'puter') {
        $frontendProvider = 'openrouter';
    }

    // Default model selection varies by provider.
    $providers = $aiProvider->getActive();
    $defaultModel = aiSystemResolveModel(
        $aiProvider,
        $frontendProvider,
        (string)($settings['frontend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );
    $backendProvider = $settings['backend_provider'] ?? $frontendProvider;
    $backendModel = aiSystemResolveModel(
        $aiProvider,
        $backendProvider,
        (string)($settings['backend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );

    // Build provider list for frontend use (no API keys exposed)
    $activeProviders = $providers;
    $providerList = [];
    foreach ($activeProviders as $p) {
        $providerName = $p['provider_name'];

        $providerList[] = [
            'provider_name' => $providerName,
            'display_name' => $p['display_name'],
            'has_api_key' => !empty($settings[$providerName . '_api_key'] ?? ''),
            'models' => $p['supported_models'] ?? [],
            'is_default' => !empty($p['is_default']),
            'is_active' => !empty($p['is_active'])
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'provider' => $frontendProvider,
        'model' => $defaultModel,
        'frontend_model' => $defaultModel,
        'backend_model' => $backendModel,
        'providers' => $providerList,
        'openrouter_key_source' => $openrouterKeySource
    ]);
});

// ==================== GET /api/ai-system/admin-defaults ====================
$router->get('/api/ai-system/admin-defaults', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();
    $providers = $aiProvider->getActive();

    $defaultProvider = trim((string)($settings['default_provider'] ?? ''));
    if ($defaultProvider === '') {
        $effective = $aiProvider->getEffectiveProvider();
        $defaultProvider = $effective['provider_name'] ?? 'openrouter';
    }

    $defaultModel = aiSystemResolveModel(
        $aiProvider,
        $defaultProvider,
        '',
        $providers,
        (string)($settings['default_model'] ?? '')
    );

    header('Content-Type: application/json');
    echo json_encode([
        'provider' => $defaultProvider,
        'model' => $defaultModel,
        'default_model' => $settings['default_model'] ?? ''
    ]);
});
}

// ==================== POST /admin/ai-system/save ====================
$router->post('/admin/ai-system/save', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $providers = $aiProvider->getActive();
    $frontendProvider = $_POST['frontend_provider'] ?? 'openrouter';
    $backendProvider = $_POST['backend_provider'] ?? $_POST['default_provider'] ?? 'kilo';
    $defaultModel = $_POST['default_model'] ?? 'openrouter/auto';
    $frontendModelInput = $_POST['frontend_model'] ?? '';
    $backendModelInput = $_POST['backend_model'] ?? '';
    $frontendModel = aiSystemResolveModel($aiProvider, $frontendProvider, $frontendModelInput, $providers, $defaultModel);
    $backendModel = aiSystemResolveModel($aiProvider, $backendProvider, $backendModelInput, $providers, $defaultModel);
    $blockedFrontend = false;
    $blockedBackend = false;
    $hfFirstChat = '';

    if ($frontendProvider === 'huggingface' || $backendProvider === 'huggingface') {
        $hfProvider = $aiProvider->getByName('huggingface');
        $hfModels = $hfProvider['supported_models'] ?? [];
        if (empty($hfModels)) {
            $config = AIProvider::getProviderConfig('huggingface');
            $hfModels = $config['models'] ?? [];
        }
        $hfChatModels = $aiProvider->filterHuggingFaceChatModels($hfModels);
        $hfFirstChat = (string)(array_key_first($hfChatModels) ?? '');
    }

    if ($frontendProvider === 'huggingface' && $frontendModel !== '' && !$aiProvider->isHuggingFaceChatModel($frontendModel)) {
        $blockedFrontend = true;
        $frontendModel = $hfFirstChat;
    }
    if ($backendProvider === 'huggingface' && $backendModel !== '' && !$aiProvider->isHuggingFaceChatModel($backendModel)) {
        $blockedBackend = true;
        $backendModel = $hfFirstChat;
    }
    if ($frontendProvider === 'huggingface' && $frontendModel === '' && $hfFirstChat !== '') {
        $frontendModel = $hfFirstChat;
    }
    if ($backendProvider === 'huggingface' && $backendModel === '' && $hfFirstChat !== '') {
        $backendModel = $hfFirstChat;
    }

    // Save general settings
    $settingsToSave = [
        'default_provider' => $_POST['default_provider'] ?? 'kilo',
        'frontend_provider' => $frontendProvider,
        'backend_provider' => $backendProvider,
        'default_model' => $defaultModel,
        'frontend_model' => $frontendModel,
        'backend_model' => $backendModel,
        'max_tokens' => (int)($_POST['max_tokens'] ?? 4000),
        'temperature' => (float)($_POST['temperature'] ?? 0.7),
        'enable_fallback' => isset($_POST['enable_fallback']),
        'content_enhancement_enabled' => isset($_POST['content_enhancement_enabled']),
        'auto_publish_ai_content' => isset($_POST['auto_publish_ai_content']),
        'default_author' => $_POST['default_author'] ?? 'BroxBhai AI',
        'image_context_max_messages' => (int)($_POST['image_context_max_messages'] ?? 10),
        // New separate prompts for Admin and Public assistants
        'admin_system_prompt' => $_POST['admin_system_prompt'] ?? '',
        'public_system_prompt' => $_POST['public_system_prompt'] ?? '',
        'system_prompt' => $_POST['system_prompt'] ?? '', // Keep for backwards compatibility
        'custom_instructions' => $_POST['custom_instructions'] ?? ''
    ];

    $aiProvider->updateSettings($settingsToSave);

    // Save API keys
    foreach ($_POST['api_keys'] ?? [] as $providerName => $apiKey) {
        if (!empty(trim($apiKey))) {
            $aiProvider->updateSetting($providerName . '_api_key', trim($apiKey));
        }
    }

    // Save default provider
    if (!empty($_POST['default_provider'])) {
        $provider = $aiProvider->getByName($_POST['default_provider']);
        if ($provider) {
            $aiProvider->setAsDefault($provider['id']);
        }
    }

    if ($frontendModelInput !== '' && $frontendModelInput !== $frontendModel) {
        showMessage('Frontend model corrected to a valid model for the selected provider.', 'warning');
    }
    if ($backendModelInput !== '' && $backendModelInput !== $backendModel) {
        showMessage('Backend model corrected to a valid model for the selected provider.', 'warning');
    }
    if ($blockedFrontend) {
        showMessage('Hugging Face frontend model is not chat-capable for /v1/responses and was corrected.', 'warning');
    }
    if ($blockedBackend) {
        showMessage('Hugging Face backend model is not chat-capable for /v1/responses and was corrected.', 'warning');
    }
    if (($frontendProvider === 'huggingface' || $backendProvider === 'huggingface') && $hfFirstChat === '') {
        showMessage('No chat-capable Hugging Face models are available. Update the HF model list to continue.', 'warning');
    }

    showMessage('AI SYSTEM saved successfully!', 'success');
    redirect('/admin/ai-system');
});

// ==================== POST /admin/ai-system/add-provider ====================
$router->post('/admin/ai-system/add-provider', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);

    $displayName = trim($_POST['display_name'] ?? '');
    $apiEndpoint = trim($_POST['api_endpoint'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');

    if (empty($displayName) || empty($apiEndpoint)) {
        showMessage('Please provide provider name and API endpoint.', 'danger');
        redirect('/admin/ai-system');
        return;
    }

    $providerName = strtolower(preg_replace('/[^a-z0-9]/', '_', $displayName));
    $providerName = preg_replace('/_+/', '_', $providerName);

    $providerId = $aiProvider->create([
        'provider_name' => $providerName,
        'display_name' => $displayName,
        'description' => $_POST['description'] ?? 'Custom AI provider',
        'api_endpoint' => $apiEndpoint,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 100
    ]);

    if (!empty($apiKey)) {
        $aiProvider->updateSetting($providerName . '_api_key', $apiKey);
    }

    showMessage('Provider "' . htmlspecialchars($displayName) . '" added successfully!', 'success');
    redirect('/admin/ai-system');
});

// ==================== POST /admin/ai-system/update-provider ====================
$router->post('/admin/ai-system/update-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);

    $payload = $_POST;
    if (empty($payload)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $payload = $json;
        }
    }

    if (!validateCsrfToken($payload['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        return;
    }

    $providerId = (int)($payload['provider_id'] ?? 0);
    $action = $payload['action'] ?? '';

    switch ($action) {
        case 'toggle':
            $provider = $aiProvider->getById($providerId);
            if ($provider) {
                $aiProvider->update($providerId, ['is_active' => !$provider['is_active']]);
                echo json_encode(['success' => true, 'is_active' => !$provider['is_active']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Provider not found']);
            }
            break;

        case 'set_default':
            $aiProvider->setAsDefault($providerId);
            echo json_encode(['success' => true]);
            break;

        case 'test':
            $provider = $aiProvider->getById($providerId);
            if ($provider) {
                $result = $aiProvider->testConnection($provider['provider_name'], $payload['model'] ?? null);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'error' => 'Provider not found']);
            }
            break;
        case 'set_multimodal':
            $provider = $aiProvider->getById($providerId);
            if (!$provider) {
                echo json_encode(['success' => false, 'error' => 'Provider not found']);
                break;
            }
            $enabled = !empty($payload['enabled']);
            $extra = $provider['extra_settings'] ?? [];
            if (!is_array($extra)) {
                $extra = [];
            }
            $extra['supports_multimodal'] = $enabled;
            $ok = $aiProvider->update($providerId, ['extra_settings' => $extra]);
            echo json_encode(['success' => $ok, 'supports_multimodal' => $enabled]);
            break;

        case 'set_model_multimodal':
            $provider = $aiProvider->getById($providerId);
            if (!$provider) {
                echo json_encode(['success' => false, 'error' => 'Provider not found']);
                break;
            }
            $modelId = trim((string)($payload['model_id'] ?? ''));
            if ($modelId === '') {
                echo json_encode(['success' => false, 'error' => 'Model ID is required']);
                break;
            }
            $enabled = !empty($payload['enabled']);
            $extra = $provider['extra_settings'] ?? [];
            if (!is_array($extra)) {
                $extra = [];
            }
            if (!isset($extra['model_multimodal']) || !is_array($extra['model_multimodal'])) {
                $extra['model_multimodal'] = [];
            }
            $extra['model_multimodal'][$modelId] = $enabled;
            $ok = $aiProvider->update($providerId, ['extra_settings' => $extra]);
            echo json_encode(['success' => $ok, 'model_id' => $modelId, 'enabled' => $enabled]);
            break;

        case 'update_config':
            $provider = $aiProvider->getById($providerId);
            if (!$provider) {
                echo json_encode(['success' => false, 'error' => 'Provider not found']);
                break;
            }
            if (($provider['provider_name'] ?? '') !== 'huggingface') {
                echo json_encode(['success' => false, 'error' => 'Only Hugging Face can be updated here']);
                break;
            }

            $apiEndpoint = trim((string)($payload['api_endpoint'] ?? ''));
            $supportedModels = $payload['supported_models'] ?? null;
            if (!is_array($supportedModels)) {
                echo json_encode(['success' => false, 'error' => 'Invalid supported_models']);
                break;
            }

            $normalized = [];
            foreach ($supportedModels as $id => $label) {
                $id = trim((string)$id);
                $label = trim((string)$label);
                if ($id === '' || $label === '') {
                    continue;
                }
                $normalized[$id] = $label;
            }

            $update = [
                'supported_models' => $normalized
            ];
            if ($apiEndpoint !== '') {
                $update['api_endpoint'] = $apiEndpoint;
            }

            $ok = $aiProvider->update($providerId, $update);
            echo json_encode(['success' => $ok]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
});

// ==================== POST /admin/ai-system/delete-provider ====================
$router->post('/admin/ai-system/delete-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        return;
    }

    $providerId = (int)($_POST['provider_id'] ?? 0);
    $provider = $aiProvider->getById($providerId);

    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }

    if ($provider['provider_name'] === 'custom' && $provider['sort_order'] >= 90) {
        $aiProvider->updateSetting($provider['provider_name'] . '_api_key', '');
        $result = $aiProvider->delete($providerId);
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Cannot delete built-in providers']);
    }
});

// ==================== API Routes ====================

// GET /api/ai/providers
$router->get('/api/ai/providers', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    header('Content-Type: application/json');
    echo json_encode($aiProvider->getActive());
});

// GET /api/ai/settings
$router->get('/api/ai/settings', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();
    $providers = $aiProvider->getActive();
    $frontendProvider = $settings['frontend_provider'] ?? 'openrouter';
    $backendProvider = $settings['backend_provider'] ?? $frontendProvider;
    $settings['frontend_model'] = aiSystemResolveModel(
        $aiProvider,
        $frontendProvider,
        (string)($settings['frontend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );
    $settings['backend_model'] = aiSystemResolveModel(
        $aiProvider,
        $backendProvider,
        (string)($settings['backend_model'] ?? ''),
        $providers,
        (string)($settings['default_model'] ?? '')
    );
    header('Content-Type: application/json');
    echo json_encode($settings);
});

// POST /api/ai/test (centralized in app/Routes/AISystemRoutes.php)
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
$router->post('/api/ai/test', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $result = $aiProvider->testConnection($_POST['provider'] ?? '', $_POST['model'] ?? null);
    header('Content-Type: application/json');
    echo json_encode($result);
});
}

// GET /api/ai/ollama/status - Check Ollama server status
$router->get('/api/ai/ollama/status', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $result = $aiProvider->checkOllamaStatus();
    header('Content-Type: application/json');
    echo json_encode($result);
});

// GET /api/ai/ollama/model/:modelName - Get specific model info
$router->get('/api/ai/ollama/model/([^/]+)', function ($modelName) use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $result = $aiProvider->getOllamaModelInfo($modelName);
    header('Content-Type: application/json');
    echo json_encode($result);
});

// GET /api/ai/current-provider
$router->get('/api/ai/current-provider', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $provider = $aiProvider->getEffectiveProvider();

    if (!$provider) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No active AI provider configured']);
        return;
    }

    $settings = $aiProvider->getSettings();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'provider' => [
            'name' => $provider['provider_name'],
            'display_name' => $provider['display_name'],
            'models' => array_keys($provider['supported_models'] ?? [])
        ],
        'settings' => [
            'default_model' => $settings['default_model'] ?? 'gpt-4o-mini',
            'max_tokens' => $settings['max_tokens'] ?? 4000,
            'temperature' => $settings['temperature'] ?? 0.7
        ]
    ]);
});

// GET /api/ai/default-provider (Admin only) (centralized in app/Routes/AISystemRoutes.php)
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
$router->get('/api/ai/default-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $settings = $aiProvider->getSettings();

    $provider = trim((string)($settings['default_provider'] ?? ''));
    if ($provider === '') {
        $effective = $aiProvider->getEffectiveProvider();
        $provider = $effective['provider_name'] ?? 'openrouter';
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'provider' => $provider
    ]);
});
}

// GET /api/ai/models?provider=fireworks (centralized in app/Routes/AISystemRoutes.php)
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
$router->get('/api/ai/models', function () use ($mysqli) {
    $providerName = $_GET['provider'] ?? '';
    $scope = $_GET['scope'] ?? '';
    $forceRefresh = !empty($_GET['refresh']);
    $aiProvider = new AIProvider($mysqli);

    header('Content-Type: application/json');

    if ($providerName === 'ollama' || $scope === 'admin' || $forceRefresh) {
        if (
            !run_middleware('auth', ['method' => 'GET', 'uri' => '/api/ai/models'])
            || !run_middleware('admin_only', ['method' => 'GET', 'uri' => '/api/ai/models'])
        ) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            return;
        }
    }

    $settings = $aiProvider->getSettings();
    $defaultModel = $settings['default_model'] ?? '';

    if (!$providerName) {
        $providers = [];
        $providerMeta = [];
        foreach ($aiProvider->getActive() as $provider) {
            $name = $provider['provider_name'] ?? '';
            if ($name === '') {
                continue;
            }
            $models = $provider['supported_models'] ?? [];
            if (empty($models)) {
                $config = AIProvider::getProviderConfig($name);
                $models = $config['models'] ?? [];
            }

            $list = [];
            foreach ($models as $id => $label) {
                $list[] = [
                    'id' => (string)$id,
                    'name' => (string)$label,
                    'default' => ($defaultModel !== '' && $defaultModel === (string)$id)
                ];
            }

            if (!empty($list) && !array_filter($list, fn($m) => !empty($m['default']))) {
                $list[0]['default'] = true;
            }

            $providers[$name] = $list;
            $providerMeta[$name] = [
                'supports_multimodal' => !empty($provider['supports_multimodal'])
            ];
        }

    echo json_encode([
        'success' => true,
        'providers' => $providers,
        'provider_meta' => $providerMeta
    ]);
    return;
    }

    $provider = $aiProvider->getByName($providerName);
    if (!$provider) {
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }

    $models = $provider['supported_models'] ?? [];
    if (empty($models)) {
        $config = AIProvider::getProviderConfig($providerName);
        $models = $config['models'] ?? [];
    }

    // OpenRouter / OpenAI / Fireworks / Hugging Face / Ollama / Kilo can optionally return remote list when configured
    if (in_array($providerName, ['openrouter', 'openai', 'fireworks', 'huggingface', 'ollama', 'kilo'], true)) {
        $remote = $aiProvider->fetchRemoteModels($providerName, $forceRefresh);
        if (!empty($remote)) {
            $models = $remote;
        }
    }

    if (empty($models)) {
        echo json_encode(['success' => false, 'error' => 'No models available']);
        return;
    }

    $providerSupportsRich = $aiProvider->supportsRichContent($providerName, $provider);
    $overrides = $provider['extra_settings']['model_multimodal'] ?? [];
    if (!is_array($overrides)) {
        $overrides = [];
    }

    $list = [];
    foreach ($models as $id => $label) {
        $modelId = (string)$id;
        if (array_key_exists($modelId, $overrides)) {
            $supportsMultimodal = (bool)$overrides[$modelId];
        } else {
            $supportsMultimodal = $providerSupportsRich;
        }
        $list[] = [
            'id' => $modelId,
            'name' => (string)$label,
            'default' => ($defaultModel !== '' && $defaultModel === (string)$id),
            'supports_multimodal' => $supportsMultimodal
        ];
    }

    if ($providerName === 'kilo' && !empty($list)) {
        usort($list, function ($a, $b) {
            $aFree = str_contains($a['id'], ':free') || str_contains(strtolower($a['name']), 'free');
            $bFree = str_contains($b['id'], ':free') || str_contains(strtolower($b['name']), 'free');
            if ($aFree === $bFree) {
                return strcmp($a['id'], $b['id']);
            }
            return $aFree ? -1 : 1;
        });
        // Preselect the first free model
        foreach ($list as &$item) {
            $item['default'] = false;
        }
        unset($item);
        foreach ($list as &$item) {
            $isFree = str_contains($item['id'], ':free') || str_contains(strtolower($item['name']), 'free');
            if ($isFree) {
                $item['default'] = true;
                break;
            }
        }
        unset($item);
    }

    if (!empty($list) && !array_filter($list, fn($m) => !empty($m['default']))) {
        $list[0]['default'] = true;
    }

    $payload = ['success' => true, 'models' => $list];
    $meta = $aiProvider->getLastRemoteModelsMeta();
    if (is_array($meta) && isset($meta['cached_at'], $meta['cache_ttl'])) {
        $payload['cached_at'] = (int)$meta['cached_at'];
        $payload['cache_ttl'] = (int)$meta['cache_ttl'];
        if (!empty($meta['source'])) {
            $payload['cache_source'] = (string)$meta['source'];
        }
    }

    echo json_encode($payload);
});
}

// POST /api/ai/chat (Public assistant) - centralized in app/Routes/AISystemRoutes.php
if (!defined('BROX_AI_API_ROUTES_HANDLED')) {
$router->post('/api/ai/chat', function () use ($mysqli) {
    run_middleware('rate_limit', [
        'scope' => 'ai_public_chat',
        'limit' => 30,
        'window' => 60,
        'is_api' => true
    ]);

    // CSRF validation - accept from body or header
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $csrfToken = (string)($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($csrfToken) && function_exists('validateCsrfToken') && !validateCsrfToken($csrfToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Invalid CSRF token',
            'error_code' => 'csrf_token_invalid'
        ]);
        return;
    }

    aiChatHandleRequest($input, $mysqli, false, false);
});

// POST /api/ai/clear-image-context
$router->post('/api/ai/clear-image-context', function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $sessionKey = null;
    if (!empty($input['visitorToken'])) {
        $sessionKey = 'visitor_' . (string)$input['visitorToken'];
    } else {
        $userId = AuthManager::getCurrentUserId() ?? ($_SESSION['user_id'] ?? null);
        if ($userId) {
            $sessionKey = 'user_' . (int)$userId;
        }
    }

    if ($sessionKey && isset($_SESSION['ai_image_context'][$sessionKey])) {
        unset($_SESSION['ai_image_context'][$sessionKey]);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
});

// POST /api/admin/ai/upload (Admin-only image upload for copilot)
$router->post('/api/admin/ai/upload', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        aiChatSendJson(['success' => false, 'error' => 'No file uploaded'], 400);
        return;
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        aiChatSendJson(['success' => false, 'error' => 'Upload failed'], 400);
        return;
    }

    if (!class_exists('UploadService')) {
        aiChatSendJson(['success' => false, 'error' => 'Upload service unavailable'], 500);
        return;
    }

    $userId = 0;
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    } elseif (!empty($_SESSION['auth_user_id'])) {
        $userId = (int)$_SESSION['auth_user_id'];
    }

    $uploadService = new UploadService($mysqli, $userId);
    $result = $uploadService->upload($file, 'ai_upload', ['preserve_name' => true]);
    if (empty($result['success'])) {
        aiChatSendJson(['success' => false, 'error' => $result['error'] ?? 'Upload failed'], 400);
        return;
    }

    aiChatSendJson([
        'success' => true,
        'url' => $result['url'] ?? '',
        'size' => $result['size'] ?? ($file['size'] ?? 0),
        'mime' => $file['type'] ?? ''
    ]);
});

// POST /api/admin/ai/chat (Admin-only)
$router->post('/api/admin/ai/chat', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    aiChatHandleRequest($input, $mysqli, true, true);
});

// POST /api/ai-system/chat (Legacy alias for admin)
$router->post('/api/ai-system/chat', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    aiChatHandleRequest($input, $mysqli, true, true);
});
}

// ==================== Knowledge Base Management (Admin) ====================
require_once __DIR__ . '/../Models/AIKnowledge.php';

// GET /api/admin/ai-knowledge - list knowledge slices
$router->get('/api/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $rows = $model->list($limit, $offset);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'items' => $rows]);
});

// GET /api/admin/ai-knowledge/{id}
$router->get('/api/admin/ai-knowledge/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $row = $model->getById((int)$id);
    header('Content-Type: application/json');
    echo json_encode(['success' => $row !== null, 'item' => $row]);
});

// POST /api/admin/ai-knowledge - create or update
$router->post('/api/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);

    // Support both JSON requests and multipart/form-data file uploads
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($input['id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $source = in_array($input['source_type'] ?? 'text', ['text', 'pdf']) ? $input['source_type'] : 'text';
    $category = !empty($input['category']) ? trim($input['category']) : null;
    $priority = isset($input['priority']) ? (int)$input['priority'] : 0;
    $isActive = isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1;

    // SECURITY: Validate content doesn't contain potentially dangerous URLs (SSRF protection)
    if (!empty($content)) {
        // Block dangerous URL schemes that could lead to SSRF
        $forbiddenSchemes = ['file://', 'ftp://', 'gopher://', 'dict://', 'sftp://', 'php://', 'expect://', 'ssh2://'];
        foreach ($forbiddenSchemes as $scheme) {
            if (stripos($content, $scheme) !== false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Forbidden URL scheme in content: ' . $scheme]);
                return;
            }
        }

        // Extract and validate any URLs found in content
        if (preg_match_all('/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i', $content, $urlMatches)) {
            $blockedPatterns = [
                'localhost',
                '127.',
                '0.0.0.0',
                '::1',
                '10.',
                '172.(1[6-9]|2|3[01]).',
                '192.168.',
                '169.254.', // Link-local
                '.local',
                '.lan',
                '.intranet',
                'metadata.google.internal', // Cloud metadata
            ];

            foreach ($urlMatches[1] as $url) {
                $parsedUrl = @parse_url($url);
                if (!$parsedUrl) continue;

                $host = $parsedUrl['host'] ?? '';
                if (empty($host)) continue;

                // Check for blocked patterns
                foreach ($blockedPatterns as $pattern) {
                    if (stripos($host, $pattern) !== false) {
                        http_response_code(400);
                        echo json_encode(['success' => false, 'error' => 'Forbidden URL: Internal hosts are not allowed']);
                        return;
                    }
                }

                // Block private/reserved IPs
                if (
                    filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false &&
                    filter_var($host, FILTER_VALIDATE_IP) !== false
                ) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Forbidden URL: Private IP addresses are not allowed']);
                    return;
                }
            }
        }
    }
    // Handle uploaded PDF file (optional)
    if (!empty($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../public_html/uploads/knowledge';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $tmp = $_FILES['pdf_file']['tmp_name'];
        $orig = basename($_FILES['pdf_file']['name']);
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
        $target = $uploadDir . '/' . time() . '_' . $safe;
        if (move_uploaded_file($tmp, $target)) {
            // Store the public path in content for later processing
            $publicPath = '/uploads/knowledge/' . basename($target);
            $content = 'FILEPATH:' . $publicPath;
            $source = 'pdf';
        }
    }

    if ($id > 0) {
        $ok = $model->update($id, [
            'title' => $title,
            'content' => $content,
            'source_type' => $source,
            'category' => $category,
            'priority' => $priority,
            'is_active' => $isActive
        ]);
        echo json_encode(['success' => $ok]);
        return;
    }

    $newId = $model->create([
        'title' => $title,
        'content' => $content,
        'source_type' => $source,
        'category' => $category,
        'priority' => $priority,
        'is_active' => $isActive
    ]);
    echo json_encode(['success' => $newId > 0, 'id' => $newId]);
});

// DELETE /api/admin/ai-knowledge/{id}
$router->post('/api/admin/ai-knowledge/delete', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID required']);
        return;
    }
    $ok = $model->delete($id);
    echo json_encode(['success' => $ok]);
});

// ==================== Self-Improving KB API ====================
// NOTE: KB feedback endpoint moved to app/Routes/AISystemRoutes.php as:
// POST /api/ai/knowledge/feedback

// GET /api/admin/ai-knowledge/suggestions - Get improvement suggestions
$router->get('/api/admin/ai-knowledge/suggestions', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $suggestions = $model->getImprovementSuggestions(10);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
});

// GET /api/admin/ai-knowledge/analytics - Get KB analytics
$router->get('/api/admin/ai-knowledge/analytics', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $analytics = $model->getAnalytics();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'analytics' => $analytics]);
});

// GET /api/admin/ai-knowledge/by-quality - Get knowledge sorted by quality
$router->get('/api/admin/ai-knowledge/by-quality', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    $limit = min(50, (int)($_GET['limit'] ?? 20));
    $items = $model->getByQuality($limit);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'items' => $items]);
});

// POST /api/ai/record-usage - Record knowledge usage
$router->post('/api/ai/record-usage', ['middleware' => ['csrf']], function () use ($mysqli) {
    $model = new AIKnowledge($mysqli);
    
    $knowledgeId = (int)($_POST['knowledge_id'] ?? 0);
    
    if (!$knowledgeId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Knowledge ID required']);
        return;
    }
    
    $ok = $model->recordUsage($knowledgeId);
    header('Content-Type: application/json');
    echo json_encode(['success' => $ok]);
});

// ==================== RAG (Retrieval-Augmented Generation) API ====================

// POST /api/admin/ai-knowledge/reindex - Reindex all knowledge items with embeddings
$router->post('/api/admin/ai-knowledge/reindex', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    require_once __DIR__ . '/../Modules/AISystem/Layer/RAGEngine.php';

    $rag = new RAGEngine($mysqli);
    $result = $rag->reindexAll();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'result' => $result]);
});

// GET /api/admin/ai-knowledge/search - Search knowledge base (for RAG)
$router->get('/api/admin/ai-knowledge/search', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    require_once __DIR__ . '/../Modules/AISystem/Layer/RAGEngine.php';

    $query = $_GET['q'] ?? '';
    $limit = min(10, (int)($_GET['limit'] ?? 5));

    if (strlen($query) < 2) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Query too short']);
        return;
    }

    $rag = new RAGEngine($mysqli);
    $results = $rag->retrieve($query, ['limit' => $limit]);
    $context = $rag->buildContext($results);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => $results,
        'context' => $context
    ]);
});

// --- ADMIN CHAT MANAGEMENT ROUTES ---

// GET /admin/ai-chats - Conversations Management Dashboard
$router->get('/admin/ai-chats', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin'],
        ['label' => 'AI Conversations', 'url' => '/admin/ai-chats']
    ];

    echo $twig->render('admin/ai/chats.twig', [
        'title' => 'AI Conversations',
        'breadcrumbs' => $breadcrumbs,
        'current_page' => 'ai-chats',
        'csrf_token' => generateCsrfToken()
    ]);
});

// GET /admin/ai-knowledge - Knowledge Base Management UI
$router->get('/admin/ai-knowledge', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => '/admin'],
        ['label' => 'AI Knowledge Base', 'url' => '/admin/ai-knowledge']
    ];

    echo $twig->render('admin/ai/knowledge.twig', [
        'title' => 'AI Knowledge Base',
        'breadcrumbs' => $breadcrumbs,
        'csrf_token' => generateCsrfToken()
    ]);
});

// GET /api/admin/ai-chats - List all conversations
$router->get('/api/admin/ai-chats', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    require_once __DIR__ . '/../Models/AIChatModel.php';
    $chatModel = new AIChatModel($mysqli);
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $convs = $chatModel->listConversations($limit, $offset);
    foreach ($convs as &$conv) {
        if (!isset($conv['visitor_token'])) {
            $conv['visitor_token'] = $conv['guest_token'] ?? null;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'conversations' => $convs]);
});

// GET /api/admin/ai-chats/{id} - Get transcript
$router->get('/api/admin/ai-chats/(\d+)', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    require_once __DIR__ . '/../Models/AIChatModel.php';
    $chatModel = new AIChatModel($mysqli);
    $messages = $chatModel->getMessages((int)$id);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'messages' => $messages]);
});

// POST /api/admin/ai-chats/reply - Log manual admin response
$router->post('/api/admin/ai-chats/reply', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    require_once __DIR__ . '/../Models/AIChatModel.php';
    $chatModel = new AIChatModel($mysqli);

    $input = json_decode(file_get_contents('php://input'), true);
    $convId = $input['conversation_id'] ?? 0;
    $content = $input['content'] ?? '';

    if (!$convId || !$content) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Conversation ID and content are required']);
        return;
    }

    $result = $chatModel->addMessage((int)$convId, 'assistant', $content);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
});

// POST /api/admin/ai-chats/end - Close conversation
$router->post('/api/admin/ai-chats/end', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    require_once __DIR__ . '/../Models/AIChatModel.php';
    $chatModel = new AIChatModel($mysqli);

    $input = json_decode(file_get_contents('php://input'), true);
    $convId = $input['conversation_id'] ?? 0;

    if (!$convId) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Conversation ID is required']);
        return;
    }

    $result = $chatModel->setStatus((int)$convId, 'closed');
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
});

// POST /api/ai-system/set-default
$router->post('/api/ai-system/set-default', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    if (!$id) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ID is required']);
        return;
    }

    $result = $aiProvider->setAsDefault($id);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
});

// POST /api/ai-system/toggle-provider
$router->post('/api/ai-system/toggle-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $active = $input['active'] ?? false;

    $result = $aiProvider->update($id, ['is_active' => $active]);
    header('Content-Type: application/json');
    echo json_encode(['success' => $result]);
});

// POST /api/ai-system/delete-provider
$router->post('/api/ai-system/delete-provider', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;

    $provider = $aiProvider->getById($id);
    if (!$provider) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
        return;
    }

    if ($provider['provider_name'] === 'custom' && $provider['sort_order'] >= 90) {
        $result = $aiProvider->delete($id);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Cannot delete built-in providers']);
    }
});

// POST /api/ai-system/test
$router->post('/api/ai-system/test', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? 0;
    $model = $input['model'] ?? null;

    $provider = $aiProvider->getById($id);
    if ($provider) {
        $result = $aiProvider->testConnection($provider['provider_name'], $model);
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Provider not found']);
    }
});

// GET /api/admin/ai-tools - List available AI assistant tools
$router->get('/api/admin/ai-tools', ['middleware' => ['auth', 'admin_only']], function () {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'tools' => ToolRegistry::listTools(),
        'tools_for_api' => ToolRegistry::getToolsForAPI(),
        'count' => ToolRegistry::count(),
        'circuit_breaker' => ToolRegistry::getCircuitBreakerStatus()
    ]);
});

// POST /api/admin/ai-tools/execute - Execute a tool directly
$router->post('/api/admin/ai-tools/execute', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $toolName = $input['tool'] ?? '';
    $args = $input['args'] ?? [];
    $stream = !empty($input['stream']);

    if (empty($toolName)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tool name is required']);
        return;
    }

    $result = ToolRegistry::execute($toolName, $args, $mysqli, [
        'stream' => $stream,
        'call_id' => $input['call_id'] ?? 'call_' . bin2hex(random_bytes(8))
    ]);

    header('Content-Type: application/json');
    echo json_encode($result);
});

// POST /api/admin/ai-tools/execute-parallel - Execute multiple tools in parallel
$router->post('/api/admin/ai-tools/execute-parallel', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $toolCalls = $input['tool_calls'] ?? [];
    if (empty($toolCalls) || !is_array($toolCalls)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tool_calls array is required']);
        return;
    }

    // Normalize call IDs
    foreach ($toolCalls as &$call) {
        if (empty($call['call_id'])) {
            $call['call_id'] = 'call_' . bin2hex(random_bytes(8));
        }
    }
    unset($call);

    $results = ToolRegistry::executeParallel($toolCalls, $mysqli, [
        'stream' => !empty($input['stream']),
        'timeout' => $input['timeout'] ?? 60,
        'max_concurrent' => $input['max_concurrent'] ?? count($toolCalls)
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'results' => $results,
        'tool_messages' => ToolRegistry::buildToolResultMessages($results)
    ]);
});

// POST /api/admin/ai-tools/process-streaming - Process streaming tool calls from AI
$router->post('/api/admin/ai-tools/process-streaming', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $toolCalls = $input['tool_calls'] ?? [];
    if (empty($toolCalls) || !is_array($toolCalls)) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'tool_calls array is required']);
        return;
    }

    $results = ToolRegistry::processStreamingToolCalls($toolCalls, $mysqli, [
        'stream' => !empty($input['stream'])
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'results' => $results,
        'tool_messages' => ToolRegistry::buildToolResultMessages($results)
    ]);
});

// POST /api/admin/ai-tools/reset-circuit-breaker - Reset circuit breaker for a tool
$router->post('/api/admin/ai-tools/reset-circuit-breaker', ['middleware' => ['auth', 'admin_only', 'csrf']], function () {
    $input = json_decode(file_get_contents('php://input'), true);
    $toolName = $input['tool'] ?? '';

    if (empty($toolName)) {
        ToolRegistry::resetAllCircuitBreakers();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'All circuit breakers reset']);
        return;
    }

    ToolRegistry::resetCircuitBreaker($toolName);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => "Circuit breaker reset for '{$toolName}'"]);
});

// GET /api/admin/system-health - Unified system health check for AI assistant
$router->get('/api/admin/system-health', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    header('Content-Type: application/json');

    $health = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'server' => [
            'status' => 'online',
            'time' => date('Y-m-d H:i:s')
        ],
        'database' => [
            'status' => 'connected',
            'check' => false
        ],
        'api' => [
            'status' => 'responsive',
            'check' => false
        ],
        'cache' => [
            'status' => 'active',
            'check' => false
        ]
    ];

    // Check database
    try {
        $result = $mysqli->query('SELECT 1');
        $health['database']['check'] = $result !== false;
    } catch (Exception $e) {
        $health['database']['status'] = 'error';
        $health['database']['error'] = $e->getMessage();
    }

    // Check API responsiveness (cache/file system)
    $cacheFile = __DIR__ . '/../../storage/cache/.health_check';
    try {
        @file_put_contents($cacheFile, time());
        $health['cache']['check'] = file_exists($cacheFile);
        @unlink($cacheFile);
    } catch (Exception $e) {
        $health['cache']['status'] = 'error';
    }

    // Check API (try a simple endpoint)
    $health['api']['check'] = true;

    echo json_encode($health);
});

// POST /admin/ai-system/browse-url - Browse URL content for AI assistant
$router->post('/admin/ai-system/browse-url', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? '';
    $query = $input['query'] ?? '';

    if (empty($url)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'URL is required']);
        return;
    }

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        return;
    }

    // Check for SSRF - only allow http/https
    $parsed = parse_url($url);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Only HTTP/HTTPS URLs are allowed']);
        return;
    }

    // Block private IPs and localhost
    $host = $parsed['host'] ?? '';
    $privateHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', 'localhost.localdomain'];
    if (in_array($host, $privateHosts) || preg_match('/^(10\\.|172\\.(1[6-9]|2|3[01])\\.|192\\.168\\.)/', gethostbyname($host))) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Private/localhost URLs are not allowed']);
        return;
    }

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Failed to fetch URL (HTTP $httpCode)"]);
            return;
        }

        // Extract text content from HTML
        $text = strip_tags($html);
        $text = preg_replace('/\\s+/', ' ', $text);
        $text = trim($text);

        // Limit to first 10000 chars
        $text = substr($text, 0, 10000);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'content' => $text,
            'url' => $url
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /admin/ai-system/web-search - Search the web for information
$router->post('/admin/ai-system/web-search', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // CSRF validation
    $csrfToken = (string)($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!empty($csrfToken) && function_exists('validateCsrfToken') && !validateCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return;
    }
    
    $query = $input['query'] ?? '';
    $limit = min((int)($input['limit'] ?? 10), 20);

    if (empty($query)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Search query is required']);
        return;
    }

    try {
        // Use DuckDuckGo HTML search (free, no API key required)
        $searchUrl = 'https://html.duckduckgo.com/html/?q=' . urlencode($query) . '&limit=' . $limit;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $searchUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Search failed (HTTP $httpCode)"]);
            return;
        }

        // Parse DuckDuckGo results
        $results = [];
        
        // Match result links
        preg_match_all('/<a class="result__a" href="([^"]+)"[^>]*>(.+?)<\/a>/', $html, $links, PREG_SET_ORDER);
        
        // Match result snippets
        preg_match_all('/<a class="result__snippet"[^>]*>(.+?)<\/a>/', $html, $snippets, PREG_SET_ORDER);
        
        // Match result titles
        preg_match_all('/<a class="result__a"[^>]*>(.+?)<\/a>/', $html, $titles, PREG_SET_ORDER);

        // Combine results
        for ($i = 0; $i < min(count($links), $limit); $i++) {
            $title = '';
            if (isset($titles[$i][1])) {
                $title = strip_tags(html_entity_decode($titles[$i][1]));
            }
            
            $url = '';
            if (isset($links[$i][1])) {
                // DuckDuckGo redirects through its own URL, extract actual URL
                $url = html_entity_decode($links[$i][1]);
                if (strpos($url, 'uddg=') !== false) {
                    parse_str(parse_url($url, PHP_URL_QUERY), $params);
                    $url = $params['uddg'] ?? $url;
                }
            }
            
            $snippet = '';
            if (isset($snippets[$i][1])) {
                $snippet = strip_tags(html_entity_decode($snippets[$i][1]));
            }

            if (!empty($url) && !empty($title)) {
                $results[] = [
                    'title' => trim($title),
                    'url' => $url,
                    'snippet' => trim($snippet)
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'results' => $results
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /admin/ai-system/add-knowledge - Add knowledge from URL to AI Knowledge Base
$router->post('/admin/ai-system/add-knowledge', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? '';
    $title = $input['title'] ?? '';
    $category = $input['category'] ?? 'general';

    if (empty($url)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'URL is required']);
        return;
    }

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        return;
    }

    // Check for SSRF - only allow http/https
    $parsed = parse_url($url);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Only HTTP/HTTPS URLs are allowed']);
        return;
    }

    try {
        // Fetch the URL content
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($html)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => "Failed to fetch URL (HTTP $httpCode)"]);
            return;
        }

        // Extract text content
        $text = strip_tags($html);
        $text = preg_replace('/\\s+/', ' ', $text);
        $text = trim($text);
        $text = substr($text, 0, 5000);

        // If no title provided, extract from page
        if (empty($title)) {
            if (preg_match('/<title[^>]*>([^<]+)<\\/title>/i', $html, $matches)) {
                $title = trim($matches[1]);
            } else {
                $title = 'Knowledge from ' . $url;
            }
        }

        // Save to knowledge base
        require_once __DIR__ . '/../Models/AIKnowledge.php';
        $knowledgeModel = new AIKnowledge($mysqli);
        $result = $knowledgeModel->create([
            'title' => $title,
            'content' => $text,
            'category' => $category,
            'source_url' => $url,
            'is_active' => 1,
            'priority' => 1
        ]);

        if ($result > 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Knowledge added successfully',
                'id' => $result,
                'title' => $title,
                'content_preview' => substr($text, 0, 200) . '...'
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to save knowledge']);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

// POST /admin/ai-system/ocr - OCR for images uploaded to AI assistant
$router->post('/admin/ai-system/ocr', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    $input = json_decode(file_get_contents('php://input'), true);
    $imageData = $input['image'] ?? '';

    if (empty($imageData)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Image data is required']);
        return;
    }

    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'GD extension not available']);
        return;
    }

    try {
        // Handle base64 data with or without prefix
        if (preg_match('/^data:image\\/(\\w+);base64,/', $imageData, $matches)) {
            $mimeType = $matches[1];
            $imageData = base64_decode(preg_replace('/^data:image\\/\\w+;base64,/', '', $imageData));
        } else {
            $imageData = base64_decode($imageData);
        }

        if (!$imageData) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid image data']);
            return;
        }

        // Create image resource from data
        $image = imagecreatefromstring($imageData);
        if (!$image) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Could not create image from data']);
            return;
        }

        // Save temporary file
        $tempFile = sys_get_temp_dir() . '/ocr_' . uniqid() . '.png';
        imagepng($image, $tempFile);
        imagedestroy($image);

        // Try to use Tesseract if available
        $text = '';
        $error = '';

        if (function_exists('exec') && file_exists($tempFile)) {
            @exec("tesseract " . escapeshellarg($tempFile) . " stdout -l eng 2>&1", $output, $returnCode);
            if ($returnCode === 0 && !empty($output)) {
                $text = implode("\n", $output);
            } else {
                $error = 'Tesseract not available';
            }
        } else {
            $error = 'Tesseract not available';
        }

        // Try PaddleOCR if available (Python)
        if (empty($text) && function_exists('exec') && file_exists($tempFile)) {
            // Check if paddleocr is installed
            @exec("pip show paddleocr 2>&1 | head -1", $paddleCheck, $paddleReturn);
            if (!empty($paddleCheck) && strpos($paddleCheck[0] ?? '', 'Name:') !== false) {
                $paddleScript = sys_get_temp_dir() . '/paddle_ocr_' . uniqid() . '.py';
                $paddlePyCode = "
from paddleocr import PaddleOCR
from PIL import Image
import sys

img_path = sys.argv[1]
ocr = PaddleOCR(use_angle_cls=True, lang='en', show_log=False)
result = ocr.ocr(img_path, cls=True)

if result and result[0]:
    texts = []
    for line in result[0]:
        if line and len(line) >= 2:
            texts.append(line[1][0])
    print('\\n'.join(texts))
else:
    print('')
";
                file_put_contents($paddleScript, $paddlePyCode);

                @exec("python " . escapeshellarg($paddleScript) . " " . escapeshellarg($tempFile) . " 2>&1", $paddleOutput, $paddleReturnCode);
                @unlink($paddleScript);

                if ($paddleReturnCode === 0 && !empty($paddleOutput)) {
                    $text = implode("\n", $paddleOutput);
                    $error = '';
                }
            }
        }

        // If local OCR failed, try OCR.space free API
        if (empty($text) && function_exists('curl_init') && file_exists($tempFile)) {
            $apiKey = 'helloworld'; // Free tier API key
            $postData = [
                'apikey' => $apiKey,
                'language' => 'eng',
                'isOverlayRequired' => false,
                'file' => new CURLFile($tempFile, 'image/png', 'ocr.png')
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.ocr.space/parse/image',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $apiResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $apiResponse) {
                $result = json_decode($apiResponse, true);
                if (!empty($result['ParsedResults'][0]['ParsedText'])) {
                    $text = $result['ParsedResults'][0]['ParsedText'];
                } elseif (!empty($result['ErrorMessage'])) {
                    $error = implode(', ', $result['ErrorMessage']);
                }
            }
        }

        // Clean up temp file
        @unlink($tempFile);

        if (!empty($text) && trim($text) !== '') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'text' => trim($text)
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $error ?: 'No readable text found in the image. Please ensure the image has clear, visible text.'
            ]);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});
