<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\RunScraperPipelineJob;
use Illuminate\Support\Facades\Log;

class ScraperPipelineService
{
    /**
     * Dispatch a scraper pipeline run to the scraping queue.
     *
     * @param string|null $type    articles|mobiles|null (null = all configured sources)
     * @param int         $limit   max items to process per source
     * @param string|null $sourceId  specific source row id to run (null = all)
     */
    public function run(?string $type = null, int $limit = 20, ?string $sourceId = null): array
    {
        $enabled = (bool) app(AppSettings::class)->get('scraper_enabled', false);

        if (! $enabled) {
            Log::notice('ScraperPipelineService: scraper disabled in app settings — run skipped');

            return [
                'processed' => 0,
                'added' => 0,
                'skipped' => 0,
                'status' => 'disabled',
            ];
        }

        RunScraperPipelineJob::dispatch($type, $limit, $sourceId)
            ->onConnection('scraping')
            ->onQueue('scraping');

        return [
            'processed' => 0,
            'added' => 0,
            'skipped' => 0,
            'status' => 'dispatched',
            'queue' => 'scraping',
            'type' => $type,
            'limit' => $limit,
            'source_id' => $sourceId,
        ];
    }
}
