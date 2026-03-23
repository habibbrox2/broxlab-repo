// Bangladeshi news sources configuration
// 10+ sources with homepage and RSS URLs for fallback.

export default {
    bdnews24: {
        name: 'BD News 24',
        listUrl: 'https://bangla.bdnews24.com/',
        baseUrl: 'https://bangla.bdnews24.com',
        rssUrl: 'https://bangla.bdnews24.com/rss',
        listAnchorWhitelist: ['bangla.bdnews24.com', 'bdnews24.com'],
        articlePathRegex: /\/\d{4}\/\d{2}\/\d{2}\/[^\s/]+/i
    },
    samakal: {
        name: 'Samakal',
        listUrl: 'https://samakal.com/latest/news',
        baseUrl: 'https://samakal.com',
        rssUrl: 'https://samakal.com/feed',
        listAnchorWhitelist: ['samakal.com'],
        articlePathRegex: /\/\w+\/[0-9]{4}-[0-9]{2}-[0-9]{2}\/\d+/i
    },
    prothomalo: {
        name: 'Prothom Alo',
        listUrl: 'https://www.prothomalo.com/',
        baseUrl: 'https://www.prothomalo.com',
        rssUrl: 'https://www.prothomalo.com/rss',
        listAnchorWhitelist: ['prothomalo.com'],
        articlePathRegex: /\/\w+\/\d+/i
    },
    jugantor: {
        name: 'Jugantor',
        listUrl: 'https://www.jugantor.com/latest',
        baseUrl: 'https://www.jugantor.com',
        rssUrl: 'https://www.jugantor.com/rss',
        listAnchorWhitelist: ['jugantor.com'],
        articlePathRegex: /\/\w+\/\d+/i
    },
    banglatribune: {
        name: 'Bangla Tribune',
        listUrl: 'https://www.banglatribune.com/',
        baseUrl: 'https://www.banglatribune.com',
        rssUrl: 'https://www.banglatribune.com/feed',
        listAnchorWhitelist: ['banglatribune.com'],
        articlePathRegex: /\/(\w+-?)+\/\d{4}-\d{2}-\d{2}/i
    },
    dailysun: {
        name: 'Daily Sun',
        listUrl: 'https://www.daily-sun.com/latest-news',
        baseUrl: 'https://www.daily-sun.com',
        rssUrl: 'https://www.daily-sun.com/beta/rss',
        listAnchorWhitelist: ['daily-sun.com'],
        articlePathRegex: /\/print\/\d+/i
    },
    kalerkantho: {
        name: 'Kaler Kantho',
        listUrl: 'https://www.kalerkantho.com/',
        baseUrl: 'https://www.kalerkantho.com',
        rssUrl: 'https://www.kalerkantho.com/feed',
        listAnchorWhitelist: ['kalerkantho.com'],
        articlePathRegex: /\/\w+\/\d+/i
    },
    amardesh: {
        name: 'Amar Desh',
        listUrl: 'https://www.amardesh.com/',
        baseUrl: 'https://www.amardesh.com',
        rssUrl: 'https://www.amardesh.com/feed',
        listAnchorWhitelist: ['amardesh.com'],
        articlePathRegex: /\/\w+\/\d+/i
    },
    dhakatribune: {
        name: 'Dhaka Tribune',
        listUrl: 'https://www.dhakatribune.com/',
        baseUrl: 'https://www.dhakatribune.com',
        rssUrl: 'https://www.dhakatribune.com/feed',
        listAnchorWhitelist: ['dhakatribune.com'],
        articlePathRegex: /\/[0-9]{4}\/\d{2}\/\d{2}\/\w+/i
    },
    financialexpress: {
        name: 'The Financial Express',
        listUrl: 'https://thefinancialexpress.com.bd/',
        baseUrl: 'https://thefinancialexpress.com.bd',
        rssUrl: 'https://thefinancialexpress.com.bd/rss',
        listAnchorWhitelist: ['thefinancialexpress.com.bd'],
        articlePathRegex: /\/[\w-]+\/[\d]+/i
    }
};
