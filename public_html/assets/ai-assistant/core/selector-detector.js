/**
 * AI Selector Detector
 * Uses Puter AI to analyze HTML and detect CSS selectors for web scraping
 */

import { getPuterClient } from './puter.js';

/**
 * Analyze HTML structure and detect appropriate CSS selectors using AI
 * @param {string} html - Raw HTML content
 * @param {string} url - Source URL for context
 * @param {Object} options - Configuration options
 * @returns {Promise<Object>} Detected selectors
 */
export async function detectSelectorsWithAI(html, url, options = {}) {
    const {
        maxItems = 5,
        includePatterns = true
    } = options;

    try {
        const puter = await getPuterClient();

        // Prepare a sample of the HTML for analysis (first 50KB to avoid token limits)
        const htmlSample = html.substring(0, 50000);

        const prompt = `Analyze this HTML and detect CSS selectors for scraping news/articles.
    
URL: ${url}

HTML Sample (first 50KB):
${htmlSample}

Return a JSON object with these exact fields:
{
  "list_container": "CSS selector for the main container that holds all article items",
  "list_item": "CSS selector for individual article items within the container",
  "list_title": "CSS selector for article titles/headlines (usually <a> tags)",
  "list_link": "CSS selector for article links (href attribute)",
  "list_date": "CSS selector for publication dates",
  "list_image": "CSS selector for article thumbnail images",
  "title": "CSS selector for article title on detail page",
  "content": "CSS selector for main article content",
  "image": "CSS selector for featured image on detail page",
  "excerpt": "CSS selector for article excerpt/summary",
  "date": "CSS selector for publication date on detail page",
  "author": "CSS selector for author name"
}

Guidelines:
- Prefer class selectors (.class-name) over ID selectors (#id) for flexibility
- Use descendant selectors (parent child) when needed (e.g., "article h2")
- Look for semantic HTML5 elements (article, section, time, etc.)
- Check for common patterns like "card", "item", "post", "article", "story", "news"
- For list pages, find the parent container with repeated children
- For dates, check <time> tags and datetime attributes
- For images, check <img> tags and og:image meta tags

Only return valid CSS selectors or empty strings. Be specific but not overly specific.`;

        const response = await puter.ai.chat([
            {
                role: 'user',
                content: prompt
            }
        ], {
            model: 'gpt-4.1-mini'
        });

        // Extract the text response
        const responseText = response?.text || response?.message?.content || '';

        // Parse JSON from the response
        const selectors = parseSelectorResponse(responseText);

        // Validate and clean selectors
        return validateSelectors(selectors);

    } catch (error) {
        console.error('AI Selector Detection Error:', error);
        throw new Error(`AI selector detection failed: ${error.message}`);
    }
}

/**
 * Parse JSON selectors from AI response
 */
function parseSelectorResponse(responseText) {
    try {
        // Try to find JSON in the response
        const jsonMatch = responseText.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
            return JSON.parse(jsonMatch[0]);
        }
        return {};
    } catch (error) {
        console.error('Failed to parse selector response:', error);
        return {};
    }
}

/**
 * Validate and clean selector values
 */
function validateSelectors(selectors) {
    const requiredFields = [
        'list_container', 'list_item', 'list_title', 'list_link', 'list_date', 'list_image',
        'title', 'content', 'image', 'excerpt', 'date', 'author'
    ];

    const validated = {};

    for (const field of requiredFields) {
        const value = selectors[field];
        if (typeof value === 'string' && value.trim()) {
            validated[field] = value.trim();
        } else {
            validated[field] = '';
        }
    }

    return validated;
}

/**
 * Test selectors against HTML to verify they work
 */
export async function testSelectors(html, selectors) {
    const results = {
        list_container: false,
        list_item: false,
        list_title: false,
        list_link: false,
        list_date: false,
        list_image: false,
        title: false,
        content: false,
        image: false,
        excerpt: false,
        date: false,
        author: false
    };

    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // Test each selector
        for (const [field, selector] of Object.entries(selectors)) {
            if (!selector) continue;

            try {
                const elements = doc.querySelectorAll(selector);
                if (elements.length > 0) {
                    results[field] = true;
                }
            } catch (e) {
                // Invalid selector, ignore
            }
        }
    } catch (error) {
        console.error('Selector testing error:', error);
    }

    return results;
}

/**
 * Generate a human-readable explanation of detected selectors
 */
export function explainSelectors(selectors) {
    const explanations = [];

    if (selectors.list_container) {
        explanations.push(`📦 Container: Found a list with selector "${selectors.list_container}"`);
    }
    if (selectors.list_item) {
        explanations.push(`📝 Items: Individual articles use "${selectors.list_item}"`);
    }
    if (selectors.list_title) {
        explanations.push(`📰 Titles: Headlines selected with "${selectors.list_title}"`);
    }
    if (selectors.list_date) {
        explanations.push(`📅 Dates: Publication dates via "${selectors.list_date}"`);
    }
    if (selectors.content) {
        explanations.push(`📄 Content: Main article body using "${selectors.content}"`);
    }

    return explanations.join('\n');
}

/**
 * Learn from successful scraping - store selector patterns
 * This is a placeholder for future self-learning capability
 */
export async function learnSelectors(url, selectors, success = true) {
    // In a full implementation, this would:
    // 1. Store successful selector patterns in the database
    // 2. Build a knowledge base of selector patterns per domain
    // 3. Use ML to predict selectors for new URLs

    console.log('Learning selectors for:', url, selectors, 'Success:', success);

    // For now, we just log the data
    // In production, you'd save to a scrape_patterns table
    return {
        stored: true,
        url,
        selectors,
        success
    };
}

/**
 * Get suggested selectors from learned patterns (cache)
 */
export async function getLearnedSelectors(url) {
    // This would query the database for learned patterns
    // For now, return empty to force AI detection
    return null;
}
