<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Write-scope tools: drafts, categories, devices, comment replies, reports,
 * activity logs — plus scope enforcement (read keys can never write).
 */
class McpWriteToolsTest extends McpTestCase
{
    protected const WRITE_KEY = 'test-mcp-write-key-0987654321';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mcp.write_api_keys' => [self::WRITE_KEY],
            'mcp.write_enabled' => true,
        ]);

        Cache::flush();
    }

    protected function rpcWrite(array $payload)
    {
        return $this->rpc($payload, self::WRITE_KEY);
    }

    protected function callWrite(string $name, ?array $args = null)
    {
        return $this->callTool($name, $args, self::WRITE_KEY);
    }

    // ── Scope enforcement ────────────────────────────────────────────────

    public function test_read_key_cannot_see_write_tools(): void
    {
        $names = array_column($this->rpc(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'), 'name');

        $this->assertNotContains('create_article_draft', $names);
        $this->assertNotContains('get_site_report', $names);
        $this->assertContains('search_articles', $names);
    }

    public function test_write_key_sees_all_tools(): void
    {
        $names = array_column($this->rpcWrite(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'), 'name');

        $this->assertContains('create_article_draft', $names);
        $this->assertContains('get_site_report', $names);
        $this->assertContains('get_activity_logs', $names);
        $this->assertCount(18, $names); // 11 read + 7 write
    }

    public function test_read_key_calling_write_tool_is_forbidden(): void
    {
        $response = $this->callTool('create_article_draft', ['title' => 'X', 'content' => 'Y']);
        $data = $this->toolResult($response);

        $this->assertTrue($response->json('result.isError'));
        $this->assertSame('FORBIDDEN', $data['error']);
    }

    public function test_write_tools_disabled_when_no_keys(): void
    {
        config(['mcp.write_api_keys' => []]);

        // With no write keys configured, the write key itself no longer
        // authenticates at all (fail closed) — 401, not FORBIDDEN.
        $this->callTool('get_site_report', [], self::WRITE_KEY)
            ->assertStatus(401);
    }

    // ── Article drafts ───────────────────────────────────────────────────

    public function test_create_article_draft_creates_unpublished_post(): void
    {
        $data = $this->toolResult($this->callWrite('create_article_draft', [
            'title' => 'MCP Test Draft Article',
            'content' => 'This is a test draft created by the MCP integration test.',
        ]));

        $this->assertTrue($data['created']);
        $this->assertSame('draft', $data['status']);
        $this->assertFalse($data['published']);
        $this->assertNotFalse(filter_var($data['url'], FILTER_VALIDATE_URL));

        $row = DB::table('posts')->where('id', (int) $data['id'])->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->published); // MUST be a draft
    }

    public function test_draft_missing_title_is_invalid(): void
    {
        $response = $this->callWrite('create_article_draft', ['content' => 'no title']);
        $this->assertTrue($response->json('result.isError'));
        $this->assertStringContainsString('INVALID_ARGUMENT', $response->json('result.content.0.text'));
    }

    public function test_draft_is_not_visible_via_public_read_tools(): void
    {
        $data = $this->toolResult($this->callWrite('create_article_draft', [
            'title' => 'Hidden MCP Draft Uniqueness Probe',
            'content' => 'draft body',
        ]));

        // Read tools must NOT return the draft.
        $search = $this->toolResult($this->callTool('search_articles', ['query' => 'Hidden MCP Draft Uniqueness Probe']));
        foreach ($search['results'] as $r) {
            $this->assertNotSame($data['id'], $r['id']);
        }

        $response = $this->callTool('get_article', ['slug' => $data['slug']]);
        $this->assertTrue($response->json('result.isError')); // NOT_FOUND
    }

    // ── Categories ───────────────────────────────────────────────────────

    public function test_create_category_persists(): void
    {
        $name = 'MCP Test Category ' . uniqid();
        $data = $this->toolResult($this->callWrite('create_category', ['name' => $name]));

        $this->assertTrue($data['created']);
        $row = DB::table('categories')->where('id', (int) $data['id'])->first();
        $this->assertNotNull($row);
        $this->assertSame($name, $row->name);
    }

    // ── Devices ──────────────────────────────────────────────────────────

    public function test_create_device_persists(): void
    {
        $model = 'MCP Test Device ' . uniqid();
        $data = $this->toolResult($this->callWrite('create_device', [
            'brand_name' => 'TestBrand',
            'model_name' => $model,
            'official_price' => 19999,
        ]));

        $this->assertTrue($data['created']);
        $row = DB::table('mobiles')->where('id', (int) $data['id'])->first();
        $this->assertNotNull($row);
        $this->assertSame('TestBrand', $row->brand_name);
    }

    public function test_create_device_rejects_duplicates(): void
    {
        $model = 'MCP Dup Device ' . uniqid();
        $args = ['brand_name' => 'TestBrand', 'model_name' => $model];

        $this->callWrite('create_device', $args);
        $response = $this->callWrite('create_device', $args);
        $data = $this->toolResult($response);

        $this->assertTrue($response->json('result.isError'));
        $this->assertStringContainsString('already exists', $data['message']);
    }

    // ── Comment moderation ───────────────────────────────────────────────

    public function test_list_pending_comments_returns_structured_results(): void
    {
        $data = $this->toolResult($this->callWrite('list_pending_comments', ['limit' => 5]));
        $this->assertArrayHasKey('results', $data);
    }

    public function test_reply_to_missing_comment_is_not_found(): void
    {
        $response = $this->callWrite('reply_to_comment', ['comment_id' => 999999999, 'content' => 'test']);
        $data = $this->toolResult($response);

        $this->assertSame('NOT_FOUND', $data['error']);
    }

    public function test_reply_creates_pending_reply(): void
    {
        $parentId = (int) DB::table('comments')->insertGetId([
            'guest_name' => 'MCP Test Guest',
            'content' => 'Test parent comment',
            'status' => 'approved',
            'content_type' => 'post',
            'content_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $data = $this->toolResult($this->callWrite('reply_to_comment', [
                'comment_id' => $parentId,
                'content' => 'Official MCP test reply',
            ]));

            $this->assertTrue($data['created']);
            $row = DB::table('comments')->where('id', (int) $data['id'])->first();
            $this->assertSame('pending', $row->status);
        } finally {
            DB::table('comments')->whereIn('id', [$parentId, (int) ($data['id'] ?? 0)])->delete();
        }
    }

    // ── Reports & logs ───────────────────────────────────────────────────

    public function test_site_report_returns_counts(): void
    {
        $data = $this->toolResult($this->callWrite('get_site_report'));

        $this->assertArrayHasKey('posts', $data);
        $this->assertArrayHasKey('comments_pending', $data);
        $this->assertGreaterThan(0, $data['posts']['total']);
    }

    public function test_activity_logs_record_write_actions(): void
    {
        $this->callWrite('create_article_draft', [
            'title' => 'Audit Log Probe Article',
            'content' => 'audit body',
        ]);

        $data = $this->toolResult($this->callWrite('get_activity_logs', ['limit' => 10]));

        $actions = array_column($data['results'], 'action');
        $this->assertContains('mcp.article.draft_created', $actions);
    }

    public function test_write_tool_content_never_leaks_secrets(): void
    {
        $response = $this->callWrite('create_article_draft', ['title' => '', 'content' => '']);
        $content = (string) $response->getContent();

        $this->assertStringNotContainsString(self::WRITE_KEY, $content);
        $this->assertStringNotContainsString(self::KEY, $content);
    }
}
