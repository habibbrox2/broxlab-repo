<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AutoPublishService;
use App\Support\MobilePublisher;
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
        {--type= : Restrict to one category (news|jobs|tech|mobile)}
        {--source= : Restrict to one source key}
        {--limit= : Max items per source}
        {--enrich : Run the configured AI provider over each item}
        {--no-publish : Skip the auto-publish step (snapshots only)}
        {--force : Run even when the pipeline is disabled in settings}';

    protected $description = 'Extract the latest content from the configured Bangladesh news, jobs, tech and mobile sources';

    public function handle(ScraperPipelineService $pipeline, AutoPublishService $publisher, MobilePublisher $mobilePublisher): int
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

        // Auto-publish step: turn the newly extracted items into posts.
        if ($this->option('no-publish')) {
            $this->line('Auto-publish skipped (--no-publish).');

            return self::SUCCESS;
        }

        // Publish posts for news/jobs/tech sources (skip for mobile-only runs).
        if ($type !== 'mobile') {
            if (! $publisher->isEnabled() && ! $this->option('force')) {
                $this->line('Auto-publish is off — scraped items stay as snapshots only.');
            } else {
                $published = $publisher->publishAll($type, $limit);
                $this->info(sprintf(
                    'Auto-published %d post(s) (%d already existed, %d failed) from %d source snapshot(s).',
                    $published['published'],
                    $published['skipped'],
                    $published['failed'],
                    $published['sources'],
                ));
            }
        }

        // Publish mobile items to the mobiles table.
        if ($type === null || $type === 'mobile') {
            if (! $mobilePublisher->isEnabled() && ! $this->option('force')) {
                $this->line('Mobile publishing is off (pipeline disabled).');
            } else {
                $mobilePublished = $mobilePublisher->publishAll($type, $limit);
                $this->info(sprintf(
                    'Mobile-published %d, updated %d, skipped %d, failed %d from %d source snapshot(s).',
                    $mobilePublished['published'],
                    $mobilePublished['updated'],
                    $mobilePublished['skipped'],
                    $mobilePublished['failed'],
                    $mobilePublished['sources'],
                ));
            }
        }

        return self::SUCCESS;
    }
}
