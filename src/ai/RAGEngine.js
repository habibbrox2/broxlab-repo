/**
 * RAG Engine (Retrieval-Augmented Generation)
 * 
 * Provides RAG capabilities for Node.js:
 * - Vector search with Qdrant
 * - Context building
 * - Document chunking
 */

import { QdrantClient } from '@qdrant/client';
import { RAG_CONFIG, FEATURE_FLAGS } from './config.js';
import aiRouter from './AIRouter.js';
import logger from './utils/Logger.js';
import LiteParse from '../scraper/utils/LiteParse.js';

class RAGEngine {
    constructor(options = {}) {
        this.qdrant = null;
        this.collectionName = options.collectionName || RAG_CONFIG.qdrant.collectionName;
        this.embeddingModel = options.embeddingModel || RAG_CONFIG.embedding.model;
        this.embeddingProvider = options.embeddingProvider || RAG_CONFIG.embedding.provider;
        this.maxResults = options.maxResults || RAG_CONFIG.retrieval.maxResults;
        this.minScore = options.minScore || RAG_CONFIG.retrieval.minScore;

        this.initializeQdrant();
    }

    /**
     * Initialize Qdrant client
     */
    initializeQdrant() {
        if (!FEATURE_FLAGS.ENABLE_RAG) {
            logger.info('RAG is disabled');
            return;
        }

        try {
            this.qdrant = new QdrantClient({
                url: RAG_CONFIG.qdrant.url,
                apiKey: RAG_CONFIG.qdrant.apiKey || undefined,
            });
            logger.info('Initialized Qdrant client');
        } catch (error) {
            logger.warn('Failed to initialize Qdrant', { error: error.message });
            this.qdrant = null;
        }
    }

    /**
     * Generate embedding for text
     */
    async generateEmbedding(text) {
        const embedding = await aiRouter.embed(
            text,
            this.embeddingProvider,
            this.embeddingModel
        );

        return embedding.embedding;
    }

    /**
     * Search for relevant documents
     */
    async search(query, options = {}) {
        if (!this.qdrant) {
            throw new Error('Qdrant not initialized');
        }

        const limit = options.limit || this.maxResults;

        // Generate query embedding
        const queryEmbedding = await this.generateEmbedding(query);

        // Search Qdrant
        const results = await this.qdrant.search(this.collectionName, {
            vector: queryEmbedding,
            limit,
            score_threshold: this.minScore,
            filter: options.filter || undefined,
        });

        // Format results
        return results.map(result => ({
            id: result.id,
            score: result.score,
            payload: result.payload,
            text: result.payload?.text || '',
            metadata: result.payload?.metadata || {},
        }));
    }

    /**
     * Add documents to vector store
     */
    async addDocuments(documents, options = {}) {
        if (!this.qdrant) {
            throw new Error('Qdrant not initialized');
        }

        const points = [];

        for (let i = 0; i < documents.length; i++) {
            const doc = documents[i];
            const id = doc.id || `doc_${Date.now()}_${i}`;

            // Chunk document if needed
            const chunks = this.chunkText(doc.text || doc.content, {
                chunkSize: options.chunkSize || 1000,
                overlap: options.overlap || 100,
            });

            for (const chunk of chunks) {
                const embedding = await this.generateEmbedding(chunk.text);

                points.push({
                    id: `${id}_${points.length}`,
                    vector: embedding,
                    payload: {
                        text: chunk.text,
                        metadata: {
                            ...doc.metadata,
                            source: doc.source || 'unknown',
                            originalId: id,
                            ...chunk.metadata,
                        },
                    },
                });
            }
        }

        // Upsert to Qdrant
        await this.qdrant.upsert(this.collectionName, {
            points,
        });

        logger.info('Added documents to vector store', {
            documents: documents.length,
            chunks: points.length,
        });

        return { documents: documents.length, chunks: points.length };
    }

    /**
     * Delete documents from vector store
     */
    async deleteDocuments(ids) {
        if (!this.qdrant) {
            throw new Error('Qdrant not initialized');
        }

        await this.qdrant.delete(this.collectionName, {
            points: ids,
        });

        logger.info('Deleted documents from vector store', { ids: ids.length });

        return { deleted: ids.length };
    }

    /**
     * Build context from search results
     */
    buildContext(searchResults, options = {}) {
        const maxTokens = options.maxTokens || 4000;
        let context = '';
        let tokenCount = 0;

        // Estimate tokens (rough: 1 token ≈ 4 characters)
        const estimateTokens = (text) => Math.ceil(text.length / 4);

        for (const result of searchResults) {
            const text = result.text;
            const tokens = estimateTokens(text);

            if (tokenCount + tokens > maxTokens) {
                break;
            }

            context += `[Source: ${result.metadata?.source || 'unknown'}]\n${text}\n\n`;
            tokenCount += tokens;
        }

        return context.trim();
    }

    /**
     * Query with RAG
     */
    async query(query, options = {}) {
        // Step 1: Retrieve relevant documents
        const searchResults = await this.search(query, {
            limit: options.maxResults || this.maxResults,
            filter: options.filter,
        });

        if (searchResults.length === 0) {
            // No relevant documents, fall back to regular chat
            logger.info('No RAG results, falling back to regular chat');
            return {
                withoutRAG: true,
                response: await aiRouter.chat(
                    options.messages || [{ role: 'user', content: query }],
                    options.provider,
                    options.model,
                    options
                ),
            };
        }

        // Step 2: Build context
        const context = this.buildContext(searchResults, {
            maxTokens: options.contextMaxTokens || 4000,
        });

        // Step 3: Build messages with context
        const systemPrompt = options.systemPrompt ||
            'You are a helpful assistant. Use the provided context to answer questions accurately.';

        const contextPrompt = `Context information:\n${context}\n\n---\n\nQuestion: ${query}`;

        const messages = [
            { role: 'system', content: systemPrompt },
            ...(options.messages || []),
            { role: 'user', content: contextPrompt },
        ];

        // Step 4: Generate response
        const response = await aiRouter.chat(
            messages,
            options.provider,
            options.model,
            options
        );

        return {
            withoutRAG: false,
            response,
            sources: searchResults.map(r => ({
                id: r.id,
                score: r.score,
                metadata: r.metadata,
            })),
            context,
        };
    }

    /**
     * Chunk text into smaller pieces
     */
    chunkText(text, options = {}) {
        const chunkSize = options.chunkSize || 1000;
        const overlap = options.overlap || 100;

        const chunks = [];
        const sentences = text.split(/(?<=[.!?])\s+/);

        let currentChunk = '';

        for (const sentence of sentences) {
            if (currentChunk.length + sentence.length > chunkSize && currentChunk.length > 0) {
                chunks.push({
                    text: currentChunk.trim(),
                    metadata: { index: chunks.length },
                });

                // Keep overlap
                const overlapText = currentChunk.slice(-overlap);
                currentChunk = overlapText + sentence;
            } else {
                currentChunk += ' ' + sentence;
            }
        }

        if (currentChunk.trim()) {
            chunks.push({
                text: currentChunk.trim(),
                metadata: { index: chunks.length },
            });
        }

        return chunks;
    }

    /**
     * Process HTML content using LiteParse
     * Extracts content, converts to Markdown, and chunks for RAG
     */
    async processHTMLContent(html, url, options = {}) {
        try {
            // Use LiteParse to extract and process content
            const result = await LiteParse.parseURL(url, html);
            
            // Convert chunks to RAG format
            const chunks = result.chunks.map((text, index) => ({
                text,
                metadata: {
                    index,
                    url,
                    title: result.title,
                    description: result.description,
                },
            }));
            
            logger.info('Processed HTML content with LiteParse', {
                url,
                chunks: chunks.length,
                wordCount: result.wordCount,
            });
            
            return {
                content: result.content,
                markdown: result.markdown,
                chunks,
                metadata: {
                    url,
                    title: result.title,
                    description: result.description,
                    wordCount: result.wordCount,
                    chunkCount: chunks.length,
                },
            };
        } catch (error) {
            logger.error('Failed to process HTML content', { 
                error: error.message, 
                url 
            });
            throw error;
        }
    }

    /**
     * Process markdown content for RAG
     */
    async processMarkdownContent(markdown, url, options = {}) {
        try {
            // Parse markdown to HTML
            const html = LiteParse.parseMarkdown(markdown);
            
            // Extract content
            const content = LiteParse.extractContent(html);
            
            // Chunk for RAG
            const chunks = LiteParse.chunkText(content).map((text, index) => ({
                text,
                metadata: { index, url },
            }));
            
            return {
                content,
                markdown,
                chunks,
                metadata: {
                    url,
                    wordCount: content.split(/\s+/).length,
                    chunkCount: chunks.length,
                },
            };
        } catch (error) {
            logger.error('Failed to process markdown content', { 
                error: error.message, 
                url 
            });
            throw error;
        }
    }

    /**
     * Get collection info
     */
    async getCollectionInfo() {
        if (!this.qdrant) {
            return null;
        }

        try {
            return await this.qdrant.getCollection(this.collectionName);
        } catch (error) {
            logger.warn('Failed to get collection info', { error: error.message });
            return null;
        }
    }
}

// Export singleton instance
const ragEngine = new RAGEngine();

export default ragEngine;
export { RAGEngine };