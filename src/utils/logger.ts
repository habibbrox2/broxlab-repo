import pino from 'pino';
import { config } from '../config/index';

// Create logger instance
// Use simple JSON transport to avoid ESM module resolution issues with pino-pretty
const logger = pino({
    level: config.logging.level,
});

// Create child logger with context
export function createLogger(context: string) {
    return logger.child({ context });
}

// Export default logger
export default logger;
