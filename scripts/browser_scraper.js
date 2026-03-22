import puppeteer from 'puppeteer';

const url = process.argv[2];
if (!url) {
    process.stderr.write('missing_url');
    process.exit(1);
}

const timeout = Number(process.env.BROWSER_TIMEOUT || '30000');
const proxy = process.env.BROWSER_PROXY || '';
const userAgent = process.env.BROWSER_USER_AGENT || '';

const args = [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage'
];
if (proxy) {
    args.push(`--proxy-server=${proxy}`);
}

let browser;
try {
    browser = await puppeteer.launch({
        headless: 'new',
        args
    });

    const page = await browser.newPage();
    if (userAgent) {
        await page.setUserAgent(userAgent);
    }
    await page.goto(url, { waitUntil: 'networkidle2', timeout });
    const html = await page.content();
    process.stdout.write(html || '');
} catch (error) {
    process.stderr.write(error?.message || 'browser_scraper_failed');
    process.exit(1);
} finally {
    if (browser) {
        try {
            await browser.close();
        } catch (e) {
            // ignore
        }
    }
}
