<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy `StatisticsModel` (same four counts, same tables).
 * Uses the query builder so counts behave identically to the legacy SQL.
 */
class SiteStatistics
{
    public function totalPosts(): int
    {
        return (int) DB::table('posts')->where('published', 1)->count();
    }

    public function totalUsers(): int
    {
        return (int) DB::table('users')->where('status', 'active')->count();
    }

    public function totalMobiles(): int
    {
        return (int) DB::table('mobiles')->count();
    }

    public function totalComments(): int
    {
        return (int) DB::table('comments')->count();
    }

    public function all(): array
    {
        return [
            'total_posts' => $this->totalPosts(),
            'total_users' => $this->totalUsers(),
            'total_mobiles' => $this->totalMobiles(),
            'total_comments' => $this->totalComments(),
        ];
    }
}