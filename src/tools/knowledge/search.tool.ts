import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import { query } from '../../config/database';
import logger from '../../utils/logger';

const searchKbSchema = z.object({
    query: z.string().min(1).max(500),
    limit: z.number().int().positive().optional().default(10),
    category: z.string().optional(),
});

export const searchKnowledgeBaseTool: ToolDefinition = {
    name: 'search_knowledge_base',
    displayName: 'Search Knowledge Base',
    description: 'Search the AI knowledge base for relevant information using semantic or keyword search',
    parameters: searchKbSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 15000,
    maxRetries: 2,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { query: searchQuery, limit, category } = args;

        try {
            // Check if ai_knowledge table exists
            const tableExists = await checkTableExists('ai_knowledge');
            if (!tableExists) {
                return {
                    success: false,
                    error: 'Knowledge base table not found',
                };
            }

            // Build query
            let sql = `
                SELECT 
                    id,
                    title,
                    content,
                    category,
                    tags,
                    embedding,
                    created_at,
                    updated_at
                FROM ai_knowledge
                WHERE is_active = 1
            `;

            const params: any[] = [];

            if (category) {
                sql += ` AND category = ?`;
                params.push(category);
            }

            // Simple keyword search (in production, use full-text or vector search)
            if (searchQuery) {
                sql += ` AND (title LIKE ? OR content LIKE ? OR tags LIKE ?)`;
                const likePattern = `%${searchQuery}%`;
                params.push(likePattern, likePattern, likePattern);
            }

            sql += ` ORDER BY updated_at DESC LIMIT ?`;
            params.push(limit);

            const results = await query(sql, params);

            // Format results
            const formattedResults = results.map((row: any) => ({
                id: row.id,
                title: row.title,
                snippet: row.content.substring(0, 200) + (row.content.length > 200 ? '...' : ''),
                category: row.category,
                tags: row.tags ? JSON.parse(row.tags) : [],
                lastUpdated: row.updated_at,
            }));

            return {
                success: true,
                data: {
                    query: searchQuery,
                    total: results.length,
                    results: formattedResults,
                    hasMore: results.length === limit,
                },
            };
        } catch (error: any) {
            logger.error('Knowledge base search failed:', error);
            return {
                success: false,
                error: `Search failed: ${error.message}`,
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

async function queryOne<T = any>(sql: string, params: any[] = []): Promise<T | null> {
    const rows = await query<T>(sql, params);
    return rows.length > 0 ? rows[0] : null;
}
