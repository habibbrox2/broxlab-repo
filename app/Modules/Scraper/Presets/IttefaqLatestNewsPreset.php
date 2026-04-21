<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * IttefaqLatestNewsPreset - Scraper preset for The Daily Ittefaq latest news feed
 */
class IttefaqLatestNewsPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'ittefaq_latest_news';
    }

    public function getName(): string
    {
        return 'Ittefaq Latest News';
    }

    public function getDescription(): string
    {
        return 'Preset for The Daily Ittefaq latest-news feed and related article pages.';
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
        return 20;
    }

    public function getDelay(): int
    {
        return 2;
    }

    public function getMaxPages(): int
    {
        return 3;
    }

    public function getPaginationType(): string
    {
        return 'none';
    }

    public function getPaginationSelector(): ?string
    {
        return null;
    }

    public function getPaginationPattern(): ?string
    {
        return null;
    }

    public function getConfig(): array
    {
        return [
            'list_container' => 'main .each.col_in.content_type_news',
            'list_item' => 'main .each.col_in.content_type_news',
            'list_title' => 'h2.title a.link_overlay',
            'list_link' => 'h2.title a.link_overlay',
            'list_date' => '.additional .time',
            'list_category' => '.additional .category',
            'list_image' => 'div.image img',
            'title' => 'h1.title',
            'content' => 'article.jw_detail_content_holder .jw_article_body, article.jw_detail_content_holder, .content_detail_content_inner, .summery, article, main, .news-details, .story-details, .details, .content',
            'image' => '.featured_image img, article img, main img, .news-details img, .story-details img, .image img',
            'excerpt' => '.summery, .summary, .excerpt, .lead, .article-summary',
            'date' => '.tts_time, .additional .time, time, .time, .date, .published, .news-date',
            'author' => '.author .name, .author, .byline, [rel="author"]',
            'category' => '.additional .category, .breadcrumb a, .category a, a[href*="/category/"]',
            'tags' => '.tags a, [rel="tag"]',
            'pagination' => 'a[rel="next"], .pagination a.next, .more a'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.ittefaq.com.bd/latest-news',
            'https://www.ittefaq.com.bd/'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'ittefaq.com.bd') && str_contains($url, '/latest-news');
    }
}
