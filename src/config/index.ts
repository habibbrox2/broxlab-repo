import dotenv from 'dotenv';
import { z } from 'zod';
import { runtime } from './runtime';

// Load environment variables
dotenv.config();

// Environment schema for validation
const envSchema = z.object({
    // Server
    NODE_ENV: z.enum(['development', 'production', 'test']).default('development'),
    PORT: z.string().default('3000'),
    HOST: z.string().default('0.0.0.0'),
    APP_URL: z.string().default(''),

    // Database
    DB_HOST: z.string().default('localhost'),
    DB_PORT: z.string().default('3306'),
    DB_USER: z.string().default('root'),
    DB_PASS: z.string().default(''),
    DB_NAME: z.string().default('broxlab'),
    DB_CONNECTION_LIMIT: z.string().default('10'),

    // Redis
    REDIS_HOST: z.string().default('localhost'),
    REDIS_PORT: z.string().default('6379'),
    REDIS_PASSWORD: z.string().default(''),
    REDIS_DB: z.string().default('0'),

    // AI Providers
    OPENROUTER_API_KEY: z.string().optional(),
    ANTHROPIC_API_KEY: z.string().optional(),
    OPENAI_API_KEY: z.string().optional(),
    OLLAMA_BASE_URL: z.string().default('https://api.ollama.ai/v1'),
    FIREWORKS_API_KEY: z.string().optional(),
    HUGGINGFACE_API_KEY: z.string().optional(),
    KILO_API_KEY: z.string().optional(),

    // Default AI Settings
    DEFAULT_PROVIDER: z.string().default('openrouter'),
    DEFAULT_MODEL: z.string().default('openrouter/auto'),
    FRONTEND_MODEL: z.string().default('openrouter/gpt-4o'),
    BACKEND_MODEL: z.string().default('openrouter/gpt-4o'),
    MAX_TOKENS: z.string().default('4000'),
    TEMPERATURE: z.string().default('0.7'),

    // Security
    JWT_SECRET: z.string().default('change-me-in-production'),
    CSRF_SECRET: z.string().default('change-me-in-production'),
    NODE_SERVICE_API_KEY: z.string().optional(),

    // Rate Limiting
    RATE_LIMIT_WINDOW_MS: z.string().default('60000'),
    RATE_LIMIT_MAX_REQUESTS: z.string().default('100'),

    // Logging
    LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace']).default('info'),
    LOG_PRETTY: z.string().default('true'),

    // CORS
    CORS_ORIGIN: z.string().default('*'),
    CORS_CREDENTIALS: z.string().default('true'),
});

// Validate and parse environment
const env = envSchema.safeParse(process.env);

if (!env.success) {
    console.error('❌ Invalid environment variables:');
    env.error.errors.forEach((err) => {
        console.error(`  - ${err.path.join('.')}: ${err.message}`);
    });
    process.exit(1);
}

const DEFAULT_SECRET = 'change-me-in-production';

function parseInteger(value: string, name: string): number {
    const parsed = Number.parseInt(value, 10);
    if (Number.isNaN(parsed)) {
        throw new Error(`${name} must be a valid integer`);
    }
    return parsed;
}

function parseFloatValue(value: string, name: string): number {
    const parsed = Number.parseFloat(value);
    if (Number.isNaN(parsed)) {
        throw new Error(`${name} must be a valid number`);
    }
    return parsed;
}

function assertProductionSecrets(nodeEnv: string, jwtSecret: string, csrfSecret: string): void {
    if (nodeEnv !== 'production') {
        return;
    }

    const errors: string[] = [];

    if (!jwtSecret || jwtSecret === DEFAULT_SECRET || jwtSecret.length < 32) {
        errors.push('JWT_SECRET must be set to a unique value with at least 32 characters');
    }

    if (!csrfSecret || csrfSecret === DEFAULT_SECRET || csrfSecret.length < 32) {
        errors.push('CSRF_SECRET must be set to a unique value with at least 32 characters');
    }

    if (errors.length > 0) {
        console.error('❌ Invalid production security configuration:');
        for (const error of errors) {
            console.error(`  - ${error}`);
        }
        process.exit(1);
    }
}

const normalizedPort = parseInteger(env.data.PORT, 'PORT');
const rawHost = env.data.HOST.trim();
const loopbackHosts = new Set(['localhost', '127.0.0.1', '::1', '0.0.0.0', '::', '']);
const normalizedHost = env.data.NODE_ENV === 'development'
    ? (rawHost || '0.0.0.0')
    : (loopbackHosts.has(rawHost) ? '0.0.0.0' : (rawHost || '0.0.0.0'));
const fallbackHost = loopbackHosts.has(normalizedHost) ? 'localhost' : normalizedHost;
const protocol = env.data.NODE_ENV === 'production' ? 'https' : 'http';
const portSegment = !Number.isNaN(normalizedPort) ? `:${normalizedPort}` : '';
const fallbackAppUrl = `${protocol}://${fallbackHost}${portSegment}`;
const appUrl = (env.data.APP_URL.trim() || fallbackAppUrl).replace(/\/+$/, '');
const corsOrigin = env.data.CORS_ORIGIN === '*' ? '*' : (env.data.CORS_ORIGIN || appUrl);

assertProductionSecrets(env.data.NODE_ENV, env.data.JWT_SECRET, env.data.CSRF_SECRET);

// Export typed config
export const config = {
    // Server
    nodeEnv: env.data.NODE_ENV,
    port: normalizedPort,
    host: normalizedHost,
    appUrl,
    isDevelopment: env.data.NODE_ENV === 'development',
    isProduction: env.data.NODE_ENV === 'production',
    isTest: env.data.NODE_ENV === 'test',
    runtime: {
        name: runtime.name,
        version: runtime.version,
        nodeVersion: runtime.nodeVersion,
    },

    // Database
    database: {
        host: env.data.DB_HOST,
        port: parseInteger(env.data.DB_PORT, 'DB_PORT'),
        user: env.data.DB_USER,
        password: env.data.DB_PASS,
        database: env.data.DB_NAME,
        connectionLimit: parseInteger(env.data.DB_CONNECTION_LIMIT, 'DB_CONNECTION_LIMIT'),
    },

    // Redis
    redis: {
        host: env.data.REDIS_HOST,
        port: parseInteger(env.data.REDIS_PORT, 'REDIS_PORT'),
        password: env.data.REDIS_PASSWORD || undefined,
        db: parseInteger(env.data.REDIS_DB, 'REDIS_DB'),
    },

    // AI Providers
    ai: {
        openrouter: {
            apiKey: env.data.OPENROUTER_API_KEY,
        },
        anthropic: {
            apiKey: env.data.ANTHROPIC_API_KEY,
        },
        openai: {
            apiKey: env.data.OPENAI_API_KEY,
        },
        ollama: {
            baseURL: env.data.OLLAMA_BASE_URL,
        },
        fireworks: {
            apiKey: env.data.FIREWORKS_API_KEY,
        },
        huggingface: {
            apiKey: env.data.HUGGINGFACE_API_KEY,
        },
        kilo: {
            apiKey: env.data.KILO_API_KEY,
        },
        defaultProvider: env.data.DEFAULT_PROVIDER,
        defaultModel: env.data.DEFAULT_MODEL,
        frontendModel: env.data.FRONTEND_MODEL,
        backendModel: env.data.BACKEND_MODEL,
        maxTokens: parseInteger(env.data.MAX_TOKENS, 'MAX_TOKENS'),
        temperature: parseFloatValue(env.data.TEMPERATURE, 'TEMPERATURE'),
    },

    // Security
    security: {
        jwtSecret: env.data.JWT_SECRET,
        csrfSecret: env.data.CSRF_SECRET,
        serviceApiKey: env.data.NODE_SERVICE_API_KEY || '',
    },

    // Rate Limiting
    rateLimit: {
        windowMs: parseInteger(env.data.RATE_LIMIT_WINDOW_MS, 'RATE_LIMIT_WINDOW_MS'),
        maxRequests: parseInteger(env.data.RATE_LIMIT_MAX_REQUESTS, 'RATE_LIMIT_MAX_REQUESTS'),
    },

    // Logging
    logging: {
        level: env.data.LOG_LEVEL,
        pretty: env.data.LOG_PRETTY === 'true',
    },

    // CORS
    cors: {
        origin: corsOrigin,
        credentials: env.data.CORS_CREDENTIALS === 'true',
    },
} as const;

export type Config = typeof config;
