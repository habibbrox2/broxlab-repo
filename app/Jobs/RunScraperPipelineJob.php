<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ScraperPipelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScraperPipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly ?string $type = null,
        public readonly int $limit = 20,
        public readonly ?string $sourceId = null,
        public readonly bool $enrich = false,
    ) {}

    public function handle(ScraperPipelineService $pipeline): void
    {
        try {
            $result = $pipeline->run(
                type: $this->type,
                limit: $this->limit,
                sourceId: $this->sourceId,
                enrich: $this->enrich,
            );

            Log::info('Scraper pipeline job completed', [
                'queue' => $this->queue,
                'type' => $this->type,
                'limit' => $this->limit,
                'source_id' => $this->sourceId,
                'sources' => data_get($result, 'sources', 0),
                'fetched' => data_get($result, 'totals.fetched', 0),
                'added' => data_get($result, 'totals.added', 0),
                'skipped' => data_get($result, 'totals.skipped', 0),
            ]);
        } catch (\Throwable $e) {
            Log::error('Scraper pipeline job failed: ' . $e->getMessage(), [
                'queue' => $this->queue,
                'type' => $this->type,
                'limit' => $this->limit,
                'source_id' => $this->sourceId,
            ]);

            throw $e;
        }
    }

    public function tags(): array
    {
        return array_values(array_filter([
            'scraping',
            $this->type ?: 'scraping:all',
            $this->sourceId ? 'source_id:' . $this->sourceId : '',
            $this->enrich ? 'scraping:enriched' : '',
        ]));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunScraperPipelineJob permanently failed: ' . $exception->getMessage(), [
            'type' => $this->type,
            'limit' => $this->limit,
            'source_id' => $this->sourceId,
        ]);
    }
}
