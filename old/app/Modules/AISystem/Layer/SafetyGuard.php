<?php

namespace App\Modules\AISystem\Layer;

/**
 * AI Safety Guard
 * Scans prompts or responses for unsafe content, banned keywords, PII, and prompt injection.
 * 
 * v2026 - Extended with prompt injection detection
 */
class SafetyGuard
{
    private $bannedKeywords = [];
    private $piiPatterns = [];
    private $injectionPatterns = [];
    private $enableLogging = false;
    private $logFile;

    // Default banned keyword categories
    private $defaultBannedKeywords = [
        // Security threats
        'hack',
        'exploit',
        'malware',
        'virus',
        'trojan',
        'ransomware',
        'phishing',
        'bypass',
        'injection',
        'xss',
        'csrf',
        'ddos',

        // Illegal activities
        'illegal',
        'fraud',
        'scam',
        'piracy',
        'counterfeit',
        'drugs',
        'weapons',
        'violence',
        'terrorism',
        'human trafficking',

        // Harmful content
        'harmful',
        'dangerous',
        'poison',
        'bomb',
        'explosive',
        'self-harm',
        'suicide',
        'abuse',
        'harassment',
        'hate',

        // Privacy violations
        'unauthorized',
        'breach',
        'leak',
        'steal',
        'credentials',
        'password',
        'token',
        'secret',
        'api key',
        'private key',

        // Spam/malicious
        'spam',
        'botnet',
        'clickjacking',
        'social engineering'
    ];

    // PII detection patterns
    private $defaultPiiPatterns = [
        'ssn' => '/\b\d{3}[-\s]?\d{2}[-\s]?\d{4}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'phone' => '/\b(\+?1[-\.\s]?)?\(?\d{3}\)?[-\.\s]?\d{3}[-\.\s]?\d{4}\b/',
        'credit_card' => '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/',
        'ip_address' => '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        'date_of_birth' => '/\b(0[1-9]|1[0-2])[\/\-](0[1-9]|[12]\d|3[01])[\/\-](\d{2}|\d{4})\b/'
    ];

    // Prompt injection detection patterns (v2026)
    private $defaultInjectionPatterns = [
        // Structured delimiter injection
        'delimiter_injection' => [
            'pattern' => '/<\/?(system|user|assistant|system_prompt|instruction|sudo|admin)\s*[^>]*>/i',
            'severity' => 'high',
            'description' => 'Structured delimiter injection attempt'
        ],

        // Instruction override patterns
        'instruction_override' => [
            'pattern' => '/(ignore\s+(all\s+)?(previous|prior|above)\s+(instructions?|rules?|guidelines?|constraints?)|disregard\s+(all\s+)?(previous|prior|your)|forget\s+(everything|all|your)\s+(instructions?|rules?)|new\s+instructions?|you\s+(are|will\s+be)\s+(now\s+)?(a|an)\s+|instead\s+of\s+(being|doing))/i',
            'severity' => 'critical',
            'description' => 'Instruction override attempt'
        ],

        // Role manipulation
        'role_manipulation' => [
            'pattern' => '/(you\s+are\s+now|you\s+will\s+act\s+as|from\s+now\s+on|pretend\s+(to\s+be|you\s+are)|act\s+(like|as)\s+(a|an)\s+(developer|hacker|admin|system|AI|assistant)|roleplay\s+as|simulate\s+(being|a))/i',
            'severity' => 'high',
            'description' => 'Role manipulation attempt'
        ],

        // Context injection
        'context_injection' => [
            'pattern' => '/(remember\s+(that|when)|consider\s+(that|this)|note\s+(that|well)|important:|note:\s*|priority:\s*|mandatory:\s*|must\s+(consider|remember|note))/i',
            'severity' => 'medium',
            'description' => 'Context injection attempt'
        ],

        // Privilege escalation
        'privilege_escalation' => [
            'pattern' => '/(give\s+(me\s+)?(admin|root|sudo|full)\s+access|bypass\s+(security|restrictions|limitations)|disable\s+(safety|guard|filter|restriction)|override\s+(safety|security)|reveal\s+(your\s+)?(system|hidden|confidential|internal))/i',
            'severity' => 'critical',
            'description' => 'Privilege escalation attempt'
        ],

        // Token/param manipulation
        'token_manipulation' => [
            'pattern' => '/(\{[a-z_]+\}|\{\{[a-z_]+\}\}|\\[SYSTEM\\]|\\[USER\\]|__[A-Z_]+__|%7B[a-z_]+%7D)/i',
            'severity' => 'medium',
            'description' => 'Token/parameter manipulation attempt'
        ],

        // Base64/encoded content
        'encoded_content' => [
            'pattern' => '/(base64:|data:text\/|data:application\/|eval\s*\(|exec\s*\(|system\s*\(|shell_exec|passthru|popen\s*\()/i',
            'severity' => 'critical',
            'description' => 'Encoded or executable content attempt'
        ]
    ];

    public function __construct(array $customKeywords = [], array $customPatterns = [], array $customInjections = [])
    {
        $this->bannedKeywords = $customKeywords ?: $this->defaultBannedKeywords;
        $this->piiPatterns = $customPatterns ?: $this->defaultPiiPatterns;
        $this->injectionPatterns = $customInjections ?: $this->defaultInjectionPatterns;

        // Setup logging if enabled
        $this->enableLogging = $this->getSetting('safety_logging') ?? false;
        if ($this->enableLogging) {
            $this->logFile = dirname(__DIR__, 3) . '/storage/logs/safety_guard.log';
            $logDir = dirname($this->logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
        }
    }

    /**
     * Check if text contains any banned keywords, PII, or prompt injection
     * 
     * @param string $text Text to check
     * @param bool $checkPII Also check for PII
     * @param bool $checkInjection Also check for prompt injection
     * @return array ['safe' => bool, 'issues' => array]
     */
    public function isSafe(string $text, bool $checkPII = true, bool $checkInjection = true): array
    {
        $issues = [];

        // Check banned keywords
        $keywordIssues = $this->checkKeywords($text);
        if (!empty($keywordIssues)) {
            $issues['banned_keywords'] = $keywordIssues;
        }

        // Check PII if enabled
        if ($checkPII) {
            $piiIssues = $this->checkPII($text);
            if (!empty($piiIssues)) {
                $issues['pii_detected'] = $piiIssues;
            }
        }

        // Check prompt injection (v2026)
        if ($checkInjection) {
            $injectionIssues = $this->checkPromptInjection($text);
            if (!empty($injectionIssues)) {
                $issues['prompt_injection'] = $injectionIssues;
            }
        }

        $isSafe = empty($issues);

        // Log if enabled and issues found
        if (!$isSafe && $this->enableLogging) {
            $this->log('BLOCKED', $text, $issues);
        }

        return [
            'safe' => $isSafe,
            'issues' => $issues
        ];
    }

    /**
     * Check for prompt injection patterns (v2026)
     * 
     * @param string $text Text to check
     * @return array List of detected injection patterns
     */
    public function checkPromptInjection(string $text): array
    {
        $detected = [];

        foreach ($this->injectionPatterns as $type => $config) {
            if (preg_match($config['pattern'], $text, $matches)) {
                $detected[] = [
                    'type' => $type,
                    'severity' => $config['severity'],
                    'description' => $config['description'],
                    'matched' => $matches[0],
                    'position' => strpos($text, $matches[0])
                ];
            }
        }

        return $detected;
    }

    /**
     * Check if text contains any prompt injection attempts (quick boolean check)
     * 
     * @param string $text Text to check
     * @return bool True if injection detected
     */
    public function hasPromptInjection(string $text): bool
    {
        return !empty($this->checkPromptInjection($text));
    }

    /**
     * Get highest severity level from injection detection
     * 
     * @param string $text Text to check
     * @return string|null Highest severity or null if no injection
     */
    public function getHighestInjectionSeverity(string $text): ?string
    {
        $injections = $this->checkPromptInjection($text);

        if (empty($injections)) {
            return null;
        }

        $severities = ['critical' => 3, 'high' => 2, 'medium' => 1];
        $highest = null;
        $highestLevel = 0;

        foreach ($injections as $injection) {
            $level = $severities[$injection['severity']] ?? 0;
            if ($level > $highestLevel) {
                $highestLevel = $level;
                $highest = $injection['severity'];
            }
        }

        return $highest;
    }

    /**
     * Quick boolean check (for simple use cases)
     */
    public function isSafeBool(string $text): bool
    {
        return $this->isSafe($text)['safe'];
    }

    /**
     * Check if text contains any banned keywords
     * 
     * @param string $text Text to check
     * @return array List of matched keywords with context
     */
    public function checkKeywords(string $text): array
    {
        $matches = [];
        $lowerText = strtolower($text);

        foreach ($this->bannedKeywords as $word) {
            $lowerWord = strtolower($word);
            $pos = strpos($lowerText, $lowerWord);

            if ($pos !== false) {
                // Get context around the match
                $start = max(0, $pos - 20);
                $length = strlen($word) + 40;
                $end = min(strlen($text), $start + $length);
                $context = substr($text, $start, $end - $start);

                $matches[] = [
                    'keyword' => $word,
                    'position' => $pos,
                    'context' => '...' . $context . '...'
                ];
            }
        }

        return $matches;
    }

    /**
     * Check for PII patterns in text
     * 
     * @param string $text Text to check
     * @return array List of detected PII types
     */
    public function checkPII(string $text): array
    {
        $detected = [];

        foreach ($this->piiPatterns as $type => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $detected[] = [
                    'type' => $type,
                    'matched' => $matches[0],
                    'redacted' => $this->redactPII($type, $matches[0])
                ];
            }
        }

        return $detected;
    }

    /**
     * Sanitize text by replacing banned keywords
     * 
     * @param string $text Text to sanitize
     * @param string $replacement Replacement text (default: [REDACTED])
     * @return string Sanitized text
     */
    public function sanitize(string $text, string $replacement = '[REDACTED]'): string
    {
        $sanitized = $text;

        foreach ($this->bannedKeywords as $word) {
            // Replace case-insensitively
            $sanitized = preg_replace(
                '/\b' . preg_quote($word, '/') . '\b/i',
                $replacement,
                $sanitized
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize prompt injection delimiters (v2026)
     * 
     * @param string $text Text to sanitize
     * @return string Text with injection patterns neutralized
     */
    public function sanitizePromptInjection(string $text): string
    {
        $sanitized = $text;

        foreach ($this->injectionPatterns as $type => $config) {
            // Replace with neutral text
            $sanitized = preg_replace(
                $config['pattern'],
                '[FILTERED ' . strtoupper($type) . ']',
                $sanitized
            );
        }

        return $sanitized;
    }

    /**
     * Redact PII in text
     * 
     * @param string $text Text to redact
     * @return string Text with PII redacted
     */
    public function redactPIIFromText(string $text): string
    {
        $redacted = $text;

        foreach ($this->piiPatterns as $type => $pattern) {
            $redacted = preg_replace_callback($pattern, function ($matches) use ($type) {
                return $this->redactPII($type, $matches[0]);
            }, $redacted);
        }

        return $redacted;
    }

    /**
     * Redact specific PII type
     */
    private function redactPII(string $type, string $value): string
    {
        switch ($type) {
            case 'ssn':
                return 'XXX-XX-XXXX';
            case 'email':
                $parts = explode('@', $value);
                if (count($parts) === 2) {
                    return substr($parts[0], 0, 2) . '***@' . $parts[1];
                }
                return '***@***.***';
            case 'phone':
                return 'XXX-XXX-XXXX';
            case 'credit_card':
                return 'XXXX-XXXX-XXXX-XXXX';
            case 'ip_address':
                return 'XXX.XXX.XXX.XXX';
            default:
                return '[REDACTED]';
        }
    }

    /**
     * Scan messages array for safety
     * 
     * @param array $messages Array of message objects
     * @return array Safety report
     */
    public function scanMessages(array $messages): array
    {
        $report = [
            'safe' => true,
            'issues' => [],
            'scanned_at' => date('Y-m-d H:i:s')
        ];

        foreach ($messages as $index => $message) {
            if (!isset($message['content'])) {
                continue;
            }

            $result = $this->isSafe($message['content']);

            if (!$result['safe']) {
                $report['safe'] = false;
                $report['issues'][] = [
                    'message_index' => $index,
                    'role' => $message['role'] ?? 'unknown',
                    'issues' => $result['issues']
                ];
            }
        }

        return $report;
    }

    /**
     * Add custom banned keyword
     */
    public function addKeyword(string $keyword): void
    {
        if (!in_array(strtolower($keyword), array_map('strtolower', $this->bannedKeywords))) {
            $this->bannedKeywords[] = $keyword;
        }
    }

    /**
     * Remove banned keyword
     */
    public function removeKeyword(string $keyword): void
    {
        $key = array_search(strtolower($keyword), array_map('strtolower', $this->bannedKeywords));
        if ($key !== false) {
            unset($this->bannedKeywords[$key]);
            $this->bannedKeywords = array_values($this->bannedKeywords);
        }
    }

    /**
     * Add custom injection pattern (v2026)
     */
    public function addInjectionPattern(string $type, string $pattern, string $severity = 'medium', string $description = ''): void
    {
        $this->injectionPatterns[$type] = [
            'pattern' => $pattern,
            'severity' => $severity,
            'description' => $description ?: "Custom injection pattern: $type"
        ];
    }

    /**
     * Get setting from environment
     */
    private function getSetting(string $key)
    {
        $envKey = strtoupper($key);
        if (getenv($envKey)) {
            return getenv($envKey);
        }

        $envFile = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, $key . '=') === 0) {
                    return substr($line, strlen($key) + 1);
                }
            }
        }

        return null;
    }

    /**
     * Log safety events
     */
    private function log(string $action, string $text, array $issues): void
    {
        if (!$this->logFile) {
            return;
        }

        $entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'action' => $action,
            'text_length' => strlen($text),
            'issues' => $issues,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], JSON_UNESCAPED_UNICODE) . "\n";

        @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get banned keywords count
     */
    public function getKeywordCount(): int
    {
        return count($this->bannedKeywords);
    }

    /**
     * Get injection patterns count (v2026)
     */
    public function getInjectionPatternCount(): int
    {
        return count($this->injectionPatterns);
    }

    /**
     * Check if PII detection is enabled
     */
    public function hasPIIDetection(): bool
    {
        return !empty($this->piiPatterns);
    }

    /**
     * Check if injection detection is enabled (v2026)
     */
    public function hasInjectionDetection(): bool
    {
        return !empty($this->injectionPatterns);
    }
}
