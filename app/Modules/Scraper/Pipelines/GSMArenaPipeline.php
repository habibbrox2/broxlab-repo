<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Pipelines;

use App\Models\GSMArenaBDDeviceModel;
use App\Models\GSMArenaDeviceModel;
use App\Models\GSMArenaNewsModel;
use App\Models\ScraperModel;
use App\Modules\Scraper\Services\GSMArenaScraperService;
use App\Modules\Scraper\Services\HttpClientService;

class GSMArenaPipeline
{
    private ScraperModel $model;
    private HttpClientService $client;
    private array $config;

    public function __construct(ScraperModel $model, ?HttpClientService $client = null)
    {
        $this->model = $model;
        $this->client = $client ?? new HttpClientService();
        $this->config = require __DIR__ . '/../config/gsmarena.php';
    }

    public function run(string $type, int $maxPages, bool $testMode = false, ?callable $progress = null): array
    {
        $settings = $this->config[$type] ?? null;
        if (!$settings) {
            throw new \InvalidArgumentException("Unknown GSMArena scraper type: {$type}");
        }

        $source = $this->getSourceFor($settings);
        $sourceId = (int)($source['id'] ?? 0);
        $stats = [
            'total_scraped' => 0,
            'saved' => 0,
            'errors' => 0,
            'pages' => 0,
        ];

        $service = new GSMArenaScraperService($this->client, $settings, $type);
        $result = $service->scrapeAllPages($maxPages, function ($page, $total, $success, $items, $url, $error) use (
            &$stats,
            $type,
            $sourceId,
            $testMode,
            $progress
        ) {
            $stats['pages'] = $page;
            $stats['total_scraped'] += count($items);

            if ($success && !$testMode && $sourceId > 0) {
                foreach ($items as $item) {
                    $saved = $this->saveItem($type, $sourceId, $item);
                    if ($saved['success']) {
                        $stats['saved']++;
                    } else {
                        $stats['errors']++;
                    }
                }
            }

            if (is_callable($progress)) {
                $progress($page, $total, $success, $items, $url, $error);
            }
        });

        $stats['success'] = $result['success'];
        $stats['errors'] += $result['errors'] ?? 0;
        $status = [
            'type' => $type,
            'source_id' => $sourceId,
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats,
        ];

        $this->writeStatus($type, $status);

        return [
            'type' => $type,
            'settings' => $settings,
            'status' => $status,
        ];
    }

    public function getLastStatus(string $type): array
    {
        $file = $this->getStatusFile($type);
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getSourceFor(array $settings): array
    {
        if (!empty($settings['source_id'])) {
            $source = $this->model->getSourceById((int)$settings['source_id']);
            if ($source) {
                return $source;
            }
        }

        $url = $settings['source_url'] ?? '';
        if ($url !== '') {
            $source = $this->model->getSourceByUrl($url);
            if ($source) {
                return $source;
            }
        }

        return ['id' => 0, 'name' => $settings['label'] ?? $settings['type']];
    }

    private function saveItem(string $type, int $sourceId, array $item): array
    {
        switch ($type) {
            case 'news':
                return $this->getNewsModel()->saveNews($sourceId, $item);
            case 'devices':
                return $this->getDeviceModel()->saveDevice($sourceId, $item);
            case 'bd':
                return $this->getBdModel()->saveDevice($sourceId, $item);
            default:
                return ['success' => false, 'error' => 'Unknown type'];
        }
    }

    private function getNewsModel(): GSMArenaNewsModel
    {
        static $model;
        if ($model === null) {
            $model = new GSMArenaNewsModel($this->model->getMysqli());
        }
        return $model;
    }

    private function getDeviceModel(): GSMArenaDeviceModel
    {
        static $model;
        if ($model === null) {
            $model = new GSMArenaDeviceModel($this->model->getMysqli());
        }
        return $model;
    }

    private function getBdModel(): GSMArenaBDDeviceModel
    {
        static $model;
        if ($model === null) {
            $model = new GSMArenaBDDeviceModel($this->model->getMysqli());
        }
        return $model;
    }

    private function writeStatus(string $type, array $status): void
    {
        $file = $this->getStatusFile($type);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, json_encode($status, JSON_PRETTY_PRINT));
    }

    private function getStatusFile(string $type): string
    {
        return __DIR__ . '/../logs/gsmarena_' . $type . '_last_run.json';
    }
}
