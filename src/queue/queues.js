import { Queue, QueueEvents } from 'bullmq';
import redis from './redis.js';

export const QUEUE_NAMES = {
    JOB: 'scrape-job',
    RETRY: 'scrape-retry',
    FAILED: 'scrape-failed'
};

export const scrapeQueue = new Queue(QUEUE_NAMES.JOB, {
    connection: redis,
    defaultJobOptions: {
        attempts: Number(process.env.SCRAPE_MAX_ATTEMPTS || 3),
        backoff: {
            type: 'exponential',
            delay: Number(process.env.SCRAPE_RETRY_DELAY_MS || 2000)
        },
        removeOnComplete: false,
        removeOnFail: false
    }
});

export const retryQueue = new Queue(QUEUE_NAMES.RETRY, {
    connection: redis,
    defaultJobOptions: {
        attempts: Number(process.env.SCRAPE_RETRY_MAX_ATTEMPTS || 3),
        backoff: {
            type: 'exponential',
            delay: Number(process.env.SCRAPE_RETRY_DELAY_MS || 3000)
        },
        removeOnComplete: false,
        removeOnFail: false
    }
});

export const failedQueue = new Queue(QUEUE_NAMES.FAILED, {
    connection: redis,
    defaultJobOptions: {
        removeOnComplete: true,
        removeOnFail: false
    }
});

export const scrapeEvents = new QueueEvents(QUEUE_NAMES.JOB, { connection: redis });
export const retryEvents = new QueueEvents(QUEUE_NAMES.RETRY, { connection: redis });
