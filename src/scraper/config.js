/**
 * Multi-Source Scraper Configuration
 * Configuration for the multi-agent web scraping system
 * Supports multiple sources (bdnews24, prothomalo, etc.)
 * Can load selectors from website_presets table
 */

import EnvLoader from './utils/EnvLoader.js';

const parseList = (value) => {
    return String(value || '')
        .split(',')
        .map(v => v.trim())
        .filter(Boolean)
};

const CONFIG = {
    // Cache for dynamically loaded presets
    _presetCache: new Map(),
    // Default source configuration
    source: {
        // Can be overridden by command line or environment
        get defaultSource() {
            return process.env.SCRAPER_SOURCE || 'bdnews24';
        }
    },

    // Source-specific selectors
    // Each source can have its own selectors
    sources: {
        bdnews24: {
            name: 'BD News 24',
            baseUrl: 'https://bangla.bdnews24.com/',
            homepageUrl: 'https://bangla.bdnews24.com/',
            selectors: {
                ticker: {
                    primary: '.news-scroll-content a',
                    fallback: ['.news-scroll a', 'a[href*="bangla.bdnews24.com"]']
                },
                article: {
                    title: {
                        primary: '.details-title h1',
                        fallback: ['h1', '.article-title h1', '.title h1']
                    },
                    subtitle: {
                        primary: '.details-title h2',
                        fallback: ['h2.subtitle', '.article-subtitle h2']
                    },
                    author: {
                        primary: '.author',
                        fallback: ['.author-name', '.byline', '[rel="author"]']
                    },
                    published: {
                        primary: '.pub-up span',
                        fallback: ['.published-date', '.pub-date', 'time[datetime]']
                    },
                    image: {
                        primary: '.details-img img',
                        fallback: ['article img', '.featured-image img', '.main-image img']
                    },
                    content: {
                        primary: '#contentDetails p',
                        fallback: ['.details-brief p', 'article p', '.article-body p', '.content p']
                    }
                }
            }
        },
        samakal: {
            name: 'Samakal',
            baseUrl: 'https://samakal.com/',
            homepageUrl: 'https://samakal.com/latest/news',
            selectors: {
                ticker: {
                    // List page anchors: <div id="data-wrapper"> ... <div class="CatListNews"><a href="...">...</a>
                    primary: '#data-wrapper .CatListNews > a',
                    title: '.CatListhead h3',
                    link: 'a',
                    fallback: ['#data-wrapper a[href*="samakal.com"]', 'a[href*="samakal.com"]']
                },
                article: {
                    title: {
                        primary: '.dheading h1',
                        fallback: ['h1', 'meta[property="og:title"]', 'title']
                    },
                    subtitle: {
                        primary: '.dheading .DsubHead',
                        fallback: ['.dheading h2', 'meta[name="description"]', 'meta[property="og:description"]']
                    },
                    author: {
                        primary: '.writter p',
                        fallback: ['.author', '.byline', '[rel="author"]']
                    },
                    published: {
                        primary: '.dateAndTime p',
                        fallback: ['time[datetime]', 'meta[property="article:published_time"]']
                    },
                    image: {
                        primary: '.DNewsImg img',
                        fallback: ['meta[property="og:image"]', 'article img', 'img']
                    },
                    content: {
                        primary: '#contentDetails p',
                        fallback: ['#contentDetails', 'article p', '.content p', '.dNewsDesc p']
                    }
                }
            }
        },
        ittefaq: {
            name: 'The Daily Ittefaq',
            baseUrl: 'https://www.ittefaq.com.bd/',
            homepageUrl: 'https://www.ittefaq.com.bd/latest-news',
            selectors: {
                ticker: {
                    // Latest list page cards: <div class="each"> ... <h2 class="title"><a class="link_overlay" href="//www.ittefaq.com.bd/...">Title</a>
                    primary: '.contents_listing .each h2.title a.link_overlay[href]',
                    link: 'a',
                    fallback: ['.contents_listing a.link_overlay[href]', 'a[href*="ittefaq.com.bd/"]']
                },
                article: {
                    title: {
                        primary: 'h1[itemprop="headline"].title, h1[itemprop="headline"], h1.title',
                        fallback: ['h1', 'meta[property="og:title"]', 'title']
                    },
                    subtitle: {
                        primary: 'meta[name="description"]',
                        fallback: ['meta[property="og:description"]']
                    },
                    author: {
                        primary: '.additional_info_container .author .name, [itemprop="author"] .name, .author .name',
                        fallback: ['[itemprop="author"]', '.byline', '[rel="author"]']
                    },
                    published: {
                        // <span class="tts_time" itemprop="datePublished" content="2026-03-21T10:18:32+06:00">প্রকাশ : ...</span>
                        primary: 'span.tts_time[itemprop="datePublished"], [itemprop="datePublished"]',
                        fallback: ['time[datetime]', 'meta[property="article:published_time"]', 'meta[itemprop="datePublished"]']
                    },
                    image: {
                        // Prefer structured meta first; fallback to hero image
                        primary: 'meta[itemprop="image"][content*="/uploads/"], meta[property="og:image"][content*="/uploads/"], .featured_image img',
                        fallback: ['.featured_image img', 'article img', 'img']
                    },
                    content: {
                        primary: 'div[itemprop="articleBody"] p',
                        fallback: ['.jw_article_body p', 'article p', '.content_detail_content_inner p']
                    }
                }
            }
        },
        jugantor: {
            name: 'Jugantor',
            baseUrl: 'https://www.jugantor.com/',
            homepageUrl: 'https://www.jugantor.com/latest',
            selectors: {
                ticker: {
                    // Latest list page cards: .loadMoreCategoryNewsDesktop .media ... <h4 class="title10">..</h4> + <a class="linkOverlay" href="..."></a>
                    primary: '.loadMoreCategoryNewsDesktop .media.positionRelative',
                    title: 'h4.title10',
                    link: 'a.linkOverlay',
                    fallback: ['a.linkOverlay[href*="jugantor.com/"]', 'a[href*="jugantor.com/"]']
                },
                article: {
                    title: {
                        primary: 'h1.desktopDetailHeadline strong, h1.desktopDetailHeadline',
                        fallback: ['h1', 'meta[property="og:title"]', 'title']
                    },
                    subtitle: {
                        primary: 'meta[name="description"]',
                        fallback: ['meta[property="og:description"]']
                    },
                    author: {
                        primary: 'p.desktopDetailReporter',
                        fallback: ['.desktopDetailReporter', '.reporter', '.author', '[rel="author"]']
                    },
                    published: {
                        primary: 'p.desktopDetailPTime',
                        fallback: ['time[datetime]', 'meta[property="article:published_time"]', '.desktopDetailPTime']
                    },
                    image: {
                        primary: '.desktopDetailPhoto img',
                        fallback: ['meta[property="og:image"]', 'meta[itemprop="image"]', 'article img', 'img']
                    },
                    content: {
                        primary: '.desktopDetailBody p',
                        fallback: ['.desktopDetailBody', 'article p', '.content p']
                    }
                }
            }
        },
        prothomalo: {
            name: 'Prothom Alo',
            baseUrl: 'https://www.prothomalo.com/',
            homepageUrl: 'https://www.prothomalo.com/',
            selectors: {
                ticker: {
                    primary: '.story-card a',
                    fallback: ['a[href*="prothomalo.com"]']
                },
                article: {
                    title: {
                        primary: 'h1[itemprop="headline"]',
                        fallback: ['h1.title', 'h1']
                    },
                    subtitle: {
                        primary: '.article-summary',
                        fallback: ['.summary', '.subtitle']
                    },
                    author: {
                        primary: '[itemprop="author"]',
                        fallback: ['.author-name', '.byline']
                    },
                    published: {
                        primary: 'time[datetime]',
                        fallback: ['.published-date', '.date']
                    },
                    image: {
                        primary: '[itemprop="image"] img',
                        fallback: ['article img', '.featured-image img']
                    },
                    content: {
                        primary: '[itemprop="articleBody"] p',
                        fallback: ['.article-content p', 'article p', '.content p']
                    }
                }
            }
        },
        prothomalo_latest: {
            name: 'Prothom Alo (Latest)',
            baseUrl: 'https://www.prothomalo.com/',
            homepageUrl: 'https://www.prothomalo.com/collection/latest',
            selectors: {
                ticker: {
                    // The latest collection page contains multiple card types; `a.title-link` is consistent.
                    primary: 'h3.headline-title a.title-link[href]',
                    title: 'span.tilte-no-link-parent',
                    link: 'a',
                    fallback: ['a.title-link[href*="prothomalo.com"]', 'a[href*="prothomalo.com"]']
                },
                article: {
                    title: {
                        primary: '.story-title-info h1, h1[data-title-0]',
                        fallback: ['h1[itemprop="headline"]', 'h1', 'meta[property="og:title"]', 'title']
                    },
                    subtitle: {
                        primary: 'meta[name="description"]',
                        fallback: ['meta[property="og:description"]']
                    },
                    author: {
                        primary: '.author-location, [itemprop="author"], .author-name',
                        fallback: ['.byline', '[rel="author"]']
                    },
                    published: {
                        primary: 'time[datetime]',
                        fallback: ['meta[property="article:published_time"]', 'time', '.published-time', '.published-at']
                    },
                    image: {
                        primary: '.story-page-hero img, figure img',
                        fallback: ['meta[property="og:image"]', '[itemprop="image"] img', 'img']
                    },
                    content: {
                        // Works for nagorik.prothomalo.com and many prothomalo story pages.
                        primary: '.story-element-text p',
                        fallback: ['[itemprop="articleBody"] p', 'article p', '.story-content p', '.content p']
                    }
                }
            }
        },
        thedailystar_today: {
            name: "The Daily Star (Bangla) - Today's News",
            pipeline: 'autocontent_articles',
            baseUrl: 'https://bangla.thedailystar.net/',
            homepageUrl: 'https://bangla.thedailystar.net/todays-news',
            selectors: {
                ticker: {
                    primary: '.view-today-s-news .views-row h3.card-title a[href]',
                    fallback: ['.view-today-s-news .views-row a[href*=\"/news/\"]']
                },
                article: {
                    title: {
                        primary: '.node-content h1',
                        fallback: ['h1', 'meta[property=\"og:title\"]', 'title']
                    },
                    subtitle: {
                        primary: 'meta[name=\"description\"]',
                        fallback: ['meta[property=\"og:description\"]']
                    },
                    author: {
                        primary: '.block-author-info-block .font-medium',
                        fallback: ['.block-author-info-block span']
                    },
                    published: {
                        primary: '.block-article-meta-block span.text-gray-600',
                        fallback: ['.block-article-meta-block span']
                    },
                    image: {
                        primary: '.block-news-featured-image img',
                        fallback: ['meta[property=\"og:image\"]']
                    },
                    content: {
                        primary: '.block-field-blocknodenewsbody .text-formatted',
                        fallback: ['article', 'main']
                    }
                }
            }
        },
        gsmarena_news: {
            name: 'GSMArena News',
            pipeline: 'autocontent_articles',
            baseUrl: 'https://www.gsmarena.com/',
            homepageUrl: 'https://www.gsmarena.com/',
            urlRules: {
                exclude: [
                    /sub_confirmation/i,
                    /\/tipus\.php3/i,
                    /\/rss/i,
                    /\/compare\.php3/i,
                    /\/makers\.php3/i,
                    /\/glossary\.php3/i,
                    /\/privacy/i,
                    /\/contact/i
                ]
            },
            selectors: {
                ticker: {
                    primary: '.news-column-index .news-item > a[href]',
                    title: 'h3',
                    link: 'a',
                    fallback: ['.news-column-index .news-item a[href]']
                },
                article: {
                    title: {
                        primary: 'h1.article-info-name',
                        fallback: ['meta[property="og:title"]', 'title', 'h1']
                    },
                    subtitle: {
                        primary: 'meta[name="description"]',
                        fallback: ['meta[property="og:description"]']
                    },
                    author: {
                        primary: '.article-tags .reviewer a',
                        fallback: ['.reviewer a', 'a[href*="author.php3"]']
                    },
                    published: {
                        primary: '.article-tags .dtreviewed',
                        fallback: ['meta[property="article:published_time"]', 'time[datetime]']
                    },
                    image: {
                        primary: 'meta[property="og:image"]',
                        fallback: ['.center-stage-background', 'img.center-stage-background']
                    },
                    content: {
                        primary: '#review-body p:not(.image-row):not(.article-source)',
                        fallback: ['#review-body p', 'article p']
                    }
                }
            }
        },
        gsmarena_devices: {
            name: 'GSMArena Latest Devices',
            pipeline: 'mobiles_direct',
            baseUrl: 'https://www.gsmarena.com/',
            homepageUrl: 'https://www.gsmarena.com/',
            urlRules: {
                exclude: [
                    /sub_confirmation/i,
                    /\/tipus\.php3/i,
                    /\/rss/i,
                    /\/privacy/i,
                    /\/contact/i
                ]
            },
            selectors: {
                ticker: {
                    primary: '.module.module-phones.module-latest a.module-phones-link[href]',
                    fallback: ['a.module-phones-link[href]']
                },
                article: {
                    title: { primary: 'h1.specs-phone-name-title[data-spec="modelname"]', fallback: ['h1', 'meta[property="og:title"]', 'title'] },
                    subtitle: { primary: 'meta[name="description"]', fallback: ['meta[property="og:description"]'] },
                    author: { primary: '', fallback: [] },
                    published: { primary: '', fallback: [] },
                    image: { primary: '.specs-photo-main img', fallback: ['meta[property="og:image"]'] },
                    content: { primary: '#specs-list', fallback: ['#specs-list table'] }
                }
            }
        },
        gsmarena_bd_devices: {
            name: 'GSMArena BD Devices',
            pipeline: 'mobiles_direct',
            baseUrl: 'https://www.gsmarena.com.bd/',
            homepageUrl: 'https://www.gsmarena.com.bd/',
            urlRules: {
                exclude: [
                    /\/privacy/i,
                    /\/contact/i
                ]
            },
            selectors: {
                ticker: {
                    // Latest devices grid items
                    // Note: `.product-thumb` contains two direct anchors (device + "View Details"). Exclude `.vdetails`.
                    primary: '.area .product-thumb > a[href][title]:not(.vdetails)',
                    title: '.mobile_name',
                    link: 'a',
                    fallback: ['.product-thumb > a[href][title]:not(.vdetails)']
                },
                article: {
                    title: { primary: 'h1.ptitle', fallback: ['h1', 'meta[property="og:title"]', 'title'] },
                    subtitle: { primary: 'meta[name="description"]', fallback: ['meta[property="og:description"]'] },
                    author: { primary: '', fallback: [] },
                    published: { primary: '', fallback: [] },
                    image: { primary: 'img.img-responsive', fallback: ['meta[property="og:image"]'] },
                    content: { primary: 'table.table_specs', fallback: ['.table_specs'] }
                }
            }
        }
    },

    // Default selectors (fallback if source not found)
    defaultSelectors: {
        // Ticker/Homepage selectors
        ticker: {
            primary: '.news-scroll-content a',
            fallback: [
                '.news-scroll a',
                'a[href*="bangla.bdnews24.com"]'
            ]
        },
        // Article page selectors
        article: {
            title: {
                primary: '.details-title h1',
                fallback: ['h1', '.article-title h1', '.title h1']
            },
            subtitle: {
                primary: '.details-title h2',
                fallback: ['h2.subtitle', '.article-subtitle h2']
            },
            author: {
                primary: '.author',
                fallback: ['.author-name', '.byline', '[rel="author"]']
            },
            published: {
                primary: '.pub-up span',
                fallback: ['.published-date', '.pub-date', 'time[datetime]']
            },
            image: {
                primary: '.details-img img',
                fallback: ['article img', '.featured-image img', '.main-image img']
            },
            content: {
                primary: '#contentDetails p',
                fallback: [
                    '.details-brief p',
                    'article p',
                    '.article-body p',
                    '.content p'
                ]
            }
        }
    },

    // Helper functions
    getSourceConfig: async function (sourceKey) {
        // First check static sources
        if (this.sources[sourceKey]) {
            return this.sources[sourceKey];
        }

        // Try to load from database presets
        try {
            const db = await import('./services/DatabaseService.js');
            const dbService = db.default;

            if (dbService.isConnected()) {
                const preset = await dbService.fetchWebsitePreset(sourceKey);
                if (preset) {
                    // Cache it
                    this._presetCache.set(sourceKey, preset);
                    return preset;
                }
            }
        } catch (e) {
            // Database not available, use defaults
        }

        return this.sources[this.source.defaultSource];
    },

    getSelectors: function (sourceKey) {
        // Check static sources first
        if (this.sources[sourceKey]?.selectors) {
            return this.sources[sourceKey].selectors;
        }

        // Check cache
        if (this._presetCache.has(sourceKey)) {
            return this._presetCache.get(sourceKey).selectors;
        }

        return this.defaultSelectors;
    },

    // Get all available sources (static + from database)
    getAvailableSources: async function () {
        const sources = Object.keys(this.sources);

        try {
            const db = await import('./services/DatabaseService.js');
            const dbService = db.default;

            if (dbService.isConnected()) {
                const presets = await dbService.fetchAllPresets();
                for (const preset of presets) {
                    if (!sources.includes(preset.preset_key)) {
                        sources.push(preset.preset_key);
                    }
                }
            }
        } catch (e) {
            // Ignore
        }

        return sources;
    },

    // HTTP Client settings
    http: {
        timeout: 5000,
        maxRetries: 3,
        retryDelay: 1000,
        userAgents: [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15'
        ]
    },

    proxy: {
        get list() {
            return parseList(process.env.SCRAPER_PROXIES || process.env.PROXY_LIST || '');
        }
    },

    browser: {
        timeout: 30000,
        clearanceTimeoutMs: 60000,
        headless: 'new',
        userDataDir: process.env.SCRAPER_PUPPETEER_PROFILE || null,
        useStealth: true
    },

    // Validation rules
    validation: {
        minContentLength: 200,
        minParagraphs: 3,
        requiredFields: ['title', 'link']
    },

    // Concurrency settings
    concurrency: {
        maxParallelFetches: 5
    },

    // Database settings (for direct MySQL connection)
    database: {
        get host() {
            return process.env.DB_HOST || process.env.MYSQL_HOST || 'localhost';
        },
        get user() {
            return process.env.DB_USER || process.env.DB_USERNAME || process.env.MYSQL_USER || '';
        },
        get password() {
            return process.env.DB_PASSWORD || process.env.DB_PASS || process.env.MYSQL_PASSWORD || '';
        },
        get database() {
            return process.env.DB_NAME || process.env.DB_DATABASE || process.env.MYSQL_DATABASE || '';
        },
        get port() {
            const port = parseInt(process.env.DB_PORT || process.env.MYSQL_PORT || '3306', 10);
            return Number.isFinite(port) ? port : 3306;
        }
    },

    // AI settings for self-healing
    ai: {
        get enabled() {
            return process.env.AI_ENABLED === 'true';
        },
        get provider() {
            return process.env.AI_PROVIDER || 'claude';
        },
        maxHtmlSize: 20480 // 20KB
    },

    // Logging
    logging: {
        get level() {
            return process.env.LOG_LEVEL || 'info'; // debug, info, warn, error
        },
        get file() {
            return process.env.LOG_FILE || null;
        }
    }
};

/**
 * Configuration Validation
 */
function validateConfig() {
    // Load environment variables before validation
    EnvLoader.load();

    const errors = [];

    // Check database configuration
    const dbConfig = {
        host: process.env.DB_HOST,
        port: process.env.DB_PORT,
        user: process.env.DB_USER,
        password: process.env.DB_PASSWORD,
        database: process.env.DB_NAME
    };

    if (!dbConfig.host) errors.push('DB_HOST environment variable is required');
    if (!dbConfig.user) errors.push('DB_USER environment variable is required');
    if (!dbConfig.database) errors.push('DB_NAME environment variable is required');

    // Validate numeric values
    const port = parseInt(process.env.DB_PORT || '3306');
    if (isNaN(port) || port < 1 || port > 65535) {
        errors.push('DB_PORT must be a valid port number (1-65535)');
    }

    // Validate concurrency settings
    const maxParallel = parseInt(process.env.MAX_PARALLEL_FETCHES || '5');
    if (isNaN(maxParallel) || maxParallel < 1) {
        errors.push('MAX_PARALLEL_FETCHES must be a positive integer');
    }

    // HTTP timeout
    const timeout = parseInt(process.env.HTTP_TIMEOUT || '30000');
    if (isNaN(timeout) || timeout < 1000) {
        errors.push('HTTP_TIMEOUT must be >= 1000ms');
    }

    if (errors.length > 0) {
        const message = `Configuration validation failed:\n${errors.join('\n')}`;
        throw new Error(message);
    }

    return true;
}

// Add validation method to CONFIG
CONFIG._validated = false;

export function validateOnStartup() {
    if (!CONFIG._validated) {
        validateConfig();
        CONFIG._validated = true;
    }
}

export { validateConfig };
export default CONFIG;
