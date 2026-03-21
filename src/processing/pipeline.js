/**
 * Multimodal RAG Pipeline - Text Processing
 * Handles text chunking, embeddings, and vector storage
 */

import fs from 'fs';
import { v4 as uuidv4 } from 'uuid';
import { Logger } from '../utils/index.js';

const logger = new Logger({ name: 'RAGPipeline', level: process.env.RAG_LOG_LEVEL || process.env.LOG_LEVEL || 'info' });

/**
 * @typedef {Object} ProcessedChunk
 * @property {string} content - Chunk content
 * @property {string} source - Source identifier
 * @property {number} chunk_index - Chunk index
 * @property {Object} metadata - Additional metadata
 */

class TextProcessor {
    /**
     * @param {Object} options - Options for text processing
     * @param {number} options.chunkSize - Size of text chunks
     * @param {number} options.chunkOverlap - Overlap between chunks
     */
    constructor(options = {}) {
        this.chunkSize = options.chunkSize || 1000;
        this.chunkOverlap = options.chunkOverlap || 200;
        this.separators = ['\n\n', '\n', '. ', '? ', '! ', ' '];
    }

    /**
     * Process text and split into chunks
     * @param {string} text - Text to process
     * @param {string} source - Source identifier
     * @returns {Array<ProcessedChunk>} Array of processed chunks
     */
    processText(text, source) {
        const chunks = this._splitText(text);
        return chunks.map((chunk, i) => ({
            content: chunk,
            source: source,
            chunk_index: i,
            metadata: { source, chunk_index: i, type: 'text' }
        }));
    }

    /**
     * Split text using recursive character splitting
     * @param {string} text - Text to split
     * @returns {Array<string>} Array of text chunks
     */
    _splitText(text) {
        if (!text || text.length === 0) return [];

        const chunks = [];
        let startIndex = 0;
        const textLength = text.length;

        while (startIndex < textLength) {
            let endIndex = startIndex + this.chunkSize;

            if (endIndex >= textLength) {
                chunks.push(text.substring(startIndex));
                break;
            }

            // Try to split at a separator
            let splitFound = false;
            for (const separator of this.separators) {
                const separatorIndex = text.indexOf(separator, startIndex + this.chunkSize - 200);
                if (separatorIndex !== -1 && separatorIndex < startIndex + this.chunkSize) {
                    endIndex = separatorIndex + separator.length;
                    splitFound = true;
                    break;
                }
            }

            if (!splitFound) {
                // Hard split at chunk size
                endIndex = startIndex + this.chunkSize;
            }

            chunks.push(text.substring(startIndex, endIndex).trim());
            startIndex = endIndex - this.chunkOverlap;

            if (startIndex < 0) startIndex = 0;
        }

        return chunks.filter(chunk => chunk.length > 0);
    }

    /**
     * Process documents (compatible with LangChain style)
     * @param {Array} documents - Array of document objects
     * @returns {Array} Split documents
     */
    processDocuments(documents) {
        const results = [];
        for (const doc of documents) {
            const chunks = this.processText(doc.page_content || doc.content || doc.text, doc.source || 'unknown');
            results.push(...chunks);
        }
        return results;
    }
}

class EmbeddingManager {
    /**
     * @param {Object} options - Options for embeddings
     * @param {string} options.modelName - Model name for embeddings
     */
    constructor(options = {}) {
        this.modelName = options.modelName || 'sentence-transformers/all-MiniLM-L6-v2';
        this.embeddings = null;
        this._transformers = null;
        this._pipeline = null;
    }

    /**
     * Initialize the embedding model
     */
    async initialize() {
        logger.info('Loading embedding model', { model: this.modelName });

        try {
            const { pipeline, env } = await import('@xenova/transformers');

            // Disable local model files to use remote
            env.allowLocalModels = false;
            env.useBrowserCache = true;

            this._pipeline = await pipeline('feature-extraction', this.modelName);
            logger.info('Embedding model loaded successfully');

            this.embeddings = {
                embedQuery: async (query) => {
                    const output = await this._pipeline(query, { pooling: 'mean', normalize: true });
                    return Array.from(output.data);
                },
                embedDocuments: async (documents) => {
                    const embeddings = [];
                    for (const doc of documents) {
                        const output = await this._pipeline(doc, { pooling: 'mean', normalize: true });
                        embeddings.push(Array.from(output.data));
                    }
                    return embeddings;
                }
            };

            return this.embeddings;
        } catch (e) {
            logger.error('Failed to load embedding model', { error: e.message });
            // Return a mock embedding function as fallback
            this.embeddings = {
                embedQuery: async (query) => {
                    // Simple hash-based embedding as fallback
                    const hash = this._simpleHash(query);
                    return new Array(384).fill(0).map((_, i) => Math.sin(hash + i) * 0.1);
                },
                embedDocuments: async (documents) => {
                    return Promise.all(documents.map(doc => this.embeddings.embedQuery(doc)));
                }
            };
            return this.embeddings;
        }
    }

    /**
     * Simple hash function for mock embeddings
     * @param {string} str - String to hash
     * @returns {number} Hash value
     */
    _simpleHash(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return hash;
    }

    /**
     * Get embeddings instance
     * @returns {Object} Embeddings object
     */
    getEmbeddings() {
        return this.embeddings;
    }

    /**
     * Embed a single query
     * @param {string} query - Query string
     * @returns {Promise<Array>} Embedding vector
     */
    async embedQuery(query) {
        if (!this.embeddings) {
            await this.initialize();
        }
        return await this.embeddings.embedQuery(query);
    }

    /**
     * Embed multiple documents
     * @param {Array<string>} documents - Array of document strings
     * @returns {Promise<Array<Array>>} Array of embedding vectors
     */
    async embedDocuments(documents) {
        if (!this.embeddings) {
            await this.initialize();
        }
        return await this.embeddings.embedDocuments(documents);
    }
}

class VectorStoreManager {
    /**
     * @param {Object} embeddings - Embeddings instance
     * @param {Object} options - Options for vector store
     */
    constructor(embeddings, options = {}) {
        this.embeddings = embeddings;
        this.persistDirectory = options.persistDirectory || './data/vector_store';
        this.provider = options.provider || 'memory';
        this.vectorStore = null;
        this.documents = [];
        this.vectors = [];
        this._loadOrCreateStore();
    }

    /**
     * Load or create vector store
     */
    _loadOrCreateStore() {
        if (fs.existsSync(this.persistDirectory)) {
            logger.info('Loading existing vector store', { path: this.persistDirectory });
            this._loadStore();
        } else {
            logger.info('Creating new vector store');
            this._createStore();
        }
    }

    /**
     * Create new vector store
     */
    _createStore() {
        this.documents = [];
        this.vectors = [];
    }

    /**
     * Load existing vector store
     */
    _loadStore() {
        try {
            const dataPath = `${this.persistDirectory}/store.json`;

            if (fs.existsSync(dataPath)) {
                const data = JSON.parse(fs.readFileSync(dataPath, 'utf-8'));
                this.documents = data.documents || [];
                this.vectors = data.vectors || [];
            } else {
                this._createStore();
            }
        } catch (e) {
            logger.error('Failed to load vector store', { error: e.message });
            this._createStore();
        }
    }

    /**
     * Save vector store to disk
     */
    _saveStore() {
        try {
            if (!fs.existsSync(this.persistDirectory)) {
                fs.mkdirSync(this.persistDirectory, { recursive: true });
            }

            const dataPath = `${this.persistDirectory}/store.json`;
            fs.writeFileSync(dataPath, JSON.stringify({
                documents: this.documents,
                vectors: this.vectors
            }, null, 2));

            logger.info('Vector store saved', { path: dataPath });
        } catch (e) {
            logger.error('Failed to save vector store', { error: e.message });
        }
    }

    /**
     * Add documents to the vector store
     * @param {Array} documents - Array of documents
     */
    async addDocuments(documents) {
        if (!documents || documents.length === 0) return;

        const texts = documents.map(doc => doc.page_content || doc.content || doc.text || '');

        try {
            const embeddings = await this.embeddings.embedDocuments(texts);

            for (let i = 0; i < documents.length; i++) {
                this.documents.push({
                    ...documents[i],
                    id: documents[i].id || uuidv4()
                });
                this.vectors.push(embeddings[i]);
            }

            this._saveStore();
            logger.info('Added documents to vector store', { count: documents.length });
        } catch (e) {
            logger.error('Failed to add documents', { error: e.message });
        }
    }

    /**
     * Get retriever for similarity search
     * @param {Object} options - Retriever options
     * @returns {Object} Retriever object
     */
    asRetriever(options = {}) {
        const searchType = options.searchType || 'similarity';
        const k = options.k || 5;

        return {
            getRelevantDocuments: async (query) => {
                return await this.similaritySearch(query, k);
            }
        };
    }

    /**
     * Perform similarity search
     * @param {string} query - Query string
     * @param {number} k - Number of results
     * @returns {Array} Search results
     */
    async similaritySearch(query, k = 5) {
        if (!this.embeddings) {
            throw new Error('Embeddings not initialized');
        }

        const queryEmbedding = await this.embeddings.embedQuery(query);

        // Calculate cosine similarity
        const similarities = this.vectors.map((vector, index) => {
            const similarity = this._cosineSimilarity(queryEmbedding, vector);
            return { index, similarity };
        });

        // Sort by similarity and get top k
        similarities.sort((a, b) => b.similarity - a.similarity);
        const topK = similarities.slice(0, k);

        return topK.map(({ index }) => this.documents[index]);
    }

    /**
     * Calculate cosine similarity between two vectors
     * @param {Array} vec1 - First vector
     * @param {Array} vec2 - Second vector
     * @returns {number} Cosine similarity
     */
    _cosineSimilarity(vec1, vec2) {
        let dotProduct = 0;
        let norm1 = 0;
        let norm2 = 0;

        for (let i = 0; i < vec1.length; i++) {
            dotProduct += vec1[i] * vec2[i];
            norm1 += vec1[i] * vec1[i];
            norm2 += vec2[i] * vec2[i];
        }

        return dotProduct / (Math.sqrt(norm1) * Math.sqrt(norm2));
    }

    /**
     * Similarity search with scores
     * @param {string} query - Query string
     * @param {number} k - Number of results
     * @returns {Array} Results with scores
     */
    async similaritySearchWithScore(query, k = 5) {
        if (!this.embeddings) {
            throw new Error('Embeddings not initialized');
        }

        const queryEmbedding = await this.embeddings.embedQuery(query);

        const similarities = this.vectors.map((vector, index) => ({
            document: this.documents[index],
            score: this._cosineSimilarity(queryEmbedding, vector)
        }));

        similarities.sort((a, b) => b.score - a.score);
        return similarities.slice(0, k);
    }
}

class HybridRetriever {
    /**
     * @param {VectorStoreManager} vectorStore - Vector store manager
     * @param {Array} documents - Array of documents
     * @param {Object} options - Retriever options
     */
    constructor(vectorStore, documents, options = {}) {
        this.vectorStore = vectorStore;
        this.documents = documents;
        this.semanticWeight = options.semanticWeight || 0.7;
        this.keywordWeight = options.keywordWeight || 0.3;
        this.semanticRetriever = vectorStore.asRetriever({ searchType: 'similarity', k: 5 });
        this.bm25Retriever = null;
        this._setupRetrievers();
    }

    /**
     * Setup retrievers
     */
    _setupRetrievers() {
        // BM25-style keyword retrieval using simple term frequency
        this.bm25Retriever = {
            getRelevantDocuments: async (query) => {
                const queryTerms = query.toLowerCase().split(/\s+/);
                const scores = this.documents.map(doc => {
                    const content = (doc.page_content || doc.content || doc.text || '').toLowerCase();
                    let score = 0;
                    for (const term of queryTerms) {
                        if (content.includes(term)) {
                            score += 1;
                        }
                    }
                    return score;
                });

                const scored = this.documents.map((doc, i) => ({ doc, score: scores[i] }));
                scored.sort((a, b) => b.score - a.score);

                return scored.slice(0, 5).filter(s => s.score > 0).map(s => s.doc);
            }
        };
    }

    /**
     * Get relevant documents
     * @param {string} query - Query string
     * @returns {Array} Relevant documents
     */
    async getRelevantDocuments(query) {
        const semanticResults = await this.semanticRetriever.getRelevantDocuments(query);
        const keywordResults = await this.bm25Retriever.getRelevantDocuments(query);

        const seen = new Set();
        const combined = [];

        for (const doc of semanticResults) {
            const key = (doc.page_content || doc.content || doc.text || '').substring(0, 50);
            if (!seen.has(key)) {
                seen.add(key);
                combined.push({ doc, weight: this.semanticWeight });
            }
        }

        for (const doc of keywordResults) {
            const key = (doc.page_content || doc.content || doc.text || '').substring(0, 50);
            if (!seen.has(key)) {
                seen.add(key);
                combined.push({ doc, weight: this.keywordWeight });
            }
        }

        combined.sort((a, b) => b.weight - a.weight);
        return combined.slice(0, 5).map(({ doc }) => doc);
    }
}

class MultimodalRAGPipeline {
    /**
     * Initialize the RAG pipeline
     */
    constructor() {
        this.textProcessor = new TextProcessor();
        this.embeddingManager = new EmbeddingManager();
        this.vectorStoreManager = null;
        this.documents = [];
    }

    /**
     * Initialize the pipeline
     */
    async initialize() {
        const embeddings = await this.embeddingManager.initialize();
        this.vectorStoreManager = new VectorStoreManager(embeddings);
        logger.info('RAG Pipeline initialized');
    }

    /**
     * Ingest text into the pipeline
     * @param {string} text - Text to ingest
     * @param {string} source - Source identifier
     * @returns {Promise<number>} Number of chunks created
     */
    async ingestText(text, source = 'unknown') {
        const chunks = this.textProcessor.processText(text, source);

        const documents = chunks.map(chunk => ({
            page_content: chunk.content,
            metadata: chunk.metadata
        }));

        await this.vectorStoreManager.addDocuments(documents);
        this.documents.push(...documents);

        logger.info('Ingested chunks', { count: chunks.length, source });
        return chunks.length;
    }

    /**
     * Get retriever for querying
     * @param {boolean} hybrid - Whether to use hybrid retrieval
     * @returns {Object} Retriever object
     */
    getRetriever(hybrid = true) {
        if (hybrid) {
            return new HybridRetriever(
                this.vectorStoreManager,
                this.documents
            );
        }
        return this.vectorStoreManager.asRetriever();
    }

    /**
     * Perform similarity search
     * @param {string} query - Query string
     * @param {number} k - Number of results
     * @returns {Promise<Array>} Search results
     */
    async similaritySearch(query, k = 5) {
        return await this.vectorStoreManager.similaritySearch(query, k);
    }
}

export { TextProcessor, EmbeddingManager, VectorStoreManager, HybridRetriever, MultimodalRAGPipeline };
export default {
    TextProcessor,
    EmbeddingManager,
    VectorStoreManager,
    HybridRetriever,
    MultimodalRAGPipeline
};
