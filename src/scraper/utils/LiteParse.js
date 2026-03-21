/**
 * LiteParse - Lightweight HTML/Markdown processing for LLM/RAG
 *
 * Used by `src/ai/RAGEngine.js` to:
 * - extract main text from HTML/Markdown
 * - convert HTML to Markdown
 * - chunk content for vector ingestion
 */

import { parseDocument } from 'htmlparser2';
import { findAll, getElementsByTagName, getText } from 'domutils';
import { marked } from 'marked';
import TurndownService from 'turndown';
import he from 'he';
import Logger from './Logger.js';

marked.setOptions({ gfm: true, breaks: true });

const turndown = new TurndownService({
    headingStyle: 'atx',
    codeBlockStyle: 'fenced',
});

class LiteParse {
    constructor(options = {}) {
        this.options = {
            minTextLength: options.minTextLength ?? 20,
            maxTextLength: options.maxTextLength ?? 50000,
            chunkSize: options.chunkSize ?? 1000,
            chunkOverlap: options.chunkOverlap ?? 200,
        };

        this.stats = {
            parseTimeMs: 0,
            chunksCreated: 0,
            textExtractedChars: 0,
        };
    }

    parseHTML(html) {
        const start = Date.now();

        try {
            if (!html || typeof html !== 'string') return null;

            const cleaned = html
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<noscript[\s\S]*?<\/noscript>/gi, ' ')
                // Ensure text boundaries across adjacent tags (e.g., </h1><p>).
                .replace(/></g, '> <');

            const document = parseDocument(cleaned);
            this.stats.parseTimeMs += Date.now() - start;
            return document;
        } catch (error) {
            this.stats.parseTimeMs += Date.now() - start;
            Logger.error('LiteParse: parseHTML failed', { error: error.message });
            return null;
        }
    }

    parseMarkdown(markdown) {
        try {
            if (!markdown || typeof markdown !== 'string') return '';
            return marked.parse(markdown);
        } catch (error) {
            return '';
        }
    }

    extractText(node, options = {}) {
        try {
            if (!node) return '';

            const minLength =
                Number.isFinite(options.minLength) ? options.minLength : this.options.minTextLength;

            let text = getText(node);
            text = he.decode(text || '');
            text = text.replace(/\s+/g, ' ').trim();

            if (text.length > this.options.maxTextLength) {
                text = text.slice(0, this.options.maxTextLength);
            }

            if (text.length < minLength) {
                return '';
            }

            this.stats.textExtractedChars = text.length;
            return text;
        } catch (error) {
            return '';
        }
    }

    extractContent(html) {
        const document = this.parseHTML(html);
        if (!document) return '';

        // Prefer <body>, but support fragments without it.
        const bodies = getElementsByTagName('body', document, true);
        const targetNode = bodies.length > 0 ? bodies[0] : document;

        return this.extractText(targetNode);
    }

    htmlToMarkdown(html) {
        try {
            if (!html || typeof html !== 'string') return '';
            return turndown.turndown(html);
        } catch (error) {
            return '';
        }
    }

    extractStructuredData(html) {
        const document = this.parseHTML(html);
        if (!document) return null;

        const data = {
            title: '',
            description: '',
            links: [],
            images: [],
            headings: [],
            paragraphs: [],
        };

        const titleTags = getElementsByTagName('title', document, true);
        if (titleTags.length > 0) {
            data.title = this.extractText(titleTags[0], { minLength: 0 });
        }

        const metaTags = findAll(
            (el) => el?.type === 'tag' && el?.name === 'meta',
            document,
            true
        );

        const getMeta = (predicate) => {
            const tag = metaTags.find(predicate);
            const content = tag?.attribs?.content;
            return typeof content === 'string' ? content.trim() : '';
        };

        if (!data.title) {
            data.title =
                getMeta((m) => (m.attribs?.property || '').toLowerCase() === 'og:title') ||
                getMeta((m) => (m.attribs?.name || '').toLowerCase() === 'twitter:title') ||
                '';
        }

        data.description =
            getMeta((m) => (m.attribs?.name || '').toLowerCase() === 'description') ||
            getMeta((m) => (m.attribs?.property || '').toLowerCase() === 'og:description') ||
            getMeta((m) => (m.attribs?.name || '').toLowerCase() === 'twitter:description') ||
            '';

        const headingNodes = [
            ...getElementsByTagName('h1', document, true),
            ...getElementsByTagName('h2', document, true),
            ...getElementsByTagName('h3', document, true),
        ];

        for (const node of headingNodes) {
            const text = this.extractText(node, { minLength: 0 });
            if (text) data.headings.push(text);
            if (data.headings.length >= 50) break;
        }

        const paragraphNodes = getElementsByTagName('p', document, true);
        for (const node of paragraphNodes) {
            const text = this.extractText(node);
            if (text) data.paragraphs.push(text);
            if (data.paragraphs.length >= 200) break;
        }

        const linkNodes = getElementsByTagName('a', document, true);
        for (const node of linkNodes) {
            const href = node?.attribs?.href ? String(node.attribs.href).trim() : '';
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) continue;

            const text = this.extractText(node, { minLength: 0 });
            data.links.push({ href, text: text || '' });
            if (data.links.length >= 200) break;
        }

        const imageNodes = getElementsByTagName('img', document, true);
        for (const node of imageNodes) {
            const src = node?.attribs?.src ? String(node.attribs.src).trim() : '';
            if (!src) continue;

            const alt = node?.attribs?.alt ? String(node.attribs.alt).trim() : '';
            data.images.push({ src, alt });
            if (data.images.length >= 200) break;
        }

        return data;
    }

    chunkText(text, options = {}) {
        const chunkSize = options.chunkSize ?? this.options.chunkSize;
        const chunkOverlap = options.chunkOverlap ?? this.options.chunkOverlap;

        if (!text || typeof text !== 'string') return [];
        if (text.length <= chunkSize) {
            this.stats.chunksCreated = text.trim() ? 1 : 0;
            return text.trim() ? [text.trim()] : [];
        }

        const chunks = [];
        let start = 0;

        while (start < text.length) {
            let end = Math.min(start + chunkSize, text.length);

            // Prefer breaking on whitespace when possible.
            if (end < text.length) {
                const window = text.slice(start, end);
                const lastSpace = window.lastIndexOf(' ');
                if (lastSpace > Math.floor(chunkSize * 0.6)) {
                    end = start + lastSpace;
                }
            }

            const chunk = text.slice(start, end).trim();
            if (chunk) chunks.push(chunk);

            if (end >= text.length) break;

            start = Math.max(0, end - chunkOverlap);
        }

        this.stats.chunksCreated = chunks.length;
        return chunks;
    }

    async parseURL(url, html) {
        const content = this.extractContent(html);
        const structured = this.extractStructuredData(html);
        const markdown = this.htmlToMarkdown(html);
        const chunks = this.chunkText(content);

        const wordCount = content ? content.split(/\s+/).filter(Boolean).length : 0;

        return {
            url,
            title: structured?.title || '',
            description: structured?.description || '',
            content,
            markdown,
            chunks,
            chunkCount: chunks.length,
            wordCount,
        };
    }

    getStats() {
        return { ...this.stats };
    }
}

export default new LiteParse();
