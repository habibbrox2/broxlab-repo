<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ported from legacy app/Models/ContentModel.php — the category/tag CRUD used
 * by TagsCategoriesController's /admin routes (list w/ pagination + search +
 * sort, read, create, update, hard delete).
 *
 * Parity notes:
 * - Legacy deletes categories/tags with hard DELETE (no soft-delete columns on
 *   these tables), so delete mirrors that exactly.
 * - Legacy slugify prefers the banglish JS-parity helper (global function only
 *   loaded in the legacy app); the deterministic fallback regex port is used
 *   here, identical to ContentModel::slugify's fallback branch.
 */
class TagCategoryService
{
    public function __construct(protected UserProfileService $users) {}

    public const CATEGORY_SORTS = ['id', 'name', 'created_at', 'updated_at'];
    public const TAG_SORTS = ['id', 'name'];

    // ── Categories ────────────────────────────────────────────────────

    /** Port of ContentModel::getCategories. */
    public function getCategories(int $page, int $limit, string $search = '', string $sort = 'name', string $order = 'ASC'): array
    {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sort = in_array($sort, self::CATEGORY_SORTS, true) ? $sort : 'name';

        $q = DB::table('categories')
            ->select('id', 'name', 'slug', 'created_at', 'updated_at')
            ->orderBy($sort, $order)
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Port of ContentModel::getCategoriesCount. */
    public function getCategoriesCount(string $search = ''): int
    {
        $q = DB::table('categories');

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return (int) $q->count();
    }

    /** Port of ContentModel::getCategoryById. */
    public function getCategoryById(int $id): ?object
    {
        return DB::table('categories')->select('id', 'name', 'slug')->where('id', $id)->first();
    }

    /** Port of ContentModel::createCategory. Returns new id or null on failure. */
    public function createCategory(string $name, ?string $slug = null): ?int
    {
        try {
            return DB::table('categories')->insertGetId([
                'name' => $name,
                'slug' => $slug !== null && $slug !== '' ? $slug : $this->slugify($name),
            ]);
        } catch (\Throwable $e) {
            Log::warning('category create failed: '.$e->getMessage());

            return null;
        }
    }

    /** Port of ContentModel::updateCategory. */
    public function updateCategory(int $id, string $name, ?string $slug = null): bool
    {
        try {
            DB::table('categories')->where('id', $id)->update([
                'name' => $name,
                'slug' => $slug !== null && $slug !== '' ? $slug : $this->slugify($name),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('category update failed: '.$e->getMessage());

            return false;
        }
    }

    /** Port of ContentModel::deleteCategory — hard delete (legacy parity). */
    public function deleteCategory(int $id): bool
    {
        return DB::table('categories')->where('id', $id)->delete() > 0;
    }

    // ── Tags ──────────────────────────────────────────────────────────

    /** Port of ContentModel::getTags. */
    public function getTags(int $page, int $limit, string $search = '', string $sort = 'name', string $order = 'ASC'): array
    {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sort = in_array($sort, self::TAG_SORTS, true) ? $sort : 'name';

        $q = DB::table('tags')
            ->select('id', 'name', 'slug')
            ->orderBy($sort, $order)
            ->limit($limit)
            ->offset(($page - 1) * $limit);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return $q->get()->map(fn ($r) => (array) $r)->all();
    }

    /** Port of ContentModel::getTagsCount. */
    public function getTagsCount(string $search = ''): int
    {
        $q = DB::table('tags');

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return (int) $q->count();
    }

    /** Port of ContentModel::getTagById. */
    public function getTagById(int $id): ?object
    {
        return DB::table('tags')->select('id', 'name', 'slug')->where('id', $id)->first();
    }

    /** Port of ContentModel::createTag. Returns new id or null on failure. */
    public function createTag(string $name, ?string $slug = null): ?int
    {
        try {
            return DB::table('tags')->insertGetId([
                'name' => $name,
                'slug' => $slug !== null && $slug !== '' ? $slug : $this->slugify($name),
            ]);
        } catch (\Throwable $e) {
            Log::warning('tag create failed: '.$e->getMessage());

            return null;
        }
    }

    /** Port of ContentModel::updateTag. */
    public function updateTag(int $id, string $name, ?string $slug = null): bool
    {
        try {
            DB::table('tags')->where('id', $id)->update([
                'name' => $name,
                'slug' => $slug !== null && $slug !== '' ? $slug : $this->slugify($name),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('tag update failed: '.$e->getMessage());

            return false;
        }
    }

    /** Port of ContentModel::deleteTag — hard delete (legacy parity). */
    public function deleteTag(int $id): bool
    {
        return DB::table('tags')->where('id', $id)->delete() > 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Port of ContentModel::slugify — the fallback branch (legacy's banglish
     * JS-parity global helper is only available inside the legacy app).
     */
    public function slugify(string $text): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $text), '-'));

        return $slug !== '' ? $slug : 'n-a';
    }

    /**
     * Port of the legacy logActivity calls in TagsCategoriesController — an
     * activity_logs row per create/update/delete, success or failure.
     */
    public function logActivity(string $action, string $resourceType, int $resourceId, array $details, string $status = 'success'): void
    {
        try {
            $user = auth()->user();

            DB::table('activity_logs')->insert([
                'user_id' => $user?->id ?? 0,
                // The users table has no `role` column — derive the actor's role
                // from the RBAC tables (single shared definition), falling back
                // to 'admin' for unauthenticated/system contexts.
                'role' => $this->actorRole($user),
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'status' => $status,
                'ip_address' => request()?->ip() ?? '0.0.0.0',
                'user_agent' => mb_substr((string) (request()?->userAgent() ?? ''), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('tag/category activity log failed (non-fatal): '.$e->getMessage());
        }
    }

    /**
     * First RBAC role name for the actor, or 'admin' when unauthenticated
     * (legacy rows used that as the default for system-context entries).
     */
    protected function actorRole(?object $user): string
    {
        $roles = $this->users->rbacFor($user?->id)['roles'];

        return $roles[0] ?? 'admin';
    }
}
