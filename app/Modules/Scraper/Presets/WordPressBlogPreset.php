<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * WordPressBlogPreset - Scraper preset for WordPress blogs
 * 
 * Generic preset for WordPress-powered blogs and news sites
 */
class WordPressBlogPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'wordpress_blog';
    }

    public function getName(): string
    {
        return 'WordPress Blog';
    }

    public function getDescription(): string
    {
        return 'Generic scraper preset for WordPress-powered blogs and news sites.';
    }

    public function getCategory(): string
    {
        return 'Blog';
    }

    public function getIcon(): string
    {
        return '📝';
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
        return 60;
    }

    public function getDelay(): int
    {
        return 2;
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
            'list_container' => '.post',
            'list_item' => '.post',
            'list_title' => '.entry-title a',
            'list_link' => '.entry-title a',
            'list_date' => '.entry-date',
            'list_excerpt' => '.entry-summary',
            'list_image' => '.post-thumbnail img',
            'title' => '.entry-title',
            'content' => '.entry-content',
            'image' => '.wp-post-image',
            'excerpt' => '.entry-summary',
            'date' => '.entry-date',
            'author' => '.byline',
            'category' => '.post-category',
            'tags' => '.post-tags a',
            'pagination' => '.nav-links .next'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://example.wordpress.com/',
            'https://example.com/blog/',
            'https://news.example.com/'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        // Simple check for common WordPress indicators
        return $this->matchesDomain($url, 'wordpress.com') ||
            str_contains($url, '/wp-content/') ||
            str_contains($url, '/wp-includes/');
    }
}
