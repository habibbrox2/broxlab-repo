<?php
/**
 * ChatToolService - Slash command parsing and auto-tool execution loop
 * Extracted from AISystemController.php for modularity
 */

class ChatToolService
{
    /**
     * Map of command aliases
     */
    public static array $commandAliases = [
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
        'get-cache-stats' => 'get_cache_stats',
        'user-stats' => 'get_user_stats',
        'user_stats' => 'get_user_stats',
        'content-stats' => 'get_content_stats',
        'content_stats' => 'get_content_stats',
        'search-kb' => 'search_knowledge_base',
        'search_kb' => 'search_knowledge_base',
        'web-search' => 'web_search',
        'web_search' => 'web_search',
        'clear-cache' => 'clear_cache',
        'clear_cache' => 'clear_cache',
        'list-tools' => 'list_tools',
        'list_tools' => 'list_tools',
        'help' => 'list_tools',
    ];

    /**
     * Parse slash command from text
     */
    public static function parseCommand(string $text): ?array
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
            'raw_args' => trim((string)($m[2] ?? '')),
        ];
    }

    /**
     * Execute auto-tool loop (recursive tool calling)
     */
    public static function executeAutoToolLoop(
        AIProvider $aiProvider,
        string $provider,
        string $model,
        array $messages,
        array $options,
        mysqli $mysqli
    ): array {
        $response = $aiProvider->callAPI($provider, $model, $messages, $options);
        if (empty($response['success'])) {
            return $response;
        }

        $maxRounds = 4;
        $round = 0;
        $executedToolCalls = [];

        while ($round < $maxRounds) {
            $toolCalls = $response['tool_calls'] ?? null;
            if (!is_array($toolCalls) || empty($toolCalls)) {
                break;
            }

            $assistantMessage = [
                'role' => 'assistant',
                'content' => (string)($response['content'] ?? ''),
                'tool_calls' => $toolCalls,
            ];
            if (!empty($response['annotations']) && is_array($response['annotations'])) {
                $assistantMessage['annotations'] = $response['annotations'];
            }
            $messages[] = $assistantMessage;

            $results = ToolRegistry::processStreamingToolCalls($toolCalls, $mysqli, ['stream' => false]);
            $toolMessages = ToolRegistry::buildToolResultMessages($results);
            foreach ($toolMessages as $toolMessage) {
                $messages[] = $toolMessage;
            }

            $executedToolCalls[] = [
                'round' => $round + 1,
                'calls' => array_map(static function (array $call): array {
                    $function = $call['function'] ?? [];
                    return [
                        'id' => (string)($call['id'] ?? ''),
                        'name' => is_array($function) ? (string)($function['name'] ?? '') : '',
                    ];
                }, $toolCalls),
            ];

            $response = $aiProvider->callAPI($provider, $model, $messages, $options);
            if (empty($response['success'])) {
                $response['auto_tool_calls'] = $executedToolCalls;
                return $response;
            }
            $round++;
        }

        if (!empty($executedToolCalls)) {
            $response['auto_tool_calls'] = $executedToolCalls;
        }
        return $response;
    }

    /**
     * Execute a slash command via ToolRegistry
     */
    public static function executeCommand(array $cmd, mysqli $mysqli, bool $isAdmin): ?array
    {
        if (!$cmd || !$isAdmin) {
            return null;
        }

        // Resolve command alias
        $originalCmd = $cmd['cmd'];
        $cmd['cmd'] = self::$commandAliases[$cmd['cmd']] ?? $cmd['cmd'];

        // Execute tool via registry
        $toolResult = ToolRegistry::execute($cmd['cmd'], $cmd['args'], $mysqli);

        if (!$toolResult['success']) {
            $errorMsg = $toolResult['error'] ?? 'Tool execution failed';
            $errorCode = $toolResult['error_code'] ?? 'tool_error';

            if ($errorCode === 'tool_not_found') {
                $availableTools = array_map(fn($t) => '/' . $t['name'], ToolRegistry::listTools());
                $errorMsg = "Unknown command: /{$originalCmd}\n\nAvailable: " . implode(', ', $availableTools);
            }

            return [
                'success' => false,
                'error' => $errorMsg,
                'error_code' => $errorCode,
            ];
        }

        // Format tool output
        $toolOutput = json_encode($toolResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $execTime = $toolResult['execution_time_ms'] ?? 0;
        $cached = !empty($toolResult['cached']) ? ' (cached)' : '';
        $callId = 'call_' . bin2hex(random_bytes(8));

        return [
            'success' => true,
            'cmd' => $cmd,
            'original_cmd' => $originalCmd,
            'data' => $toolResult['data'],
            'tool_output' => $toolOutput,
            'execution_time_ms' => $execTime,
            'cached' => $cached,
            'call_id' => $callId,
            'raw_args' => $cmd['raw_args'] ?? '',
        ];
    }
}
