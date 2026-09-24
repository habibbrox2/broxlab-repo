<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use Tests\TestCase;

/**
 * MCP protocol + security tests against the live /mcp endpoint.
 * Enables MCP via config override; no real secrets involved.
 */
abstract class McpTestCase extends TestCase
{
    protected const KEY = 'test-mcp-key-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mcp.enabled' => true,
            'mcp.api_keys' => [self::KEY],
            'mcp.rate_limit' => 0, // default off; dedicated tests enable it
            'mcp.cache_ttl' => 0,
            'mcp.logging' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return \Illuminate\Testing\TestResponse
     */
    protected function rpc(array $payload, ?string $key = self::KEY)
    {
        $headers = $key !== null ? ['Authorization' => 'Bearer ' . $key] : [];

        return $this->postJson('/mcp', $payload, $headers);
    }

    /**
     * @param  array<string, mixed>|null  $arguments
     * @return \Illuminate\Testing\TestResponse
     */
    protected function callTool(string $name, ?array $arguments = null, ?string $key = self::KEY)
    {
        return $this->rpc([
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments ?? (object) []],
        ], $key);
    }

    /** Decode the text content of a successful tools/call response. */
    protected function toolResult($response): mixed
    {
        $json = $response->json();

        return json_decode($json['result']['content'][0]['text'] ?? 'null', true);
    }
}
