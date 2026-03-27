import pino from 'pino';
import { config } from '../config/index.js';

// Create logger instance
const logger = pino({
    level: config.logging.level,
    ...(config.logging.pretty && {
        transport: {
            target: 'pino-pretty',
            options: {
                colorize: true,
                translateTime: 'SYS:standard',
                ignore: 'pid,hostname',
            },
        },
    }),
});

// Create child logger with context
export function createLogger(context: string) {
    return logger.child({ context });
}

// Export default logger
export default logger;
