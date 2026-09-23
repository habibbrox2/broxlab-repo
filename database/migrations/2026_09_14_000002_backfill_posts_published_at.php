<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill posts.published_at from created_at where it is NULL.
 *
 * The legacy home feed used COALESCE(published_at, created_at) as the sort
 * key. 56 posts carry a NULL published_at; normalising them lets the feed
 * sort directly on the indexed published_at column (see
 * 2026_09_14_000001_add_indexes_to_posts) without losing those posts at the
 * end of every ordering.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        DB::table('posts')
            ->whereNull('published_at')
            ->update(['published_at' => DB::raw('`created_at`')]);
    }

    public function down(): void
    {
        // Backfill is not reversible (we cannot know which rows were NULL);
        // leaving published_at populated is harmless.
    }
};
