import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index.js';
import { query, queryOne } from '../../config/database.js';
import logger from '../../utils/logger.js';

const contentStatsSchema = z.object({
    days: z.number().int().positive().optional().default(30),
    type: z.enum(['all', 'posts', 'pages', 'comments']).optional().default('all'),
});

export const getContentStatsTool: ToolDefinition = {
    name: 'get_content_stats',
    displayName: 'Get Content Statistics',
    description: 'Get statistics about website content including posts, pages, comments, and engagement metrics',
    parameters: contentStatsSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 15000,
    maxRetries: 2,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { days, type } = args;

        try {
            const dateCondition = `AND created_at >= DATE_SUB(NOW(), INTERVAL ${days} DAY)`;
            const stats: any = {
                period: `${days} days`,
            };

            if (type === 'all' || type === 'posts') {
                const [totalPosts, publishedPosts, recentPosts] = await Promise.all([
                    queryOne('SELECT COUNT(*) as count FROM posts'),
                    queryOne('SELECT COUNT(*) as count FROM posts WHERE status = "published"'),
                    queryOne(`SELECT COUNT(*) as count FROM posts ${dateCondition}`),
                ]);

                stats.posts = {
                    total: totalPosts?.count || 0,
                    published: publishedPosts?.count || 0,
                    draft: (totalPosts?.count || 0) - (publishedPosts?.count || 0),
                    recent: recentPosts?.count || 0,
                };
            }

            if (type === 'all' || type === 'pages') {
                const [totalPages, publishedPages] = await Promise.all([
                    queryOne('SELECT COUNT(*) as count FROM pages'),
                    queryOne('SELECT COUNT(*) as count FROM pages WHERE status = "published"'),
                ]);

                stats.pages = {
                    total: totalPages?.count || 0,
                    published: publishedPages?.count || 0,
                    draft: (totalPages?.count || 0) - (publishedPages?.count || 0),
                };
            }

            if (type === 'all' || type === 'comments') {
                const [totalComments, recentComments, approvedComments] = await Promise.all([
                    queryOne('SELECT COUNT(*) as count FROM comments'),
                    queryOne(`SELECT COUNT(*) as count FROM comments ${dateCondition}`),
                    queryOne('SELECT COUNT(*) as count FROM comments WHERE status = "approved"'),
                ]);

                stats.comments = {
                    total: totalComments?.count || 0,
                    recent: recentComments?.count || 0,
                    approved: approvedComments?.count || 0,
                    pending: (totalComments?.count || 0) - (approvedComments?.count || 0),
                };
            }

            // Get top content by views/engagement if available
            if (type === 'all' || type === 'posts') {
                try {
                    const topPosts = await query(`
                        SELECT id, title, slug, view_count, created_at
                        FROM posts
                        WHERE status = 'published'
                        ORDER BY view_count DESC
                        LIMIT 10
                    `);
                    stats.topPosts = topPosts;
                } catch (error: any) {
                    // Table might not have view_count column
                    logger.debug('View count not available:', error.message);
                }
            }

            // Get content creation trends
            const trends = await query(`
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM posts
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ${Math.min(days, 90)} DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 30
            `);
            stats.creationTrend = trends;

            return {
                success: true,
                data: stats,
            };
        } catch (error: any) {
            logger.error('Failed to get content stats:', error);
            return {
                success: false,
                error: `Failed to get content statistics: ${error.message}`,
            };
        }
    },
};
