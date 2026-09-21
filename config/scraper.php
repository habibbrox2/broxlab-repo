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
            'feed' => 'https://bangla.bdnews24.com/rss',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'dhakapost',
            'name' => 'Dhaka Post (ঢাকা পোস্ট)',
            'type' => 'news',
            'homepage' => 'https://www.dhakapost.com',
            'feed' => 'https://www.dhakapost.com/rss',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'jagonews24',
            'name' => 'Jago News 24 (জাগো নিউজ ২৪)',
            'type' => 'news',
            'homepage' => 'https://www.jagonews24.com',
            'feed' => 'https://www.jagonews24.com/rss',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'banglatribune',
            'name' => 'Bangla Tribune (বাংলা ট্রিবিউন)',
            'type' => 'news',
            'homepage' => 'https://www.banglatribune.com',
            'feed' => 'https://www.banglatribune.com/rss',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],

        // ---------- 2. Jobs ----------
        [
            'key' => 'bdjobs',
            'name' => 'BDJobs.com',
            'type' => 'jobs',
            'homepage' => 'https://www.bdjobs.com',
            'feed' => null,
            'lang' => 'en',
            'strategy' => 'html',
            'link_pattern' => '/(job|jobs|career)/i',
            'enabled' => true,
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
            'enabled' => true,
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
            'enabled' => true,
        ],
        [
            'key' => 'bdjobstoday',
            'name' => 'BD Jobs Today',
            'type' => 'jobs',
            'homepage' => 'https://bdjobstoday.com',
            'feed' => 'https://bdjobstoday.com/feed',
            'lang' => 'bn',
            'strategy' => 'auto',
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
            'enabled' => true,
        ],
        [
            'key' => 'prothomalo_tech',
            'name' => 'Prothom Alo Tech',
            'type' => 'tech',
            'homepage' => 'https://www.prothomalo.com/collection/technology',
            'feed' => 'https://www.prothomalo.com/collection/technology/feed',
            'lang' => 'bn',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'dhakatribune_tech',
            'name' => 'Dhaka Tribune — Tech',
            'type' => 'tech',
            'homepage' => 'https://www.dhakatribune.com/technology',
            'feed' => 'https://www.dhakatribune.com/feed',
            'lang' => 'en',
            'strategy' => 'auto',
            'enabled' => true,
        ],
        [
            'key' => 'bdnews24_tech',
            'name' => 'bdnews24 — Technology',
            'type' => 'tech',
            'homepage' => 'https://bdnews24.com/technology',
            'feed' => 'https://bdnews24.com/technology/rss',
            'lang' => 'en',
            'strategy' => 'auto',
            'enabled' => true,
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
            'enabled' => true,
        ],
        [
            'key' => 'mobilebd',
            'name' => 'MobileBD (মোবাইলবিডি)',
            'type' => 'mobile',
            'homepage' => 'https://www.mobilebd.co',
            'feed' => null,
            'lang' => 'bn',
            'strategy' => 'sitemap',
            'detail_pattern' => '/{slug}/i',
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
            'strategy' => 'sitemap',
            'detail_pattern' => '/{brand}/{slug}-bd{.*}/i',
            'parser' => 'gsmarena_bd',
            'enabled' => true,
        ],
    ],
];
