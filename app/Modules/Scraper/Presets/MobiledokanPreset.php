<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Presets;

/**
 * MobiledokanPreset - Scraper preset for Mobiledokan
 * 
 * Bangladesh's popular mobile phone and gadget marketplace
 */
class MobiledokanPreset extends BasePreset
{
    public function getKey(): string
    {
        return 'mobiledokan';
    }

    public function getName(): string
    {
        return 'MobileDokan';
    }

    public function getDescription(): string
    {
        return 'Bangladesh\'s popular mobile phone and gadget marketplace with latest devices and prices.';
    }

    public function getCategory(): string
    {
        return 'Products';
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
        return 60;
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
            'list_container' => '.product-item',
            'list_item' => '.product-item',
            'list_title' => '.product-title a',
            'list_link' => '.product-title a',
            'list_price' => '.product-price',
            'list_image' => '.product-image img',
            'title' => '.product-details-title',
            'content' => '.product-description',
            'price' => '.product-price-current',
            'image' => '.product-main-image img',
            'availability' => '.product-availability',
            'brand' => '.product-brand',
            'model' => '.product-model',
            'pagination' => '.pagination a.next'
        ];
    }

    public function getExampleUrls(): array
    {
        return [
            'https://www.mobiledokan.com/',
            'https://www.mobiledokan.com/mobile-phones',
            'https://www.mobiledokan.com/smartphones',
            'https://www.mobiledokan.com/accessories'
        ];
    }

    public function matchesUrl(string $url): bool
    {
        return $this->matchesDomain($url, 'mobiledokan.com');
    }
}
