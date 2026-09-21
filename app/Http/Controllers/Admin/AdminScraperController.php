<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Ai\AiProviderRepository;
use App\Support\Scraper\ScraperStore;
use App\Support\Scraper\SourceCatalog;
use App\Support\ScraperPipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminScraperController extends Controller
{
    public function __construct(
        protected SourceCatalog $catalog,
        protected ScraperStore $store,
        protected ScraperPipelineService $pipeline,
    ) {}

    public function index(): View
    {
        $sources = $this->sourcesWithState();

        return $this->view('admin.scraper.index', [
            'title' => 'Scraping Pipeline',
            'header_title' => 'Scraping Pipeline',
            'sources' => $sources,
            'stats' => $this->pipeline->status(),
            'recentRuns' => $this->store->runs(5),
            'aiProvider' => app(AiProviderRepository::class)->default(),
        ]);
    }

    public function jobs(): View
    {
        return $this->view('admin.scraper.jobs', [
            'title' => 'Scraping Jobs',
            'header_title' => 'Scraping Jobs',
            'runs' => $this->store->runs(50),
            'stats' => $this->pipeline->status(),
        ]);
    }

    public function sources(): View
    {
        return $this->view('admin.scraper.sources', [
            'title' => 'Scraping Sources',
            'header_title' => 'Scraping Sources',
            'sources' => $this->sourcesWithState(),
        ]);
    }

    public function sourcesCreate(): View
    {
        return $this->view('admin.scraper.source-create', [
            'title' => 'Add Scraping Source',
            'header_title' => 'Add Scraping Source',
            'sources' => $this->sourcesWithState(),
        ]);
    }

    public function showSource(Request $request, string $key): View
    {
        $source = $this->catalog->find($key);
        if ($source === null) {
            abort(404);
        }

        $stored = $this->store->readSource((string) $source['key']);

        return $this->view('admin.scraper.source-show', [
            'title' => 'Source: ' . $source['name'],
            'header_title' => 'Source: ' . $source['name'],
            'source' => $source,
            'snapshot' => $stored,
            'path' => $this->store->sourcePath((string) $source['key']),
        ]);
    }

    public function settings(Request $request): View
    {
        return $this->settingsView('overview', $request);
    }

    public function settingsAutomation(Request $request): View
    {
        return $this->settingsView('automation', $request);
    }

    public function settingsLimits(Request $request): View
    {
        return $this->settingsView('limits', $request);
    }

    public function settingsStorage(Request $request): View
    {
        return $this->settingsView('storage', $request);
    }

    public function logs(): View
    {
        $files = [];
        $dir = $this->store->basePath() . '/sources';
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $files[] = [
                'name' => basename($file),
                'size' => filesize($file) ?: 0,
                'modified' => date('c', filemtime($file) ?: time()),
            ];
        }
        usort($files, fn (array $a, array $b) => strcmp($b['modified'], $a['modified']));

        return $this->view('admin.scraper.logs', [
            'title' => 'Scraping Logs',
            'header_title' => 'Scraping Logs',
            'runs' => $this->store->runs(50),
            'files' => $files,
            'stats' => $this->pipeline->status(),
        ]);
    }

    /**
     * POST /admin/scraper/run — execute a pipeline run now.
     */
    public function run(Request $request): RedirectResponse
    {
        // A full-catalog run hits ~15 external sites sequentially; don't let a
        // low PHP max_execution_time abort it halfway through.
        @set_time_limit(0);

        $validated = $request->validate([
            'type' => ['nullable', 'in:news,jobs,tech'],
            'source' => ['nullable', 'string', 'max:64'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'enrich' => ['nullable', 'boolean'],
        ]);

        $result = $this->pipeline->run(
            type: $validated['type'] ?? null,
            limit: isset($validated['limit']) ? (int) $validated['limit'] : null,
            sourceId: $validated['source'] ?? null,
            enrich: (bool) ($validated['enrich'] ?? false),
        );

        $totals = $result['totals'] ?? ['fetched' => 0, 'added' => 0, 'skipped' => 0];

        return back()->with('status', sprintf(
            'Extraction run complete — %d source(s), %d fetched, %d new, %d already seen.',
            (int) ($result['sources'] ?? 0),
            (int) ($totals['fetched'] ?? 0),
            (int) ($totals['added'] ?? 0),
            (int) ($totals['skipped'] ?? 0),
        ));
    }

    /**
     * POST /admin/scraper/source/{key}/clear — drop a stored snapshot.
     */
    public function clearSource(string $key): RedirectResponse
    {
        $source = $this->catalog->find($key);
        if ($source === null) {
            abort(404);
        }

        $this->store->forgetSource((string) $source['key']);

        return back()->with('status', 'Snapshot cleared for ' . $source['name']);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sourcesWithState(): array
    {
        $sources = $this->catalog->all();
        $summaries = $this->store->summaries(array_map(fn (array $s) => (string) $s['key'], $sources));

        return array_map(function (array $source) use ($summaries): array {
            $state = $summaries[(string) $source['key']] ?? ['count' => 0, 'updated_at' => null];
            $source['stored_count'] = $state['count'];
            $source['updated_at'] = $state['updated_at'];

            return $source;
        }, $sources);
    }

    protected function settingsView(string $section, Request $request): View
    {
        return $this->view('admin.scraper.settings', [
            'title' => 'Scraping Settings',
            'header_title' => 'Scraping Settings',
            'section' => $section,
            'stats' => $this->pipeline->status(),
            'config' => [
                'enabled' => $this->pipeline->isEnabled(),
                'max_items' => (int) config('scraper.max_items'),
                'timeout' => (int) config('scraper.timeout'),
                'retries' => (int) config('scraper.retries'),
                'delay_ms' => (int) config('scraper.delay_ms'),
                'storage_path' => $this->store->basePath(),
                'seen_cap' => (int) config('scraper.seen_cap'),
                'ai_enrich' => (bool) config('scraper.ai_enrich'),
                'user_agent' => (string) config('scraper.user_agent'),
            ],
            'aiProvider' => app(AiProviderRepository::class)->default(),
        ]);
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
