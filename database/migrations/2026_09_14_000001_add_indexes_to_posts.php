<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the public home feed (HomeFeedService).
 *
 * Root cause observed locally: every home-page request full-scanned the
 * posts table (13k rows, ~167 MB of content blobs) because `published`,
 * `published_at` and `created_at` carry no index in the legacy schema.
 * `COUNT(*) WHERE published = 1` alone took ~0.7 s and the unified feed
 * sort pushed total page load past 3-5 s.
 *
 * NOTE FOR THE LEGACY / PRODUCTION DATABASE: this migration only alters the
 * Laravel-managed database. If the live site still reads the original legacy
 * database (see migration/PLAN.md "Database strategy"), apply the same
 * indexes there manually:
 *
 *   ALTER TABLE posts
 *       ADD INDEX idx_posts_published_published_at (published, published_at),
 *       ADD INDEX idx_posts_created_at (created_at);
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        $this->addIndexIfMissing('posts', ['published', 'published_at'], 'idx_posts_published_published_at');
        $this->addIndexIfMissing('posts', ['published', 'created_at'], 'idx_posts_published_created_at');
        $this->addIndexIfMissing('posts', ['created_at'], 'idx_posts_created_at');
    }

    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        foreach ([
            'idx_posts_published_published_at' => ['published', 'published_at'],
            'idx_posts_published_created_at' => ['published', 'created_at'],
            'idx_posts_created_at' => ['created_at'],
        ] as $name => $columns) {
            if ($this->indexExists('posts', $name)) {
                Schema::table('posts', fn ($t) => $t->dropIndex($name));
            }
        }
    }

    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        // on MySQL/MariaDB the index build can take a while on large tables;
        // that is expected one-time cost.
        Schema::table($table, fn ($t) => $t->index($columns, $name));
    }

    private function indexExists(string $table, string $name): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

        return ! empty($indexes);
    }
};
