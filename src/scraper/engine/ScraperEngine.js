import HttpClient from '../utils/HttpClient.js';
import logger from '../../utils/scraperLogger.js';
import ContentProcessor from '../ContentProcessor.js';

class ScraperEngine {
    async scrape(url, options = {}) {
        const startedAt = Date.now();

        try {
            const result = await HttpClient.fetchHtml(url, {
                proxyEnabled: options.proxyEnabled,
                proxyList: options.proxyList,
                proxyUrl: options.proxyUrl,
                timeout: options.timeout
            });

            if (!result.success) {
                return { success: false, error: result.error || 'fetch_failed', url };
            }

            const processed = ContentProcessor.process(result.html, url);

            const result = {
                success: true,
                url,
                proxy_used: options.proxyUrl || '',
                timestamp: new Date().toISOString(),
                ...processed
            };
            if (options.return_html) {
                result.raw_html = result.html;
            }
            return result;
        } catch (error) {
            logger.warn('Scrape failed', { url, error: error?.message });
            return { success: false, error: error?.message || 'scrape_failed', url };
        }
    }
}

export default new ScraperEngine();
