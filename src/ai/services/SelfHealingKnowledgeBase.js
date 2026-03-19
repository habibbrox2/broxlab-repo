/**
 * SelfHealingKnowledgeBase Service
 * Automatically monitors and improves Knowledge Base quality
 * 
 * Features:
 * - Quality scoring and monitoring
 * - Auto-improvement of low-quality entries
 * - Outdated content detection
 * - Duplicate detection and merging
 * - Usage-based content suggestions
 */

import { knowledgeBase } from './KnowledgeBase.js';
import { aiRouter } from '../AIRouter.js';
import Logger from '../utils/Logger.js';

class SelfHealingKnowledgeBase {
    constructor(options = {}) {
        this.enabled = options.enabled ?? true;
        this.autoImprove = options.autoImprove ?? false;
        this.qualityThreshold = options.qualityThreshold ?? 50;
        this.batchSize = options.batchSize ?? 10;
        this.lookbackDays = options.lookbackDays ?? 30;
    }

    /**
     * Run health check on KB - analyze all entries
     */
    async runHealthCheck() {
        Logger.info('Starting KB health check...');

        try {
            // Get KB entries to analyze
            const entries = await this.getKBEntriesForAnalysis();

            const results = {
                total: entries.length,
                healthy: 0,
                needsImprovement: 0,
                outdated: 0,
                duplicates: 0,
                improved: 0,
                errors: []
            };

            // Analyze each entry
            for (const entry of entries) {
                try {
                    const analysis = await this.analyzeEntry(entry);

                    if (analysis.qualityScore >= this.qualityThreshold) {
                        results.healthy++;
                    } else if (analysis.isOutdated) {
                        results.outdated++;
                        if (this.autoImprove) {
                            await this.improveEntry(entry, analysis);
                            results.improved++;
                        }
                    } else {
                        results.needsImprovement++;
                        if (this.autoImprove) {
                            await this.improveEntry(entry, analysis);
                            results.improved++;
                        }
                    }
                } catch (error) {
                    results.errors.push({
                        id: entry.id,
                        error: error.message
                    });
                }
            }

            // Check for duplicates
            const duplicates = await this.detectDuplicates(entries);
            results.duplicates = duplicates.length;

            Logger.info('KB health check completed', results);
            return results;
        } catch (error) {
            Logger.error('KB health check failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Get KB entries for analysis (from PHP API)
     */
    async getKBEntriesForAnalysis() {
        try {
            // Fetch from PHP KB API
            const response = await fetch(`${process.env.PHP_API_URL || 'http://localhost'}/api/admin/ai-knowledge/list`, {
                headers: {
                    'Authorization': `Bearer ${process.env.PHP_API_TOKEN || ''}`
                }
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch KB: ${response.status}`);
            }

            const data = await response.json();
            return data.knowledge || [];
        } catch (error) {
            Logger.warn('Could not fetch from PHP KB, using local fallback', { error: error.message });
            return [];
        }
    }

    /**
     * Analyze a single KB entry
     */
    async analyzeEntry(entry) {
        const analysis = {
            qualityScore: 0,
            isOutdated: false,
            issues: [],
            suggestions: []
        };

        // Check content length
        const contentLength = (entry.content || '').length;
        if (contentLength < 50) {
            analysis.issues.push('Content too short');
            analysis.qualityScore += 10;
        } else if (contentLength > 5000) {
            analysis.issues.push('Content too long - consider splitting');
            analysis.qualityScore += 30;
        } else {
            analysis.qualityScore += 40;
        }

        // Check for required fields
        if (!entry.title || entry.title.length < 5) {
            analysis.issues.push('Title missing or too short');
            analysis.qualityScore += 20;
        } else {
            analysis.qualityScore += 20;
        }

        if (!entry.category) {
            analysis.issues.push('Missing category');
            analysis.qualityScore += 15;
        } else {
            analysis.qualityScore += 15;
        }

        // Check if outdated (older than lookbackDays)
        if (entry.updated_at) {
            const updatedDate = new Date(entry.updated_at);
            const daysSinceUpdate = (Date.now() - updatedDate.getTime()) / (1000 * 60 * 60 * 24);

            if (daysSinceUpdate > this.lookbackDays * 2) {
                analysis.isOutdated = true;
                analysis.issues.push(`Content is ${Math.floor(daysSinceUpdate)} days old`);
            } else if (daysSinceUpdate > this.lookbackDays) {
                analysis.qualityScore -= 10;
            }
        }

        // Check for quality indicators
        const content = entry.content || '';

        // Check for formatting
        if (content.includes('\n\n') || content.includes('•') || content.includes('- ')) {
            analysis.qualityScore += 15;
        } else {
            analysis.issues.push('No formatting detected - use bullet points');
        }

        // Check for keywords/tags
        if (entry.tags && entry.tags.length > 0) {
            analysis.qualityScore += 10;
        } else {
            analysis.issues.push('No tags/keywords');
        }

        // Add suggestions
        if (analysis.qualityScore < this.qualityThreshold) {
            analysis.suggestions = this.generateSuggestions(analysis.issues, entry);
        }

        return analysis;
    }

    /**
     * Generate improvement suggestions using AI
     */
    generateSuggestions(issues, entry) {
        const suggestions = [];

        if (issues.includes('Content too short')) {
            suggestions.push('Expand content with more details and examples');
        }
        if (issues.includes('Content too long')) {
            suggestions.push('Split into multiple entries or summarize key points');
        }
        if (issues.includes('Title missing or too short')) {
            suggestions.push('Improve title to be more descriptive (5+ words)');
        }
        if (issues.includes('No formatting detected')) {
            suggestions.push('Add bullet points, headers, or structured formatting');
        }
        if (issues.includes('No tags/keywords')) {
            suggestions.push('Add relevant tags for better searchability');
        }

        return suggestions;
    }

    /**
     * Improve a KB entry using AI
     */
    async improveEntry(entry, analysis) {
        Logger.info(`Improving KB entry: ${entry.id}`);

        const improvePrompt = `You are a Knowledge Base expert. Improve the following KB entry to make it more useful and well-structured.

Current Issues: ${analysis.issues.join(', ')}

Original Title: ${entry.title}
Original Content: ${entry.content}
Category: ${entry.category || 'General'}

Provide an improved version in JSON format:
{
  "title": "Improved title",
  "content": "Improved and well-formatted content",
  "category": "Same or better category",
  "tags": ["relevant", "tags"]
}

Respond only with valid JSON.`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: improvePrompt }],
                provider: 'auto',
                system: 'You are a KB improvement expert.'
            });

            const improved = JSON.parse(response.content);

            // Update the entry via PHP API
            await this.updateKBEntry(entry.id, {
                title: improved.title,
                content: improved.content,
                category: improved.category,
                tags: improved.tags
            });

            Logger.info(`KB entry ${entry.id} improved successfully`);
            return { success: true, improved };
        } catch (error) {
            Logger.error(`Failed to improve KB entry ${entry.id}`, { error: error.message });
            return { success: false, error: error.message };
        }
    }

    /**
     * Update KB entry via PHP API
     */
    async updateKBEntry(id, data) {
        try {
            const response = await fetch(
                `${process.env.PHP_API_URL || 'http://localhost'}/api/admin/ai-knowledge/${id}`,
                {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${process.env.PHP_API_TOKEN || ''}`
                    },
                    body: JSON.stringify(data)
                }
            );

            if (!response.ok) {
                throw new Error(`Failed to update KB: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            Logger.error('Failed to update KB entry', { error: error.message });
            throw error;
        }
    }

    /**
     * Detect duplicate entries
     */
    async detectDuplicates(entries) {
        const duplicates = [];
        const seen = new Map();

        for (const entry of entries) {
            // Simple similarity check based on title
            const normalizedTitle = (entry.title || '').toLowerCase().trim();

            if (seen.has(normalizedTitle)) {
                duplicates.push({
                    entry1: seen.get(normalizedTitle),
                    entry2: entry.id,
                    reason: 'Similar title'
                });
            } else {
                seen.set(normalizedTitle, entry.id);
            }

            // Check content similarity
            for (const [existingId, existingEntry] of seen) {
                if (existingId !== normalizedTitle) {
                    const similarity = this.calculateSimilarity(
                        entry.content || '',
                        existingEntry.content || ''
                    );

                    if (similarity > 0.8) {
                        duplicates.push({
                            entry1: existingEntry.id,
                            entry2: entry.id,
                            reason: `Content similarity: ${Math.round(similarity * 100)}%`
                        });
                    }
                }
            }
        }

        return duplicates;
    }

    /**
     * Calculate text similarity (simple Jaccard)
     */
    calculateSimilarity(text1, text2) {
        const words1 = new Set(text1.toLowerCase().split(/\s+/));
        const words2 = new Set(text2.toLowerCase().split(/\s+/));

        const intersection = new Set([...words1].filter(x => words2.has(x)));
        const union = new Set([...words1, ...words2]);

        return union.size > 0 ? intersection.size / union.size : 0;
    }

    /**
     * Suggest new content based on usage patterns
     */
    async suggestNewContent() {
        Logger.info('Analyzing usage patterns for content suggestions...');

        try {
            // Get feedback/usage data from PHP
            const feedback = await this.getUsageFeedback();

            // Find common queries with no results
            const unsuccessfulQueries = this.analyzeUnsuccessfulQueries(feedback);

            const suggestions = [];

            for (const query of unsuccessfulQueries.slice(0, 5)) {
                const suggestion = await this.generateContentSuggestion(query);
                if (suggestion) {
                    suggestions.push(suggestion);
                }
            }

            return suggestions;
        } catch (error) {
            Logger.error('Failed to generate content suggestions', { error: error.message });
            return [];
        }
    }

    /**
     * Get usage feedback from PHP
     */
    async getUsageFeedback() {
        try {
            const response = await fetch(
                `${process.env.PHP_API_URL || 'http://localhost'}/api/ai/feedback`,
                {
                    headers: {
                        'Authorization': `Bearer ${process.env.PHP_API_TOKEN || ''}`
                    }
                }
            );

            if (!response.ok) {
                return [];
            }

            const data = await response.json();
            return data.feedback || [];
        } catch (error) {
            return [];
        }
    }

    /**
     * Analyze unsuccessful queries
     */
    analyzeUnsuccessfulQueries(feedback) {
        const queryCounts = {};

        for (const item of feedback) {
            if (!item.successful && item.query) {
                queryCounts[item.query] = (queryCounts[item.query] || 0) + 1;
            }
        }

        return Object.entries(queryCounts)
            .sort((a, b) => b[1] - a[1])
            .map(([query]) => query);
    }

    /**
     * Generate content suggestion for a query
     */
    async generateContentSuggestion(query) {
        const prompt = `A user searched for "${query}" but found no relevant KB entry. 
Suggest a KB entry title and outline that would help users with this topic.

Provide JSON:
{
  "suggestedTitle": "Title for the KB entry",
  "suggestedContent": "Brief outline of what this entry should contain",
  "suggestedCategory": "Appropriate category",
  "suggestedTags": ["tag1", "tag2"]
}`;

        try {
            const response = await aiRouter.chat({
                messages: [{ role: 'user', content: prompt }],
                provider: 'auto'
            });

            const suggestion = JSON.parse(response.content);
            return {
                query,
                ...suggestion,
                generatedAt: new Date().toISOString()
            };
        } catch (error) {
            return null;
        }
    }

    /**
     * Auto-healing scheduler (run periodically)
     */
    async scheduleHealing(intervalHours = 24) {
        Logger.info(`KB self-healing scheduled every ${intervalHours} hours`);

        const runHealing = async () => {
            if (!this.enabled) {
                Logger.info('KB self-healing is disabled');
                return;
            }

            try {
                await this.runHealthCheck();

                if (this.autoImprove) {
                    const suggestions = await this.suggestNewContent();
                    Logger.info(`Generated ${suggestions.length} content suggestions`);
                }
            } catch (error) {
                Logger.error('Scheduled healing failed', { error: error.message });
            }
        };

        // Run immediately
        await runHealing();

        // Schedule recurring
        setInterval(runHealing, intervalHours * 60 * 60 * 1000);
    }
}

// Export singleton
const selfHealingKB = new SelfHealingKnowledgeBase({
    enabled: process.env.KB_SELF_HEALING_ENABLED === 'true',
    autoImprove: process.env.KB_AUTO_IMPROVE === 'true',
    qualityThreshold: parseInt(process.env.KB_QUALITY_THRESHOLD || '50'),
    lookbackDays: parseInt(process.env.KB_LOOKBACK_DAYS || '30')
});

export { SelfHealingKnowledgeBase, selfHealingKB };
export default selfHealingKB;