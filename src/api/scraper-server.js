import express from 'express';
import helmet from 'helmet';
import cors from 'cors';
import rateLimit from 'express-rate-limit';
import { scrapeQueue, retryQueue, failedQueue } from '../queue/queues.js';
import { validateUrl } from '../utils/urlValidator.js';
import logger from '../utils/scraperLogger.js';

const app = express();
const port = Number(process.env.SCRAPER_API_PORT || 7010);

app.use(helmet());
app.use(cors());
app.use(express.json({ limit: '2mb' }));

app.use(rateLimit({
    windowMs: 60 * 1000,
    max: Number(process.env.SCRAPER_RATE_LIMIT || 60)
}));

app.get('/health', (req, res) => {
    return res.json({
        success: true,
        service: 'scraper-queue-api',
        timestamp: new Date().toISOString()
    });
});

app.post('/scrape', async (req, res) => {
    try {
        const url = String(req.body?.url || '');
        const validation = await validateUrl(url);
        if (!validation.ok) {
            return res.status(400).json({ success: false, error: validation.error });
        }

        const job = await scrapeQueue.add('scrape', {
            url,
            options: req.body?.options || {}
        });

        return res.json({ success: true, jobId: job.id });
    } catch (e) {
        logger.error('scrape enqueue failed', { error: e?.message });
        return res.status(500).json({ success: false, error: 'enqueue_failed' });
    }
});

app.get('/status/:jobId', async (req, res) => {
    const jobId = req.params.jobId;
    const job = await scrapeQueue.getJob(jobId)
        || await retryQueue.getJob(jobId)
        || await failedQueue.getJob(jobId);
    if (!job) return res.status(404).json({ success: false, error: 'job_not_found' });
    const state = await job.getState();
    return res.json({ success: true, jobId, state });
});

app.get('/result/:jobId', async (req, res) => {
    const jobId = req.params.jobId;
    const job = await scrapeQueue.getJob(jobId)
        || await retryQueue.getJob(jobId)
        || await failedQueue.getJob(jobId);
    if (!job) return res.status(404).json({ success: false, error: 'job_not_found' });
    const state = await job.getState();
    if (state !== 'completed') {
        return res.json({ success: false, state });
    }
    return res.json({ success: true, result: job.returnvalue });
});

app.listen(port, () => {
    logger.info(`Scraper API running on ${port}`);
});
