<?php

namespace App\Support;

use App\Models\HaSupplier;

/**
 * Supplier management for Hero Alif.
 */
class HaSupplierService
{
    public function list(string $search = '', int $perPage = 50)
    {
        $q = HaSupplier::query()->orderByDesc('id');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', $term)
                    ->orWhere('shop_name', 'like', $term)
                    ->orWhere('mobile', 'like', $term);
            });
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function find(int $id): ?HaSupplier
    {
        return HaSupplier::query()->find($id);
    }

    public function create(array $data): int
    {
        return HaSupplier::query()->create([
            'name' => $data['name'],
            'shop_name' => $data['shop_name'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'opening_due' => $data['opening_due'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->id;
    }

    public function update(int $id, array $data): void
    {
        HaSupplier::query()->whereKey($id)->update([
            'name' => $data['name'],
            'shop_name' => $data['shop_name'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'opening_due' => $data['opening_due'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    public function delete(int $id): bool
    {
        return (bool) HaSupplier::query()->whereKey($id)->delete();
    }

    /**
     * Options list for purchase form dropdowns.
     */
    public function options(): array
    {
        return HaSupplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'shop_name'])
            ->map(fn ($s) => ['id' => $s->id, 'label' => $s->shop_name ? "{$s->name} ({$s->shop_name})" : $s->name])
            ->all();
    }
}
