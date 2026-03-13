<?php

/**
 * AISystemController.php
 * Handles AI provider configuration routes in the Admin Panel.
 * All database operations are handled by AIProvider model.
 */

require_once __DIR__ . '/../Models/AIProvider.php';
require_once __DIR__ . '/../Models/AppSettings.php';
require_once __DIR__ . '/../Helpers/PromptLoader.php';
require_once __DIR__ . '/../Models/AIChatModel.php';
require_once __DIR__ . '/../Models/AuthManager.php';
require_once __DIR__ . '/../Models/UploadService.php';

function aiChatSendJson(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function aiChatStreamContent(string $content): void
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
            if (!$url) {
                continue;
            }
            $refs[] = [
                'url' => $url,
                'name' => $image['name'] ?? null,
                'mime' => $image['mime'] ?? null,
                'size' => isset($image['size']) ? (int)$image['size'] : null
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
            'size' => isset($ref['size']) ? (int)$ref['size'] : null
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
        if (!empty($metaParts)) {
            $line .= ' (' . implode(', ', $metaParts) . ')';
        }
        $lines[] = $line;
    }

    return $prompt . "\n" . implode("\n", $lines);
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
    $stmt->execute();
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

    // Admin-only slash commands routing
    $cmd = ($isAdmin && $lastUserMessage !== '') ? aiChatParseSlashCommand($lastUserMessage) : null;
    if ($cmd) {
        $supported = ['summarize', 'analyze-logs'];
        if (!in_array($cmd['cmd'], $supported, true)) {
            aiChatSendJson([
                'success' => false,
                'error' => 'Not implemented yet. Supported: /summarize, /analyze-logs',
                'error_code' => 'unsupported_command'
            ], 400);
            return;
        }

        // Command mode disables KB + image context (logs-only addon; no DOM snapshot)
        $imageRefs = [];
        $hasImageContent = false;

        if ($cmd['cmd'] === 'summarize') {
            $summarizerPath = __DIR__ . '/../../system/prompts/summarizer.md';
            $summarizerPrompt = file_exists($summarizerPath) ? (string)file_get_contents($summarizerPath) : '';
            $target = $cmd['args'] !== ''
                ? $cmd['args']
                : ("[ADMIN CONTEXT]\n" . trim((string)($contextData ? json_encode($contextData, JSON_UNESCAPED_UNICODE) : '')) . "\n\n"
                    . "[RECENT CONVERSATION]\n" . aiChatBuildRecentConversationText($messages, 10));

            $systemPrompt .= "\n\n[MODE: SUMMARIZE]\nReturn a concise summary. Follow the summarizer rules below.\n\n" . $summarizerPrompt;
            $messages = [
                ['role' => 'user', 'content' => $target]
            ];
            $lastUserMessage = $target;
        }

        if ($cmd['cmd'] === 'analyze-logs') {
            $path = aiChatResolveErrorLogPath();
            if (!file_exists($path)) {
                aiChatSendJson([
                    'success' => false,
                    'error' => 'Log file not found: storage/logs/errors.log',
                    'error_code' => 'log_missing'
                ], 404);
                return;
            }
            $raw = aiChatReadLastLines($path, 200);
            $errors = aiChatSelectRecentErrors($raw, 25);
            $errorsText = $errors ? implode("\n", $errors) : '(No recent ERROR/CRITICAL/WARNING lines found.)';

            $hint = $cmd['args'] !== '' ? ("\n\n[HINT]\n" . $cmd['args']) : '';
            $userText =
                "[RECENT ERRORS]\n" . $errorsText . "\n\n" .
                "[ADMIN CONTEXT]\n" . trim((string)($contextData ? json_encode($contextData, JSON_UNESCAPED_UNICODE) : '')) .
                $hint . "\n\n" .
                "Analyze the errors and provide:\n"
                . "1) Most likely root cause (ranked)\n"
                . "2) Concrete next steps\n"
                . "3) What to check in /admin/error-logs\n";

            $systemPrompt .= "\n\n[MODE: ANALYZE LOGS]\nDo not request secrets. Do not suggest destructive actions without confirmation.\n";
            $messages = [
                ['role' => 'user', 'content' => $userText]
            ];
            $lastUserMessage = $userText;
        }
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
                $chatModel->addMessage($convId, 'user', $lastUserMessage);
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
            $chatModel->addMessage($convId, 'assistant', $aiText);
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
        aiChatStreamContent($response['content'] ?? '');
        return;
    }

    if (!empty($response['success'])) {
        aiChatSendJson([
            'success' => true,
            'content' => $response['content'] ?? '',
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

// POST /api/ai/test
$router->post('/api/ai/test', function () use ($mysqli) {
    $aiProvider = new AIProvider($mysqli);
    $result = $aiProvider->testConnection($_POST['provider'] ?? '', $_POST['model'] ?? null);
    header('Content-Type: application/json');
    echo json_encode($result);
});

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

// GET /api/ai/default-provider (Admin only)
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

// GET /api/ai/models?provider=fireworks
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

// POST /api/ai/chat (Public assistant)
$router->post('/api/ai/chat', function () use ($mysqli) {
    run_middleware('rate_limit', [
        'scope' => 'ai_public_chat',
        'limit' => 30,
        'window' => 60,
        'is_api' => true
    ]);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
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
