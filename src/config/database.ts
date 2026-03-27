import mysql from 'mysql2/promise';
import { config } from './index.js';
import logger from '../utils/logger.js';

// Create connection pool
const pool = mysql.createPool({
    host: config.database.host,
    port: config.database.port,
    user: config.database.user,
    password: config.database.password,
    database: config.database.database,
    connectionLimit: config.database.connectionLimit,
    waitForConnections: true,
    queueLimit: 0,
    enableKeepAlive: true,
    keepAliveInitialDelay: 0,
});

// Test connection
export async function testConnection(): Promise<boolean> {
    try {
        const connection = await pool.getConnection();
        await connection.ping();
        connection.release();
        logger.info('✅ Database connection established');
        return true;
    } catch (error) {
        logger.error('❌ Database connection failed:', error);
        return false;
    }
}

// Execute query with prepared statement
export async function query<T = any>(
    sql: string,
    params?: any[]
): Promise<T[]> {
    try {
        const [rows] = await pool.execute(sql, params);
        return rows as T[];
    } catch (error) {
        logger.error('Database query error:', { sql, params, error });
        throw error;
    }
}

// Execute query and return first row
export async function queryOne<T = any>(
    sql: string,
    params?: any[]
): Promise<T | null> {
    const rows = await query<T>(sql, params);
    return rows.length > 0 ? rows[0] : null;
}

// Execute insert/update/delete and return affected rows
export async function execute(
    sql: string,
    params?: any[]
): Promise<mysql.ResultSetHeader> {
    try {
        const [result] = await pool.execute(sql, params);
        return result as mysql.ResultSetHeader;
    } catch (error) {
        logger.error('Database execute error:', { sql, params, error });
        throw error;
    }
}

// Get connection for transactions
export async function getConnection(): Promise<mysql.PoolConnection> {
    return await pool.getConnection();
}

// Close pool (for graceful shutdown)
export async function closePool(): Promise<void> {
    try {
        await pool.end();
        logger.info('Database connection pool closed');
    } catch (error) {
        logger.error('Error closing database pool:', error);
    }
}

export default pool;
