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
            'list_container' => '.news-list .news-item',
            'list_item' => '.news-item',
            'list_title' => '.title a',
            'list_link' => '.title a',
            'list_date' => '.time',
            'list_image' => '.img img',
            'title' => 'h1.article-title',
            'content' => '.article-body',
            'image' => '.article-featured-image img',
            'excerpt' => '.article-excerpt',
            'date' => '.article-time',
            'author' => '.author-name',
            'category' => '.category-name',
            'tags' => '.tags a',
            'pagination' => '.pagination a.next'
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
