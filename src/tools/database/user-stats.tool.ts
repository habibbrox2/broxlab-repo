import { z } from 'zod';
import type { ToolDefinition, ToolContext, ToolResult } from '../../types/index;
import { queryOne, query } from '../../config/database;
import logger from '../../utils/logger;

const userStatsSchema = z.object({
    userId: z.number().int().positive().optional(),
    days: z.number().int().positive().optional().default(30),
});

export const getUserStatsTool: ToolDefinition = {
    name: 'get_user_stats',
    displayName: 'Get User Statistics',
    description: 'Get user statistics including counts, activity, and demographics',
    parameters: userStatsSchema,
    requiresAuth: true,
    cacheable: true,
    timeout: 15000,
    maxRetries: 2,
    execute: async (args: any, _context: ToolContext): Promise<ToolResult> => {
        const { userId, days } = args;

        try {
            if (userId) {
                // Get stats for specific user
                const user = await queryOne(
                    'SELECT id, email, name, role, created_at, last_login, is_active FROM users WHERE id = ?',
                    [userId]
                );

                if (!user) {
                    return {
                        success: false,
                        error: `User with ID ${userId} not found`,
                    };
                }

                // Get user's activity stats
                const [loginCount, postCount, commentCount] = await Promise.all([
                    queryOne(
                        'SELECT COUNT(*) as count FROM user_sessions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
                        [userId, days]
                    ),
                    queryOne(
                        'SELECT COUNT(*) as count FROM posts WHERE user_id = ?',
                        [userId]
                    ),
                    queryOne(
                        'SELECT COUNT(*) as count FROM comments WHERE user_id = ?',
                        [userId]
                    ),
                ]);

                return {
                    success: true,
                    data: {
                        user,
                        activity: {
                            logins: loginCount?.count || 0,
                            posts: postCount?.count || 0,
                            comments: commentCount?.count || 0,
                        },
                        period: `${days} days`,
                    },
                };
            } else {
                // Get overall user statistics
                const [
                    totalUsers,
                    activeUsers,
                    newUsers,
                    roleDistribution,
                ] = await Promise.all([
                    queryOne('SELECT COUNT(*) as count FROM users'),
                    queryOne(
                        'SELECT COUNT(*) as count FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
                    ),
                    queryOne(
                        'SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)',
                        [days]
                    ),
                    query(`
                        SELECT role, COUNT(*) as count 
                        FROM users 
                        GROUP BY role 
                        ORDER BY count DESC
                    `),
                ]);

                return {
                    success: true,
                    data: {
                        total: totalUsers?.count || 0,
                        active: activeUsers?.count || 0,
                        new: newUsers?.count || 0,
                        roles: roleDistribution,
                        period: `${days} days`,
                    },
                };
            }
        } catch (error: any) {
            logger.error('Failed to get user stats:', error);
            return {
                success: false,
                error: `Failed to get user statistics: ${error.message}`,
            };
        }
    },
};
