/**
 * Multi-Source Scraper Configuration
 * Configuration for the multi-agent web scraping system
 * Supports multiple sources (bdnews24, prothomalo, etc.)
 * Can load selectors from website_presets table
 */

import DatabaseService from './services/DatabaseService.js';

const CONFIG = {
    // Cache for dynamically loaded presets
    _presetCache: new Map(),
    // Default source configuration
    source: {
        // Can be overridden by command line or environment
        defaultSource: process.env.SCRAPER_SOURCE || 'bdnews24'
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
    getSourceConfig: async function(sourceKey) {
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

    getSelectors: function(sourceKey) {
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
    getAvailableSources: async function() {
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
        host: process.env.DB_HOST || 'localhost',
        user: process.env.DB_USER || 'root',
        password: process.env.DB_PASSWORD || '',
        database: process.env.DB_NAME || 'broxbhai',
        port: process.env.DB_PORT || 3306
    },

    // AI settings for self-healing
    ai: {
        enabled: process.env.AI_ENABLED === 'true',
        provider: process.env.AI_PROVIDER || 'claude',
        maxHtmlSize: 20480 // 20KB
    },

    // Logging
    logging: {
        level: process.env.LOG_LEVEL || 'info', // debug, info, warn, error
        file: process.env.LOG_FILE || null
    }
};

export default CONFIG;