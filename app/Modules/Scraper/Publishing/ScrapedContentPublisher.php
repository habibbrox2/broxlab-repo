<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Publishing;

use App\Models\ScraperModel;

class ScrapedContentPublisher
{
    private \mysqli $mysqli;
    private ScraperModel $scraperModel;
    private \ContentModel $contentModel;
    private \MobileModel $mobileModel;

    public function __construct(\mysqli $mysqli, ScraperModel $scraperModel, \ContentModel $contentModel, \MobileModel $mobileModel)
    {
        $this->mysqli = $mysqli;
        $this->scraperModel = $scraperModel;
        $this->contentModel = $contentModel;
        $this->mobileModel = $mobileModel;
    }

    public function publishForSource(int $sourceId, array $options = []): array
    {
        $maxArticles = isset($options['max_articles']) ? max(1, (int)$options['max_articles']) : 20;
        $maxMobiles = isset($options['max_mobiles']) ? max(1, (int)$options['max_mobiles']) : 20;

        $articles = $this->publishArticlesForSource($sourceId, $maxArticles);
        $mobiles = $this->publishMobilesForSource($sourceId, $maxMobiles);

        return [
            'posts_published' => (int)($articles['posts_published'] ?? 0),
            'posts_skipped' => (int)($articles['posts_skipped'] ?? 0),
            'mobiles_published' => (int)($mobiles['mobiles_published'] ?? 0),
            'mobiles_skipped' => (int)($mobiles['mobiles_skipped'] ?? 0),
            'errors' => array_merge($articles['errors'] ?? [], $mobiles['errors'] ?? []),
        ];
    }

    private function publishArticlesForSource(int $sourceId, int $limit): array
    {
        $sourceId = max(1, (int)$sourceId);
        $source = $this->scraperModel->getSourceById($sourceId);
        $categoryId = (int)($source['category_id'] ?? 0);

        $rows = $this->scraperModel->getPublishableArticlesForSource($sourceId, $limit);
        $postsPublished = 0;
        $postsSkipped = 0;
        $errors = [];

        foreach ($rows as $article) {
            $articleId = (int)($article['id'] ?? 0);
            $title = trim((string)($article['seo_title'] ?? $article['title'] ?? ''));
            $content = (string)($article['content'] ?? '');
            $author = trim((string)($article['author'] ?? '')) ?: 'Auto Scraper';

            if ($articleId <= 0 || ($title === '' && trim($content) === '')) {
                $postsSkipped++;
                continue;
            }

            $slug = $this->contentModel->generateUniquePermalink($title);
            $postId = (int)$this->contentModel->createPost($title !== '' ? $title : 'Untitled', $content, $author, $slug, 1, null);
            if ($postId <= 0) {
                $errors[] = "Failed to create post for scraped article {$articleId}";
                continue;
            }

            $this->contentModel->markPostPublished($postId);
            if ($categoryId > 0) {
                $this->contentModel->setPostCategoryId($postId, $categoryId);
                $this->contentModel->attachCategoriesToContent('post', $postId, [$categoryId]);
            }

            $tagIds = $this->resolveTagIdsFromArticle($article);
            if ($tagIds !== []) {
                $this->contentModel->attachTagsToContent('post', $postId, $tagIds);
            }

            $ok = $this->scraperModel->markArticlePublished($articleId, $postId);
            if (!$ok) {
                $errors[] = "Failed to mark scraped article {$articleId} as published (post_id={$postId})";
                continue;
            }

            $postsPublished++;
        }

        return [
            'posts_published' => $postsPublished,
            'posts_skipped' => $postsSkipped,
            'errors' => $errors,
        ];
    }

    private function publishMobilesForSource(int $sourceId, int $limit): array
    {
        $sourceId = max(1, (int)$sourceId);
        $rows = $this->scraperModel->getPublishableMobilesForSource($sourceId, $limit);
        $mobilesPublished = 0;
        $mobilesSkipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $scrapedId = (int)($row['id'] ?? 0);
            $brand = trim((string)($row['brand'] ?? ''));
            $model = trim((string)($row['model'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));

            if ($brand === '' || $model === '') {
                // fallback: try to derive from title
                if ($title !== '') {
                    [$brandGuess, $modelGuess] = $this->guessBrandModelFromTitle($title);
                    $brand = $brand ?: $brandGuess;
                    $model = $model ?: $modelGuess;
                }
            }

            if ($scrapedId <= 0 || $brand === '' || $model === '') {
                $mobilesSkipped++;
                continue;
            }

            if ($this->mobileModel->mobileExists($brand, $model)) {
                $this->scraperModel->markMobilePublished($scrapedId);
                $mobilesSkipped++;
                continue;
            }

            $releaseDate = $this->normalizeDate((string)($row['release_date'] ?? ''));
            $mobileId = (int)$this->mobileModel->insertMobile(
                $brand,
                $model,
                0,
                0,
                'official',
                $releaseDate,
                1
            );

            if ($mobileId <= 0) {
                $errors[] = "Failed to insert mobile for scraped mobile {$scrapedId} ({$brand} {$model})";
                continue;
            }

            $specPayload = $row['specifications'] ?? null;
            $specArray = $this->decodeJsonField($specPayload);
            if ($specArray !== []) {
                [$keys, $values] = $this->flattenSpecs($specArray);
                if ($keys !== []) {
                    $this->mobileModel->insertSpecifications($mobileId, $keys, $values);
                }
            }

            $this->scraperModel->markMobilePublished($scrapedId);
            $mobilesPublished++;
        }

        return [
            'mobiles_published' => $mobilesPublished,
            'mobiles_skipped' => $mobilesSkipped,
            'errors' => $errors,
        ];
    }

    private function resolveTagIdsFromArticle(array $article): array
    {
        $rawTags = [];
        foreach (['tags', 'tags_json'] as $field) {
            if (!array_key_exists($field, $article)) {
                continue;
            }
            $decoded = $this->decodeJsonField($article[$field]);
            if ($decoded !== []) {
                $rawTags = array_merge($rawTags, $decoded);
            }
        }

        $names = [];
        foreach ($rawTags as $tag) {
            if (is_string($tag)) {
                $name = trim($tag);
                if ($name !== '') {
                    $names[] = $name;
                }
                continue;
            }
            if (is_array($tag)) {
                $candidate = trim((string)($tag['name'] ?? $tag['title'] ?? ''));
                if ($candidate !== '') {
                    $names[] = $candidate;
                }
            }
        }

        $names = array_values(array_unique(array_filter($names)));
        if ($names === []) {
            return [];
        }

        $tagIds = [];
        foreach ($names as $name) {
            $slug = $this->slugify($name);
            $existing = $slug !== '' ? $this->contentModel->getTagBySlug($slug) : null;
            if ($existing && isset($existing['id'])) {
                $tagIds[] = (int)$existing['id'];
                continue;
            }
            $tagIds[] = (int)$this->contentModel->createTag($name, $slug !== '' ? $slug : null);
        }

        return array_values(array_unique(array_filter($tagIds)));
    }

    private function decodeJsonField($value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        $text = trim((string)$value);
        if ($text === '') {
            return [];
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', (string)$text);
        $text = trim((string)$text, '-');
        $text = preg_replace('~-+~', '-', (string)$text);
        $text = strtolower((string)$text);
        return $text === '' ? '' : $text;
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
        return date('Y-m-d');
    }

    private function guessBrandModelFromTitle(string $title): array
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
        if ($title === '') {
            return ['', ''];
        }

        $parts = explode(' ', $title);
        $brand = trim((string)($parts[0] ?? ''));
        $model = trim((string)substr($title, strlen($brand)));
        return [$brand, $model !== '' ? $model : $title];
    }

    private function flattenSpecs(array $specs): array
    {
        $keys = [];
        $values = [];

        $walk = function ($value, string $prefix) use (&$walk, &$keys, &$values): void {
            if (is_array($value)) {
                $isAssoc = array_keys($value) !== range(0, count($value) - 1);
                foreach ($value as $k => $v) {
                    $label = $isAssoc ? (string)$k : (string)($prefix !== '' ? $prefix : 'item');
                    $nextPrefix = $prefix !== '' ? ($prefix . '.' . $label) : $label;
                    $walk($v, $nextPrefix);
                }
                return;
            }

            $str = is_scalar($value) ? trim((string)$value) : '';
            if ($str === '' || $prefix === '') {
                return;
            }

            $keys[] = $prefix;
            $values[] = $str;
        };

        foreach ($specs as $k => $v) {
            $walk($v, (string)$k);
        }

        // limit
        $keys = array_slice($keys, 0, 200);
        $values = array_slice($values, 0, 200);
        return [$keys, $values];
    }
}

