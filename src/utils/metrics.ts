import { register, collectDefaultMetrics, Gauge, Counter, Histogram } from 'prom-client';

// Enable default metrics collection
collectDefaultMetrics();

// Custom metrics
export const metrics = {
    // Request metrics
    httpRequestsTotal: new Counter({
        name: 'http_requests_total',
        help: 'Total number of HTTP requests',
        labelNames: ['method', 'route', 'status_code']
    }),

    httpRequestDuration: new Histogram({
        name: 'http_request_duration_seconds',
        help: 'Duration of HTTP requests in seconds',
        labelNames: ['method', 'route'],
        buckets: [0.1, 0.5, 1, 2, 5, 10]
    }),

    // AI metrics
    aiRequestsTotal: new Counter({
        name: 'ai_requests_total',
        help: 'Total number of AI requests',
        labelNames: ['provider', 'model', 'success']
    }),

    aiTokensUsed: new Counter({
        name: 'ai_tokens_used_total',
        help: 'Total number of AI tokens used',
        labelNames: ['provider', 'model']
    }),

    // Tool metrics
    toolExecutionsTotal: new Counter({
        name: 'tool_executions_total',
        help: 'Total number of tool executions',
        labelNames: ['tool_name', 'success']
    }),

    toolExecutionDuration: new Histogram({
        name: 'tool_execution_duration_seconds',
        help: 'Duration of tool executions in seconds',
        labelNames: ['tool_name'],
        buckets: [0.1, 0.5, 1, 2, 5, 10, 30]
    }),

    // Cache metrics
    cacheHits: new Counter({
        name: 'cache_hits_total',
        help: 'Total number of cache hits',
        labelNames: ['cache_type']
    }),

    cacheMisses: new Counter({
        name: 'cache_misses_total',
        help: 'Total number of cache misses',
        labelNames: ['cache_type']
    }),

    // Database metrics
    dbConnectionsActive: new Gauge({
        name: 'db_connections_active',
        help: 'Number of active database connections'
    }),

    dbQueryDuration: new Histogram({
        name: 'db_query_duration_seconds',
        help: 'Duration of database queries in seconds',
        labelNames: ['query_type'],
        buckets: [0.01, 0.05, 0.1, 0.5, 1, 2]
    }),

    // System health
    healthChecksTotal: new Counter({
        name: 'health_checks_total',
        help: 'Total number of health checks performed',
        labelNames: ['service', 'status']
    })
};

// Metrics middleware
export function metricsMiddleware() {
    return async (request: any, reply: any) => {
        const start = Date.now();

        reply.raw.on('finish', () => {
            const duration = (Date.now() - start) / 1000;

            metrics.httpRequestsTotal
                .labels(request.method, request.route?.path || request.url, reply.statusCode.toString())
                .inc();

            metrics.httpRequestDuration
                .labels(request.method, request.route?.path || request.url)
                .observe(duration);
        });
    };
}

// Metrics endpoint
export function setupMetricsEndpoint(app: any) {
    app.get('/metrics', async (request: any, reply: any) => {
        try {
            const metricsData = await register.metrics();
            reply.type('text/plain').send(metricsData);
        } catch (error) {
            reply.status(500).send('Error generating metrics');
        }
    });
} 