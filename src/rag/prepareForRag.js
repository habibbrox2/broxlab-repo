export function prepareForRag(scrapeResult) {
    return {
        success: !!scrapeResult.success,
        url: scrapeResult.url || '',
        title: scrapeResult.title || '',
        content: scrapeResult.content || '',
        metadata: scrapeResult.metadata || {},
        status: scrapeResult.success ? 'success' : 'failed',
        proxy_used: scrapeResult.proxy_used || '',
        timestamp: scrapeResult.timestamp || new Date().toISOString()
    };
}
