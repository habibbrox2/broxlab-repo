<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * Optional AI post-processing for extracted content. Given an item's headline
 * and feed summary, the default provider returns a short Bengali/English
 * summary plus a category and tags. Failures degrade to the untouched item.
 */
class ContentEnricher
{
    /** @var array<int, string> */
    public const CATEGORIES = [
        'News', 'Jobs', 'Technology', 'Sports', 'Entertainment', 'Business', 'Politics', 'Other',
    ];

    public function __construct(
        protected AiClient $client,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function enrich(array $item): array
    {
        $lang = ($item['lang'] ?? 'bn') === 'bn' ? 'Bengali (বাংলা)' : 'English';
        $categories = implode(', ', self::CATEGORIES);

        $system = 'You are a concise Bangladeshi news editor. Always reply with a single strict JSON object and nothing else.';

        $user = "Summarise and classify this item in {$lang}.\n\n"
            . "Headline: " . ($item['title'] ?? '') . "\n"
            . 'Excerpt: ' . mb_substr((string) ($item['summary'] ?? ''), 0, 600) . "\n"
            . "Source type: " . ($item['type'] ?? 'news') . "\n\n"
            . 'Return JSON with keys: summary (max 240 chars, ' . $lang . '), '
            . 'category (one of: ' . $categories . '), tags (array of up to 5 short strings).';

        $result = $this->client->chat([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ], provider: $this->provider(), options: ['max_tokens' => 400, 'temperature' => 0.2]);

        if (! $result['ok']) {
            $item['ai_error'] = $result['error'];

            return $item;
        }

        $parsed = $this->parseJson((string) $result['content']);
        if ($parsed === []) {
            $item['ai_error'] = 'AI response was not valid JSON';

            return $item;
        }

        $item['ai_summary'] = isset($parsed['summary']) && is_string($parsed['summary'])
            ? trim($parsed['summary'])
            : null;
        $item['ai_category'] = isset($parsed['category']) && is_string($parsed['category'])
            ? trim($parsed['category'])
            : null;
        $item['ai_tags'] = isset($parsed['tags']) && is_array($parsed['tags'])
            ? array_values(array_slice(array_filter($parsed['tags'], 'is_string'), 0, 5))
            : [];
        $item['ai_model'] = $result['model'];
        $item['ai_enriched_at'] = gmdate('c');

        return $item;
    }

    protected function provider(): ?array
    {
        $model = trim((string) config('scraper.ai_model', ''));

        $provider = app(AiProviderRepository::class)->default();
        if ($provider !== null && $model !== '') {
            $provider['model'] = $model;
        }

        return $provider;
    }

    /**
     * Tolerant JSON extraction (models sometimes wrap output in code fences).
     *
     * @return array<string, mixed>
     */
    protected function parseJson(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        $content = (string) preg_replace('/^```(?:json)?|```$/m', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the first {...} block.
        if (preg_match('/\{.*\}/s', $content, $match) === 1) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
