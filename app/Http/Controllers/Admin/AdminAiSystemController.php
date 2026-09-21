<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Ai\AiClient;
use App\Support\Ai\AiProviderRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAiSystemController extends Controller
{
    public function __construct(
        protected AiProviderRepository $providers,
        protected AiClient $client,
    ) {}

    public function index(): View
    {
        return $this->view('admin.aisystem.index', [
            'title' => 'AI System',
            'header_title' => 'AI System',
            'providerCount' => $this->providers->count(),
            'defaultProvider' => $this->providers->default(),
        ]);
    }

    /**
     * Manage AI providers (OpenRouter + any OpenAI-compatible endpoint).
     */
    public function providers(Request $request): View
    {
        return $this->view('admin.aisystem.providers', [
            'title' => 'AI Providers',
            'header_title' => 'AI Providers',
            'providers' => $this->providers->allMasked(),
            'drivers' => AiProviderRepository::DRIVER_LABELS,
            'defaultBaseUrls' => AiProviderRepository::DRIVER_BASE_URLS,
            'edit' => $request->query->has('edit') ? $this->providers->find((string) $request->query->get('edit')) : null,
            'testResult' => $request->session()->get('ai_test_result'),
        ]);
    }

    public function providerStore(Request $request): RedirectResponse
    {
        $data = $this->validatedProvider($request);
        $provider = $this->providers->upsert($data);

        return redirect('/admin/aisystem/providers')
            ->with('status', 'AI provider "' . $provider['name'] . '" saved.');
    }

    public function providerUpdate(Request $request, string $id): RedirectResponse
    {
        $existing = $this->providers->find($id);
        if ($existing === null) {
            return redirect('/admin/aisystem/providers')->with('error', 'Provider not found.');
        }

        $data = $this->validatedProvider($request);
        $data['id'] = $id;
        $provider = $this->providers->upsert($data);

        return redirect('/admin/aisystem/providers')
            ->with('status', 'AI provider "' . $provider['name'] . '" updated.');
    }

    public function providerDelete(string $id): RedirectResponse
    {
        if (! $this->providers->delete($id)) {
            return redirect('/admin/aisystem/providers')->with('error', 'Provider not found.');
        }

        return redirect('/admin/aisystem/providers')->with('status', 'AI provider removed.');
    }

    public function providerDefault(string $id): RedirectResponse
    {
        if (! $this->providers->setDefault($id)) {
            return redirect('/admin/aisystem/providers')->with('error', 'Provider not found.');
        }

        return redirect('/admin/aisystem/providers')->with('status', 'Default AI provider updated.');
    }

    /**
     * POST /admin/aisystem/providers/{id}/test — live connectivity probe.
     */
    public function providerTest(string $id): RedirectResponse
    {
        $provider = $this->providers->find($id);
        if ($provider === null) {
            return redirect('/admin/aisystem/providers')->with('error', 'Provider not found.');
        }

        $result = $this->client->test($provider);

        $message = $result['ok']
            ? sprintf('%s responded in %d ms using %s (%s).', $provider['name'], $result['latency_ms'], $result['model'] ?: $provider['model'], $result['message'] ?? 'ok')
            : sprintf('%s failed: %s', $provider['name'], $result['error']);

        return redirect('/admin/aisystem/providers')
            ->with($result['ok'] ? 'status' : 'error', $message)
            ->with('ai_test_result', $result);
    }

    // The remaining AI System screens are not part of this slice — they land
    // back on the overview instead of producing a 500.

    public function analytics(): RedirectResponse
    {
        return redirect('/admin/aisystem')->with('status', 'AI analytics will be available once provider usage is tracked.');
    }

    public function chat(): RedirectResponse
    {
        return redirect('/admin/aisystem')->with('status', 'AI chat is not enabled yet.');
    }

    public function knowledge(): RedirectResponse
    {
        return redirect('/admin/aisystem')->with('status', 'Knowledge base is not enabled yet.');
    }

    public function writer(): RedirectResponse
    {
        return redirect('/admin/aisystem')->with('status', 'AI article writer is not enabled yet.');
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<string, mixed>
     */
    protected function validatedProvider(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', 'in:' . implode(',', AiProviderRepository::DRIVERS)],
            'base_url' => ['nullable', 'url', 'max:255'],
            'model' => ['nullable', 'string', 'max:160'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:32768'],
            'headers' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['enabled'] = $request->boolean('enabled');
        $validated['is_default'] = $request->boolean('is_default');

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function view(string $view, array $data): View
    {
        $appSettings = DB::table('app_settings')->first();
        $data['appSettings'] = $appSettings ? (array) $appSettings : [];

        return view($view, $data);
    }
}
