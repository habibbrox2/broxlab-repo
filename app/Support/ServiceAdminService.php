<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ServiceAdminService
 *
 * Service layer for admin services CRUD — mirrors the legacy
 * ServicesController admin paths under /admin/services/* so those
 * routes can be served by Laravel through the strangler-fig bridge.
 *
 * Legacy source: app/Controllers/ServicesController.php
 */
class ServiceAdminService
{
    // ---------- Helpers ----------

    /** Normalize from legacy `name` column on the read side to `service_title` for the form. */
    public static function serviceName(?string $name): string
    {
        return $name ?? '';
    }

    /** Map legacy `name` to `title` for the create/edit payload. */
    public static function mapLegacyNameToTitle(?string $name): string
    {
        return $name ?? '';
    }

    /** Build a slug from a service title (legacy behavior: strtolower + dashes). */
    public static function slugify(string $title): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', trim($title));
        return trim($slug, '-') ?: 'service';
    }

    /** Check whether a slug is already taken (excluding an optional id). */
    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = DB::table('services')->where('slug', $slug)->where('deleted_at', null);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    // ---------- List ----------

    /**
     * Get paginated services list.
     *
     * Legacy source: ServicesController::servicesList()
     */
    public static function getServicesList(
        int $page = 1,
        int $perPage = 15,
        string $sort = 'id',
        string $order = 'DESC',
        string $search = ''
    ): array {
        $sort = in_array($sort, ['id', 'name', 'created_at'], true) ? $sort : 'id';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $offset = max(0, ($page - 1) * $perPage);

        $query = DB::table('services')
            ->select(
                'services.id',
                'services.name',
                'services.description',
                'services.slug',
                'services.status',
                'services.created_at',
            )
            ->where('deleted_at', null);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('slug', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $services = $query
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => self::serviceName($row->name),
                'name' => $row->name,
                'description' => $row->description ?? '',
                'slug' => $row->slug,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'image' => '',
                'category' => '',
            ])
            ->all();

        $lastPage = (int) max(1, ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = max(0, ($page - 1) * $perPage);
        $from = $total === 0 ? 0 : $offset + 1;
        $to = min($offset + $perPage, $total);

        return [
            'services' => $services,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'search' => $search,
                'status' => '',
                'sort' => $sort,
                'order' => $order,
            ],
            'sort' => $sort,
            'order' => $order,
            'search' => $search,
        ];
    }

    // ---------- View ----------

    /**
     * Get a single service by id.
     */
    public static function getServiceById(int $id): ?array
    {
        $row = DB::table('services')
            ->select('id', 'name', 'description', 'slug', 'category', 'icon', 'status', 'is_premium', 'price', 'created_at')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$row) {
            return null;
        }

        $images = DB::table('service_images')
            ->select('id', 'image_path', 'thumbnail_path', 'alt_text', 'caption', 'is_featured', 'display_order')
            ->where('service_id', $id)
            ->where('deleted_at', null)
            ->orderBy('display_order')
            ->get()
            ->all();

        return [
            'id' => $row->id,
            'title' => self::serviceName($row->name),
            'name' => $row->name,
            'description' => $row->description ?? '',
            'slug' => $row->slug,
            'category' => $row->category ?? '',
            'icon' => $row->icon ?? '',
            'status' => $row->status,
            'is_premium' => (bool) ($row->is_premium ?? false),
            'price' => $row->price,
            'created_at' => $row->created_at,
            'images' => $images,
        ];
    }

    // ---------- Create ----------

    /**
     * Create a new service.
     *
     * Legacy source: ServicesController::addService()
     */
    public static function createService(array $data, ?User $admin = null): array
    {
        $errors = [];

        $title = trim($data['service_title'] ?? '');
        if ($title === '') {
            $errors['service_title'] = 'Service title is required';
        }

        $description = trim($data['service_description'] ?? '');
        if ($description === '') {
            $errors['service_description'] = 'Service description is required';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        DB::beginTransaction();
        try {
            $slug = self::slugify($title);
            $baseSlug = $slug;
            $counter = 1;
            while (self::slugExists($slug)) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $now = now();
            $serviceId = DB::table('services')->insertGetId([
                'name' => $title,
                'description' => $description,
                'slug' => $slug,
                'form_fields' => $data['service_form_template_json'] ?? null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Images (legacy behavior: one row per image URL line)
            $imagesRaw = trim($data['service_images'] ?? '');
            if ($imagesRaw !== '') {
                $imageUrls = explode("\n", $imagesRaw);
                $order = 0;
                foreach ($imageUrls as $url) {
                    $url = trim($url);
                    if ($url === '') {
                        continue;
                    }
                    DB::table('service_images')->insert([
                        'service_id' => $serviceId,
                        'image_path' => $url,
                        'alt_text' => $title,
                        'display_order' => $order++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Activity log (legacy behavior + ActivityController compat)
            self::logActivity($admin, $serviceId, 'services', 'insert', 'Service inserted successfully');

            DB::commit();

            return [
                'service_id' => $serviceId,
                'status' => 'Service inserted successfully',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Update ----------

    /**
     * Update an existing service.
     *
     * Legacy source: ServicesController::editService()
     */
    public static function updateService(int $id, array $data, ?User $admin = null): array
    {
        // Pull current values first (legacy behavior)
        $current = DB::table('services')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$current) {
            return ['error' => 'Service not found'];
        }

        $errors = [];

        $title = trim($data['service_title'] ?? '');
        if ($title === '') {
            $errors['service_title'] = 'Service title is required';
        }

        $description = trim($data['service_description'] ?? '');
        if ($description === '') {
            $errors['service_description'] = 'Service description is required';
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        // Slug is editable in the legacy form; re-validate uniqueness
        $slug = self::slugify($title);
        $baseSlug = $slug;
        $counter = 1;
        while (self::slugExists($slug, $id)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        DB::beginTransaction();
        try {
            $now = now();
            DB::table('services')
                ->where('id', $id)
                ->where('deleted_at', null)
                ->update([
                    'name' => $title,
                    'description' => $description,
                    'slug' => $slug,
                    'form_fields' => $data['service_form_template_json'] ?? null,
                    'updated_at' => $now,
                ]);

            // Image handling: legacy example-image uploads call updateServiceImages();
            // here we only replace if new URLs were submitted.
            $imagesRaw = trim($data['service_images'] ?? '');
            if ($imagesRaw !== '') {
                // Remove old
                DB::table('service_images')
                    ->where('service_id', $id)
                    ->where('deleted_at', null)
                    ->delete();

                $imageUrls = explode("\n", $imagesRaw);
                $order = 0;
                foreach ($imageUrls as $url) {
                    $url = trim($url);
                    if ($url === '') {
                        continue;
                    }
                    DB::table('service_images')->insert([
                        'service_id' => $id,
                        'image_path' => $url,
                        'alt_text' => $title,
                        'display_order' => $order++,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Activity log
            self::logActivity($admin, $id, 'services', 'update', 'Service updated successfully');

            DB::commit();

            return [
                'service_id' => $id,
                'status' => 'Service updated successfully',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Delete ----------

    /**
     * Soft-delete a service and its images.
     *
     * Legacy source: ServicesController::deleteService()
     */
    public static function deleteService(int $id, ?User $admin = null): array
    {
        $service = DB::table('services')
            ->where('id', $id)
            ->where('deleted_at', null)
            ->first();

        if (!$service) {
            return ['error' => 'Service not found'];
        }

        DB::beginTransaction();
        try {
            $now = now();

            // Soft-delete images first
            DB::table('service_images')
                ->where('service_id', $id)
                ->update(['deleted_at' => $now]);

            // Soft-delete the service
            DB::table('services')
                ->where('id', $id)
                ->update(['deleted_at' => $now]);

            // Activity log
            self::logActivity($admin, $id, 'services', 'delete', 'Service deleted permanently');

            DB::commit();

            return [
                'service_id' => $id,
                'status' => 'Service deleted successfully',
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // ---------- Internal ----------

    private static function logActivity(?User $admin, int $id, string $domain, string $action, string $message): void
    {
        if ($admin === null || !$admin->id) {
            return;
        }

        if (class_exists('\App\Services\ActivityService')) {
            try {
                \App\Services\ActivityService::log(
                    $admin->id,
                    $admin->username ?? 'admin',
                    $domain,
                    $action,
                    $message,
                    $id
                );
            } catch (\Throwable) {
                // Activity logging must not block CRUD
            }
            return;
        }

        // Fallback direct insert (matches legacy activity logger)
        try {
            DB::table('activity_log')->insert([
                'user_id' => $admin->id,
                'username' => $admin->username ?? 'admin',
                'activity' => $message,
                'domain' => $domain,
                'action' => $action,
                'item_id' => $id,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Table may not exist in test/database
        }
    }
}
