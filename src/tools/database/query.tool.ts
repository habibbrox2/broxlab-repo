import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index';
import { query, queryOne } from '../../config/database';
import logger from '../../utils/logger';

const querySchema = z.object({
    sql: z.string().min(1).max(1000),
    params: z.array(z.any()).optional().default([]),
    limit: z.number().int().positive().optional().default(100),
});

export const queryDatabaseTool: ToolDefinition = {
    name: 'query_database',
    displayName: 'Query Database',
    description: 'Execute SQL queries against the database. Supports SELECT, INSERT, UPDATE, DELETE. Use with caution.',
    parameters: querySchema,
    requiresAuth: true,
    cacheable: false,
    timeout: 30000,
    maxRetries: 2,
    execute: async (args: any, context: ToolContext): Promise<ToolResult> => {
        const { sql, params = [], limit } = args;

        // Security: Only allow SELECT, SHOW, DESCRIBE for safety
        const trimmedSql = sql.trim().toLowerCase();
        const allowedPrefixes = ['select', 'show', 'describe', 'explain', 'pragma'];

        if (!allowedPrefixes.some(prefix => trimmedSql.startsWith(prefix))) {
            // For non-SELECT operations, require explicit admin flag
            if (!context.isAdmin) {
                return {
                    success: false,
                    error: 'Only administrators can execute write operations',
                };
            }

            // Additional safety: prevent dangerous operations
            const dangerousPrefixes = ['drop', 'truncate', 'alter', 'create', 'replace', 'rename'];
            if (dangerousPrefixes.some(prefix => trimmedSql.startsWith(prefix))) {
                return {
                    success: false,
                    error: 'Dangerous database operations are not allowed',
                };
            }
        }

        try {
            // Apply limit for SELECT queries
            let finalSql = sql;
            if (trimmedSql.startsWith('select') && limit) {
                // Simple limit injection - in production use proper query builder
                if (!sql.toLowerCase().includes('limit')) {
                    finalSql = `${sql} LIMIT ${limit}`;
                }
            }

            // Check if it's a SELECT query (returns multiple rows)
            if (trimmedSql.startsWith('select') || trimmedSql.startsWith('show') || trimmedSql.startsWith('describe')) {
                const results = await query(finalSql, params);
                return {
                    success: true,
                    data: {
                        rows: results,
                        count: results.length,
                        affectedRows: 0,
                    },
                };
            } else {
                // INSERT, UPDATE, DELETE
                const result = await queryOne(finalSql, params);
                return {
                    success: true,
                    data: {
                        affectedRows: result.affectedRows || 0,
                        insertId: result.insertId || 0,
                        message: result.info || 'Query executed',
                    },
                };
            }
        } catch (error: any) {
            logger.error('Database query failed', {
                sql: sql.substring(0, 100),
                error: error.message,
                userId: context.userId,
            });

            return {
                success: false,
                error: `Database error: ${error.message}`,
            };
        }
    },
};
