<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRevenueController extends Controller
{
    public function index(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.index', [
            'title' => 'Revenue',
            'header_title' => 'Revenue',
            'appSettings' => $appSettings,
        ]);
    }

    public function ads(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.ads', [
            'title' => 'Advertising',
            'header_title' => 'Advertising',
            'appSettings' => $appSettings,
        ]);
    }

    public function sponsored(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        $packages = \App\Models\SponsoredPackage::query()
            ->withTrashed()
            ->orderBy('sort_order')
            ->orderBy('tier')
            ->get();

        return view('admin.revenue.sponsored', [
            'title' => 'Sponsored Packages',
            'header_title' => 'Sponsored Packages',
            'appSettings' => $appSettings,
            'packages' => $packages,
        ]);
    }

    public function sponsoredCreate(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.sponsored-create', [
            'title' => 'Create Sponsored Package',
            'header_title' => 'Sponsored Packages',
            'appSettings' => $appSettings,
            'package' => new \App\Models\SponsoredPackage(),
        ]);
    }

    public function sponsoredStore(Request $request): RedirectResponse
    {
        $data = $this->validatedPackage($request);
        if ($data === null) {
            return redirect('/admin/revenue/sponsored/create')
                ->withInput()
                ->with('error', 'Failed to create package. Please check the form and try again.');
        }

        \App\Models\SponsoredPackage::query()->create($data);

        return redirect('/admin/revenue/sponsored')->with('status', 'Package created successfully.');
    }

    public function sponsoredEdit(Request $request): View
    {
        $id = (int) $request->query('id', '0');
        $package = $id > 0 ? \App\Models\SponsoredPackage::query()->find($id) : null;

        if ($package === null) {
            abort(404);
        }

        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.sponsored-edit', [
            'title' => 'Edit Sponsored Package',
            'header_title' => 'Sponsored Packages',
            'appSettings' => $appSettings,
            'package' => $package,
        ]);
    }

    public function sponsoredUpdate(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', '0');
        $package = \App\Models\SponsoredPackage::query()->find($id);

        if ($package === null) {
            return redirect('/admin/revenue/sponsored')->with('error', 'Package not found.');
        }

        $data = $this->validatedPackage($request);
        if ($data === null) {
            return redirect('/admin/revenue/sponsored/edit?id=' . $id)
                ->withInput()
                ->with('error', 'Failed to update package. Please check the form and try again.');
        }

        $package->update($data);

        return redirect('/admin/revenue/sponsored')->with('status', 'Package updated successfully.');
    }

    public function sponsoredDestroy(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', '0');
        $package = \App\Models\SponsoredPackage::query()->find($id);

        if ($package !== null) {
            $package->delete();
        }

        return redirect('/admin/revenue/sponsored')->with('status', 'Package deleted.');
    }

    /** Restore a soft-deleted package back into the active list. */
    public function sponsoredRestore(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', '0');
        $package = \App\Models\SponsoredPackage::withTrashed()->find($id);

        if ($package !== null && $package->trashed()) {
            $package->restore();
        }

        return redirect('/admin/revenue/sponsored')->with('status', 'Package restored.');
    }

    /** Validate sponsored package form data; null on failure. */
    private function validatedPackage(Request $request): ?array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'billing_period' => ['required', 'in:monthly,yearly'],
            'tier' => ['required', 'integer', 'between:1,3'],
            'features' => ['nullable', 'string', 'max:4000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
        ]);

        $features = array_values(array_filter(array_map(
            static fn (string $line): string => trim($line),
            explode("\n", (string) ($validated['features'] ?? ''))
        ), static fn (string $line): bool => $line !== ''));

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'icon' => $validated['icon'] ?? 'sparkles',
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'billing_period' => $validated['billing_period'],
            'tier' => (int) $validated['tier'],
            'features' => $features === [] ? null : $features,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    public function donations(): View
    {
        $appSettings = (array) (DB::table('app_settings')->first() ?? []);

        return view('admin.revenue.donations', [
            'title' => 'Donations',
            'header_title' => 'Donations',
            'appSettings' => $appSettings,
        ]);
    }

    // The ad and gateway sub-tabs are not part of this slice — they land back on
    // their parent screen instead of producing a 500.

    public function adsAnalytics(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad analytics is not available yet.');
    }

    public function adsCampaigns(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad campaign management is not available yet.');
    }

    public function adsPlacements(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad placement management is not available yet.');
    }

    public function adsSettings(): RedirectResponse
    {
        return redirect('/admin/revenue/ads')->with('status', 'Ad settings are not available yet.');
    }

    public function donationsBkash(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The bKash gateway is not available yet.');
    }

    public function donationsNagad(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The Nagad gateway is not available yet.');
    }

    public function donationsRocket(): RedirectResponse
    {
        return redirect('/admin/revenue/donations')->with('status', 'The Rocket gateway is not available yet.');
    }
}
