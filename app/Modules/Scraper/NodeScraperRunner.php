<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * NodeScraperRunner
 * Runs the Node.js scraper for a given AutoContent source ID and returns JSON output.
 */
class NodeScraperRunner
{
    private string $nodePath;
    private int $defaultTimeoutSec;

    public function __construct(?string $nodePath = null, int $defaultTimeoutSec = 120)
    {
        $this->nodePath = $nodePath ?: (getenv('NODE_PATH') ?: 'node');
        $this->defaultTimeoutSec = max(10, $defaultTimeoutSec);
    }

    /**
     * Run node scraper for an AutoContent source row.
     *
     * @return array{success: bool, exit_code?: int, data?: array, stdout?: string, stderr?: string, error?: string}
     */
    public function runForSourceId(int $sourceId, int $max = 10, ?int $timeoutSec = null): array
    {
        $timeoutSec = $timeoutSec === null ? $this->defaultTimeoutSec : max(10, (int)$timeoutSec);
        $max = max(1, (int)$max);

        $repoRoot = dirname(__DIR__, 3);
        $scraperPath = $repoRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'scraper' . DIRECTORY_SEPARATOR . 'index.js';

        if (!is_file($scraperPath)) {
            return ['success' => false, 'error' => 'scraper_entry_not_found'];
        }

        $cmd = escapeshellcmd($this->nodePath)
            . ' ' . escapeshellarg($scraperPath)
            . ' --sourceId=' . (int)$sourceId
            . ' --max=' . (int)$max;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $repoRoot);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'proc_open_failed'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();
        $exitCode = 0;

        while (true) {
            $stdout .= (string)stream_get_contents($pipes[1]);
            $stderr .= (string)stream_get_contents($pipes[2]);

            $status = proc_get_status($process);
            if (!($status['running'] ?? false)) {
                $exitCode = (int)($status['exitcode'] ?? 0);
                break;
            }

            if ((time() - $start) > $timeoutSec) {
                proc_terminate($process);
                $exitCode = -1;
                $stderr .= "\nTimeout after {$timeoutSec}s";
                break;
            }

            usleep(100000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $trimmed = trim($stdout);
        $data = null;
        if ($trimmed !== '') {
            $data = json_decode($trimmed, true);
        }

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'error' => 'node_scraper_failed',
            ];
        }

        if (!is_array($data)) {
            return [
                'success' => false,
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'error' => 'invalid_json_output',
            ];
        }

        return [
            'success' => true,
            'exit_code' => $exitCode,
            'data' => $data,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}

