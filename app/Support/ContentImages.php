<?php

namespace App\Support;

/**
 * Port of the legacy ContentModel/HomeModel image extraction helpers —
 * pulls <img src="..."> values out of post/page content HTML.
 */
class ContentImages
{
    public function extractMultiple(string $html, int $limit = 3): array
    {
        if (empty($html)) {
            return [];
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match_all('/src=["\']([^"\']+)["\']/i', $html, $matches)) {
            $images = array_values(array_filter(
                array_map('trim', $matches[1]),
                fn ($src) => $src !== '' && ! str_starts_with($src, 'data:') && ! str_starts_with($src, 'blob:')
            ));

            return array_slice($images, 0, max(1, $limit));
        }

        return [];
    }

    public function extractFirst(?string $html): ?string
    {
        $images = $this->extractMultiple((string) $html, 1);

        return $images[0] ?? null;
    }
}