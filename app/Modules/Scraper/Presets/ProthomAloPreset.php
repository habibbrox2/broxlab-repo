<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * ProthomAloPreset - Scraper preset for Prothom Alo
 * 
 * Bangladesh's most widely read Bengali newspaper
 */
class ProthomAloPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'prothomalo';
    }

    public function getName(): string
    {
        return 'Prothom Alo';
    }

    public function getDescription(): string
    {
        return 'Bangladesh\'s most widely read Bengali newspaper with comprehensive news coverage.';
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
            'list_container' => '.story-element',
            'list_item' => '.story-element',
            'list_title' => '.story-title a',
            'list_link' => '.story-title a',
            'list_date' => '.story-time',
            'list_image' => '.story-image img',
            'title' => 'h1.story-details-headline',
            'content' => '.story-element.story-element--text',
            'image' => '.story-element.story-element--image img',
            'excerpt' => '.story-summary',
            'date' => '.story-publish-time',
            'author' => '.story-byline',
            'category' => '.story-section',
            'tags' => '.story-tags a',
            'pagination' => '.pagination a.next'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.prothomalo.com/',
            'https://www.prothomalo.com/bangladesh',
            'https://www.prothomalo.com/world',
            'https://www.prothomalo.com/sports'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'prothomalo.com');
    }
}
