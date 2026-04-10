<?php

declare(strict_types=1);

namespace App\Models;

class GSMArenaBDDeviceModel
{
    private ScraperModel $scraper;
    private \mysqli $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->scraper = new ScraperModel($mysqli);
        $this->mysqli = $mysqli;
    }

    public function saveDevice(int $sourceId, array $device): array
    {
        $payload = [
            'source_id' => $sourceId,
            'source_url' => $device['details_url'] ?? $device['url'] ?? '',
            'title' => $device['title'] ?? $device['name'] ?? 'BD Device',
            'price' => $this->parsePriceValue($device['price'] ?? $device['price_text'] ?? ''),
            'brand' => $device['brand'] ?? null,
            'model' => $device['model'] ?? null,
            'image_url' => $device['image'] ?? '',
            'specifications' => $device,
            'release_date' => $device['date'] ?? null,
            'status' => 'active',
        ];

        $success = $this->scraper->saveMobile($payload);

        return [
            'success' => (bool)$success,
            'error' => $success ? null : 'Failed to persist BD device'
        ];
    }

    public function getTotalCount(int $sourceId): int
    {
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM web_scraping_mobiles WHERE source_id = ?");
        $stmt->bind_param("i", $sourceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)($result['total'] ?? 0);
    }

    private function parsePriceValue(string $value): int
    {
        $numeric = preg_replace('/[^0-9]/', '', $value);
        return (int)$numeric;
    }
}
