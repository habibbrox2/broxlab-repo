<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ScraperPipelineService;
use Illuminate\Console\Command;

/**
 * Runs the content extraction pipeline from the CLI / scheduler.
 *
 *   php artisan scraper:run                     # all enabled sources (respects the enable gate)
 *   php artisan scraper:run --type=tech         # one category
 *   php artisan scraper:run --source=prothomalo # a single source
 *   php artisan scraper:run --force             # run even when auto-run is disabled
 */
class RunScraperContentCommand extends Command
{
    protected $signature = 'scraper:run
        {--type= : Restrict to one category (news|jobs|tech)}
        {--source= : Restrict to one source key}
        {--limit= : Max items per source}
        {--enrich : Run the configured AI provider over each item}
        {--force : Run even when the pipeline is disabled in settings}';

    protected $description = 'Extract the latest content from the configured Bangladesh news, jobs and tech sources';

    public function handle(ScraperPipelineService $pipeline): int
    {
        if (! $pipeline->isEnabled() && ! $this->option('force')) {
            $this->warn('Content extraction is disabled. Set CONTENT_EXTRACT_ENABLED=true or pass --force.');

            return self::SUCCESS;
        }

        $type = $this->option('type') ? (string) $this->option('type') : null;
        $source = $this->option('source') ? (string) $this->option('source') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $this->info('Running content extraction' . ($source ? " for {$source}" : ($type ? " for {$type}" : '')) . '...');

        $result = $pipeline->run($type, $limit, $source, (bool) $this->option('enrich'));

        $rows = [];
        foreach ($result['results'] ?? [] as $row) {
            $rows[] = [
                $row['name'] ?? $row['key'] ?? '—',
                $row['status'] ?? '—',
                $row['strategy'] ?? '—',
                (int) ($row['fetched'] ?? 0),
                (int) ($row['added'] ?? 0),
                (int) ($row['skipped'] ?? 0),
                substr((string) ($row['error'] ?? ''), 0, 60),
            ];
        }

        if ($rows !== []) {
            $this->table(
                ['Source', 'Status', 'Strategy', 'Fetched', 'New', 'Skipped', 'Error'],
                $rows
            );
        }

        $totals = $result['totals'] ?? ['fetched' => 0, 'added' => 0, 'skipped' => 0];
        $this->info(sprintf(
            'Done — %d source(s), %d fetched, %d new, %d already seen.',
            (int) ($result['sources'] ?? 0),
            (int) $totals['fetched'],
            (int) $totals['added'],
            (int) $totals['skipped'],
        ));

        return self::SUCCESS;
    }
}
