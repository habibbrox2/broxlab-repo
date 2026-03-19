/**
 * DiffDetector Agent
 * Compares ticker links with database to identify new articles
 */

import Logger from '../utils/Logger.js';

class DiffDetector {
    constructor() {
        this.existingLinks = new Set();
        this.db = null;
    }

    /**
     * Initialize with existing links from database
     * @param {Array} existingLinks - Array of existing article links
     */
    async initialize(existingLinks = []) {
        this.existingLinks = new Set(existingLinks.map(l => this.normalizeLink(l)));
        Logger.info(`DiffDetector initialized with ${this.existingLinks.size} existing links`);
    }

    /**
     * Initialize from database
     * @param {Object} dbService - Database service instance
     */
    async initializeFromDb(dbService) {
        if (!dbService) {
            Logger.warn('No database service provided, skipping DB initialization');
            return;
        }

        try {
            const existingLinks = await dbService.getExistingLinks();
            this.existingLinks = new Set(existingLinks.map(l => this.normalizeLink(l)));
            Logger.info(`DiffDetector initialized from DB with ${this.existingLinks.size} existing links`);
        } catch (error) {
            Logger.error('Failed to initialize DiffDetector from DB', { error: error.message });
        }
    }

    /**
     * Find new links that don't exist in the database
     * @param {Array} links - Array of link objects {title, link}
     * @returns {Array} - Array of new links
     */
    findNewLinks(links) {
        const newLinks = [];

        for (const item of links) {
            const normalizedLink = this.normalizeLink(item.link);

            if (!this.existingLinks.has(normalizedLink)) {
                newLinks.push({
                    ...item,
                    link: normalizedLink
                });
                // Add to existing to prevent duplicates within same batch
                this.existingLinks.add(normalizedLink);
            }
        }

        Logger.info(`Found ${newLinks.length} new links out of ${links.length} total`);

        return newLinks;
    }

    /**
     * Normalize link for comparison
     */
    normalizeLink(link) {
        if (!link) return '';

        try {
            // Remove common tracking parameters
            const url = new URL(link);
            const trackingParams = ['utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'gclid'];

            for (const param of trackingParams) {
                url.searchParams.delete(param);
            }

            // Remove trailing slashes for consistent comparison
            let normalized = url.toString();
            if (normalized.endsWith('/')) {
                normalized = normalized.slice(0, -1);
            }

            return normalized.toLowerCase();
        } catch (e) {
            // If URL parsing fails, return lowercase original
            return link.toLowerCase();
        }
    }

    /**
     * Add links to existing set (for in-memory tracking)
     */
    addLinks(links) {
        for (const link of links) {
            this.existingLinks.add(this.normalizeLink(link));
        }
    }

    /**
     * Check if a single link exists
     */
    hasLink(link) {
        return this.existingLinks.has(this.normalizeLink(link));
    }

    /**
     * Get count of tracked links
     */
    getLinkCount() {
        return this.existingLinks.size;
    }

    /**
     * Reset the detector
     */
    reset() {
        this.existingLinks.clear();
    }
}

export default DiffDetector;