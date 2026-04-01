<?php

declare(strict_types=1);

return [
    'news' => [
        'label' => 'GSMArena News',
        'type' => 'news',
        'source_url' => 'https://www.gsmarena.com/news.php',
        'base_url' => 'https://www.gsmarena.com',
        'pagination_pattern' => '/news.php?p={page}',
        'selectors' => [
            'list_item' => '.news-list .article-item',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => '.article-summary',
            'date' => '.article-date',
            'image' => '.article-media img',
        ],
        'default_pages' => 5,
        'source_id' => null,
    ],
    'devices' => [
        'label' => 'GSMArena Devices',
        'type' => 'devices',
        'source_url' => 'https://www.gsmarena.com/reviews.php',
        'base_url' => 'https://www.gsmarena.com',
        'pagination_pattern' => '/reviews.php?p={page}',
        'selectors' => [
            'list_item' => '.review-list .review-item',
            'title' => 'h3 a',
            'url' => 'h3 a',
            'summary' => '.review-description',
            'date' => '.article-date',
            'image' => '.review-img img',
            'price' => '.review-price',
        ],
        'default_pages' => 3,
        'source_id' => null,
    ],
    'bd' => [
        'label' => 'GSMArena Bangladesh',
        'type' => 'bd',
        'source_url' => 'https://www.gsmarena.com.bd/phones.php',
        'base_url' => 'https://www.gsmarena.com.bd',
        'pagination_pattern' => '/phones.php?page={page}',
        'selectors' => [
            'list_item' => '.product-list .product-thumb',
            'title' => '.mobile_name',
            'url' => 'a',
            'image' => 'img',
            'price' => '.mobile_price',
            'details_url' => 'a.vdetails',
        ],
        'default_pages' => 4,
        'source_id' => null,
    ],
];
