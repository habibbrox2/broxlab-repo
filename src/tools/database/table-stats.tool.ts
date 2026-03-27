import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
import { query } from '../../config/database.js';
import logger from '../../utils/logger.js';

const tableStatsSchema = z.object({
    tableName: z.string().optional(),
});

export const getTableStatsTool: ToolDefinition = {
    name: 'get_table_stats',
    displayName: 'Get Table Statistics',
    description: 'Get statistics about database tables including row counts, sizes, and indexes',
    parameters: tableStatsSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 15000,
    maxRetries: 2,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { tableName } = args;

        try {
            if (tableName) {
                // Get stats for specific table
                const stats = await query(`
                    SELECT 
                        TABLE_NAME,
                        TABLE_ROWS,
                        DATA_LENGTH,
                        INDEX_LENGTH,
                        CREATE_TIME,
                        UPDATE_TIME
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = ?
                `, [tableName]);

                if (stats.length === 0) {
                    return {
                        success: false,
                        error: `Table '${tableName}' not found`,
                    };
                }

                return {
                    success: true,
                    data: {
                        table: stats[0],
                        totalSize: (stats[0].DATA_LENGTH || 0) + (stats[0].INDEX_LENGTH || 0),
                    },
                };
            } else {
                // Get stats for all tables
                const allStats = await query(`
                    SELECT 
                        TABLE_NAME,
                        TABLE_ROWS,
                        DATA_LENGTH,
                        INDEX_LENGTH,
                        CREATE_TIME,
                        UPDATE_TIME
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
                `);

                const totalSize = allStats.reduce((sum, table) => {
                    return sum + (table.DATA_LENGTH || 0) + (table.INDEX_LENGTH || 0);
                }, 0);

                return {
                    success: true,
                    data: {
                        tables: allStats,
                        totalTables: allStats.length,
                        totalSize,
                    },
                };
            }
        } catch (error: any) {
            logger.error('Failed to get table stats:', error);
            return {
                success: false,
                error: `Failed to get table statistics: ${error.message}`,
            };
        }
    },
};
