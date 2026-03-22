import cheerio from 'cheerio';

class ContentProcessor {
    process(html, url) {
        const $ = cheerio.load(html);
        $('script, style, noscript').remove();

        const title = $('title').first().text().trim() || $('h1').first().text().trim();
        const metadata = {
            description: $('meta[name="description"]').attr('content') || '',
            ogTitle: $('meta[property="og:title"]').attr('content') || '',
            ogDescription: $('meta[property="og:description"]').attr('content') || '',
            ogImage: $('meta[property="og:image"]').attr('content') || ''
        };

        let content = $('article').text().trim();
        if (!content) content = $('main').text().trim();
        if (!content) content = $('body').text().trim();

        const links = [];
        $('a[href]').each((_, el) => {
            if (links.length >= 5) return false;
            const href = $(el).attr('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            try {
                links.push(new URL(href, url).href);
            } catch {
                // ignore malformed
            }
        });

        return {
            url,
            title: title || metadata.ogTitle || '',
            description: metadata.description || metadata.ogDescription || '',
            image: metadata.ogImage || '',
            content,
            links,
            metadata
        };
    }
}

export default new ContentProcessor();
