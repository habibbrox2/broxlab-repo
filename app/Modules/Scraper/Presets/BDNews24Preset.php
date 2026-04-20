<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * BDNews24Preset - Scraper preset for BDNews24.com
 *
 * Bangladesh's leading news website
 */
class BDNews24Preset extends BasePreset
{
    public function getKey(): string
    {
        return 'bdnews24';
    }

    public function getName(): string
    {
        return 'BDNews24';
    }

    public function getDescription(): string
    {
        return 'Bangladesh\'s leading news website with comprehensive coverage of national and international news.';
    }

    public function getCategory(): string
    {
        return 'News';
    }

    public function getIcon(): string
    {
        return '📰';
    }

    public function getType(): string
    {
        return 'static';
    }

    public function getContentType(): string
    {
        return 'article';
    }

    public function getFetchInterval(): int
    {
        return 30;
    }

    public function getDelay(): int
    {
        return 3;
    }

    public function getMaxPages(): int
    {
        return 10;
    }

    public function getPaginationType(): string
    {
        return 'query';
    }

    public function getPaginationSelector(): ?string
    {
        return null;
    }

    public function getPaginationPattern(): ?string
    {
        return 'page';
    }

    public function getConfig(): array
    {
        return [
            'list_container' => 'article',
            'list_item' => 'article',
            'list_title' => 'article a',
            'list_link' => 'article a',
            'list_date' => 'time',
            'list_image' => 'article img',
            'title' => 'h1',
            'content' => 'article, main',
            'image' => 'article img',
            'excerpt' => 'p',
            'date' => 'time',
            'author' => '[rel="author"], .author',
            'category' => 'nav a, .breadcrumb a',
            'tags' => 'a[rel="tag"]',
            'pagination' => 'a[rel="next"], .pagination a.next'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.bdnews24.com/',
            'https://www.bdnews24.com/bangladesh',
            'https://www.bdnews24.com/business',
            'https://www.bdnews24.com/technology'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'bdnews24.com');
    }
}
