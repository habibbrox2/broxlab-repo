<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * GSMArenaBDPreset - Scraper preset for GSMArena BD
 *
 * Mobile phone specifications and prices in Bangladesh
 */
class GSMArenaBDPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'gsmarena-bd';
    }

    public function getName(): string
    {
        return 'GSMArena BD';
    }

    public function getDescription(): string
    {
        return 'Mobile phone specifications, reviews, and prices in Bangladesh.';
    }

    public function getCategory(): string
    {
        return 'Technology';
    }

    public function getIcon(): string
    {
        return '📱';
    }

    public function getType(): string
    {
        return 'static';
    }

    public function getContentType(): string
    {
        return 'product';
    }

    public function getFetchInterval(): int
    {
        return 120;
    }

    public function getDelay(): int
    {
        return 2;
    }

    public function getMaxPages(): int
    {
        return 20;
    }

    public function getPaginationType(): string
    {
        return 'path';
    }

    public function getPaginationSelector(): ?string
    {
        return '.pagination a.next';
    }

    public function getPaginationPattern(): ?string
    {
        return '/page/{page}';
    }

    public function getConfig(): array
    {
        return [
            'list_container' => '.device-list .device-item',
            'list_item' => '.device-item',
            'list_title' => '.device-name a',
            'list_link' => '.device-name a',
            'list_date' => '.release-date',
            'list_image' => '.device-image img',
            'title' => 'h1.device-title',
            'content' => '.device-specs',
            'image' => '.device-gallery img',
            'excerpt' => '.device-summary',
            'date' => '.release-date',
            'author' => '.reviewer',
            'category' => '.brand-name',
            'tags' => '.tags a',
            'price' => '.price',
            'specs' => '.specifications',
            'pagination' => '.pagination a.next'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.gsmarena.com.bd/',
            'https://www.gsmarena.com.bd/samsung',
            'https://www.gsmarena.com.bd/xiaomi',
            'https://www.gsmarena.com.bd/apple'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'gsmarena.com.bd');
    }
}
