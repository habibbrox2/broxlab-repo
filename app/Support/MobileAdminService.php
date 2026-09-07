<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Port of legacy MobileModel admin CRUD operations.
 *
 * Parity notes:
 * - Status normalization matches legacy normalizeMobileStatus().
 * - Specifications use DELETE + reinsert (same as legacy updateSpecifications).
 * - Images use DELETE + reinsert (same as legacy updateImages).
 * - Delete is a hard delete with transaction (mobile_specs, mobile_images, mobiles).
 * - Activity logging with 'admin' role for all CRUD operations.
 */
class MobileAdminService
{
    public const STATUSES = ['official', 'unofficial', 'both'];
    public const SORTS = ['id', 'brand_name', 'model_name', 'official_price', 'status', 'created_at', 'is_official'];

    public function __construct(
        protected PostTaxonomy $taxonomy
    ) {}

    // ── Status normalization (parity with legacy normalizeMobileStatus) ──

    /**
     * Normalize mobile status from raw input + is_official flag.
     * Same logic as legacy normalizeMobileStatus().
     */
    public function normalizeStatus(string $rawStatus, ?int $isOfficial = null): ?string
    {
        $status = strtolower(trim($rawStatus));

        $map = [
            'official' => 'official',
            'unofficial' => 'unofficial',
            'both' => 'both',
            'active' => 'official',
            'inactive' => 'unofficial',
            'published' => 'official',
            'draft' => 'unofficial',
            '1' => 'official',
            '0' => 'unofficial',
            'true' => 'official',
            'false' => 'unofficial',
        ];

        if (isset($map[$status])) {
            return $map[$status];
        }

        if ($status === '' && $isOfficial !== null) {
            return (int) $isOfficial === 1 ? 'official' : 'unofficial';
        }

        return null;
    }

    // ── Reads ─────────────────────────────────────────────────────────

    /**
     * Get paginated, searched, sorted mobiles (admin list).
     * Port of MobileModel::getMobiles().
     */
    public function getMobiles(int $page, int $limit, string $search = '', string $sort = 'brand_name', string $order = 'ASC', array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'brand_name';

        $query = DB::table('mobiles')
            ->select('id', 'brand_name', 'model_name', 'official_price', 'unofficial_price', 'status', 'release_date', 'is_official', 'created_at')
            ->orderBy($sort, $order)
            ->skip($offset)
            ->take($limit);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', '%' . $search . '%')
                    ->orWhere('model_name', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_official']) && $filters['is_official'] !== '') {
            $query->where('is_official', (int) $filters['is_official']);
        }

        $result = $query->get()->map(fn($x) => (array) $x)->all();

        // Attach first image per mobile (batch query, no N+1)
        if (!empty($result)) {
            $mobileIds = array_column($result, 'id');
            $images = $this->getMobileImages($mobileIds);
            $firstImageByMobile = [];
            foreach ($images as $img) {
                if (!isset($firstImageByMobile[$img['mobile_id']])) {
                    $firstImageByMobile[$img['mobile_id']] = $img['image_url'];
                }
            }
            foreach ($result as &$mobile) {
                $mobile['image_path'] = $firstImageByMobile[$mobile['id']] ?? null;
            }
        }

        return $result;
    }

    /**
     * Get total count with optional search and filters.
     * Port of MobileModel::getMobilesCount().
     */
    public function getMobilesCount(string $search = '', array $filters = []): int
    {
        $query = DB::table('mobiles');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', '%' . $search . '%')
                    ->orWhere('model_name', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_official']) && $filters['is_official'] !== '') {
            $query->where('is_official', (int) $filters['is_official']);
        }

        return (int) $query->count();
    }

    /**
     * Get mobile by ID with basic fields.
     * Port of MobileModel::fetchMobileById().
     */
    public function getMobileById(int $id): ?object
    {
        $row = DB::table('mobiles')->where('id', $id)->first();
        return $row;
    }

    /**
     * Get all specification keys (distinct).
     * Port of MobileModel::fetchAllSpecKeys().
     */
    public function getAllSpecKeys(): array
    {
        return DB::table('mobile_specs')
            ->distinct()
            ->orderBy('spec_key')
            ->pluck('spec_key')
            ->map(fn($k) => ['spec_key' => $k])
            ->all();
    }

    /**
     * Get specifications for a mobile.
     * Port of MobileModel::fetchSpecsByMobileId().
     */
    public function getSpecsByMobileId(int $id): array
    {
        return DB::table('mobile_specs')
            ->where('mobile_id', $id)
            ->orderBy('id')
            ->get(['spec_key', 'spec_value'])
            ->map(fn($x) => (array) $x)
            ->all();
    }

    /**
     * Get images for a mobile.
     * Port of MobileModel::fetchImagesByMobileId().
     */
    public function getImagesByMobileId(int $id): array
    {
        return DB::table('mobile_images')
            ->where('mobile_id', $id)
            ->orderBy('id')
            ->get(['id', 'image_url'])
            ->map(fn($x) => (array) $x)
            ->all();
    }

    /**
     * Get images for multiple mobiles (batch query).
     */
    public function getMobileImages(array $mobileIds): array
    {
        if (empty($mobileIds)) {
            return [];
        }

        return DB::table('mobile_images')
            ->whereIn('mobile_id', $mobileIds)
            ->orderBy('mobile_id')
            ->orderBy('id')
            ->get(['mobile_id', 'image_url'])
            ->map(fn($x) => (array) $x)
            ->all();
    }

    /**
     * Get selected tags for a mobile.
     */
    public function getMobileTags(int $mobileId): array
    {
        return $this->taxonomy->tagsForContent('mobile', $mobileId);
    }

    // ── Writes ────────────────────────────────────────────────────────

    /**
     * Insert a new mobile.
     * Port of MobileModel::insertMobile().
     */
    public function insertMobile(
        string $brandName,
        string $modelName,
        float $officialPrice,
        float $unofficialPrice,
        string $status,
        string $releaseDate,
        int $isOfficial = 0
    ): int {
        try {
            $id = DB::table('mobiles')->insertGetId([
                'brand_name' => $brandName,
                'model_name' => $modelName,
                'official_price' => $officialPrice,
                'unofficial_price' => $unofficialPrice,
                'status' => $status,
                'release_date' => $releaseDate,
                'is_official' => $isOfficial,
            ]);

            return (int) $id;
        } catch (\Throwable $e) {
            Log::warning('MobileAdminService: insertMobile failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update a mobile's basic fields.
     * Port of MobileModel::updateMobile().
     */
    public function updateMobile(
        int $id,
        string $brandName,
        string $modelName,
        float $officialPrice,
        float $unofficialPrice,
        string $status,
        string $releaseDate,
        int $isOfficial = 0
    ): bool {
        try {
            $affected = DB::table('mobiles')
                ->where('id', $id)
                ->update([
                    'brand_name' => $brandName,
                    'model_name' => $modelName,
                    'official_price' => $officialPrice,
                    'unofficial_price' => $unofficialPrice,
                    'status' => $status,
                    'release_date' => $releaseDate,
                    'is_official' => $isOfficial,
                ]);

            return $affected > 0;
        } catch (\Throwable $e) {
            Log::warning('MobileAdminService: updateMobile failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Insert/update specifications (DELETE + reinsert pattern).
     * Port of MobileModel::updateSpecifications().
     */
    public function updateSpecifications(int $mobileId, array $keys, array $values): void
    {
        DB::table('mobile_specs')->where('mobile_id', $mobileId)->delete();

        if (empty($keys)) {
            return;
        }

        $placeholders = [];
        $params = [];

        foreach ($keys as $i => $key) {
            $placeholders[] = "(?, ?, ?)";
            $params[] = $mobileId;
            $params[] = $key;
            $params[] = $values[$i] ?? '';
        }

        $sql = "INSERT INTO mobile_specs (mobile_id, spec_key, spec_value) VALUES " . implode(', ', $placeholders);

        DB::getPdo()->prepare($sql)->execute($params);
    }

    /**
     * Insert images for a mobile.
     * Port of MobileModel::insertImages().
     */
    public function insertImages(int $mobileId, array $imageUrls): void
    {
        foreach ($imageUrls as $url) {
            DB::table('mobile_images')->insert([
                'mobile_id' => $mobileId,
                'image_url' => $url,
            ]);
        }
    }

    /**
     * Update images (DELETE + reinsert pattern).
     * Port of MobileModel::updateImages().
     */
    public function updateImages(int $mobileId, array $imageUrls): void
    {
        DB::table('mobile_images')->where('mobile_id', $mobileId)->delete();
        $this->insertImages($mobileId, $imageUrls);
    }

    /**
     * Delete specific images by ID.
     * Port of MobileModel::deleteImages().
     */
    public function deleteImages(array $imageIds): bool
    {
        if (empty($imageIds)) {
            return false;
        }

        DB::table('mobile_images')
            ->whereIn('id', $imageIds)
            ->delete();

        return true;
    }

    /**
     * Delete a mobile with all related data (transaction).
     * Port of MobileModel::deleteMobile().
     */
    public function deleteMobile(int $id): bool
    {
        try {
            DB::beginTransaction();

            DB::table('mobile_specs')->where('mobile_id', $id)->delete();
            DB::table('mobile_images')->where('mobile_id', $id)->delete();
            DB::table('content_tags')->where(['content_type' => 'mobile', 'content_id' => $id])->delete();
            DB::table('content_categories')->where(['content_type' => 'mobile', 'content_id' => $id])->delete();

            $deleted = DB::table('mobiles')->where('id', $id)->delete();

            DB::commit();

            return $deleted > 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('MobileAdminService: deleteMobile failed: ' . $e->getMessage());
            return false;
        }
    }

    // ── Activity logging ─────────────────────────────────────────────

    /**
     * Log mobile CRUD activity.
     */
    public function logActivity(string $action, int $mobileId, array $details, string $status = 'success'): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => auth()->id() ?? 0,
                'role' => 'admin',
                'action' => $action,
                'resource_type' => 'mobile',
                'resource_id' => $mobileId,
                'status' => $status,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MobileAdminService: logActivity failed (non-fatal): ' . $e->getMessage());
        }
    }
}
