<?php
declare(strict_types=1);

namespace App\Telegram;

use mysqli;
use App\Telegram\BotKernel;

/**
 * WebhookController.php
 * Receives incoming Telegram updates via HTTPS POST.
 * Production-ready with rate limiting, IP verification, and input validation.
 */
class WebhookController
{
    private mysqli $mysqli;
    private \AppSettings $settings;

    // Telegram webhook IP ranges (official)
    private const TELEGRAM_IP_RANGES = [
        '149.154.160.0/20',
        '91.108.4.0/22',
        '91.108.8.0/22',
        '91.108.12.0/22',
        '91.108.16.0/22',
        '91.108.20.0/22',
        '91.108.56.0/22',
        '185.76.151.0/24',
        '205.172.28.0/24',
        '205.172.29.0/24',
    ];

    // Rate limiting: max requests per minute per IP
    private const RATE_LIMIT_PER_MINUTE = 60;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        $this->settings = new \AppSettings($mysqli);
    }

    public function handle(): void
    {
        header('Content-Type: application/json');

        // Verify secret token
        if (!$this->verifySecretToken()) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        // Verify IP is from Telegram (optional, for extra security)
        if (!$this->verifyTelegramIp()) {
            logError('Telegram webhook: Invalid IP address', 'WARNING', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        // Rate limiting
        if (!$this->checkRateLimit()) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests']);
            return;
        }

        // Read and validate input
        $input = file_get_contents('php://input');
        if (empty($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Empty request']);
            return;
        }

        // Validate JSON size (max 1MB)
        if (strlen($input) > 1048576) {
            http_response_code(413);
            echo json_encode(['error' => 'Payload too large']);
            return;
        }

        $update = json_decode($input, true);
        if (!is_array($update) || empty($update)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        // Validate required fields
        if (!isset($update['update_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing update_id']);
            return;
        }

        try {
            $kernel = new BotKernel($this->mysqli);
            $kernel->handleUpdates($update);
            echo json_encode(['status' => 'ok']);
        } catch (\Throwable $e) {
            logError('Telegram webhook handler failed: ' . $e->getMessage(), 'ERROR', [
                'update_id' => $update['update_id'] ?? 'unknown',
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Internal error']);
        }
    }

    /**
     * Test rate limiting functionality
     */
    public function testRateLimiting(): void
    {
        $ipHash = 'test_ip_hash';
        $currentTime = time();

        // Test rate limit check
        $rateLimit = $this->checkRateLimit();
        echo "Initial rate limit check: " . ($rateLimit ? "Blocked\n" : "Allowed\n");

        // Test adding rate limit entries
        $this->addRateLimitEntry($ipHash, $currentTime);
        $rateLimit = $this->checkRateLimit();
        echo "After adding entry: " . ($rateLimit ? "Blocked\n" : "Allowed\n");

        // Test cleanup
        $this->cleanupRateLimitEntries($currentTime - 3600);
        $rateLimit = $this->checkRateLimit();
        echo "After cleanup: " . ($rateLimit ? "Blocked\n" : "Allowed\n");
    }

    /**
     * Test security features
     */
    public function testSecurityFeatures(): void
    {
        // Test IP verification
        $validIps = [
            '103.20.112.0/22', // Cloudflare IP range
            '172.16.0.0/12',  // Private network (should be blocked)
            '192.168.0.0/16', // Private network (should be blocked)
            '10.0.0.0/8',     // Private network (should be blocked)
            '172.16.0.1',     // Specific private IP (should be blocked)
            '103.20.112.1'    // Valid IP (should be allowed)
        ];

        foreach ($validIps as $ip) {
            $result = $this->verifyWebhookIP($ip);
            echo "IP verification for {$ip}: " . ($result ? "Allowed\n" : "Blocked\n");
        }

        // Test input sanitization
        $testInputs = [
            "Normal text",
            "Text with <script>alert('xss')</script>",
            "Text with null byte\0",
            "Text with control chars\x01\x02",
            "Very long text " . str_repeat("a", 5000)
        ];

        foreach ($testInputs as $input) {
            $sanitized = $this->sanitizeInput($input);
            echo "Input sanitization: " . (mb_strlen($sanitized) < 100 ? $sanitized : "Length: " . mb_strlen($sanitized)) . "\n";
        }
    }

    /**
     * Verify the secret token from Telegram header
     */
    private function verifySecretToken(): bool
    {
        $expectedSecret = $this->settings->get('telegram_webhook_secret', '');
        if (empty($expectedSecret)) {
            return true; // No secret configured, skip verification
        }

        $secretHeader = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        return hash_equals($expectedSecret, $secretHeader);
    }

    /**
     * Verify the request is from Telegram's IP ranges
     */
    private function verifyTelegramIp(): bool
    {
        $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($remoteIp)) {
            return false;
        }

        // If no IP ranges configured, skip verification
        $verifyIp = $this->settings->get('telegram_verify_ip', '0');
        if ($verifyIp !== '1') {
            return true;
        }

        foreach (self::TELEGRAM_IP_RANGES as $range) {
            if ($this->ipInRange($remoteIp, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $maskLong = -1 << (32 - (int)$mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Simple rate limiting using database
     */
    private function checkRateLimit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'tg_rate_' . md5($ip);
        $now = time();

        // Clean old entries (older than 2 minutes)
        $stmt = $this->mysqli->prepare(
            "DELETE FROM telegram_rate_limit WHERE expires_at < ?"
        );
        $stmt->bind_param('i', $now);
        $stmt->execute();
        $stmt->close();

        // Count requests in last minute
        $stmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as cnt FROM telegram_rate_limit WHERE ip_hash = ? AND created_at > ?"
        );
        $minuteAgo = $now - 60;
        $stmt->bind_param('si', $key, $minuteAgo);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)($row['cnt'] ?? 0);
        $stmt->close();

        if ($count >= self::RATE_LIMIT_PER_MINUTE) {
            return false;
        }

        // Record this request
        $stmt = $this->mysqli->prepare(
            "INSERT INTO telegram_rate_limit (ip_hash, created_at, expires_at) VALUES (?, ?, ?)"
        );
        $expiresAt = $now + 120;
        $stmt->bind_param('sii', $key, $now, $expiresAt);
        $stmt->execute();
        $stmt->close();

        return true;
    }

    /**
     * Add a rate limit entry for testing
     */
    private function addRateLimitEntry(string $ipHash, int $timestamp): void
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO telegram_rate_limit (ip_hash, created_at, expires_at) VALUES (?, ?, ?)"
        );
        $expiresAt = $timestamp + 120;
        $stmt->bind_param('sii', $ipHash, $timestamp, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clean up rate limit entries for testing
     */
    private function cleanupRateLimitEntries(int $beforeTimestamp): void
    {
        $stmt = $this->mysqli->prepare(
            "DELETE FROM telegram_rate_limit WHERE created_at < ?"
        );
        $stmt->bind_param('i', $beforeTimestamp);
        $stmt->execute();
        $stmt->close();
    }
}
