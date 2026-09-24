<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Mcp\McpException;
use App\Support\Mcp\McpSchema;
use App\Support\Mcp\McpTools;
use App\Support\Mcp\McpWriteTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Remote MCP server endpoint (Streamable HTTP transport, JSON-RPC 2.0).
 *
 * POST /mcp        — JSON-RPC: initialize, tools/list, tools/call, ping
 * GET  /mcp/health — safe liveness check
 *
 * Security posture:
 *  - Disabled unless MCP_ENABLED=true AND MCP_API_KEY is set (fail closed).
 *  - Bearer token auth on every JSON-RPC request.
 *  - Per-minute rate limiting via Laravel RateLimiter.
 *  - All tool output is read-only public data.
 *  - Errors are sanitized — never leak SQL, paths, stack traces, secrets.
 */
class McpController extends Controller
{
    /** JSON-RPC error codes. */
    protected const E_PARSE = -32700;

    protected const E_INVALID_REQUEST = -32600;

    protected const E_METHOD_NOT_FOUND = -32601;

    protected const E_INVALID_PARAMS = -32602;

    protected const E_INTERNAL = -32603;

    protected const E_UNAUTHORIZED = -32001;

    protected const E_RATE_LIMITED = -32002;

    public function __construct(
        protected McpTools $tools,
        protected McpWriteTools $writeTools,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // 1. Gate: enabled + authenticated.
        if (! config('mcp.enabled')) {
            return $this->rpcError(null, self::E_UNAUTHORIZED, 'MCP service is not available', 503);
        }

        $auth = $this->authenticate($request);
        if ($auth === null) {
            return $this->rpcError(null, self::E_UNAUTHORIZED, 'Invalid or missing API key', 401);
        }
        $key = $auth['key'];
        $scope = $auth['scope'];

        // 2. Rate limit per key.
        $limiterKey = 'mcp:' . hash('sha256', $key);
        $limit = (int) config('mcp.rate_limit', 60);
        if ($limit > 0 && RateLimiter::tooManyAttempts($limiterKey, $limit)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => ['code' => self::E_RATE_LIMITED, 'message' => 'Rate limit exceeded. Try again later.'],
                'id' => null,
            ], 429)->header('Retry-After', (string) RateLimiter::availableIn($limiterKey));
        }
        if ($limit > 0) {
            RateLimiter::hit($limiterKey, 60);
        }

        // 3. Body size + content type guards.
        if ((int) $request->header('Content-Length', strlen((string) $request->getContent())) > (int) config('mcp.max_body_bytes', 65536)) {
            return $this->rpcError(null, self::E_INVALID_REQUEST, 'Request body too large', 413);
        }

        $body = json_decode((string) $request->getContent(), true);
        if (! is_array($body) || ! isset($body['method'])) {
            return $this->rpcError(null, self::E_PARSE, 'Invalid JSON-RPC 2.0 request', 400);
        }

        return $this->dispatch($body, $key, $scope);
    }

    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    // ── JSON-RPC dispatch ────────────────────────────────────────────────

    protected function dispatch(array $body, string $key, string $scope = 'read'): JsonResponse
    {
        $method = (string) ($body['method'] ?? '');
        $id = $body['id'] ?? null;
        $params = is_array($body['params'] ?? null) ? $body['params'] : [];
        $requestId = (string) Str::uuid();

        // Notifications (no id) get no response body except for the handshake.
        $isNotification = ! array_key_exists('id', $body);

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => (object) [],
                'tools/list' => $this->toolsList($scope),
                'tools/call' => $this->toolsCall($params, $requestId, $key, $scope),
                'notifications/initialized', 'initialized' => null,
                default => throw new McpException('Method not found: ' . $method, self::E_METHOD_NOT_FOUND),
            };
        } catch (McpException $e) {
            return $this->rpcError($id, $e->getCode(), $e->getMessage());
        } catch (Throwable) {
            // Sanitized: never expose internals to the client.
            $this->log($requestId, $method, $key, 0, 'error', 0);

            return $this->rpcError($id, self::E_INTERNAL, 'Internal error');
        }

        if ($isNotification) {
            return response()->json([], 202);
        }

        $this->log($requestId, $method, $key, 0, 'success', 0);

        return response()->json([
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ]);
    }

    protected function initialize(array $params): array
    {
        return [
            'protocolVersion' => config('mcp.protocol_version'),
            'capabilities' => [
                'tools' => (object) ['listChanged' => false],
            ],
            'serverInfo' => config('mcp.server_info'),
            'instructions' => 'BroxLab public content: Bangla tech articles and Bangladesh mobile phone prices. All tools are read-only.',
        ];
    }

    protected function toolsList(string $scope): array
    {
        $definitions = McpSchema::definitions($scope === 'write');

        // Defense in depth: write tools never listed for read-scope keys.
        if ($scope !== 'write') {
            $definitions = array_values(array_filter(
                $definitions,
                fn (array $t) => ! McpSchema::isWriteTool((string) $t['name'])
            ));
        }

        return ['tools' => $definitions];
    }

    protected function toolsCall(array $params, string $requestId, string $key, string $scope = 'read'): array
    {
        $name = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $tool = McpSchema::find($name);
        if ($tool === null) {
            throw new McpException('Unknown tool: ' . $name, self::E_METHOD_NOT_FOUND);
        }

        // Scope enforcement: write tools require the write-scope key.
        if (McpSchema::isWriteTool($name) && $scope !== 'write') {
            return [
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'error' => 'FORBIDDEN',
                        'message' => 'This tool requires a write-scope API key.',
                    ])],
                ],
                'isError' => true,
            ];
        }

        $start = microtime(true);
        $outcome = 'success';
        $count = 0;

        try {
            $data = $this->invoke($name, McpSchema::validate($name, $args));
            $count = isset($data['results']) && is_array($data['results']) ? count($data['results']) : ($data === null ? 0 : 1);
        } catch (McpException $e) {
            $outcome = 'invalid_argument';

            return [
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'error' => 'INVALID_ARGUMENT',
                        'message' => $e->getMessage(),
                    ], JSON_UNESCAPED_UNICODE)],
                ],
                'isError' => true,
            ];
        } catch (Throwable) {
            $outcome = 'error';

            return [
                'content' => [
                    ['type' => 'text', 'text' => json_encode(['error' => 'INTERNAL_ERROR', 'message' => 'The tool could not complete the request.'])],
                ],
                'isError' => true,
            ];
        } finally {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->log($requestId, 'tools/call:' . $name, $key, $duration, $outcome, $count);
        }

        if ($data === null) {
            return [
                'content' => [
                    ['type' => 'text', 'text' => json_encode(['error' => 'NOT_FOUND', 'message' => 'The requested content was not found.'], JSON_UNESCAPED_UNICODE)],
                ],
                'isError' => true,
            ];
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];
    }

    /**
     * Route a validated tool call to the read-only tool layer.
     *
     * @return array<string, mixed>|null
     */
    protected function invoke(string $name, array $args): ?array
    {
        return match ($name) {
            'get_site_info' => $this->tools->siteInfo(),
            'search_articles' => $this->tools->searchArticles(
                (string) $args['query'],
                $args['category'] ?? null,
                (int) ($args['page'] ?? 1),
                (int) ($args['limit'] ?? 10),
            ),
            'get_article' => $this->tools->getArticle($args['slug'] ?? null, $args['id'] ?? null),
            'get_latest_articles' => $this->tools->latestArticles(
                (int) ($args['limit'] ?? 10),
                $args['category'] ?? null,
            ),
            'list_categories' => $this->tools->categoryList(),
            'get_category_articles' => $this->tools->categoryArticles(
                (string) $args['category'],
                (int) ($args['page'] ?? 1),
                (int) ($args['limit'] ?? 10),
            ),
            'search_devices' => $this->tools->searchDevices(
                (string) $args['query'],
                $args['brand'] ?? null,
                (int) ($args['limit'] ?? 10),
            ),
            'get_device' => $this->tools->getDevice($args['slug'] ?? null, $args['id'] ?? null),
            'get_device_specs' => $this->tools->getDeviceSpecs($args['slug'] ?? null, $args['id'] ?? null),
            'compare_devices' => $this->tools->compareDevices(
                (string) $args['device1'],
                (string) $args['device2'],
            ),
            'search_site' => $this->tools->searchSite(
                (string) $args['query'],
                $args['type'] ?? null,
                (int) ($args['limit'] ?? 10),
            ),

            // ── Write-scope tools ──
            'create_article_draft' => $this->writeTools->createArticleDraft(
                (string) $args['title'],
                (string) $args['content'],
                $args['category_slug'] ?? null,
                is_array($args['tags'] ?? null) ? array_map('strval', $args['tags']) : [],
                $args['meta_description'] ?? null,
            ),
            'create_category' => $this->writeTools->createCategory(
                (string) $args['name'],
                $args['description'] ?? null,
            ),
            'create_device' => $this->writeTools->createDevice(
                (string) $args['brand_name'],
                (string) $args['model_name'],
                isset($args['official_price']) ? (float) $args['official_price'] : null,
                isset($args['unofficial_price']) ? (float) $args['unofficial_price'] : null,
                $args['release_date'] ?? null,
            ),
            'reply_to_comment' => $this->writeTools->replyToComment(
                (int) $args['comment_id'],
                (string) $args['content'],
            ),
            'list_pending_comments' => $this->writeTools->pendingComments(
                (int) ($args['limit'] ?? 20),
            ),
            'get_site_report' => $this->writeTools->siteReport(),
            'get_activity_logs' => $this->writeTools->activityLogs(
                (int) ($args['limit'] ?? 20),
            ),
            default => throw new McpException('Unknown tool: ' . $name, self::E_METHOD_NOT_FOUND),
        };
    }

    // ── Security helpers ─────────────────────────────────────────────────

    /**
     * Authenticate and resolve the key's scope: 'read' or 'write'.
     * Returns null when the key is missing/invalid.
     */
    protected function authenticate(Request $request): ?array
    {
        $keys = (array) config('mcp.api_keys', []);
        $writeKeys = (array) config('mcp.write_api_keys', []);
        if ($keys === [] && $writeKeys === []) {
            return null;
        }

        $header = (string) $request->bearerToken();
        if ($header === '') {
            $header = trim((string) $request->header('X-API-Key', ''));
        }

        if ($header === '') {
            return null;
        }

        foreach ($writeKeys as $valid) {
            if (hash_equals((string) $valid, $header)) {
                return ['key' => $valid, 'scope' => 'write'];
            }
        }

        foreach ($keys as $valid) {
            if (hash_equals((string) $valid, $header)) {
                return ['key' => $valid, 'scope' => 'read'];
            }
        }

        return null;
    }

    protected function rpcError(mixed $id, int $code, string $message, int $http = 200): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => ['code' => $code, 'message' => $message],
            'id' => $id,
        ], $http);
    }

    /**
     * Structured MCP log. Never logs tokens or payloads — only the hashed
     * client identity, tool name, timing and outcome.
     */
    protected function log(string $requestId, string $tool, string $key, int $duration, string $status, int $results): void
    {
        if (! config('mcp.logging')) {
            return;
        }

        info('[MCP] request_id=' . $requestId
            . ' tool=' . $tool
            . ' client=' . substr(hash('sha256', $key), 0, 8)
            . ' duration=' . $duration . 'ms'
            . ' status=' . $status
            . ' results=' . $results);
    }
}
