<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance index for the post-view lookup: /posts/view/{slug}.
 *
 * Root cause observed locally: `postBySlug()` runs `WHERE slug = ?` against a
 * table with no slug index, so MariaDB full-scans every row — including the
 * ~167 MB of content blobs — for EVERY lookup (2+ seconds warm, more cold).
 * The same scan also penalised previousPost()/nextPost() (select p.* with an
 * ORDER BY on id) and any LIKE '%...%' search over content.
 *
 * The index is prefix-limited (100 chars) because the longest slug is 123
 * chars and avg is 61 — a full 191-byte utf8mb4 key would be near the key
 * length limit for no benefit.
 *
 * NOTE FOR THE LEGACY / PRODUCTION DATABASE: apply the same index manually
 * there (see the earlier idx_posts_* migration note):
 *
 *   ALTER TABLE posts ADD INDEX idx_posts_slug (slug (100));
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if ($this->indexExists('posts', 'idx_posts_slug')) {
            return;
        }

        DB::statement('ALTER TABLE `posts` ADD INDEX `idx_posts_slug` (`slug`(100))');
    }

    public function down(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if ($this->indexExists('posts', 'idx_posts_slug')) {
            Schema::table('posts', fn ($t) => $t->dropIndex('idx_posts_slug'));
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]);

        return ! empty($indexes);
    }
};
