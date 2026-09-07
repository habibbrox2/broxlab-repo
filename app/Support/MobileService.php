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

        $allowed = ['id', 'brand_name', 'model_name', 'release_date', 'created_at'];
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