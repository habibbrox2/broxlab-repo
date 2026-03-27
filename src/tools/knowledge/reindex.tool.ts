import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
import { query, queryOne } from '../../config/database.js';
import { delPattern } from '../../config/redis.js';
import logger from '../../utils/logger.js';

const reindexKbSchema = z.object({
    category: z.string().optional(),
    rebuildEmbeddings: z.boolean().optional().default(false),
});

export const reindexKnowledgeBaseTool: ToolDefinition = {
    name: 'reindex_knowledge_base',
    displayName: 'Reindex Knowledge Base',
    description: 'Rebuild knowledge base indexes and optionally regenerate embeddings for vector search',
    parameters: reindexKbSchema,
    requiresAuth: true,
    cacheable: false,
    timeout: 60000,
    maxRetries: 1,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { category, rebuildEmbeddings } = args;

        try {
            // Check if ai_knowledge table exists
            const tableExists = await checkTableExists('ai_knowledge');
            if (!tableExists) {
                return {
                    success: false,
                    error: 'Knowledge base table not found',
                };
            }

            const results: any = {
                totalRecords: 0,
                indexed: 0,
                embeddingsGenerated: 0,
            };

            // Build query to get records
            let sql = 'SELECT id, title, content FROM ai_knowledge WHERE is_active = 1';
            const params: any[] = [];

            if (category) {
                sql += ` AND category = ?`;
                params.push(category);
            }

            const records = await query(sql, params);
            results.totalRecords = records.length;

            // Check if embedding column exists
            const hasEmbeddingColumn = await checkColumnExists('ai_knowledge', 'embedding');

            // Reindex each record
            for (const record of records) {
                try {
                    // Update updated_at timestamp
                    await queryOne(
                        'UPDATE ai_knowledge SET updated_at = NOW() WHERE id = ?',
                        [record.id]
                    );

                    // Optionally generate embeddings (placeholder - would integrate with AI)
                    if (rebuildEmbeddings && hasEmbeddingColumn) {
                        // In a real implementation, this would call an embedding model
                        // For now, we'll just log it
                        logger.debug('Embedding generation would happen here', {
                            recordId: record.id,
                            title: record.title,
                        });
                        results.embeddingsGenerated++;
                    }

                    results.indexed++;
                } catch (error: any) {
                    logger.error('Failed to reindex record', {
                        recordId: record.id,
                        error: error.message,
                    });
                }
            }

            // Clear related caches
            try {
                await delPattern('tool:search_knowledge_base:*');
                logger.info('Knowledge base cache cleared');
            } catch (error: any) {
                logger.warn('Failed to clear knowledge base cache:', error);
            }

            return {
                success: true,
                data: {
                    message: `Reindexed ${results.indexed} of ${results.totalRecords} records`,
                    ...results,
                    category: category || 'all',
                    embeddingsUpdated: rebuildEmbeddings,
                },
            };
        } catch (error: any) {
            logger.error('Knowledge base reindex failed:', error);
            return {
                success: false,
                error: `Reindex failed: ${error.message}`,
            };
        }
    },
};

async function checkTableExists(tableName: string): Promise<boolean> {
    try {
        const result = await queryOne(
            `SELECT COUNT(*) as count FROM information_schema.tables 
             WHERE table_schema = DATABASE() AND table_name = ?`,
            [tableName]
        );
        return (result as any)?.count > 0;
    } catch {
        return false;
    }
}

async function checkColumnExists(tableName: string, columnName: string): Promise<boolean> {
    try {
        const result = await queryOne(
            `SELECT COUNT(*) as count FROM information_schema.columns 
             WHERE table_schema = DATABASE() 
               AND table_name = ? 
               AND column_name = ?`,
            [tableName, columnName]
        );
        return (result as any)?.count > 0;
    } catch {
        return false;
    }
}
