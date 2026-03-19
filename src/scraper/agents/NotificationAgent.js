/**
 * NotificationAgent
 * Emits events/notifications when new articles are saved
 */

import Logger from '../utils/Logger.js';
import { EventEmitter } from 'events';

class NotificationAgent extends EventEmitter {
    constructor() {
        super();

        // Supported notification types
        this.types = {
            NEW_ARTICLE: 'new_article',
            SCRAPER_ERROR: 'scraper_error',
            CYCLE_COMPLETE: 'cycle_complete'
        };

        // Handlers for different notification types
        this.handlers = new Map();
    }

    /**
     * Initialize notification handlers
     */
    initialize(handlers = {}) {
        this.handlers = new Map([
            // Console logging (always enabled)
            [this.types.NEW_ARTICLE, this.handleNewArticle.bind(this)],
            [this.types.SCRAPER_ERROR, this.handleError.bind(this)],
            [this.types.CYCLE_COMPLETE, this.handleCycleComplete.bind(this)]
        ]);

        // Add custom handlers
        for (const [type, handler] of Object.entries(handlers)) {
            if (this.handlers.has(type)) {
                // Add to existing
                const existing = this.handlers.get(type);
                this.handlers.set(type, async (...args) => {
                    await existing(...args);
                    await handler(...args);
                });
            } else {
                this.handlers.set(type, handler);
            }
        }

        Logger.info('NotificationAgent initialized');
    }

    /**
     * Emit new article event
     */
    async emitNewArticle(article) {
        const event = {
            type: this.types.NEW_ARTICLE,
            article: {
                id: article.id,
                title: article.title,
                link: article.link,
                source: article.source,
                published_at: article.published_at
            },
            timestamp: new Date().toISOString()
        };

        Logger.info('New article event', {
            title: article.title,
            source: article.source
        });

        // Emit to EventEmitter
        this.emit(this.types.NEW_ARTICLE, event);

        // Call handler
        const handler = this.handlers.get(this.types.NEW_ARTICLE);
        if (handler) {
            await handler(event);
        }

        return event;
    }

    /**
     * Emit error event
     */
    async emitError(error, context = {}) {
        const event = {
            type: this.types.SCRAPER_ERROR,
            error: error.message || error,
            context,
            timestamp: new Date().toISOString()
        };

        Logger.error('Scraper error event', { error: error.message, context });

        this.emit(this.types.SCRAPER_ERROR, event);

        const handler = this.handlers.get(this.types.SCRAPER_ERROR);
        if (handler) {
            await handler(event);
        }

        return event;
    }

    /**
     * Emit cycle complete event
     */
    async emitCycleComplete(stats) {
        const event = {
            type: this.types.CYCLE_COMPLETE,
            stats,
            timestamp: new Date().toISOString()
        };

        Logger.debug('Cycle complete event', stats);

        this.emit(this.types.CYCLE_COMPLETE, event);

        const handler = this.handlers.get(this.types.CYCLE_COMPLETE);
        if (handler) {
            await handler(event);
        }

        return event;
    }

    /**
     * Default handler: log to console
     */
    async handleNewArticle(event) {
        console.log('\n📰 NEW ARTICLE SAVED');
        console.log(`   Title: ${event.article.title}`);
        console.log(`   Source: ${event.article.source}`);
        console.log(`   Link: ${event.article.link}`);
        console.log('');
    }

    /**
     * Default handler: log errors
     */
    async handleError(event) {
        console.log('\n❌ SCRAPER ERROR');
        console.log(`   Error: ${event.error}`);
        console.log(`   Context: ${JSON.stringify(event.context)}`);
        console.log('');
    }

    /**
     * Default handler: log cycle stats
     */
    async handleCycleComplete(event) {
        const { stats } = event;
        console.log('\n📊 CYCLE COMPLETE');
        console.log(`   Processed: ${stats.processed || 0}`);
        console.log(`   Saved: ${stats.articlesSaved || 0}`);
        console.log('');
    }

    /**
     * Add custom webhook handler
     */
    addWebhookHandler(url, events = ['new_article']) {
        const handler = async (event) => {
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(event)
                });

                if (!response.ok) {
                    Logger.warn('Webhook delivery failed', {
                        url,
                        status: response.status
                    });
                }
            } catch (error) {
                Logger.error('Webhook error', { url, error: error.message });
            }
        };

        for (const eventType of events) {
            if (this.handlers.has(eventType)) {
                const existing = this.handlers.get(eventType);
                this.handlers.set(eventType, async (...args) => {
                    await existing(...args);
                    await handler(...args);
                });
            } else {
                this.handlers.set(eventType, handler);
            }
        }

        Logger.info('Webhook handler added', { url, events });
    }

    /**
     * Add database log handler
     */
    addDatabaseHandler(databaseService) {
        const handler = async (event) => {
            // Could log to a notifications table
            // For now, just log to file
            Logger.info('Notification event', { type: event.type });
        };

        for (const type of Object.values(this.types)) {
            if (this.handlers.has(type)) {
                const existing = this.handlers.get(type);
                this.handlers.set(type, async (...args) => {
                    await existing(...args);
                    await handler(...args);
                });
            }
        }
    }
}

export default new NotificationAgent();