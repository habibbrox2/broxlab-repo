<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Port of the legacy MobileModel public-read methods (list, complete detail,
 * related). SQL stays faithful to the legacy queries.
 */
class MobileService
{
    public function list(int $page = 1, int $perPage = 12, string $search = '', string $sort = 'id', string $order = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $allowed = ['id', 'brand_name', 'model_name', 'release_date', 'created_at', 'official_price', 'unofficial_price'];
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }

        $query = DB::table('mobiles as m')
            ->select(
                'm.id',
                'm.brand_name',
                'm.model_name',
                'm.official_price',
                'm.unofficial_price',
                'm.status',
                'm.release_date',
                'm.created_at',
                'm.is_official'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('m.brand_name', 'like', "%{$search}%")
                    ->orWhere('m.model_name', 'like', "%{$search}%");
            });
        }

        $mobiles = $query->orderBy('m.'.$sort, $order)
            ->limit($perPage)
            ->offset($offset)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Batch-attach the first image per mobile (legacy fetchMobiles pattern)
        if (! empty($mobiles)) {
            $ids = array_column($mobiles, 'id');
            $images = DB::table('mobile_images')
                ->whereIn('mobile_id', $ids)
                ->orderBy('mobile_id')
                ->orderBy('id')
                ->get(['mobile_id', 'image_url']);

            $firstByMobile = [];
            foreach ($images as $img) {
                $firstByMobile[(int) $img->mobile_id] ??= $img->image_url;
            }

            foreach ($mobiles as &$mobile) {
                $mobile['image_path'] = $firstByMobile[(int) $mobile['id']] ?? null;
            }
            unset($mobile);
        }

        return $mobiles;
    }

    public function count(string $search = ''): int
    {
        $query = DB::table('mobiles');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('brand_name', 'like', "%{$search}%")
                    ->orWhere('model_name', 'like', "%{$search}%");
            });
        }

        return (int) $query->count();
    }

    /**
     * Full detail: basic + specifications + images + tags (getMobileComplete parity).
     */
    public function complete(int $id): ?array
    {
        $mobile = DB::table('mobiles')
            ->select('id', 'brand_name', 'model_name', 'is_official', 'official_price', 'unofficial_price', 'status', 'release_date', 'created_at')
            ->where('id', $id)
            ->first();

        if (! $mobile) {
            return null;
        }

        $mobile = (array) $mobile;

        $mobile['specifications'] = DB::table('mobile_specs')
            ->select('id', 'mobile_id', 'spec_key', 'spec_value')
            ->where('mobile_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $mobile['images'] = DB::table('mobile_images')
            ->select('id', 'mobile_id', 'image_url')
            ->where('mobile_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $mobile['tags'] = DB::table('content_tags as ct')
            ->join('tags as t', 't.id', '=', 'ct.tag_id')
            ->select('t.id', 't.name', 't.slug')
            ->where('ct.content_type', 'mobile')
            ->where('ct.content_id', $id)
            ->orderBy('ct.id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $mobile;
    }

    /**
     * Random related mobiles (legacy random-offset pattern).
     */
    /**
     * Distinct brands with mobile counts and a representative image.
     */
    public function brands(): array
    {
        $rows = DB::table('mobiles as m')
            ->select('m.brand_name', DB::raw('COUNT(*) as total'))
            ->whereNotNull('m.brand_name')
            ->where('m.brand_name', '<>', '')
            ->groupBy('m.brand_name')
            ->orderBy('m.brand_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Attach a representative image per brand (first phone's first image)
        foreach ($rows as &$brand) {
            $brand['image_path'] = $this->firstImageForBrand($brand['brand_name']);
        }
        unset($brand);

        return $rows;
    }

    /**
     * Phones filtered by price range, sorted by price ascending.
     */
    public function byPriceRange(?float $min = null, ?float $max = null, int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $query = DB::table('mobiles as m')
            ->select('m.id', 'm.brand_name', 'm.model_name', 'm.official_price', 'm.unofficial_price', 'm.status', 'm.release_date', 'm.is_official', 'm.created_at')
            ->orderBy('m.official_price', 'ASC');

        if ($min !== null && $min > 0) {
            $query->where(function (\Illuminate\Database\Query\Builder $q) use ($min) {
                $q->where('m.official_price', '>=', $min)
                    ->orWhere('m.unofficial_price', '>=', $min);
            });
        }
        if ($max !== null && $max > 0) {
            $query->where(function (\Illuminate\Database\Query\Builder $q) use ($max) {
                $q->where('m.official_price', '<=', $max)
                    ->orWhere('m.unofficial_price', '<=', $max);
            });
        }

        $mobiles = $query->limit($perPage)->offset($offset)->get()->map(fn ($r) => (array) $r)->all();

        if (! empty($mobiles)) {
            $firstByMobile = $this->firstImagesForIds(array_column($mobiles, 'id'));
            foreach ($mobiles as &$mobile) {
                $mobile['image_path'] = $firstByMobile[(int) $mobile['id']] ?? null;
            }
            unset($mobile);
        }

        return $mobiles;
    }

    public function priceCount(?float $min = null, ?float $max = null): int
    {
        $query = DB::table('mobiles m');
        if ($min !== null && $min > 0) {
            $query->where(function ($q) use ($min) {
                $q->where('m.official_price', '>=', $min)->orWhere('m.unofficial_price', '>=', $min);
            });
        }
        if ($max !== null && $max > 0) {
            $query->where(function ($q) use ($max) {
                $q->where('m.official_price', '<=', $max)->orWhere('m.unofficial_price', '<=', $max);
            });
        }
        return (int) $query->count();
    }

    public function priceStats(): array
    {
        return (array) DB::table('mobiles')->selectRaw('MIN(official_price) as min_price, MAX(official_price) as max_price, AVG(official_price) as avg_price, COUNT(*) as total')->first();
    }

    protected function firstImageForBrand(string $brand): ?string
    {
        return DB::table('mobiles as m')
            ->join('mobile_images as mi', 'mi.mobile_id', '=', 'm.id')
            ->where('m.brand_name', $brand)
            ->orderBy('m.id')
            ->orderBy('mi.id')
            ->value('mi.image_url');
    }

    protected function firstImagesForIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('mobile_images')
            ->whereIn('mobile_id', $ids)
            ->orderBy('mobile_id')
            ->orderBy('id')
            ->get(['mobile_id', 'image_url']);

        $first = [];
        foreach ($rows as $r) {
            $first[(int) $r->mobile_id] ??= $r->image_url;
        }
        return $first;
    }

    public function related(int $mobileId, int $limit = 3): array
    {
        $total = (int) DB::table('mobiles')->where('id', '!=', $mobileId)->count();

        if ($total < 1) {
            return [];
        }

        $randomOffset = random_int(0, max(0, $total - $limit));

        $rows = DB::table('mobiles')
            ->select('id', 'brand_name', 'model_name', 'official_price', 'unofficial_price', 'status', 'release_date')
            ->where('id', '!=', $mobileId)
            ->orderBy('id')
            ->offset($randomOffset)
            ->limit($limit)
            ->get()
            ->map(fn ($r) => (array) $r + ['type' => 'mobile'])
            ->all();

        return $rows;
    }
}