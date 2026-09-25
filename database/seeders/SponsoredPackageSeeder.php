<?php

namespace Database\Seeders;

use App\Models\SponsoredPackage;
use Illuminate\Database\Seeder;

class SponsoredPackageSeeder extends Seeder
{
    public function run(): void
    {
        if (SponsoredPackage::query()->exists()) {
            return;
        }

        $packages = [
            [
                'name' => 'Bronze Package',
                'slug' => 'bronze-package',
                'icon' => 'package',
                'description' => 'Basic sponsored content listing',
                'price' => 2000,
                'billing_period' => 'monthly',
                'tier' => 1,
                'features' => ['Sponsored content listing', 'Standard placement', 'Monthly report'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Silver Package',
                'slug' => 'silver-package',
                'icon' => 'star',
                'description' => 'Featured sponsored content with highlight',
                'price' => 5000,
                'billing_period' => 'monthly',
                'tier' => 2,
                'features' => ['Featured content placement', 'Highlight styling', 'Category page boost', 'Monthly report'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Gold Package',
                'slug' => 'gold-package',
                'icon' => 'crown',
                'description' => 'Premium featured + homepage banner',
                'price' => 10000,
                'billing_period' => 'monthly',
                'tier' => 3,
                'features' => ['Premium featured placement', 'Homepage banner slot', 'Top of category pages', 'Weekly report', 'Priority support'],
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            SponsoredPackage::query()->create($package);
        }
    }
}
