<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * NodeScraperRunner
 * Runs the Node.js scraper for a given AutoContent source ID and returns JSON output.
 */
class NodeScraperRunner
{
    private ?string $nodePath;
    private int $defaultTimeoutSec;

    public function __construct(?string $nodePath = null, int $defaultTimeoutSec = 120)
    {
        $this->nodePath = $this->resolveNodePath($nodePath);
        $this->defaultTimeoutSec = max(10, $defaultTimeoutSec);
    }

    /**
     * Resolve node binary path from a provided path, NODE_BINARY, NODE_PATH, or system PATH.
     */
    private function resolveNodePath(?string $maybeNode): ?string
    {
        $candidate = trim((string)$maybeNode);

        //$1. explicit candidate
        if ($candidate !== '') {
            if ($this->isExecutablePath($candidate)) {
                return $candidate;
            }

            if (strtolower(basename($candidate)) === 'node' || stripos($candidate, 'node') !== false) {
                $found = $this->resolveSystemNode();
                if ($found !== null) {
                    return $found;
                }
            }
        }

        //$2. NODE_BINARY environ
        $envNodeBinary = getenv('NODE_BINARY');
        if ($envNodeBinary !== false && trim($envNodeBinary) !== '' && $this->isExecutablePath(trim($envNodeBinary))) {
            return trim($envNodeBinary);
        }

        //$3. NODE_PATH environ
        $envNodePath = getenv('NODE_PATH');
        if ($envNodePath !== false && trim($envNodePath) !== '' && $this->isExecutablePath(trim($envNodePath))) {
            return trim($envNodePath);
        }

        //$4. system PATH / common paths
        $system = $this->resolveSystemNode();
        if ($system !== null) {
            return $system;
        }

        return null;
    }

    /**
     * Check if path exists and is executable.
     */
    private function isExecutablePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows accepts file existence for node.exe
            return file_exists($path) && is_file($path);
        }

        return file_exists($path) && is_file($path) && is_executable($path);
    }

    /**
     * Try to find node from system PATH or common locations.
     */
    private function resolveSystemNode(): ?string
    {
        $cmd = (stripos(PHP_OS_FAMILY, 'Windows') === 0) ? 'where node' : 'command -v node';
        $output = null;
        $exit = 1;
        @exec($cmd . ' 2>&1', $output, $exit);

        if ($exit === 0 && !empty($output)) {
            foreach ($output as $line) {
                $path = trim($line);
                if ($path !== '' && $this->isExecutablePath($path)) {
                    return $path;
                }
            }
        }

        $common = [
            'C:\\Program Files\\nodejs\\node.exe',
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/opt/nodejs/bin/node',
            'node'
        ];

        foreach ($common as $value) {
            if ($value === 'node') {
                continue;
            }
            if ($this->isExecutablePath($value)) {
                return $value;
            }
        }

        return null;
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

        if (empty($this->nodePath)) {
            return [
                'success' => false,
                'error' => 'node_not_found',
                'stderr' => 'Node.js not found. Set NODE_BINARY or NODE_PATH, or install Node.js so command "node" is available.',
            ];
        }

        // Build command with enhanced error handling
        $cmd = escapeshellarg($this->nodePath)
            . ' ' . escapeshellarg($scraperPath)
            . ' --sourceId=' . (int)$sourceId
            . ' --max=' . (int)$max
            . ' --timeout=' . (int)$timeoutSec;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, $_SERVER);
        $env['LOG_LEVEL'] = $env['LOG_LEVEL'] ?? 'error';
        $env['SCRAPER_JSON_ONLY'] = $env['SCRAPER_JSON_ONLY'] ?? '1';
        $env['DB_HOST'] = $env['DB_HOST'] ?? getenv('DB_HOST') ?: '';
        $env['DB_USER'] = $env['DB_USER'] ?? getenv('DB_USER') ?: '';
        $env['DB_PASS'] = $env['DB_PASS'] ?? getenv('DB_PASS') ?: '';
        $env['DB_NAME'] = $env['DB_NAME'] ?? getenv('DB_NAME') ?: '';
        $env['MYSQL_HOST'] = $env['MYSQL_HOST'] ?? $env['DB_HOST'];
        $env['MYSQL_USER'] = $env['MYSQL_USER'] ?? $env['DB_USER'];
        $env['MYSQL_PASSWORD'] = $env['MYSQL_PASSWORD'] ?? $env['DB_PASS'];
        $env['MYSQL_DATABASE'] = $env['MYSQL_DATABASE'] ?? $env['DB_NAME'];

        $process = proc_open($cmd, $descriptors, $pipes, $repoRoot, $env);
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
        $killed = false;

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
                $killed = true;
                $exitCode = -1;
                $stderr .= "\nTimeout after {$timeoutSec}s";
                break;
            }

            usleep(100000); // 100ms polling
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        $data = $this->parseJsonFromStdout($stdout);

        // Enhanced error handling
        if ($killed) {
            return [
                'success' => false,
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'error' => 'timeout_exceeded',
                'timeout_seconds' => $timeoutSec,
            ];
        }

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'error' => 'node_scraper_failed',
                'error_details' => $this->parseErrorFromStderr($stderr),
            ];
        }

        if (!is_array($data)) {
            return [
                'success' => false,
                'exit_code' => $exitCode,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'error' => 'invalid_json_output',
                'json_error' => json_last_error_msg(),
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

    /**
     * Parse error details from stderr
     */
    private function parseErrorFromStderr(string $stderr): array
    {
        $errors = [];
        $lines = explode("\n", trim($stderr));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Look for common error patterns
            if (preg_match('/Error:\s*(.+)/i', $line, $matches)) {
                $errors[] = $matches[1];
            } elseif (preg_match('/Exception:\s*(.+)/i', $line, $matches)) {
                $errors[] = $matches[1];
            } elseif (preg_match('/Failed to/i', $line)) {
                $errors[] = $line;
            }
        }

        return array_slice($errors, 0, 5); // Limit to first 5 errors
    }

    private function parseJsonFromStdout(string $stdout): ?array
    {
        $trimmed = trim($stdout);
        if ($trimmed === '') {
            return null;
        }

        $data = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        $lines = preg_split('/\r?\n/', $trimmed);
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string)$lines[$i]);
            if ($line === '') {
                continue;
            }
            $candidate = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($candidate)) {
                return $candidate;
            }
        }

        $pos = strrpos($trimmed, '{');
        if ($pos !== false) {
            $candidate = json_decode(substr($trimmed, $pos), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
