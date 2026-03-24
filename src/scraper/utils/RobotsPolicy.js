/**
 * RobotsPolicy Utility
 * Minimal robots.txt parser with in-memory cache.
 */

import axios from 'axios';
import Logger from './Logger.js';
import CONFIG from '../config.js';

class RobotsPolicy {
    constructor() {
        this.cache = new Map();
    }

    _getCacheTtlMs() {
        const ttl = Number(process.env.SCRAPER_ROBOTS_CACHE_TTL || CONFIG.scraper?.robotsCacheTtlMs || 3600000);
        return Number.isFinite(ttl) && ttl > 0 ? ttl : 3600000;
    }

    _getUserAgent(userAgent) {
        if (userAgent && typeof userAgent === 'string') return userAgent;
        return 'BroxLabScraper';
    }

    _getRobotsUrl(targetUrl) {
        const url = new URL(targetUrl);
        return `${url.protocol}//${url.host}/robots.txt`;
    }

    async _fetchRobots(targetUrl) {
        const robotsUrl = this._getRobotsUrl(targetUrl);
        try {
            const response = await axios.get(robotsUrl, {
                timeout: 5000,
                validateStatus: (status) => status >= 200 && status < 500
            });

            if (response.status >= 400) {
                return '';
            }

            return String(response.data || '');
        } catch (error) {
            Logger.warn('Robots fetch failed', { url: robotsUrl, error: error.message });
            return '';
        }
    }

    _parseRobots(text) {
        const lines = String(text || '').split(/\r?\n/);
        const groups = [];
        let currentGroup = null;

        const flushGroup = () => {
            if (currentGroup && currentGroup.userAgents.length > 0) {
                groups.push(currentGroup);
            }
            currentGroup = null;
        };

        for (const rawLine of lines) {
            const line = rawLine.split('#')[0].trim();
            if (!line) continue;

            const parts = line.split(':');
            if (parts.length < 2) continue;

            const field = parts[0].trim().toLowerCase();
            const value = parts.slice(1).join(':').trim();

            if (field === 'user-agent') {
                if (!currentGroup) {
                    currentGroup = { userAgents: [], rules: [] };
                }
                currentGroup.userAgents.push(value.toLowerCase());
                continue;
            }

            if (field === 'disallow' || field === 'allow') {
                if (!currentGroup) {
                    currentGroup = { userAgents: ['*'], rules: [] };
                }
                currentGroup.rules.push({
                    type: field,
                    path: value
                });
                continue;
            }

            // If a new group-like section starts, flush previous.
            if (field === 'sitemap' || field === 'crawl-delay') {
                flushGroup();
            }
        }

        flushGroup();
        return groups;
    }

    _matchRule(path, rulePath) {
        if (rulePath === '') return false;
        if (rulePath === '/') return true;
        return path.startsWith(rulePath);
    }

    _isAllowedByGroups(path, userAgent, groups) {
        const ua = String(userAgent || '').toLowerCase();
        const applicable = groups.filter(group => group.userAgents.some(agent => agent === '*' || ua.includes(agent)));

        if (applicable.length === 0) {
            return { allowed: true, reason: 'no_matching_robots_group' };
        }

        let bestRule = null;
        for (const group of applicable) {
            for (const rule of group.rules) {
                if (!rule.path) continue;
                if (this._matchRule(path, rule.path)) {
                    if (!bestRule || rule.path.length > bestRule.path.length) {
                        bestRule = rule;
                    }
                }
            }
        }

        if (!bestRule) {
            return { allowed: true, reason: 'no_robots_rule_match' };
        }

        if (bestRule.type === 'allow') {
            return { allowed: true, reason: 'robots_allow' };
        }

        return { allowed: false, reason: 'robots_disallow' };
    }

    async isAllowed(url, userAgent) {
        const enforce = String(process.env.SCRAPER_ROBOTS_ENFORCE || CONFIG.scraper?.robotsEnforce || 'true').toLowerCase() !== 'false';
        if (!enforce) {
            return { allowed: true, reason: 'robots_disabled' };
        }

        const targetUrl = String(url || '').trim();
        if (!targetUrl) {
            return { allowed: false, reason: 'invalid_url' };
        }

        const ttl = this._getCacheTtlMs();
        const host = new URL(targetUrl).host;
        const cached = this.cache.get(host);

        if (cached && (Date.now() - cached.fetchedAt) < ttl) {
            return this._isAllowedByGroups(new URL(targetUrl).pathname || '/', this._getUserAgent(userAgent), cached.groups);
        }

        const robotsText = await this._fetchRobots(targetUrl);
        const groups = this._parseRobots(robotsText);
        this.cache.set(host, { fetchedAt: Date.now(), groups });

        return this._isAllowedByGroups(new URL(targetUrl).pathname || '/', this._getUserAgent(userAgent), groups);
    }
}

export default new RobotsPolicy();
