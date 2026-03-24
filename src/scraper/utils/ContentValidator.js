/**
 * ContentValidator
 * Lightweight article validation stub
 */

class ContentValidator {
    constructor(options = {}) {
        this.options = {
            minTitleLength: options.minTitleLength || 5,
            minContentLength: options.minContentLength || 50,
            ...options
        };
    }

    async initialize() {
        return true;
    }

    async validate(article) {
        const issues = [];

        if (!article || typeof article !== 'object') {
            return { isValid: false, issues: ['invalid_article'] };
        }

        if (!article.title || article.title.length < this.options.minTitleLength) {
            issues.push('missing_or_short_title');
        }

        if (!article.content || article.content.length < this.options.minContentLength) {
            issues.push('missing_or_short_content');
        }

        return {
            isValid: issues.length === 0,
            issues,
            article
        };
    }

    async cleanup() {
        return true;
    }
}

export default ContentValidator;
