<?php

declare(strict_types=1);

namespace App\Support\Mcp;

/**
 * Tool registry: JSON schemas + strict input validation for MCP tools.
 *
 * Schemas are the single source of truth for tools/list and for server-side
 * validation. Every input is length-capped and type-checked; unknown fields
 * are ignored, missing required fields raise INVALID_ARGUMENT.
 */
class McpSchema
{
    protected const MAX_LIMIT = 20;

    protected const MAX_QUERY = 200;

    /**
     * Write-scope tools: only exposed to / callable with MCP_WRITE_API_KEY.
     * Every mutation is journaled via ActivityLogger; content creation is
     * draft-only (publishing stays a human decision).
     */
    protected const WRITE_TOOLS = [
        'create_article_draft',
        'create_category',
        'create_device',
        'reply_to_comment',
        'list_pending_comments',
        'get_site_report',
        'get_activity_logs',
    ];

    /** @return array<int, array<string, mixed>> MCP tool definitions. */
    public static function definitions(bool $includeWrite = false): array
    {
        $str = fn (string $desc, int $max = self::MAX_QUERY) => ['type' => 'string', 'description' => $desc, 'maxLength' => $max];

        return [
            [
                'name' => 'get_site_info',
                'description' => 'Get basic public information about BroxLab: site name, URL, description, content types, categories and available tools.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ],
            [
                'name' => 'search_articles',
                'description' => 'Search published BroxLab articles (Bangla tech news, guides, banking, earnings).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search text (required)', 'maxLength' => 200],
                        'category' => $str('Optional category slug or name, e.g. "tech-news"', 100),
                        'page' => ['type' => 'integer', 'description' => 'Page number, default 1', 'minimum' => 1, 'maximum' => 100],
                        'limit' => ['type' => 'integer', 'description' => 'Results per page, default 10, max 20', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_article',
                'description' => 'Retrieve one published BroxLab article by slug or id, including plain-text content and related articles.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => $str('Article slug', 200),
                        'id' => $str('Article numeric id as string', 20),
                    ],
                ],
            ],
            [
                'name' => 'get_latest_articles',
                'description' => 'Return the latest published BroxLab articles.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1-20), default 10', 'minimum' => 1, 'maximum' => 20],
                        'category' => $str('Optional category slug or name', 100),
                    ],
                ],
            ],
            [
                'name' => 'list_categories',
                'description' => 'List public BroxLab content categories with article counts.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ],
            [
                'name' => 'get_category_articles',
                'description' => 'List published articles in a category.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string', 'description' => 'Category slug or name (required)', 'maxLength' => 100],
                        'page' => ['type' => 'integer', 'description' => 'Page number, default 1', 'minimum' => 1, 'maximum' => 100],
                        'limit' => ['type' => 'integer', 'description' => 'Results per page (1-20), default 10', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['category'],
                ],
            ],
            [
                'name' => 'search_devices',
                'description' => 'Search mobile phones in the BroxLab Bangladesh price catalog.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search text (required), e.g. "Galaxy" or "Redmi Note"', 'maxLength' => 200],
                        'brand' => $str('Optional exact brand name filter, e.g. "Samsung"', 100),
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1-20), default 10', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'name' => 'get_device',
                'description' => 'Retrieve one mobile phone with full public details by slug ("brand-model") or id.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => $str('Device slug, e.g. "samsung-galaxy-a16"', 200),
                        'id' => $str('Device numeric id as string', 20),
                    ],
                ],
            ],
            [
                'name' => 'get_device_specs',
                'description' => 'Return structured specifications for one mobile phone.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => $str('Device slug', 200),
                        'id' => $str('Device numeric id as string', 20),
                    ],
                ],
            ],
            [
                'name' => 'compare_devices',
                'description' => 'Factual spec-by-spec comparison of two mobile phones. No subjective ranking.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'device1' => ['type' => 'string', 'description' => 'First device slug (required)', 'maxLength' => 200],
                        'device2' => ['type' => 'string', 'description' => 'Second device slug (required)', 'maxLength' => 200],
                    ],
                    'required' => ['device1', 'device2'],
                ],
            ],
            [
                'name' => 'search_site',
                'description' => 'Unified search across BroxLab articles, devices and categories.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search text (required)', 'maxLength' => 200],
                        'type' => ['type' => 'string', 'description' => 'Optional type filter', 'enum' => ['article', 'device', 'category']],
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1-20), default 10', 'minimum' => 1, 'maximum' => 20],
                    ],
                    'required' => ['query'],
                ],
            ],

            // ── Write-scope tools (MCP_WRITE_API_KEY required) ──

            [
                'name' => 'create_article_draft',
                'description' => 'Create an article DRAFT (never auto-published). A human publishes it from the admin panel.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Article title (required)', 'maxLength' => 200],
                        'content' => ['type' => 'string', 'description' => 'Article body, plain text or simple HTML (required)', 'maxLength' => 50000],
                        'category_slug' => $str('Optional category slug or exact name', 100),
                        'tags' => ['type' => 'array', 'description' => 'Optional tag names (max 10)', 'items' => ['type' => 'string', 'maxLength' => 50], 'maxItems' => 10],
                        'meta_description' => $str('Optional SEO meta description', 300),
                    ],
                    'required' => ['title', 'content'],
                ],
            ],
            [
                'name' => 'create_category',
                'description' => 'Create a new content category.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Category name (required)', 'maxLength' => 100],
                        'description' => $str('Optional description', 300),
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'name' => 'create_device',
                'description' => 'Add a mobile phone to the Bangladesh price catalog.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'brand_name' => ['type' => 'string', 'description' => 'Brand, e.g. Samsung (required)', 'maxLength' => 100],
                        'model_name' => ['type' => 'string', 'description' => 'Model, e.g. Galaxy A16 (required)', 'maxLength' => 150],
                        'official_price' => ['type' => 'number', 'description' => 'Official BDT price (0 = unknown)', 'minimum' => 0, 'maximum' => 10000000],
                        'unofficial_price' => ['type' => 'number', 'description' => 'Unofficial BDT price (0 = unknown)', 'minimum' => 0, 'maximum' => 10000000],
                        'release_date' => ['type' => 'string', 'description' => 'Release date YYYY-MM-DD', 'maxLength' => 10],
                    ],
                    'required' => ['brand_name', 'model_name'],
                ],
            ],
            [
                'name' => 'reply_to_comment',
                'description' => 'Post an official BroxLab reply to a visitor comment (queued as pending for review).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'comment_id' => ['type' => 'integer', 'description' => 'Parent comment id (required)', 'minimum' => 1],
                        'content' => ['type' => 'string', 'description' => 'Reply text (required)', 'maxLength' => 2000],
                    ],
                    'required' => ['comment_id', 'content'],
                ],
            ],
            [
                'name' => 'list_pending_comments',
                'description' => 'List visitor comments awaiting moderation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max results (1-50), default 20', 'minimum' => 1, 'maximum' => 50],
                    ],
                ],
            ],
            [
                'name' => 'get_site_report',
                'description' => 'Content report: post counts, drafts, top viewed articles, pending comments.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) [], 'additionalProperties' => false],
            ],
            [
                'name' => 'get_activity_logs',
                'description' => 'Recent MCP activity journal (what this server created/replied, with timestamps).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Max entries (1-50), default 20', 'minimum' => 1, 'maximum' => 50],
                    ],
                ],
            ],
        ];
    }

    public static function find(string $name): ?array
    {
        foreach (self::definitions(true) as $tool) {
            if ($tool['name'] === $name) {
                return $tool;
            }
        }

        return null;
    }

    /** Is the given tool a write-scope tool? */
    public static function isWriteTool(string $name): bool
    {
        return in_array($name, self::WRITE_TOOLS, true);
    }

    /**
     * Validate arguments against the tool schema. Returns the cleaned
     * argument array or throws McpException(INVALID_ARGUMENT).
     *
     * @return array<string, mixed>
     */
    public static function validate(string $toolName, array $args): array
    {
        $tool = self::find($toolName);
        if ($tool === null) {
            throw new McpException('Unknown tool: ' . $toolName, -32601);
        }

        $schema = $tool['inputSchema'];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = $schema['required'] ?? [];

        foreach ($required as $field) {
            if (! isset($args[$field]) || (is_string($args[$field]) && trim($args[$field]) === '')) {
                throw new McpException('Missing required argument: ' . $field, -32602);
            }
        }

        $clean = [];
        foreach ($args as $key => $value) {
            if (! isset($properties[$key])) {
                continue; // Unknown fields ignored.
            }
            $prop = $properties[$key];
            $type = $prop['type'] ?? 'string';

            if ($type === 'string') {
                if (! is_string($value) && ! is_numeric($value)) {
                    throw new McpException('Argument "' . $key . '" must be a string', -32602);
                }
                $value = trim((string) $value);
                $max = (int) ($prop['maxLength'] ?? 200);
                if (mb_strlen($value) > $max) {
                    throw new McpException('Argument "' . $key . '" exceeds max length of ' . $max, -32602);
                }
                if (isset($prop['enum']) && ! in_array($value, $prop['enum'], true)) {
                    throw new McpException('Argument "' . $key . '" must be one of: ' . implode(', ', $prop['enum']), -32602);
                }
                $clean[$key] = $value;
            } elseif ($type === 'integer') {
                if (! is_numeric($value)) {
                    throw new McpException('Argument "' . $key . '" must be an integer', -32602);
                }
                $value = (int) $value;
                if (isset($prop['minimum']) && $value < $prop['minimum']) {
                    throw new McpException('Argument "' . $key . '" must be >= ' . $prop['minimum'], -32602);
                }
                if (isset($prop['maximum']) && $value > $prop['maximum']) {
                    throw new McpException('Argument "' . $key . '" must be <= ' . $prop['maximum'], -32602);
                }
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
