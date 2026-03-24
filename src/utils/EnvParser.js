/**
 * Environment variable parsing utilities
 * Provides consistent parsing and validation for environment variables
 */

export const parseBoolean = (value, fallback = false) => {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'string') {
        return value.toLowerCase() === 'true';
    }
    return fallback;
};

export const parseInt = (envKey, radix = 10, fallback = 0) => {
    const value = process.env[envKey];
    if (value === undefined || value === '') return fallback;
    const parsed = Number.parseInt(value, radix);
    return Number.isNaN(parsed) ? fallback : parsed;
};

export const parseFloat = (envKey, fallback = 0.0) => {
    const value = process.env[envKey];
    if (value === undefined || value === '') return fallback;
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? fallback : parsed;
};

export const parseString = (envKey, fallback = '') => {
    const value = process.env[envKey];
    return value !== undefined ? value.trim() : fallback;
};

export const parseArray = (envKey, separator = ',', fallback = []) => {
    const value = process.env[envKey];
    if (!value) return fallback;
    return value.split(separator).map(item => item.trim()).filter(item => item.length > 0);
};

export const parseOrigins = (envKey, defaultOrigins = []) => {
    const origins = parseArray(envKey);
    if (origins.length === 0) return defaultOrigins;

    // Validate URLs
    const validOrigins = origins.filter(origin => {
        try {
            new URL(origin);
            return true;
        } catch {
            return false;
        }
    });

    return validOrigins.length > 0 ? validOrigins : defaultOrigins;
};

export const parsePort = (envKey, fallback = 3000) => {
    const port = parseInt(envKey, 10, fallback);
    return Math.max(1024, Math.min(65535, port)); // Valid port range
};

export const parseSize = (value, fallback = '10mb') => {
    if (!value) return fallback;
    // Simple validation for size strings like '10mb', '1gb', etc.
    const sizeRegex = /^(\d+)([kmgt]b?)$/i;
    const match = value.match(sizeRegex);
    if (!match) return fallback;
    return value.toLowerCase();
};