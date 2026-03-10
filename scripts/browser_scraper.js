/**
 * browser_scraper.js
 * Basic Node.js script to fetch HTML from a URL using Puppeteer.
 * Usage: node browser_scraper.js <url>
 */
const puppeteer = require('puppeteer');

(async () => {
    const url = process.argv[2];
    if (!url) {
        console.error('URL is required.');
        process.exit(1);
    }

    let browser;
    try {
        browser = await puppeteer.launch({
            headless: "new",
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        const page = await browser.newPage();

        // Emulate a standard desktop user agent
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        await page.goto(url, {
            waitUntil: 'networkidle2',
            timeout: 30000
        });

        // Get the full page content
        const html = await page.content();
        console.log(html);

    } catch (error) {
        console.error('Scraping error:', error.message);
        process.exit(1);
    } finally {
        if (browser) await browser.close();
    }
})();
