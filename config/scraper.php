<?php

/*
|--------------------------------------------------------------------------
| Content Extraction Sources (Bangladesh news / jobs / tech)
|--------------------------------------------------------------------------
|
| Each source declares a homepage. The pipeline auto-discovers a feed
| (<link rel="alternate" type="application/rss+xml">), then falls back to
| the declared `feed`, then to `sitemap.xml`, then to HTML listing
| heuristics. No JavaScript/Node service is required — everything runs in
| PHP via the Laravel HTTP client and DOMDocument.
|
| `strategy` hints the preferred path but the runner degrades gracefully:
|   auto    → feed discovery → declared feed → sitemap → HTML listing
|   feed    → declared feed (skips discovery)
|   sitemap → sitemap.xml only
|   html    → HTML listing heuristics only
|
| Mobile sources (type = 'mobile') work the same way but, after discovery,
| the runner fetches each detail-page URL and a MobileDetailParser extracts
| structured device data (brand, model, prices, specs, images) instead of
| article summaries.
|
| Add more sources by appending an entry here; the runner picks it up.
|
*/

return [

    // NOTE: the legacy MedEx/Node scraper already owns `SCRAPER_ENABLED`
    // (see SCRAPER_SERVER_BASE_URL etc. in .env). This content-extraction
    // pipeline uses its own namespaced flags so the two stacks never
    // accidentally enable each other. Default off = fail closed.
    'enabled' => env('CONTENT_EXTRACT_ENABLED', false),

    // Where extracted JSON snapshots are written (relative to storage/app).
    'storage_path' => env('CONTENT_EXTRACT_STORAGE_PATH', 'scraping'),

    // Per-run caps and politeness.
    'max_items' => (int) env('CONTENT_EXTRACT_MAX_ITEMS', 25),
    'timeout' => (int) env('CONTENT_EXTRACT_TIMEOUT', 15),
    'retries' => (int) env('CONTENT_EXTRACT_RETRIES', 2),
    'delay_ms' => (int) env('CONTENT_EXTRACT_DELAY_MS', 400),
    'seen_cap' => (int) env('CONTENT_EXTRACT_SEEN_CAP', 20000),
    'run_history' => (int) env('CONTENT_EXTRACT_RUN_HISTORY', 100),
    // Max items retained per source snapshot (newest first).
    'source_cap' => (int) env('CONTENT_EXTRACT_SOURCE_CAP', 500),
    'user_agent' => env(
        'CONTENT_EXTRACT_USER_AGENT',
        'BroxLabBot/1.0 (+https://broxlab.online; content aggregator; contact admin@broxlab.online)'
    ),

    // Optional AI post-processing: summarise / categorise each extracted item
    // using the default provider configured under /admin/aisystem.providers.
    'ai_enrich' => env('CONTENT_EXTRACT_AI_ENRICH', false),
    'ai_model' => env('CONTENT_EXTRACT_AI_MODEL'),

    // Auto-publish: turn each extracted snapshot item into a published post
    // (attributed to its source, tagged News/Jobs/Tech). Still requires the
    // extraction gate above — this only adds the publish step. Per-run cap:
    'autopublish' => env('CONTENT_EXTRACT_AUTO_PUBLISH', true),
    'autopublish_limit' => (int) env('CONTENT_EXTRACT_AUTO_PUBLISH_LIMIT', 50),

    // Token the cron pipeline validates against (set via .env). When empty,
    // the internal cron endpoint refuses to run, so a misconfigured host can
    // never accidentally trigger a scrape.
    'cron_token' => env('SCRAPER_PIPELINE_CRON_TOKEN'),

    // API keys for image services
    'remove_bg_api_key' => env('REMOVE_BG_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */
    'sources' => [

        // ---------- 1. News ----------
        [
            'key' => 'prothomalo',
            'name' => 'Prothom Alo (প্রথম আলো)',
            'type' => 'news',
            'homepage' => 'https://www.prothomalo.com',
            'feed' => 'https://www.prothomalo.com/feed',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'bdnews24_bn',
            'name' => 'bdnews24 Bangla (বাংলা)',
            'type' => 'news',
            'homepage' => 'https://bangla.bdnews24.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            // Article slugs are opaque hash IDs — fetch each detail page for
            // the real headline.
            'fetch_detail_titles' => true,
            'enabled' => true,
            // No RSS feed; the sitemap index descends into sitemap-daily-*.xml
            // which lists the latest articles with lastmod timestamps.
        ],
        [
            'key' => 'dhakapost',
            'name' => 'Dhaka Post (ঢাকা পোস্ট)',
            'type' => 'news',
            'homepage' => 'https://www.dhakapost.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'html',
            // Article URLs only — excludes /topic/ hubs and category pages.
            'link_pattern' => '#/(national|health|international|sports|entertainment|economics|tech|lifestyle|politics|education)/\d+($|\?)#i',
            'enabled' => true,
            // RSS + sitemap are Cloudflare-gated; homepage HTML works and
            // article URLs follow /<section>/<id>.
        ],
        [
            'key' => 'jagonews24',
            'name' => 'Jago News 24 (জাগো নিউজ ২৪)',
            'type' => 'news',
            'homepage' => 'https://www.jagonews24.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => false,
            'note' => 'Cloudflare bot-challenge blocks all automated access (sitemap, feed and HTML).',
        ],
        [
            'key' => 'banglatribune',
            'name' => 'Bangla Tribune (বাংলা ট্রিবিউন)',
            'type' => 'news',
            'homepage' => 'https://www.banglatribune.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            'sitemap_path' => 'news-sitemap.xml',
            // Slugs are percent-encoded Bengali — decode them for titles.
            'fetch_detail_titles' => true,
            'enabled' => true,
            // news-sitemap.xml lists the newest ~400 articles with timestamps.
        ],

        // ---------- 2. Jobs ----------
        // Note: bdjobs.com and bdjobstoday.com render job listings through
        // Angular/JS (no job links in static HTML), so they are not usable
        // without a headless browser. Verified-working sources below.
        [
            'key' => 'bdjobs',
            'name' => 'BDJobs.com',
            'type' => 'jobs',
            'homepage' => 'https://www.bdjobs.com',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'link_pattern' => '/(job|jobs|career)/i',
            'enabled' => false,
            'note' => 'Angular SPA — job links are rendered client-side; static HTML has none.',
        ],
        [
            'key' => 'chakri',
            'name' => 'Chakri.com',
            'type' => 'jobs',
            'homepage' => 'https://www.chakri.com',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'link_pattern' => '/(job|jobs|career)/i',
            'enabled' => false,
            'note' => 'Domain no longer serves a jobs site (16-byte stub response).',
        ],
        [
            'key' => 'bpsc',
            'name' => 'BPSC — Government Circulars',
            'type' => 'jobs',
            'homepage' => 'https://bpsc.gov.bd',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'html',
            'link_pattern' => '/(notice|circular|advertisement|jobs|career)/i',
            'parser' => 'html',
            'enabled' => true,
        ],
        [
            'key' => 'jobsbangladesh',
            'name' => 'Jobs in Bangladesh',
            'type' => 'jobs',
            'homepage' => 'https://www.jobsbangladesh.com',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'sitemap',
            'parser' => 'jobdetail',
            'enabled' => false,
            'note' => 'Domain unreachable (connection failure).',
        ],
        [
            'key' => 'bongobdjobs',
            'name' => 'BongoBD Jobs',
            'type' => 'jobs',
            'homepage' => 'https://www.bdremit.com/jobs',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'parser' => 'jobdetail',
            'link_pattern' => '/(job|jobs|career|vacancy)/i',
            'enabled' => false,
            'note' => 'Domain unreachable (connection failure).',
        ],
        [
            'key' => 'workfulldb',
            'name' => 'Workfully BD',
            'type' => 'jobs',
            'homepage' => 'https://www.workfully.com.bd',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'parser' => 'jobdetail',
            'link_pattern' => '/(job|jobs|career|vacancy)/i',
            'enabled' => false,
            'note' => 'Domain unreachable (connection failure).',
        ],
        [
            'key' => 'jobstationbd',
            'name' => 'Job Station BD',
            'type' => 'jobs',
            'homepage' => 'https://www.jobstationbd.com',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'parser' => 'jobdetail',
            'link_pattern' => '/(job|jobs|career|vacancy)/i',
            'enabled' => false,
            'note' => 'Domain unreachable (connection failure).',
        ],
        [
            'key' => 'ejobsresults',
            'name' => 'EJobsResults (ইজবস রেজাল্টস)',
            'type' => 'jobs',
            'homepage' => 'https://www.ejobsresults.com',
            'feed' => 'https://www.ejobsresults.com/feed',
            'lang' => 'en',
            'strategy' => 'feed',
            'enabled' => true,
            // RSS feed verified: 10 items, fresh (same-day pubDates).
        ],
        [
            'key' => 'jobstestbd',
            'name' => 'Jobs Test BD (জবস টেস্ট বিডি)',
            'type' => 'jobs',
            'homepage' => 'https://jobstestbd.com',
            'feed' => 'https://jobstestbd.com/feed/',
            'lang' => 'bn',
            'strategy' => 'feed',
            'enabled' => true,
            // RSS feed verified: 12 items, fresh (same-day pubDates).
        ],
        [
            'key' => 'prothomalo_jobs',
            'name' => 'Prothom Alo Jobs',
            'type' => 'jobs',
            'homepage' => 'https://www.prothomalo.com/career',
            'feed' => 'https://www.prothomalo.com/career/feed',
            'lang' => 'bn',
            'strategy' => 'auto',
            'parser' => 'feed',
            'enabled' => false,
            'note' => '/career/feed returns HTTP 404 — no working jobs feed.',
        ],
        [
            'key' => 'chakrikhobor',
            'name' => 'Chakri Khobor (চাকরি খবর)',
            'type' => 'jobs',
            'homepage' => 'https://www.chakrikhobor.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'html',
            'link_pattern' => '#/\d{4}/\d{2}/#i',
            'enabled' => false,
            'note' => 'Homepage returns HTTP 415 for bots.',
        ],
        [
            'key' => 'bdjobstoday',
            'name' => 'BD Jobs Today',
            'type' => 'jobs',
            'homepage' => 'https://bdjobstoday.com',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'html',
            'parser' => 'jobdetail',
            // Category pages list job_details.php?id=NNN links in static HTML.
            'listing_paths' => ['jobsbycategory.php?cat=2', 'jobsbycategory.php?cat=3', 'govtjobs.php'],
            'link_pattern' => '#job_details\.php\?id=\d+#i',
            'enabled' => true,
        ],
        [
            'key' => 'linkedin_jobs_bd',
            'name' => 'LinkedIn Jobs (Bangladesh)',
            'type' => 'jobs',
            'homepage' => 'https://www.linkedin.com/jobs/search?location=Bangladesh',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'enabled' => false,
            'note' => 'LinkedIn blocks automated access — enable only with a compliant API/proxy.',
        ],

        // ---------- 3. Technology ----------
        [
            'key' => 'techtunes',
            'name' => 'Techtunes (টেকটিউনস)',
            'type' => 'tech',
            'homepage' => 'https://www.techtunes.co',
            'feed' => 'https://www.techtunes.co/feed',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'techworldbd',
            'name' => 'Techworld Bangladesh',
            'type' => 'tech',
            'homepage' => 'https://www.techworldbd.com',
            'feed' => 'https://www.techworldbd.com/feed',
            'lang' => 'en',
            'strategy' => 'auto',
            'enabled' => false,
            'note' => 'Host serves an anti-bot JavaScript challenge page on every path.',
        ],
        [
            'key' => 'prothomalo_tech',
            'name' => 'Prothom Alo Tech',
            'type' => 'tech',
            'homepage' => 'https://www.prothomalo.com/collection/technology',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'html',
            'link_pattern' => '#prothomalo\.com/(technology|tech)/[a-z0-9]+#i',
            'enabled' => true,
            // /collection/technology/feed returns empty; scrape the HTML listing.
        ],
        [
            'key' => 'dhakatribune_tech',
            'name' => 'Dhaka Tribune — Tech',
            'type' => 'tech',
            'homepage' => 'https://www.dhakatribune.com/technology',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'auto',
            'enabled' => false,
            'note' => 'Feed endpoint returns empty/error responses.',
        ],
        [
            'key' => 'bdnews24_tech',
            'name' => 'bdnews24 — Technology',
            'type' => 'tech',
            'homepage' => 'https://bdnews24.com/technology',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'sitemap',
            'enabled' => false,
            'note' => 'Section RSS returns empty; English sitemap only covers static pages. Revisit with HTML listing.',
        ],

        // ---------- 4. Mobile ----------
        [
            'key' => 'mobiledokan',
            'name' => 'MobileDokan (মোবাইলডকান)',
            'type' => 'mobile',
            'homepage' => 'https://www.mobiledokan.co',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            // Product pages live in the aps-products sitemaps; post sitemaps
            // are old blog articles.
            'sitemap_path' => 'aps-products-sitemap.xml',
            'detail_pattern' => '/product/{slug}/i',
            'parser' => 'mobiledokan',
            'enabled' => true,
        ],
        [
            'key' => 'muthophone',
            'name' => 'MuthoPhone (মুথোফোন)',
            'type' => 'mobile',
            'homepage' => 'https://www.muthophone.com.bd',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            'detail_pattern' => '/phone/{slug}/i',
            'parser' => 'muthophone',
            'enabled' => false,
            'note' => 'Sitemap returns empty/404 and product listing is JS-rendered; no static discovery path.',
        ],
        [
            'key' => 'mobilebd',
            'name' => 'MobileBD (মোবাইলবিডি)',
            'type' => 'mobile',
            'homepage' => 'https://www.mobilebd.co',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            // Product pages live under /phone/{slug}/ in the
            // mobile-products sitemap; filter to those only.
            'sitemap_url_filter' => '#/phone/.+#i',
            'detail_pattern' => '/phone/{slug}/i',
            'parser' => 'mobilebd',
            'enabled' => true,
        ],
        [
            'key' => 'gsmarena_bd',
            'name' => 'GSMArena BD (গ্সমারেনা বিডি)',
            'type' => 'mobile',
            'homepage' => 'https://www.gsmarena.com.bd',
            'feed' => null,
            'lang' => 'en',
            // Phone detail URLs are /{brand}-{model}/ (single segment, no
            // brand/news/compare prefix) — the sitemap only lists section
            // pages, so discover them from brand listing pages instead.
            'strategy' => 'html',
            'link_pattern' => '#^/[a-z0-9-]+/$#i',
            'parser' => 'gsmarena_bd',
            'enabled' => false,
            'note' => 'Detail URLs are single-segment slugs; needs brand-page crawl support (homepage links point to sections). Re-enable after adding listing_paths per brand.',
        ],
    ],
];
