<?php
// app/Controllers/SitemapController.php
//
// Professional split sitemap system for SEO
// Routes:
//   GET /sitemap.xml           → Sitemap Index (references all others)
//   GET /sitemap-posts.xml
//   GET /sitemap-pages.xml
//   GET /sitemap-categories.xml
//   GET /sitemap-tags.xml
//   GET /sitemap-services.xml
//   GET /sitemap-products.xml  → 301 alias to services (for legacy / marketing URLs)
//
// All responses are XML with 24h cache header.
// Uses existing lightweight getSitemap*() methods on models.

declare(strict_types=1);

/** @var Router                $router */
/** @var \Twig\Environment     $twig */
/** @var mysqli                $mysqli */

$contentModel = new ContentModel($mysqli);
$serviceModel = new ServiceModel($mysqli);
$mobileModel  = new MobileModel($mysqli);

// Rich list of static + important hub pages (used by main /sitemap.xml and /sitemap-static.xml)
$staticPages = [
    // Home / Root - highest priority
    ['loc' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],

    // Core public pages
    ['loc' => '/about-us', 'priority' => 0.8, 'changefreq' => 'monthly'],
    ['loc' => '/about', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ['loc' => '/contact', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ['loc' => '/advertise', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ['loc' => '/donate', 'priority' => 0.6, 'changefreq' => 'monthly'],

    // Existing static content
    ['loc' => '/faq', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ['loc' => '/terms', 'priority' => 0.4, 'changefreq' => 'yearly'],
    ['loc' => '/privacy', 'priority' => 0.4, 'changefreq' => 'yearly'],
    ['loc' => '/newsletter', 'priority' => 0.6, 'changefreq' => 'monthly'],
    ['loc' => '/bangla-converter', 'priority' => 0.6, 'changefreq' => 'monthly'],
    ['loc' => '/ramadan-2026', 'priority' => 0.7, 'changefreq' => 'monthly'],
    ['loc' => '/ramadan', 'priority' => 0.5, 'changefreq' => 'yearly'],

    // MedEx
    ['loc' => '/medex', 'priority' => 0.8, 'changefreq' => 'weekly'],
    ['loc' => '/medex/details', 'priority' => 0.6, 'changefreq' => 'monthly'],

    // Calculators
    ['loc' => '/calculators', 'priority' => 0.7, 'changefreq' => 'weekly'],
    ['loc' => '/calculator', 'priority' => 0.5, 'changefreq' => 'yearly'],

    // Weather
    ['loc' => '/weather', 'priority' => 0.6, 'changefreq' => 'daily'],
    ['loc' => '/weather/details', 'priority' => 0.5, 'changefreq' => 'monthly'],

    // Other hubs
    ['loc' => '/services', 'priority' => 0.7, 'changefreq' => 'weekly'],
    ['loc' => '/mobiles', 'priority' => 0.6, 'changefreq' => 'weekly'],
    ['loc' => '/posts', 'priority' => 0.6, 'changefreq' => 'weekly'],
];

// Helper: return most recent date from a result set (or today)
$getLatest = static function (array $items, string $key = 'updated_at'): string {
    if (empty($items)) {
        return date('Y-m-d');
    }
    $dates = array_column($items, $key);
    $dates = array_filter($dates);
    return $dates ? max($dates) : date('Y-m-d');
};

// Common XML response headers
$setSitemapHeaders = static function (): void {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400'); // 24 hours
    header('X-Robots-Tag: noindex'); // prevent indexing the sitemaps themselves
};

// ============================================================
// SITEMAP INDEX (entry point for search engines)
// ============================================================
    $router->get('/sitemap.xml', function () use (
        $twig, $contentModel, $serviceModel, $mobileModel, $setSitemapHeaders, $getLatest
    ) {
        $setSitemapHeaders();

        try {
            $latestPosts    = method_exists($contentModel, 'getSitemapPosts')    ? $contentModel->getSitemapPosts(1)    : [];
            $latestPages    = method_exists($contentModel, 'getSitemapPages')    ? $contentModel->getSitemapPages(1)    : [];
            $latestServices = method_exists($serviceModel, 'getSitemapServices') ? $serviceModel->getSitemapServices(1) : [];
            $latestMobiles  = method_exists($mobileModel, 'getSitemapMobiles')   ? $mobileModel->getSitemapMobiles(1)   : [];

            $sitemaps = [
                ['loc' => '/sitemap-posts.xml',     'lastmod' => $getLatest($latestPosts, 'updated_at')],
                ['loc' => '/sitemap-pages.xml',     'lastmod' => $getLatest($latestPages, 'updated_at')],
                ['loc' => '/sitemap-categories.xml','lastmod' => date('Y-m-d')],
                ['loc' => '/sitemap-tags.xml',      'lastmod' => date('Y-m-d')],
                ['loc' => '/sitemap-services.xml',  'lastmod' => $getLatest($latestServices, 'updated_at')],
                ['loc' => '/sitemap-mobiles.xml',   'lastmod' => $getLatest($latestMobiles, 'created_at')],
                ['loc' => '/sitemap-static.xml',    'lastmod' => date('Y-m-d')],
            ];
        } catch (\Throwable $e) {
            error_log('Sitemap index error: ' . $e->getMessage());
            $sitemaps = [
                ['loc' => '/sitemap-static.xml', 'lastmod' => date('Y-m-d')],
            ];
        }

        echo $twig->render('public/sitemaps/sitemap-index.twig', [
            'sitemaps' => $sitemaps,
        ]);
        exit;
    });


// ============================================================
// INDIVIDUAL SITEMAPS
// ============================================================

// --- Posts ---
$router->get('/sitemap-posts.xml', function () use ($twig, $contentModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    try {
        $posts = $contentModel->getSitemapPosts(2000);
    } catch (\Throwable $e) {
        error_log('Sitemap posts error: ' . $e->getMessage());
        $posts = [];
    }
    echo $twig->render('public/sitemaps/sitemap-posts.twig', [
        'items' => $posts,
    ]);
    exit;
});

// --- Pages (published only) ---
$router->get('/sitemap-pages.xml', function () use ($twig, $contentModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    try {
        $pages = $contentModel->getSitemapPages(1000);
    } catch (\Throwable $e) {
        error_log('Sitemap pages error: ' . $e->getMessage());
        $pages = [];
    }
    echo $twig->render('public/sitemaps/sitemap-pages.twig', [
        'items' => $pages,
    ]);
    exit;
});

// --- Categories ---
$router->get('/sitemap-categories.xml', function () use ($twig, $contentModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    $categories = $contentModel->getSitemapCategories();

    echo $twig->render('public/sitemaps/sitemap-categories.twig', [
        'items' => $categories,
    ]);
    exit;
});

// --- Tags ---
$router->get('/sitemap-tags.xml', function () use ($twig, $contentModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    $tags = $contentModel->getSitemapTags();

    echo $twig->render('public/sitemaps/sitemap-tags.twig', [
        'items' => $tags,
    ]);
    exit;
});

// --- Services (primary name) ---
$router->get('/sitemap-services.xml', function () use ($twig, $serviceModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    try {
        $services = $serviceModel->getSitemapServices(1000);
    } catch (\Throwable $e) {
        error_log('Sitemap services error: ' . $e->getMessage());
        $services = [];
    }
    echo $twig->render('public/sitemaps/sitemap-services.twig', [
        'items' => $services,
    ]);
    exit;
});

// --- Products (legacy / marketing alias → 301 redirect) ---
$router->get('/sitemap-products.xml', function () {
    header('Location: /sitemap-services.xml', true, 301);
    exit;
});

// ============================================================
// STATIC / PUBLIC PAGES (Home, About, Contact, MedEx, Calculators, etc.)
// ============================================================
$router->get('/sitemap-static.xml', function () use ($twig, $setSitemapHeaders) {
    $setSitemapHeaders();

    $today = date('Y-m-d');

    // Comprehensive list of important static + hub pages
    // Home/root gets highest priority
    $staticPages = [
        // Home / Root
        ['loc' => '/', 'priority' => 1.0, 'changefreq' => 'daily', 'lastmod' => $today],

        // Core public pages
        ['loc' => '/about-us',          'priority' => 0.8, 'changefreq' => 'monthly'],
        ['loc' => '/about',             'priority' => 0.7, 'changefreq' => 'monthly'], // alias/redirect
        ['loc' => '/contact',           'priority' => 0.7, 'changefreq' => 'monthly'],
        ['loc' => '/advertise',         'priority' => 0.7, 'changefreq' => 'monthly'],
        ['loc' => '/donate',            'priority' => 0.6, 'changefreq' => 'monthly'],

        // Existing static content pages
        ['loc' => '/faq',               'priority' => 0.7, 'changefreq' => 'monthly'],
        ['loc' => '/terms',             'priority' => 0.4, 'changefreq' => 'yearly'],
        ['loc' => '/privacy',           'priority' => 0.4, 'changefreq' => 'yearly'],
        ['loc' => '/newsletter',        'priority' => 0.6, 'changefreq' => 'monthly'],
        ['loc' => '/bangla-converter',  'priority' => 0.6, 'changefreq' => 'monthly'],
        ['loc' => '/ramadan-2026',      'priority' => 0.7, 'changefreq' => 'monthly'],
        ['loc' => '/ramadan',           'priority' => 0.5, 'changefreq' => 'yearly'], // redirect

        // MedEx (herbal pharma database)
        ['loc' => '/medex',             'priority' => 0.8, 'changefreq' => 'weekly'],
        ['loc' => '/medex/details',     'priority' => 0.6, 'changefreq' => 'monthly'],

        // Calculators suite
        ['loc' => '/calculators',       'priority' => 0.7, 'changefreq' => 'weekly'],
        ['loc' => '/calculator',        'priority' => 0.5, 'changefreq' => 'yearly'], // redirect

        // Weather
        ['loc' => '/weather',           'priority' => 0.6, 'changefreq' => 'daily'],
        ['loc' => '/weather/details',   'priority' => 0.5, 'changefreq' => 'monthly'],

        // Other important hubs (if they have nice landing pages)
        ['loc' => '/services',          'priority' => 0.7, 'changefreq' => 'weekly'],
        ['loc' => '/mobiles',           'priority' => 0.6, 'changefreq' => 'weekly'],
        ['loc' => '/posts',             'priority' => 0.6, 'changefreq' => 'weekly'],
    ];

    try {
        echo $twig->render('public/sitemaps/sitemap-static.twig', [
            'pages' => $staticPages,
        ]);
    } catch (\Throwable $e) {
        error_log('Sitemap static error: ' . $e->getMessage());
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . htmlspecialchars(base_url() ?: 'http://localhost/') . '</loc></url></urlset>';
    }
    exit;
});

// --- Mobiles (individual mobile device pages) ---
$router->get('/sitemap-mobiles.xml', function () use ($twig, $mobileModel, $setSitemapHeaders) {
    $setSitemapHeaders();
    try {
        $mobiles = $mobileModel->getSitemapMobiles(2000);
    } catch (\Throwable $e) {
        error_log('Sitemap mobiles error: ' . $e->getMessage());
        $mobiles = [];
    }
    echo $twig->render('public/sitemaps/sitemap-mobiles.twig', [
        'mobiles' => $mobiles,
    ]);
    exit;
});

// --- MedEx Brands (individual brand/medicine pages) ---
$router->get('/sitemap-medex-brands.xml', function () use ($twig, $setSitemapHeaders) {
    $setSitemapHeaders();
    try {
        $medexService = new \App\Services\MedexDataService();
        $brands = $medexService->getAllBrands();
    } catch (\Throwable $e) {
        error_log('Sitemap MedEx brands error: ' . $e->getMessage());
        $brands = [];
    }
    echo $twig->render('public/sitemaps/sitemap-medex-brands.twig', [
        'brands' => $brands,
    ]);
    exit;
});
