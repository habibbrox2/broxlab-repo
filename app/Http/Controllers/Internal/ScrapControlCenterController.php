<?php

declare(strict_types=1);

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Support\ScraperPipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Internal API endpoint invoked by the standalone cron script
 * (scripts/cron/scraper-runpipeline.php) to run the scraper pipeline.
 *
 * The endpoint is protected by a shared secret token configured via
 * SCRAPER_PIPELINE_CRON_TOKEN in .env. When no token is set, the
 * endpoint refuses all calls in production (fail-closed).
 */
class ScrapControlCenterController extends Controller
{
    public function __construct(
        protected ScraperPipelineService $pipeline,
    ) {}

    /**
     * Run the scraper pipeline.
     *
     * Accepts JSON: { "limit": 20, "type": "articles"|"mobiles" }
     *
     * The type mapping bridges the cron script's vocabulary ("articles", "mobiles")
     * to the scraper config's content types ("news|jobs|tech|mobile").
     */
    public function cronRunPipeline(Request $request): JsonResponse
    {
        if (! $this->tokenIsValid($request)) {
            return response()->json([
                'error' => 'Invalid token',
                'message' => 'Authentication failed',
            ], 401);
        }

        $limit = (int) ($request->input('limit', 20));
        $limit = max(1, min($limit, 200));

        /** @var string|null $typeRaw */
        $typeRaw = $request->input('type');
        $type = $this->mapType($typeRaw);

        try {
            $exitCode = Artisan::call('scraper:run', [
                '--type' => $type,
                '--limit' => $limit,
                '--no-publish' => (bool) $request->input('no_publish', false),
            ]);

            $output = Artisan::output();

            // Read the pipeline status report (safe against missing keys).
            $result = $this->pipeline->status(false);
            $totals = $result['totals'] ?? ['added' => 0, 'skipped' => 0, 'failed' => 0];
            $processed = $totals['added'] + $totals['skipped'] ?? $totals['added'];

            return response()->json([
                'status' => $exitCode === 0 ? 'success' : 'error',
                'processed' => $processed,
                'added' => $totals['added'] ?? 0,
                'skipped' => $totals['skipped'] ?? 0,
                'type' => $type ?? 'all',
                'limit' => $limit,
                'message' => $exitCode === 0
                    ? 'Pipeline executed successfully'
                    : 'Pipeline completed with errors: ' . trim($output),
            ], $exitCode === 0 ? 200 : 500);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'type' => $type ?? 'all',
                'limit' => $limit,
                'message' => 'Pipeline failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate the X-Scraper-Cron-Token header against SCRAPER_PIPELINE_CRON_TOKEN.
     */
    protected function tokenIsValid(Request $request): bool
    {
        $expected = trim((string) config('scraper.cron_token', ''));

        // Fail-closed: no token configured means the endpoint is off-limits.
        if ($expected === '') {
            return false;
        }

        $provided = trim((string) $request->header('X-Scraper-Cron-Token', ''));

        return hash_equals($expected, $provided);
    }

    /**
     * Map the cron script's type vocabulary to the scraper config types.
     */
    protected function mapType(?string $typeRaw): ?string
    {
        return match (strtolower(trim((string) $typeRaw))) {
            'articles' => null,    // articles = all news/jobs/tech sources
            'mobiles'  => 'mobile',
            ''         => null,
            default    => $typeRaw, // allow direct type passthrough
        };
    }
}
