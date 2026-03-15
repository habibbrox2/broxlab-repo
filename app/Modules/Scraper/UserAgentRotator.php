<?php

declare(strict_types=1);

namespace App\Modules\Scraper;

/**
 * UserAgentRotator.php
 * Rotates User-Agent strings from a pool of real browser UAs
 * to mimic different browsers and platforms
 */
class UserAgentRotator
{
    private array $userAgents = [];
    private array $platformWeights = [];
    private ?string $lastUserAgent = null;
    
    // Real browser User-Agent strings pool
    private const DEFAULT_USER_AGENTS = [
        // Windows - Chrome
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 11.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Windows - Firefox
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:119.0) Gecko/20100101 Firefox/119.0',
        // Windows - Edge
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        // macOS - Chrome
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // macOS - Safari
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        // macOS - Firefox
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:120.0) Gecko/20100101 Firefox/120.0',
        // Linux - Chrome
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Linux - Firefox
        'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
        // Android - Chrome
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
        // Android - Samsung Browser
        'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 13; SM-A536B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/22.0 Chrome/119.0.0.0 Mobile Safari/537.36',
        // iOS - Safari
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 16_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1',
        // iOS - Chrome
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/119.0.0.0 Mobile/15E148 Safari/604.1',
        // iPad
        'Mozilla/5.0 (iPad; CPU OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (iPad; CPU OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
    ];

    // Platform weights for weighted random selection
    private const PLATFORM_WEIGHTS = [
        'windows' => 35,
        'macos' => 25,
        'linux' => 10,
        'android' => 15,
        'ios' => 15,
    ];

    public function __construct(array $customUserAgents = [])
    {
        $this->userAgents = $customUserAgents ?: self::DEFAULT_USER_AGENTS;
        $this->platformWeights = self::PLATFORM_WEIGHTS;
    }

    /**
     * Get a random User-Agent string
     */
    public function getRandom(): string
    {
        $userAgent = $this->userAgents[array_rand($this->userAgents)];
        $this->lastUserAgent = $userAgent;
        return $userAgent;
    }

    /**
     * Get a random User-Agent for a specific platform
     */
    public function getForPlatform(string $platform): string
    {
        $filtered = array_filter($this->userAgents, function ($ua) use ($platform) {
            return $this->isPlatform($ua, $platform);
        });

        if (empty($filtered)) {
            return $this->getRandom();
        }

        $userAgent = $filtered[array_rand($filtered)];
        $this->lastUserAgent = $userAgent;
        return $userAgent;
    }

    /**
     * Get a weighted random User-Agent based on platform distribution
     */
    public function getWeightedRandom(): string
    {
        $platform = $this->getWeightedPlatform();
        return $this->getForPlatform($platform);
    }

    /**
     * Get a weighted random platform
     */
    private function getWeightedPlatform(): string
    {
        $totalWeight = array_sum($this->platformWeights);
        $random = mt_rand(1, $totalWeight);
        
        $cumulative = 0;
        foreach ($this->platformWeights as $platform => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $platform;
            }
        }
        
        return 'windows';
    }

    /**
     * Check if User-Agent matches platform
     */
    private function isPlatform(string $userAgent, string $platform): bool
    {
        $platformPatterns = [
            'windows' => '/Windows NT|Win64/',
            'macos' => '/Mac OS X|Macintosh/',
            'linux' => '/Linux|X11/',
            'android' => '/Android/',
            'ios' => '/iPhone|iPad|iPod/',
        ];

        return isset($platformPatterns[$platform]) && 
               preg_match($platformPatterns[$platform], $userAgent) === 1;
    }

    /**
     * Get last used User-Agent
     */
    public function getLast(): ?string
    {
        return $this->lastUserAgent;
    }

    /**
     * Add custom User-Agent to pool
     */
    public function add(string $userAgent): self
    {
        if (!in_array($userAgent, $this->userAgents)) {
            $this->userAgents[] = $userAgent;
        }
        return $this;
    }

    /**
     * Remove User-Agent from pool
     */
    public function remove(string $userAgent): self
    {
        $this->userAgents = array_filter($this->userAgents, fn($ua) => $ua !== $userAgent);
        return $this;
    }

    /**
     * Get pool size
     */
    public function count(): int
    {
        return count($this->userAgents);
    }

    /**
     * Get all User-Agents
     */
    public function all(): array
    {
        return $this->userAgents;
    }

    /**
     * Set platform weights for distribution
     */
    public function setPlatformWeights(array $weights): self
    {
        $this->platformWeights = array_merge(self::PLATFORM_WEIGHTS, $weights);
        return $this;
    }

    /**
     * Parse User-Agent to get browser info
     */
    public static function parse(string $userAgent): array
    {
        $info = [
            'platform' => 'Unknown',
            'browser' => 'Unknown',
            'version' => 'Unknown',
        ];

        // Detect platform
        if (preg_match('/Windows NT 10\.0/', $userAgent)) {
            $info['platform'] = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 11\.0/', $userAgent)) {
            $info['platform'] = 'Windows 11';
        } elseif (preg_match('/Mac OS X (\d+[._]\d+)/', $userAgent, $match)) {
            $info['platform'] = 'macOS ' . str_replace('_', '.', $match[1]);
        } elseif (preg_match('/Linux/', $userAgent)) {
            $info['platform'] = 'Linux';
        } elseif (preg_match('/Android (\d+)/', $userAgent, $match)) {
            $info['platform'] = 'Android ' . $match[1];
        } elseif (preg_match('/iPhone OS (\d+)/', $userAgent, $match)) {
            $info['platform'] = 'iOS ' . $match[1];
        } elseif (preg_match('/iPad/', $userAgent)) {
            $info['platform'] = 'iPadOS';
        }

        // Detect browser
        if (preg_match('/Chrome\/(\d+)/', $userAgent, $match)) {
            $info['browser'] = 'Chrome';
            $info['version'] = $match[1];
            if (preg_match('/Edg\//', $userAgent)) {
                $info['browser'] = 'Edge';
            } elseif (preg_match('/SamsungBrowser\//', $userAgent)) {
                $info['browser'] = 'Samsung Browser';
            } elseif (preg_match('/CriOS\//', $userAgent)) {
                $info['browser'] = 'Chrome iOS';
            }
        } elseif (preg_match('/Firefox\/(\d+)/', $userAgent, $match)) {
            $info['browser'] = 'Firefox';
            $info['version'] = $match[1];
        } elseif (preg_match('/Safari\//', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $info['browser'] = 'Safari';
            if (preg_match('/Version\/(\d+)/', $userAgent, $match)) {
                $info['version'] = $match[1];
            }
        }

        return $info;
    }
}
