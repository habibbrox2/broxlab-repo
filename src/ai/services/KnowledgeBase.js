/**
 * Knowledge Base Service
 * 
 * Integrates with PHP AI Knowledge Base system:
 * - Read from MySQL via PHP API
 * - Sync to Qdrant for vector search
 * - Support both keyword and semantic search
 */

import axios from 'axios';
import { PHP_BACKEND } from '../config.js';
import logger from '../utils/Logger.js';

class KnowledgeBase {
    constructor(options = {}) {
        this.phpBridge = options.phpBridge;
        this.useQdrant = options.useQdrant ?? true;
        this.qdrantUrl = options.qdrantUrl || process.env.QDRANT_URL || 'http://localhost:6333';
        this.qdrantApiKey = options.qdrantApiKey || process.env.QDRANT_API_KEY || '';
        this.collectionName = options.collectionName || 'ai_knowledge_base';
    }

    /**
     * Search knowledge base via PHP API (keyword search)
     */
    async search(query, options = {}) {
        const limit = options.limit || 10;
        const category = options.category || null;

        try {
            const params = new URLSearchParams({
                q: query,
                limit: limit.toString(),
            });
            if (category) params.append('category', category);

            const response = await axios.get(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/search?${params}`,
                {
                    headers: { 'Accept': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data.results || response.data || [];
        } catch (error) {
            logger.error('Knowledge base search failed', { error: error.message, query });
            return [];
        }
    }

    /**
     * Get all knowledge base items (admin only)
     */
    async getAll(options = {}) {
        try {
            const params = new URLSearchParams();
            if (options.category) params.append('category', options.category);
            if (options.active !== undefined) params.append('active', options.active.toString());
            if (options.limit) params.append('limit', options.limit.toString());

            const response = await axios.get(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge?${params}`,
                {
                    headers: { 'Accept': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data.items || response.data || [];
        } catch (error) {
            logger.error('Knowledge base fetch failed', { error: error.message });
            return [];
        }
    }

    /**
     * Get single knowledge item by ID
     */
    async getById(id) {
        try {
            const response = await axios.get(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/${id}`,
                {
                    headers: { 'Accept': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data.item || response.data || null;
        } catch (error) {
            logger.error('Knowledge base getById failed', { error: error.message, id });
            return null;
        }
    }

    /**
     * Add knowledge base item via PHP API
     */
    async add(item) {
        try {
            const response = await axios.post(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/add`,
                {
                    title: item.title,
                    content: item.content,
                    category: item.category || null,
                    source_type: item.sourceType || 'manual',
                    priority: item.priority || 0,
                    is_active: item.isActive !== false,
                },
                {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 30000,
                }
            );

            return response.data;
        } catch (error) {
            logger.error('Knowledge base add failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Update knowledge base item
     */
    async update(id, item) {
        try {
            const response = await axios.post(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/update/${id}`,
                {
                    title: item.title,
                    content: item.content,
                    category: item.category,
                    source_type: item.sourceType,
                    priority: item.priority,
                    is_active: item.isActive,
                },
                {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 30000,
                }
            );

            return response.data;
        } catch (error) {
            logger.error('Knowledge base update failed', { error: error.message, id });
            throw error;
        }
    }

    /**
     * Delete knowledge base item
     */
    async delete(id) {
        try {
            const response = await axios.post(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/delete/${id}`,
                {},
                {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data;
        } catch (error) {
            logger.error('Knowledge base delete failed', { error: error.message, id });
            throw error;
        }
    }

    /**
     * Reindex knowledge base with embeddings
     */
    async reindex(provider = 'openai') {
        try {
            const response = await axios.post(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/reindex`,
                { provider },
                {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 60000,
                }
            );

            return response.data;
        } catch (error) {
            logger.error('Knowledge base reindex failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Get knowledge base statistics
     */
    async getStats() {
        try {
            const response = await axios.get(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/stats`,
                {
                    headers: { 'Accept': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data.stats || response.data || {};
        } catch (error) {
            logger.error('Knowledge base stats failed', { error: error.message });
            return {};
        }
    }

    /**
     * Get categories
     */
    async getCategories() {
        try {
            const response = await axios.get(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/categories`,
                {
                    headers: { 'Accept': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data.categories || response.data || [];
        } catch (error) {
            logger.error('Knowledge base categories failed', { error: error.message });
            return [];
        }
    }

    /**
     * Provide feedback on knowledge item
     */
    async provideFeedback(knowledgeId, isHelpful, comment = '') {
        try {
            const response = await axios.post(
                `${PHP_BACKEND.baseUrl}/api/admin/ai-knowledge/feedback`,
                {
                    knowledge_id: knowledgeId,
                    is_helpful: isHelpful,
                    comment,
                },
                {
                    headers: { 'Content-Type': 'application/json' },
                    timeout: 10000,
                }
            );

            return response.data;
        } catch (error) {
            logger.error('Knowledge base feedback failed', { error: error.message });
            throw error;
        }
    }

    /**
     * Sync from PHP to Qdrant (for Node.js RAG)
     */
    async syncToQdrant(limit = 100) {
        if (!this.useQdrant) {
            throw new Error('Qdrant sync disabled');
        }

        // Get all active knowledge items
        const items = await this.getAll({ active: true, limit });

        // Prepare for Qdrant
        const points = [];
        for (const item of items) {
            // Generate embedding using AI router
            const embedding = await this.generateEmbedding(item.content);

            points.push({
                id: `kb_${item.id}`,
                vector: embedding,
                payload: {
                    text: item.content,
                    metadata: {
                        title: item.title,
                        category: item.category,
                        source: 'knowledge_base',
                        knowledgeId: item.id,
                        qualityScore: item.quality_score,
                        usageCount: item.usage_count,
                    },
                },
            });
        }

        // Upsert to Qdrant
        await this.upsertToQdrant(points);

        logger.info('Synced knowledge base to Qdrant', { items: items.length, points: points.length });

        return { items: items.length, points: points.length };
    }

    /**
     * Generate embedding for text
     */
    async generateEmbedding(text) {
        const { aiRouter } = await import('../AIRouter.js');
        const result = await aiRouter.embed(text, 'openai', 'text-embedding-3-small');
        return result.embedding;
    }

    /**
     * Upsert points to Qdrant
     */
    async upsertToQdrant(points) {
        // This would use the Qdrant client
        // Implementation depends on whether Qdrant is configured
        logger.debug('Qdrant upsert', { points: points.length });
        // TODO: Implement actual Qdrant upsert
    }

    /**
     * Search Qdrant for similar items
     */
    async searchQdrant(query, limit = 5) {
        const embedding = await this.generateEmbedding(query);

        // TODO: Implement Qdrant search
        // This would call Qdrant API directly

        return [];
    }
}

// Export singleton
const knowledgeBase = new KnowledgeBase();

export default knowledgeBase;
export { KnowledgeBase };