<?php

declare(strict_types=1);

namespace App\Modules\Scraper\Queue;

use App\Models\ScraperModel;

/**
 * QueueService.php
 * Service for managing scraper job queues
 */
class QueueService
{
    private \mysqli $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Get queue summary
     *
     * @return array
     */
    public function getQueueSummary(): array
    {
        $stats = $this->buildJobStatusStats();
        $queueStats = $this->buildQueueTableStats();
        $lastActivity = $this->getLastJobActivity();

        return [
            'stats' => $stats,
            'queue' => $queueStats,
            'retryable' => (int)($stats['failed'] ?? 0),
            'last_activity' => $lastActivity,
            'timestamp' => date('c')
        ];
    }

    /**
     * Cancel a queued job.
     */
    public function cancel(int $jobId): array
    {
        $stmt = $this->mysqli->prepare("
            UPDATE web_scraping_jobs
            SET status = 'cancelled', completed_at = NOW(), error_message = 'Cancelled manually'
            WHERE id = ? AND status IN ('pending', 'running')
        ");
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();

        return [
            'success' => $success,
            'affected' => $success ? 1 : 0
        ];
    }

    /**
     * Dequeue the next job for processing.
     */
    public function dequeueNextJob(): ?array
    {
        $model = new ScraperModel($this->mysqli);
        return $model->fetchNextPendingJob();
    }

    /**
     * Clear all pending jobs in the queue.
     *
     * @return int Number of jobs cancelled
     */
    public function clearPendingJobs(): int
    {
        $stmt = $this->mysqli->prepare("
            UPDATE web_scraping_jobs
            SET status = 'cancelled', completed_at = NOW(), error_message = 'Cleared by admin'
            WHERE status = 'pending'
        ");
        $stmt->execute();
        $cleared = $stmt->affected_rows;
        $stmt->close();
        return $cleared;
    }

    /**
     * Retry a failed job by moving it back to pending.
     */
    public function retry(int $jobId): array
    {
        $stmt = $this->mysqli->prepare("
            UPDATE web_scraping_jobs
            SET status = 'pending', started_at = NULL, completed_at = NULL, error_message = NULL
            WHERE id = ? AND status = 'failed'
        ");
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $success = $stmt->affected_rows > 0;
        $stmt->close();

        return [
            'success' => $success,
            'job_id' => $jobId
        ];
    }

    private function buildJobStatusStats(): array
    {
        $defaults = [
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0
        ];

        $result = $this->mysqli->query("SELECT status, COUNT(*) AS count FROM web_scraping_jobs GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = strtolower($row['status'] ?? '');
                if (isset($defaults[$status])) {
                    $defaults[$status] = (int)$row['count'];
                }
            }
            $result->free();
        }

        return $defaults;
    }

    private function buildQueueTableStats(): array
    {
        $stats = [];
        $result = $this->mysqli->query("SELECT status, COUNT(*) AS count FROM web_scraping_queue GROUP BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $status = strtolower($row['status'] ?? 'unknown');
                $stats[$status] = (int)$row['count'];
            }
            $result->free();
        }
        return $stats;
    }

    private function getLastJobActivity(): ?string
    {
        $sql = "
            SELECT GREATEST(
                COALESCE(UNIX_TIMESTAMP(updated_at), 0),
                COALESCE(UNIX_TIMESTAMP(completed_at), 0),
                COALESCE(UNIX_TIMESTAMP(started_at), 0)
            ) AS last_ts
            FROM web_scraping_jobs
            ORDER BY last_ts DESC
            LIMIT 1
        ";
        $result = $this->mysqli->query($sql);
        if (!$result) {
            return null;
        }

        $row = $result->fetch_assoc();
        $result->free();

        if (!empty($row['last_ts'])) {
            return date('c', (int)$row['last_ts']);
        }

        return null;
    }
}
