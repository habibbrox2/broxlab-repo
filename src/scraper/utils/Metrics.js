/**
 * Metrics Utility
 * Track performance metrics: agent timing, success rates, memory usage
 */

import Logger from './Logger.js';

class Metrics {
    constructor() {
        this.reset();
    }

    reset() {
        this.agentMetrics = {};
        this.operationCount = 0;
        this.successCount = 0;
        this.failureCount = 0;
        this.startMemory = process.memoryUsage().heapUsed;
        this.startTime = Date.now();
    }

    /**
     * Record agent execution timing
     */
    recordAgent(agentName, durationMs, success = true) {
        if (!this.agentMetrics[agentName]) {
            this.agentMetrics[agentName] = {
                runs: 0,
                totalTime: 0,
                avgTime: 0,
                minTime: Infinity,
                maxTime: 0,
                successes: 0,
                failures: 0,
                successRate: 100
            };
        }

        const metric = this.agentMetrics[agentName];
        metric.runs++;
        metric.totalTime += durationMs;
        metric.avgTime = metric.totalTime / metric.runs;
        metric.minTime = Math.min(metric.minTime, durationMs);
        metric.maxTime = Math.max(metric.maxTime, durationMs);

        if (success) {
            metric.successes++;
        } else {
            metric.failures++;
        }

        metric.successRate = ((metric.successes / metric.runs) * 100).toFixed(2);
    }

    /**
     * Record operation result
     */
    recordOperation(success = true) {
        this.operationCount++;
        if (success) {
            this.successCount++;
        } else {
            this.failureCount++;
        }
    }

    /**
     * Get current stats
     */
    getStats() {
        const now = Date.now();
        const currentMemory = process.memoryUsage().heapUsed;
        const memoryDelta = currentMemory - this.startMemory;

        return {
            timestamp: now,
            elapsed: now - this.startTime,
            operations: {
                total: this.operationCount,
                successful: this.successCount,
                failed: this.failureCount,
                successRate: this.operationCount > 0
                    ? ((this.successCount / this.operationCount) * 100).toFixed(2)
                    : 'N/A'
            },
            memory: {
                initial: this.startMemory,
                current: currentMemory,
                delta: memoryDelta,
                deltaMb: (memoryDelta / 1024 / 1024).toFixed(2)
            },
            agents: this.agentMetrics
        };
    }

    /**
     * Log metrics to console/logger
     */
    log(level = 'info') {
        const stats = this.getStats();
        Logger[level]('Performance Metrics', stats);
    }

    /**
     * Get formatted summary
     */
    getSummary() {
        const stats = this.getStats();
        const lines = [
            '=== Metrics Summary ===',
            `Elapsed: ${(stats.elapsed / 1000).toFixed(2)}s`,
            `Operations: ${stats.operations.total} (${stats.operations.successful} success, ${stats.operations.failed} failed)`,
            `Success Rate: ${stats.operations.successRate}%`,
            `Memory Delta: ${stats.memory.deltaMb} MB`
        ];

        for (const [agent, metric] of Object.entries(stats.agents)) {
            lines.push(`  ${agent}: ${metric.runs} runs, avg ${metric.avgTime.toFixed(0)}ms, ${metric.successRate}% success`);
        }

        return lines.join('\n');
    }
}

export default new Metrics();
