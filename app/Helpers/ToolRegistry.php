<?php
/**
 * ToolRegistry - Manages AI Assistant tool execution
 * 
 * Provides a centralized registry for all admin assistant tools
 * with caching, error handling, streaming, parallel execution,
 * and standardized responses.
 * 
 * Follows OpenAI function calling best practices:
 * - Clear, descriptive tool names and parameters
 * - Enums for constrained choices
 * - Structured JSON schema for parameters
 * - Execution tracking and caching
 * - Streaming support for real-time feedback
 * - Parallel tool execution for concurrent calls
 * - Retry logic with exponential backoff
 * - Circuit breaker for failing tools
 * 
 * @package BroxBhai
 * @version 3.0.0
 */

class ToolRegistry
{
    private static array $tools = [];
    private static array $cache = [];
    private static int $cacheTtl = 300; // 5 minutes
    private static array $executionLog = [];

    // --- Parallel Execution State ---
    private static array $parallelResults = [];
    private static int $parallelTimeout = 30; // seconds per tool in parallel mode

    // --- Circuit Breaker State ---
    private static array $circuitBreaker = []; // [tool_name => ['failures' => int, 'last_failure' => int, 'state' => 'closed|open|half_open']]
    private static int $circuitBreakerThreshold = 5; // failures before opening circuit
    private static int $circuitBreakerResetTime = 60; // seconds before half-open attempt

    // --- Streaming State ---
    private static array $streamBuffer = [];

    /**
     * Register a tool with OpenAI-compatible schema
     * 
     * @param string $name Tool identifier (snake_case, e.g., 'get_weather')
     * @param callable $handler Function that executes the tool
     * @param array $metadata Tool configuration:
     *   - 'name': Display name
     *   - 'description': Clear description of what the tool does and when to use it
     *   - 'parameters': JSON schema object defining input arguments
     *   - 'namespace': Optional grouping (e.g., 'database', 'system', 'content')
     *   - 'requires_auth': Whether admin auth is required (default: true)
     *   - 'cacheable': Whether results can be cached (default: false)
     *   - 'examples': Array of example inputs/outputs for few-shot learning
     *   - 'stream': Whether to emit streaming progress (default: false)
     *   - 'timeout': Tool-specific timeout in seconds (default: 30)
     *   - 'max_retries': Maximum retry attempts on failure (default: 0)
     *   - 'retry_delay': Base delay between retries in seconds (default: 1)
     *   - 'cache_ttl': Tool-specific cache TTL override in seconds
     */
    public static function register(string $name, callable $handler, array $metadata = []): void
    {
        // Validate tool name (must be snake_case for OpenAI compatibility)
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Tool name '{$name}' must be snake_case");
        }

        self::$tools[$name] = [
            'type' => 'function',
            'handler' => $handler,
            'name' => $name,
            'display_name' => $metadata['name'] ?? self::snakeToTitle($name),
            'description' => $metadata['description'] ?? '',
            'parameters' => $metadata['parameters'] ?? [
                'type' => 'object',
                'properties' => [],
                'required' => [],
                'additionalProperties' => false
            ],
            'namespace' => $metadata['namespace'] ?? null,
            'requires_auth' => $metadata['requires_auth'] ?? true,
            'cacheable' => $metadata['cacheable'] ?? false,
            'examples' => $metadata['examples'] ?? [],
            'strict' => $metadata['strict'] ?? true,
            'timeout' => $metadata['timeout'] ?? 30,
            'max_retries' => $metadata['max_retries'] ?? 0,
            'retry_delay' => $metadata['retry_delay'] ?? 1,
            'cache_ttl' => $metadata['cache_ttl'] ?? null,
        ];
    }

    /**
     * Convert snake_case to Title Case
     */
    private static function snakeToTitle(string $name): string
    {
        return ucwords(str_replace('_', ' ', $name));
    }

    /**
     * Check if a tool exists
     */
    public static function has(string $name): bool
    {
        return isset(self::$tools[$name]);
    }

    /**
     * Get tool metadata (OpenAI-compatible format)
     */
    public static function getTool(string $name): ?array
    {
        if (!isset(self::$tools[$name])) {
            return null;
        }

        $tool = self::$tools[$name];
        return [
            'type' => 'function',
            'name' => $tool['name'],
            'description' => $tool['description'],
            'parameters' => $tool['parameters'],
            'strict' => $tool['strict'],
        ];
    }

    /**
     * Get all tools in OpenAI-compatible format for API calls
     */
    public static function getToolsForAPI(): array
    {
        $tools = [];
        foreach (self::$tools as $tool) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                    'strict' => $tool['strict'],
                ]
            ];
        }
        return $tools;
    }

    /**
     * List all registered tools (OpenAI-compatible format)
     */
    public static function listTools(): array
    {
        $list = [];
        foreach (self::$tools as $name => $tool) {
            $list[] = [
                'type' => 'function',
                'name' => $name,
                'display_name' => $tool['display_name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
                'namespace' => $tool['namespace'],
                'examples' => $tool['examples'],
            ];
        }
        return $list;
    }

    /**
     * List tools grouped by namespace
     */
    public static function listToolsByNamespace(): array
    {
        $grouped = [];
        foreach (self::$tools as $name => $tool) {
            $ns = $tool['namespace'] ?? 'general';
            $grouped[$ns][] = [
                'type' => 'function',
                'name' => $name,
                'display_name' => $tool['display_name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
            ];
        }
        return $grouped;
    }

    // =========================================================================
    // CIRCUIT BREAKER
    // =========================================================================

    /**
     * Check if circuit is open (tool is currently failing)
     */
    private static function isCircuitOpen(string $name): bool
    {
        if (!isset(self::$circuitBreaker[$name])) {
            return false;
        }

        $state = self::$circuitBreaker[$name];

        if ($state['state'] === 'closed') {
            return false;
        }

        if ($state['state'] === 'open') {
            // Check if enough time has passed to try half-open
            if ((time() - $state['last_failure']) >= self::$circuitBreakerResetTime) {
                self::$circuitBreaker[$name]['state'] = 'half_open';
                return false; // Allow one attempt
            }
            return true;
        }

        // half_open - allow the attempt
        return false;
    }

    /**
     * Record a successful execution (closes circuit)
     */
    private static function recordSuccess(string $name): void
    {
        self::$circuitBreaker[$name] = [
            'failures' => 0,
            'last_failure' => 0,
            'state' => 'closed'
        ];
    }

    /**
     * Record a failed execution (may open circuit)
     */
    private static function recordFailure(string $name): void
    {
        $failures = (self::$circuitBreaker[$name]['failures'] ?? 0) + 1;

        self::$circuitBreaker[$name] = [
            'failures' => $failures,
            'last_failure' => time(),
            'state' => $failures >= self::$circuitBreakerThreshold ? 'open' : 'closed'
        ];
    }

    /**
     * Get circuit breaker status for all tools
     */
    public static function getCircuitBreakerStatus(): array
    {
        $status = [];
        foreach (self::$circuitBreaker as $name => $state) {
            $status[$name] = $state;
        }
        return $status;
    }

    /**
     * Reset circuit breaker for a specific tool
     */
    public static function resetCircuitBreaker(string $name): void
    {
        unset(self::$circuitBreaker[$name]);
    }

    /**
     * Reset all circuit breakers
     */
    public static function resetAllCircuitBreakers(): void
    {
        self::$circuitBreaker = [];
    }

    // =========================================================================
    // CORE EXECUTION
    // =========================================================================

    /**
     * Execute a tool with full error handling, retry logic, and circuit breaker
     * 
     * @param string $name Tool name to execute
     * @param array $args Arguments passed to the tool
     * @param mysqli|null $mysqli Database connection
     * @param array $options Execution options:
     *   - 'stream': Whether to emit streaming events (default: false)
     *   - 'timeout': Override tool timeout (default: use tool config)
     *   - 'skip_circuit_breaker': Bypass circuit breaker check (default: false)
     *   - 'call_id': OpenAI-compatible call ID for tracking
     * @return array Execution result
     */
    public static function execute(string $name, array $args = [], ?mysqli $mysqli = null, array $options = []): array
    {
        $stream = $options['stream'] ?? false;
        $callId = $options['call_id'] ?? 'call_' . bin2hex(random_bytes(8));

        // Check circuit breaker
        if (!($options['skip_circuit_breaker'] ?? false) && self::isCircuitOpen($name)) {
            return [
                'success' => false,
                'error' => "Tool '{$name}' is temporarily unavailable (circuit breaker open). Try again later.",
                'error_code' => 'circuit_open',
                'tool' => $name,
                'call_id' => $callId,
                'circuit_breaker' => self::$circuitBreaker[$name] ?? null
            ];
        }

        if (!self::has($name)) {
            $available = array_keys(self::$tools);
            return [
                'success' => false,
                'error' => "Unknown tool: '{$name}'",
                'error_code' => 'tool_not_found',
                'call_id' => $callId,
                'available_tools' => $available,
                'suggestion' => !empty($available) ? "Available: " . implode(', ', array_map(fn($t) => "/{$t}", $available)) : null
            ];
        }

        $tool = self::$tools[$name];

        // Validate arguments against schema
        $validationError = self::validateArgs($args, $tool['parameters']);
        if ($validationError !== null) {
            return [
                'success' => false,
                'error' => "Invalid arguments: {$validationError}",
                'error_code' => 'invalid_arguments',
                'tool' => $name,
                'call_id' => $callId,
                'expected_schema' => $tool['parameters']
            ];
        }

        // Check cache for cacheable tools
        if ($tool['cacheable']) {
            $cacheKey = $name . '_' . md5(json_encode($args));
            $cached = self::getCache($cacheKey);
            if ($cached !== null) {
                return [
                    'success' => true,
                    'data' => $cached,
                    'tool' => $name,
                    'call_id' => $callId,
                    'cached' => true,
                    'execution_time_ms' => 0
                ];
            }
        }

        // Execute with retry logic
        $maxRetries = $options['max_retries'] ?? $tool['max_retries'];
        $retryDelay = $tool['retry_delay'];
        $lastError = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $result = self::executeOnce($name, $tool, $args, $mysqli, $stream, $callId, $options, $attempt);

            if ($result['success']) {
                self::recordSuccess($name);

                // Cache result if tool is cacheable
                if ($tool['cacheable'] && !empty($result['data'])) {
                    $cacheKey = $name . '_' . md5(json_encode($args));
                    $ttl = $tool['cache_ttl'] ?? self::$cacheTtl;
                    self::setCache($cacheKey, $result['data'], $ttl);
                }

                return $result;
            }

            $lastError = $result;

            // Don't retry on validation or not-found errors
            if (in_array($result['error_code'] ?? '', ['tool_not_found', 'invalid_arguments', 'circuit_open'])) {
                break;
            }

            // Exponential backoff for retries
            if ($attempt < $maxRetries) {
                $delay = $retryDelay * pow(2, $attempt);
                usleep((int)($delay * 1000000));

                if ($stream) {
                    self::emitStreamEvent('tool_call_retry', [
                        'tool' => $name,
                        'call_id' => $callId,
                        'attempt' => $attempt + 2,
                        'max_retries' => $maxRetries + 1,
                        'delay_seconds' => $delay
                    ]);
                }
            }
        }

        // All retries exhausted
        self::recordFailure($name);
        return $lastError;
    }

    /**
     * Execute a single tool attempt (internal, called by execute with retry loop)
     */
    private static function executeOnce(
        string $name,
        array $tool,
        array $args,
        ?mysqli $mysqli,
        bool $stream,
        string $callId,
        array $options,
        int $attempt
    ): array {
        $timeout = $options['timeout'] ?? $tool['timeout'];

        try {
            $startTime = microtime(true);

            // Emit streaming start event
            if ($stream) {
                self::emitStreamEvent('tool_call_start', [
                    'tool' => $name,
                    'call_id' => $callId,
                    'arguments' => $args,
                    'attempt' => $attempt + 1
                ]);
            }

            // Execute with timeout using pcntl_alarm if available, otherwise rely on tool handler
            if (function_exists('pcntl_signal')) {
                pcntl_signal(SIGALRM, function () use ($name, $timeout) {
                    throw new RuntimeException("Tool '{$name}' exceeded timeout of {$timeout}s");
                });
                pcntl_alarm($timeout);
            }

            $result = call_user_func($tool['handler'], $args, $mysqli);

            if (function_exists('pcntl_signal')) {
                pcntl_alarm(0); // Cancel alarm
            }

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $response = [
                'success' => true,
                'data' => $result,
                'tool' => $name,
                'call_id' => $callId,
                'execution_time_ms' => $executionTime,
                'cached' => false,
                'attempt' => $attempt + 1
            ];

            // Log execution
            self::logExecution($name, $args, $response);

            // Emit streaming completion event
            if ($stream) {
                self::emitStreamEvent('tool_call_complete', [
                    'tool' => $name,
                    'call_id' => $callId,
                    'result' => $result,
                    'execution_time_ms' => $executionTime
                ]);
            }

            return $response;

        } catch (Throwable $e) {
            $executionTime = round((microtime(true) - ($startTime ?? microtime(true))) * 1000, 2);

            if (function_exists('pcntl_signal')) {
                pcntl_alarm(0);
            }

            // Categorize error
            $errorCategory = self::categorizeError($e);

            $errorResponse = [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => $errorCategory,
                'error_category' => $errorCategory,
                'tool' => $name,
                'call_id' => $callId,
                'exception' => get_class($e),
                'execution_time_ms' => $executionTime,
                'attempt' => $attempt + 1,
                'retryable' => self::isRetryableError($errorCategory)
            ];

            logError("Tool execution failed: {$name} (attempt {$attempt}) - " . $e->getMessage(), "ERROR", [
                'tool' => $name,
                'args' => $args,
                'exception' => get_class($e),
                'error_category' => $errorCategory,
                'attempt' => $attempt + 1,
                'trace' => $e->getTraceAsString()
            ]);

            self::logExecution($name, $args, $errorResponse);

            if ($stream) {
                self::emitStreamEvent('tool_call_error', $errorResponse);
            }

            return $errorResponse;
        }
    }

    /**
     * Categorize an exception into a standard error category
     */
    private static function categorizeError(Throwable $e): string
    {
        $class = get_class($e);
        $message = strtolower($e->getMessage());

        if ($e instanceof RuntimeException && str_contains($message, 'timeout')) {
            return 'timeout';
        }
        if ($e instanceof InvalidArgumentException || str_contains($message, 'invalid')) {
            return 'validation_error';
        }
        if (str_contains($message, 'connection') || str_contains($message, 'network') || str_contains($message, 'curl')) {
            return 'network_error';
        }
        if (str_contains($message, 'permission') || str_contains($message, 'unauthorized') || str_contains($message, 'forbidden')) {
            return 'auth_error';
        }
        if (str_contains($message, 'not found') || str_contains($message, 'missing')) {
            return 'not_found';
        }
        if (str_contains($message, 'memory') || str_contains($message, 'out of memory')) {
            return 'resource_exhausted';
        }

        return 'execution_error';
    }

    /**
     * Determine if an error category is retryable
     */
    private static function isRetryableError(string $category): bool
    {
        return in_array($category, ['timeout', 'network_error', 'resource_exhausted'], true);
    }

    // =========================================================================
    // PARALLEL EXECUTION
    // =========================================================================

    /**
     * Execute multiple tools in parallel
     * 
     * @param array $toolCalls Array of ['tool' => name, 'args' => [...], 'call_id' => id]
     * @param mysqli|null $mysqli Database connection
     * @param array $options Execution options:
     *   - 'stream': Whether to emit streaming events
     *   - 'timeout': Overall timeout for all parallel calls (default: 30s per tool)
     *   - 'max_concurrent': Maximum concurrent executions (default: unlimited)
     * @return array Results indexed by call_id, each containing execution result
     */
    public static function executeParallel(array $toolCalls, ?mysqli $mysqli = null, array $options = []): array
    {
        if (empty($toolCalls)) {
            return [];
        }

        $stream = $options['stream'] ?? false;
        $maxConcurrent = $options['max_concurrent'] ?? count($toolCalls);
        $overallTimeout = $options['timeout'] ?? (self::$parallelTimeout * count($toolCalls));

        if ($stream) {
            self::emitStreamEvent('parallel_execution_start', [
                'tool_count' => count($toolCalls),
                'call_ids' => array_column($toolCalls, 'call_id')
            ]);
        }

        // Use curl_multi for parallel HTTP-style execution
        // For PHP tool handlers, we use process forking via proc_open or pcntl_fork
        if (function_exists('pcntl_fork')) {
            return self::executeParallelFork($toolCalls, $mysqli, $options, $overallTimeout);
        }

        // Fallback: sequential execution with timeout tracking
        return self::executeParallelSequential($toolCalls, $mysqli, $options, $overallTimeout);
    }

    /**
     * Parallel execution using pcntl_fork (Unix/Linux)
     */
    private static function executeParallelFork(array $toolCalls, ?mysqli $mysqli, array $options, int $timeout): array
    {
        $stream = $options['stream'] ?? false;
        $results = [];
        $children = [];
        $pipes = [];

        foreach ($toolCalls as $index => $call) {
            $toolName = $call['tool'] ?? '';
            $args = $call['args'] ?? [];
            $callId = $call['call_id'] ?? "parallel_{$index}";

            if (!self::has($toolName)) {
                $results[$callId] = [
                    'success' => false,
                    'error' => "Unknown tool: '{$toolName}'",
                    'error_code' => 'tool_not_found',
                    'call_id' => $callId
                ];
                continue;
            }

            // Create pipe for IPC
            $pipePair = [];
            if (!proc_open('echo', [], $pipePair)) {
                // Fallback to sequential for this call
                $results[$callId] = self::execute($toolName, $args, $mysqli, [
                    'stream' => $stream,
                    'call_id' => $callId
                ]);
                continue;
            }
            fclose($pipePair[0]);
            fclose($pipePair[1]);

            // Fork process
            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed, execute sequentially
                $results[$callId] = self::execute($toolName, $args, $mysqli, [
                    'stream' => $stream,
                    'call_id' => $callId
                ]);
                continue;
            }

            if ($pid === 0) {
                // Child process
                $result = self::execute($toolName, $args, $mysqli, [
                    'stream' => false, // No streaming in child
                    'call_id' => $callId,
                    'skip_circuit_breaker' => true
                ]);

                // Write result to temp file
                $tmpFile = sys_get_temp_dir() . '/tool_result_' . $callId . '.json';
                file_put_contents($tmpFile, json_encode($result));
                exit(0);
            }

            // Parent process
            $children[$pid] = [
                'call_id' => $callId,
                'tool' => $toolName,
                'started' => microtime(true)
            ];
        }

        // Wait for all children
        $deadline = microtime(true) + $timeout;

        while (!empty($children) && microtime(true) < $deadline) {
            foreach ($children as $pid => $info) {
                $tmpFile = sys_get_temp_dir() . '/tool_result_' . $info['call_id'] . '.json';

                // Check if child finished (result file exists)
                if (file_exists($tmpFile)) {
                    $resultJson = file_get_contents($tmpFile);
                    $result = json_decode($resultJson, true) ?: ['success' => false, 'error' => 'Failed to parse result'];
                    $results[$info['call_id']] = $result;
                    @unlink($tmpFile);
                    unset($children[$pid]);

                    if ($stream) {
                        self::emitStreamEvent('parallel_tool_complete', [
                            'call_id' => $info['call_id'],
                            'tool' => $info['tool'],
                            'success' => $result['success'] ?? false
                        ]);
                    }
                    continue;
                }

                // Check if process is still running
                $res = pcntl_waitpid($pid, $status, WNOHANG);
                if ($res > 0) {
                    // Process finished but no result file
                    if (!isset($results[$info['call_id']])) {
                        $results[$info['call_id']] = [
                            'success' => false,
                            'error' => 'Tool execution completed but no result was produced',
                            'error_code' => 'no_result',
                            'call_id' => $info['call_id']
                        ];
                    }
                    @unlink($tmpFile);
                    unset($children[$pid]);
                }
            }

            if (!empty($children)) {
                usleep(50000); // 50ms polling
            }
        }

        // Kill any remaining children (timeout)
        foreach ($children as $pid => $info) {
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status);

            if (!isset($results[$info['call_id']])) {
                $results[$info['call_id']] = [
                    'success' => false,
                    'error' => "Tool '{$info['tool']}' timed out",
                    'error_code' => 'timeout',
                    'call_id' => $info['call_id']
                ];
            }
            $tmpFile = sys_get_temp_dir() . '/tool_result_' . $info['call_id'] . '.json';
            @unlink($tmpFile);
        }

        if ($stream) {
            self::emitStreamEvent('parallel_execution_complete', [
                'total' => count($toolCalls),
                'succeeded' => count(array_filter($results, fn($r) => $r['success'] ?? false)),
                'failed' => count(array_filter($results, fn($r) => !($r['success'] ?? false)))
            ]);
        }

        return $results;
    }

    /**
     * Parallel execution fallback (sequential with timeout tracking)
     */
    private static function executeParallelSequential(array $toolCalls, ?mysqli $mysqli, array $options, int $timeout): array
    {
        $stream = $options['stream'] ?? false;
        $results = [];
        $startTime = microtime(true);

        foreach ($toolCalls as $call) {
            $toolName = $call['tool'] ?? '';
            $args = $call['args'] ?? [];
            $callId = $call['call_id'] ?? 'call_' . bin2hex(random_bytes(8));

            // Check overall timeout
            $elapsed = microtime(true) - $startTime;
            if ($elapsed >= $timeout) {
                $results[$callId] = [
                    'success' => false,
                    'error' => 'Parallel execution timeout exceeded',
                    'error_code' => 'timeout',
                    'call_id' => $callId
                ];
                continue;
            }

            // Execute with remaining time budget
            $remainingTime = max(1, (int)($timeout - $elapsed));
            $results[$callId] = self::execute($toolName, $args, $mysqli, [
                'stream' => $stream,
                'call_id' => $callId,
                'timeout' => $remainingTime
            ]);
        }

        return $results;
    }

    // =========================================================================
    // STREAMING SUPPORT
    // =========================================================================

    /**
     * Process streaming tool calls from AI provider
     * 
     * This handles the SSE stream of tool_call chunks from the AI provider,
     * accumulates arguments, executes tools, and streams results back.
     * 
     * @param array $toolCalls Array of tool call objects from AI provider
     * @param mysqli|null $mysqli Database connection
     * @param array $options Execution options
     * @return array Results indexed by call_id
     */
    public static function processStreamingToolCalls(array $toolCalls, ?mysqli $mysqli = null, array $options = []): array
    {
        $stream = $options['stream'] ?? true;
        $results = [];

        if ($stream) {
            self::emitStreamEvent('streaming_tool_calls_start', [
                'count' => count($toolCalls)
            ]);
        }

        // Group calls for potential parallel execution
        $parallelCalls = [];
        $sequentialCalls = [];

        foreach ($toolCalls as $call) {
            $toolName = $call['function']['name'] ?? $call['tool'] ?? '';
            $callId = $call['id'] ?? 'call_' . bin2hex(random_bytes(8));

            // Parse arguments
            $argsStr = $call['function']['arguments'] ?? $call['args'] ?? '{}';
            $args = is_string($argsStr) ? json_decode($argsStr, true) ?? [] : (array)$argsStr;

            $callData = [
                'tool' => $toolName,
                'args' => $args,
                'call_id' => $callId
            ];

            // Tools that modify state should run sequentially
            $statefulTools = ['clear_cache', 'update_config', 'delete_record'];
            if (in_array($toolName, $statefulTools, true)) {
                $sequentialCalls[] = $callData;
            } else {
                $parallelCalls[] = $callData;
            }
        }

        // Execute parallel calls first
        if (!empty($parallelCalls)) {
            $parallelResults = self::executeParallel($parallelCalls, $mysqli, [
                'stream' => $stream,
                'timeout' => self::$parallelTimeout * count($parallelCalls)
            ]);
            $results = array_merge($results, $parallelResults);
        }

        // Execute sequential calls
        foreach ($sequentialCalls as $call) {
            $results[$call['call_id']] = self::execute(
                $call['tool'],
                $call['args'],
                $mysqli,
                ['stream' => $stream, 'call_id' => $call['call_id']]
            );
        }

        if ($stream) {
            self::emitStreamEvent('streaming_tool_calls_complete', [
                'results' => array_map(fn($r) => ['call_id' => $r['call_id'] ?? '', 'success' => $r['success'] ?? false], $results)
            ]);
        }

        return $results;
    }

    /**
     * Build tool results for sending back to AI provider
     * 
     * @param array $results Results from processStreamingToolCalls or executeParallel
     * @return array Messages in OpenAI tool response format
     */
    public static function buildToolResultMessages(array $results): array
    {
        $messages = [];

        foreach ($results as $callId => $result) {
            if (!isset($result['call_id'])) {
                $result['call_id'] = $callId;
            }

            $content = $result['success']
                ? json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : json_encode([
                    'error' => $result['error'] ?? 'Unknown error',
                    'error_code' => $result['error_code'] ?? 'unknown'
                ], JSON_UNESCAPED_UNICODE);

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $result['call_id'],
                'content' => $content
            ];
        }

        return $messages;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Validate arguments against JSON schema
     */
    private static function validateArgs(array $args, array $schema): ?string
    {
        if (empty($schema['properties'])) {
            return null; // No validation needed
        }

        $required = $schema['required'] ?? [];
        $properties = $schema['properties'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (!array_key_exists($field, $args)) {
                return "Missing required field: '{$field}'";
            }
        }

        // Validate types
        foreach ($properties as $field => $propSchema) {
            if (!array_key_exists($field, $args)) {
                continue;
            }

            $value = $args[$field];
            $expectedType = $propSchema['type'] ?? null;

            if ($expectedType === 'string' && !is_string($value)) {
                return "Field '{$field}' must be a string";
            }
            if ($expectedType === 'integer' && !is_int($value)) {
                return "Field '{$field}' must be an integer";
            }
            if ($expectedType === 'number' && !is_numeric($value)) {
                return "Field '{$field}' must be a number";
            }
            if ($expectedType === 'boolean' && !is_bool($value)) {
                return "Field '{$field}' must be a boolean";
            }
            if ($expectedType === 'array' && !is_array($value)) {
                return "Field '{$field}' must be an array";
            }

            // Validate enum
            if (isset($propSchema['enum']) && !in_array($value, $propSchema['enum'], true)) {
                return "Field '{$field}' must be one of: " . implode(', ', $propSchema['enum']);
            }

            // Validate string length
            if ($expectedType === 'string' && is_string($value)) {
                if (isset($propSchema['minLength']) && mb_strlen($value) < $propSchema['minLength']) {
                    return "Field '{$field}' must be at least {$propSchema['minLength']} characters";
                }
                if (isset($propSchema['maxLength']) && mb_strlen($value) > $propSchema['maxLength']) {
                    return "Field '{$field}' must be at most {$propSchema['maxLength']} characters";
                }
            }

            // Validate number range
            if (in_array($expectedType, ['number', 'integer'], true) && is_numeric($value)) {
                if (isset($propSchema['minimum']) && $value < $propSchema['minimum']) {
                    return "Field '{$field}' must be >= {$propSchema['minimum']}";
                }
                if (isset($propSchema['maximum']) && $value > $propSchema['maximum']) {
                    return "Field '{$field}' must be <= {$propSchema['maximum']}";
                }
            }

            // Validate array items
            if ($expectedType === 'array' && is_array($value) && isset($propSchema['items'])) {
                if (isset($propSchema['minItems']) && count($value) < $propSchema['minItems']) {
                    return "Field '{$field}' must have at least {$propSchema['minItems']} items";
                }
                if (isset($propSchema['maxItems']) && count($value) > $propSchema['maxItems']) {
                    return "Field '{$field}' must have at most {$propSchema['maxItems']} items";
                }
            }
        }

        return null;
    }

    // =========================================================================
    // COMMAND PARSING
    // =========================================================================

    /**
     * Parse slash command with OpenAI-compatible argument parsing
     */
    public static function parseCommand(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || $text[0] !== '/') {
            return null;
        }

        $cmd = null;
        $argsRaw = '';

        // Support "/tool:..." format
        if (preg_match('/^\/([a-z][a-z0-9_]*)(?::(.+))?$/', $text, $m)) {
            $cmd = strtolower($m[1]);
            $argsRaw = trim((string)($m[2] ?? ''));
        } elseif (preg_match('/^\/([a-z][a-z0-9_]*)(?:\s+(.+))?$/s', $text, $m)) {
            // Support "/tool ..." format (space-separated), e.g. "/summarize https://example.com"
            $cmd = strtolower($m[1]);
            $argsRaw = trim((string)($m[2] ?? ''));
        }

        if ($cmd !== null) {
            // Parse arguments - support key=value pairs and positional args
            $args = [];
            if ($argsRaw !== '') {
                // Check for key=value format
                if (str_contains($argsRaw, '=')) {
                    foreach (preg_split('/\s+/', $argsRaw) as $part) {
                        if (str_contains($part, '=')) {
                            [$key, $value] = explode('=', $part, 2);
                            $args[trim($key)] = trim($value, '"\'');
                        }
                    }
                } else {
                    // Positional argument - pass as 'input'
                    $args['input'] = $argsRaw;
                }
            }

            return [
                'cmd' => $cmd,
                'args' => $args,
                'raw_args' => $argsRaw,
                'call_id' => 'call_' . bin2hex(random_bytes(8))
            ];
        }

        return null;
    }

    // =========================================================================
    // STREAMING EVENTS
    // =========================================================================

    /**
     * Emit streaming event (for real-time feedback)
     */
    private static function emitStreamEvent(string $type, array $data): void
    {
        if (php_sapi_name() === 'cli') {
            return; // Skip in CLI mode
        }

        $event = [
            'type' => $type,
            'timestamp' => time(),
            'data' => $data
        ];

        // Store for later retrieval
        self::$streamBuffer[] = $event;
        self::$executionLog[] = $event;

        // For SSE streaming
        if (function_exists('header') && !headers_sent()) {
            echo 'data: ' . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
            @ob_flush();
            flush();
        }
    }

    /**
     * Get buffered stream events (for non-SSE consumers)
     */
    public static function getStreamBuffer(): array
    {
        $buffer = self::$streamBuffer;
        self::$streamBuffer = [];
        return $buffer;
    }

    // =========================================================================
    // LOGGING & CACHE
    // =========================================================================

    /**
     * Log tool execution for audit
     */
    private static function logExecution(string $name, array $args, array $result): void
    {
        self::$executionLog[] = [
            'tool' => $name,
            'args' => $args,
            'success' => $result['success'] ?? false,
            'timestamp' => time(),
            'execution_time_ms' => $result['execution_time_ms'] ?? 0
        ];
    }

    /**
     * Get execution log
     */
    public static function getExecutionLog(): array
    {
        return self::$executionLog;
    }

    /**
     * Cache operations
     */
    private static function getCache(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $entry = self::$cache[$key];
        if (time() > $entry['expires']) {
            unset(self::$cache[$key]);
            return null;
        }

        return $entry['data'];
    }

    private static function setCache(string $key, mixed $data, ?int $ttl = null): void
    {
        $ttl = $ttl ?? self::$cacheTtl;
        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
    }

    /**
     * Clear all cache
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get tool count
     */
    public static function count(): int
    {
        return count(self::$tools);
    }
}
