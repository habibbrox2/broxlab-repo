<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\RunScraperPipelineJob;
use App\Support\Scraper\ScraperRunner;
use App\Support\Scraper\ScraperStore;
use Illuminate\Support\Facades\Log;

/**
 * Public entry point for the content extraction pipeline.
 *
 * - run()      executes synchronously (admin "Run now" button, queue worker).
 * - dispatch() enqueues a run on the `scraping` queue, honouring the enable gate.
 *
 * The previous implementation dispatched the job from run(), and the job called
 * run() again — an unbounded dispatch loop once `scraper_enabled` was turned on.
 * Execution and enqueueing are now separate paths.
 */
class ScraperPipelineService
{
    public function __construct(
        protected ?ScraperRunner $runner = null,
    ) {
        $this->runner ??= app(ScraperRunner::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?string $type = null, ?int $limit = null, ?string $sourceId = null, ?bool $enrich = null): array
    {
        $limit ??= (int) config('scraper.max_items', 25);

        return $this->runner->run($type, $limit, $sourceId, $enrich);
    }

    /**
     * Enqueue a run. Returns 'disabled' without enqueueing when the gate is off.
     *
     * @return array<string, mixed>
     */
    public function dispatch(?string $type = null, ?int $limit = null, ?string $sourceId = null, bool $enrich = false): array
    {
        $limit ??= (int) config('scraper.max_items', 25);

        if (! $this->isEnabled()) {
            Log::notice('ScraperPipelineService: scraper disabled — run not queued');

            return [
                'status' => 'disabled',
                'processed' => 0,
                'added' => 0,
                'skipped' => 0,
            ];
        }

        RunScraperPipelineJob::dispatch($type, $limit, $sourceId, $enrich)
            ->onConnection('scraping')
            ->onQueue('scraping');

        return [
            'status' => 'dispatched',
            'queue' => 'scraping',
            'type' => $type,
            'limit' => $limit,
            'source_id' => $sourceId,
            'enriched' => $enrich,
        ];
    }

    /**
     * The pipeline is on when either the config flag or the legacy app setting
     * says so. Both default to off (fail closed).
     */
    public function isEnabled(): bool
    {
        if ((bool) config('scraper.enabled', false)) {
            return true;
        }

        try {
            return (bool) app(AppSettings::class)->get('scraper_enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Dashboard status: aggregate counters + last run.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $store = app(ScraperStore::class);

        return array_merge([
            'enabled' => $this->isEnabled(),
            'queue' => 'scraping',
        ], $store->stats());
    }
}
