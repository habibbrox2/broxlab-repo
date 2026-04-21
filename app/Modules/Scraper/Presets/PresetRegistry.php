<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

use App\Modules\Scraper\Presets\BasePreset;
use App\Modules\Scraper\Presets\BDNews24Preset;
use App\Modules\Scraper\Presets\ProthomAloPreset;
use App\Modules\Scraper\Presets\GSMArenaBDPreset;
use App\Modules\Scraper\Presets\MobiledokanPreset;
use App\Modules\Scraper\Presets\WordPressBlogPreset;
use App\Modules\Scraper\Presets\LinkedInJobsPreset;
use App\Modules\Scraper\Presets\IttefaqLatestNewsPreset;

/**
 * PresetRegistry - Registry for all scraper presets
 *
 * Manages available presets and provides methods to retrieve them.
 */
class PresetRegistry
{
    private static ?array $presets = null;
    private static ?array $presetsByKey = null;

    /**
     * Get all available presets
     */
    public static function getAll(): array
    {
        if (self::$presets === null) {
            self::$presets = [
                new BDNews24Preset(),
                new ProthomAloPreset(),
                new GSMArenaBDPreset(),
                new MobiledokanPreset(),
                new WordPressBlogPreset(),
                new LinkedInJobsPreset(),
                new IttefaqLatestNewsPreset()
            ];
        }

        return self::$presets;
    }

    /**
     * Get preset by key
     */
    public static function getByKey(string $key): ?BasePreset
    {
        if (self::$presetsByKey === null) {
            self::$presetsByKey = [];
            foreach (self::getAll() as $preset) {
                self::$presetsByKey[$preset->getKey()] = $preset;
            }
        }

        return self::$presetsByKey[$key] ?? null;
    }

    /**
     * Find preset that matches a URL
     */
    public static function findByUrl(string $url): ?BasePreset
    {
        foreach (self::getAll() as $preset) {
            if ($preset->matchesUrl($url)) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Get presets by category
     */
    public static function getByCategory(string $category): array
    {
        return array_filter(self::getAll(), function ($preset) use ($category) {
            return $preset->getCategory() === $category;
        });
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        $categories = [];
        foreach (self::getAll() as $preset) {
            $category = $preset->getCategory();
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
        }

        return $categories;
    }

    /**
     * Find preset by content type
     */
    public static function findByContentType(string $contentType): ?BasePreset
    {
        $target = strtolower(trim($contentType));
        if ($target === '') {
            return null;
        }
        foreach (self::getAll() as $preset) {
            if (strtolower($preset->getContentType()) === $target) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * Get presets as array for API response
     */
    public static function toArray(): array
    {
        $result = [];
        foreach (self::getAll() as $preset) {
            $result[] = [
                'key' => $preset->getKey(),
                'name' => $preset->getName(),
                'description' => $preset->getDescription(),
                'category' => $preset->getCategory(),
                'icon' => $preset->getIcon(),
                'type' => $preset->getType(),
                'content_type' => $preset->getContentType(),
                'example_urls' => $preset->getExampleUrls()
            ];
        }

        return $result;
    }

    /**
     * Register a custom preset
     */
    public static function register(BasePreset $preset): void
    {
        self::$presets[] = $preset;
        self::$presetsByKey[$preset->getKey()] = $preset;
    }

    /**
     * Clear all presets (useful for testing)
     */
    public static function clear(): void
    {
        self::$presets = null;
        self::$presetsByKey = null;
    }
}
