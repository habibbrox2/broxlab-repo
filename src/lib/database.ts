/**
 * Database Utilities
 * Provides common database operation patterns
 */

import { Logger } from './logger';
import { retryWithBackoff } from './error-handler';

/**
 * Database query options
 */
export interface QueryOptions {
    timeout?: number;
    retry?: boolean;
    maxRetries?: number;
    cache?: boolean;
    cacheTTL?: number;
}

/**
 * Database connection pool manager
 */
export class DatabasePoolManager {
    private pools: Map<string, any> = new Map();
    private cache: Map<string, { data: any; expiresAt: number }> = new Map();

    /**
     * Register a connection pool
     */
    registerPool(name: string, pool: any) {
        this.pools.set(name, pool);
        Logger.info(`Database pool registered: ${name}`);
    }

    /**
     * Get a connection pool
     */
    getPool(name: string = 'default') {
        const pool = this.pools.get(name);
        if (!pool) {
            throw new Error(`Database pool not found: ${name}`);
        }
        return pool;
    }

    /**
     * Check if pool exists
     */
    hasPool(name: string = 'default'): boolean {
        return this.pools.has(name);
    }

    /**
     * Get all pools
     */
    getPools() {
        return Array.from(this.pools.entries());
    }

    /**
     * Close all pools
     */
    async closeAll() {
        for (const [name, pool] of this.pools) {
            try {
                if (pool.end) {
                    await pool.end();
                    Logger.info(`Database pool closed: ${name}`);
                }
            } catch (error) {
                Logger.error(`Error closing database pool ${name}:`, error);
            }
        }
        this.pools.clear();
    }

    /**
     * Execute query with automatic retry and caching
     */
    async executeQuery<T = any>(
        queryFn: () => Promise<T>,
        options: QueryOptions = {}
    ): Promise<T> {
        const { timeout = 30000, retry = true, maxRetries = 3, cache = false, cacheTTL = 300 } = options;

        // Check cache
        if (cache) {
            const cacheKey = queryFn.toString();
            const cached = this.cache.get(cacheKey);
            if (cached && cached.expiresAt > Date.now()) {
                Logger.debug('Cache hit for database query');
                return cached.data;
            }
        }

        // Execute with retry
        const executeWithRetry = retry
            ? () =>
                retryWithBackoff(queryFn, {
                    maxAttempts: maxRetries,
                    onRetry: (attempt, error) => {
                        Logger.warn(`Database query retry attempt ${attempt}:`, error);
                    },
                })
            : queryFn;

        try {
            const result = await Promise.race([
                executeWithRetry(),
                new Promise<T>((_, reject) =>
                    setTimeout(() => reject(new Error(`Query timeout: ${timeout}ms`)), timeout)
                ),
            ]);

            // Store in cache
            if (cache) {
                const cacheKey = queryFn.toString();
                this.cache.set(cacheKey, {
                    data: result,
                    expiresAt: Date.now() + cacheTTL * 1000,
                });
            }

            return result;
        } catch (error) {
            Logger.error('Database query failed:', error);
            throw error;
        }
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
        Logger.info('Database cache cleared');
    }

    /**
     * Get cache stats
     */
    getCacheStats() {
        return {
            size: this.cache.size,
            items: Array.from(this.cache.entries()).map(([key, value]) => ({
                key: key.substring(0, 50),
                expiresIn: Math.max(0, value.expiresAt - Date.now()),
            })),
        };
    }
}

/**
 * Repository base class
 */
export abstract class Repository {
    protected tableName: string = '';
    protected poolManager: DatabasePoolManager;

    constructor(poolManager: DatabasePoolManager) {
        this.poolManager = poolManager;
    }

    /**
     * Find by ID
     */
    async findById<T = any>(id: number, options: QueryOptions = {}): Promise<T | null> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const result = await pool.query(
                `SELECT * FROM ${this.tableName} WHERE id = ? LIMIT 1`,
                [id]
            );
            return result[0] || null;
        }, options);
    }

    /**
     * Find all
     */
    async findAll<T = any>(options: QueryOptions = {}): Promise<T[]> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const [results] = await pool.query(`SELECT * FROM ${this.tableName}`);
            return results as T[];
        }, options);
    }

    /**
     * Find one with query
     */
    async findOne<T = any>(
        query: string,
        params: any[] = [],
        options: QueryOptions = {}
    ): Promise<T | null> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const [results] = await pool.query(query, params);
            return (results as T[])[0] || null;
        }, options);
    }

    /**
     * Find many with query
     */
    async findMany<T = any>(
        query: string,
        params: any[] = [],
        options: QueryOptions = {}
    ): Promise<T[]> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const [results] = await pool.query(query, params);
            return results as T[];
        }, options);
    }

    /**
     * Count records
     */
    async count(whereQuery?: string, params: any[] = [], options: QueryOptions = {}): Promise<number> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const query = whereQuery
                ? `SELECT COUNT(*) as count FROM ${this.tableName} WHERE ${whereQuery}`
                : `SELECT COUNT(*) as count FROM ${this.tableName}`;

            const [results] = await pool.query(query, params);
            return (results as any[])[0]?.count || 0;
        }, options);
    }

    /**
     * Execute custom query
     */
    async execute<T = any>(
        query: string,
        params: any[] = [],
        options: QueryOptions = {}
    ): Promise<T> {
        return this.poolManager.executeQuery(async () => {
            const pool = this.poolManager.getPool();
            const [results] = await pool.query(query, params);
            return results as T;
        }, options);
    }
}

/**
 * Transaction manager
 */
export class TransactionManager {
    private poolManager: DatabasePoolManager;

    constructor(poolManager: DatabasePoolManager) {
        this.poolManager = poolManager;
    }

    /**
     * Execute transaction
     */
    async transaction<T>(
        callback: (connection: any) => Promise<T>
    ): Promise<T> {
        const pool = this.poolManager.getPool();
        const connection = await pool.getConnection();

        try {
            await connection.beginTransaction();
            Logger.debug('Transaction started');

            const result = await callback(connection);

            await connection.commit();
            Logger.debug('Transaction committed');

            return result;
        } catch (error) {
            await connection.rollback();
            Logger.error('Transaction rolled back:', error);
            throw error;
        } finally {
            connection.release();
        }
    }
}

/**
 * Query builder helper
 */
export class QueryBuilder {
    private select: string[] = [];
    private from: string = '';
    private joins: string[] = [];
    private wheres: string[] = [];
    private params: any[] = [];
    private orderBy: string[] = [];
    private limit: number | null = null;
    private offset: number | null = null;

    constructor(table: string) {
        this.from = table;
    }

    /**
     * Add SELECT clause
     */
    addSelect(...columns: string[]): this {
        this.select.push(...columns);
        return this;
    }

    /**
     * Add WHERE clause
     */
    where(condition: string, params: any[] = []): this {
        this.wheres.push(condition);
        this.params.push(...params);
        return this;
    }

    /**
     * Add JOIN clause
     */
    join(joinClause: string): this {
        this.joins.push(joinClause);
        return this;
    }

    /**
     * Add ORDER BY
     */
    orderBy(column: string, direction: 'ASC' | 'DESC' = 'ASC'): this {
        this.orderBy.push(`${column} ${direction}`);
        return this;
    }

    /**
     * Add LIMIT
     */
    take(limit: number): this {
        this.limit = limit;
        return this;
    }

    /**
     * Add OFFSET
     */
    skip(offset: number): this {
        this.offset = offset;
        return this;
    }

    /**
     * Build query
     */
    build(): { query: string; params: any[] } {
        let query = '';

        // SELECT
        if (this.select.length > 0) {
            query += `SELECT ${this.select.join(', ')} `;
        } else {
            query += `SELECT * `;
        }

        // FROM
        query += `FROM ${this.from} `;

        // JOINS
        if (this.joins.length > 0) {
            query += this.joins.join(' ') + ' ';
        }

        // WHERE
        if (this.wheres.length > 0) {
            query += `WHERE ${this.wheres.join(' AND ')} `;
        }

        // ORDER BY
        if (this.orderBy.length > 0) {
            query += `ORDER BY ${this.orderBy.join(', ')} `;
        }

        // LIMIT
        if (this.limit !== null) {
            query += `LIMIT ${this.limit} `;
        }

        // OFFSET
        if (this.offset !== null) {
            query += `OFFSET ${this.offset} `;
        }

        return { query: query.trim(), params: this.params };
    }
}

export default {
    DatabasePoolManager,
    Repository,
    TransactionManager,
    QueryBuilder,
};
