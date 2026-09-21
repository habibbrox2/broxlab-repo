<?php

declare(strict_types=1);

namespace App\Support\Ai;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * File-backed registry of AI providers, modelled on Freebuff's
 * "manage AI providers" screen. Exactly two drivers are supported:
 *
 *   openrouter          — OpenRouter (OpenAI-compatible at /api/v1)
 *   openai_compatible   — any self-hosted or third-party OpenAI-compatible
 *                         endpoint (base URL + optional custom headers)
 *
 * Multiple providers can be registered; one is the default used by the
 * scraper's AI enrichment and any future chat/article features.
 *
 * API keys are encrypted at rest with the app key (Laravel Crypt) and masked
 * whenever the list is handed to a view.
 */
class AiProviderRepository
{
    public const DRIVER_OPENROUTER = 'openrouter';

    public const DRIVER_OPENAI_COMPATIBLE = 'openai_compatible';

    public const DRIVERS = [
        self::DRIVER_OPENROUTER,
        self::DRIVER_OPENAI_COMPATIBLE,
    ];

    public const DRIVER_LABELS = [
        self::DRIVER_OPENROUTER => 'OpenRouter',
        self::DRIVER_OPENAI_COMPATIBLE => 'OpenAI-compatible',
    ];

    public const DRIVER_BASE_URLS = [
        self::DRIVER_OPENROUTER => 'https://openrouter.ai/api/v1',
        self::DRIVER_OPENAI_COMPATIBLE => 'https://api.openai.com/v1',
    ];

    protected string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? storage_path('app/ai/providers.json');
    }

    // -------------------------------------------------------------------- reads

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $providers = $this->readFile();

        return is_array($providers) ? array_values($providers) : [];
    }

    /**
     * Providers with the API key replaced by a mask — safe for views.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allMasked(): array
    {
        return array_map(function (array $provider): array {
            $provider['api_key'] = $this->mask($this->revealKey($provider));
            $provider['has_key'] = $this->revealKey($provider) !== '';

            return $provider;
        }, $this->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        foreach ($this->all() as $provider) {
            if ((string) $provider['id'] === (string) $id) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * The default provider, falling back to the first enabled one.
     *
     * @return array<string, mixed>|null
     */
    public function default(): ?array
    {
        $providers = $this->all();
        if ($providers === []) {
            return null;
        }

        foreach ($providers as $provider) {
            if (($provider['is_default'] ?? false) && ($provider['enabled'] ?? true)) {
                return $provider;
            }
        }

        foreach ($providers as $provider) {
            if ($provider['enabled'] ?? true) {
                return $provider;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->all());
    }

    // ------------------------------------------------------------------- writes

    /**
     * Create or update a provider. Returns the stored provider.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function upsert(array $attributes): array
    {
        $providers = $this->all();
        $id = (string) ($attributes['id'] ?? '');
        $index = null;

        foreach ($providers as $i => $provider) {
            if ((string) $provider['id'] === $id && $id !== '') {
                $index = $i;
                break;
            }
        }

        $existing = $index !== null ? $providers[$index] : null;
        $driver = $this->normalizeDriver($attributes['driver'] ?? $existing['driver'] ?? self::DRIVER_OPENROUTER);
        $now = gmdate('c');

        $provider = [
            'id' => $existing['id'] ?? ($id !== '' ? $id : Str::random(12)),
            'name' => trim((string) ($attributes['name'] ?? $existing['name'] ?? 'Untitled provider')),
            'driver' => $driver,
            'base_url' => rtrim(trim((string) ($attributes['base_url'] ?? $existing['base_url'] ?? self::DRIVER_BASE_URLS[$driver])), '/'),
            'model' => trim((string) ($attributes['model'] ?? $existing['model'] ?? '')),
            'temperature' => (float) ($attributes['temperature'] ?? $existing['temperature'] ?? 0.3),
            'max_tokens' => (int) ($attributes['max_tokens'] ?? $existing['max_tokens'] ?? 1024),
            'enabled' => (bool) ($attributes['enabled'] ?? $existing['enabled'] ?? true),
            'is_default' => (bool) ($attributes['is_default'] ?? $existing['is_default'] ?? false),
            'headers' => $this->normalizeHeaders($attributes['headers'] ?? $existing['headers'] ?? []),
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        if ($provider['name'] === '') {
            $provider['name'] = self::DRIVER_LABELS[$driver];
        }

        // Only replace the key when a non-empty one was supplied (blank keeps existing).
        $submittedKey = $attributes['api_key'] ?? null;
        if (is_string($submittedKey) && trim($submittedKey) !== '') {
            $provider['api_key_encrypted'] = Crypt::encryptString(trim($submittedKey));
        } elseif ($existing !== null) {
            $provider['api_key_encrypted'] = $existing['api_key_encrypted'] ?? null;
        } else {
            $provider['api_key_encrypted'] = null;
        }

        if ($index !== null) {
            $providers[$index] = $provider;
        } else {
            $providers[] = $provider;
        }

        if ($provider['is_default']) {
            $providers = $this->clearDefaultExcept($providers, (string) $provider['id']);
        } elseif ($this->default() === null && $index === null) {
            // First provider added becomes default automatically.
            $providers[count($providers) - 1]['is_default'] = true;
            $provider['is_default'] = true;
        }

        $this->writeFile($providers);

        return $provider;
    }

    public function setDefault(string $id): bool
    {
        $providers = $this->all();
        $found = false;
        foreach ($providers as $i => $provider) {
            $providers[$i]['is_default'] = (string) $provider['id'] === $id;
            $found = $found || $providers[$i]['is_default'];
        }

        if (! $found) {
            return false;
        }

        $this->writeFile($providers);

        return true;
    }

    public function delete(string $id): bool
    {
        $providers = $this->all();
        $remaining = array_values(array_filter(
            $providers,
            fn (array $provider) => (string) $provider['id'] !== $id
        ));

        if (count($remaining) === count($providers)) {
            return false;
        }

        // Re-elect a default if we removed it.
        $hasDefault = (bool) array_filter($remaining, fn (array $p) => $p['is_default'] ?? false);
        if (! $hasDefault && $remaining !== []) {
            $remaining[0]['is_default'] = true;
        }

        $this->writeFile($remaining);

        return true;
    }

    public function revealKey(array $provider): string
    {
        $encrypted = $provider['api_key_encrypted'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return '';
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return '';
        }
    }

    // ---------------------------------------------------------------- internals

    protected function normalizeDriver(mixed $driver): string
    {
        $driver = is_string($driver) ? strtolower(trim($driver)) : '';

        return in_array($driver, self::DRIVERS, true) ? $driver : self::DRIVER_OPENROUTER;
    }

    /**
     * @param  mixed  $headers
     * @return array<string, string>
     */
    protected function normalizeHeaders(mixed $headers): array
    {
        if (is_string($headers)) {
            $decoded = json_decode($headers, true);
            $headers = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($headers)) {
            return [];
        }

        $clean = [];
        foreach ($headers as $key => $value) {
            if (is_string($key) && is_scalar($value) && trim((string) $value) !== '') {
                $clean[trim($key)] = trim((string) $value);
            }
        }

        return $clean;
    }

    /**
     * @param  array<int, array<string, mixed>>  $providers
     * @return array<int, array<string, mixed>>
     */
    protected function clearDefaultExcept(array $providers, string $id): array
    {
        foreach ($providers as $i => $provider) {
            $providers[$i]['is_default'] = (string) $provider['id'] === $id;
        }

        return $providers;
    }

    protected function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }

        $tail = mb_substr($key, -4);

        return '••••••••' . $tail;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function readFile(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $raw = @file_get_contents($this->path);
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $providers
     */
    protected function writeFile(array $providers): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode(array_values($providers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return;
        }

        $tmp = $this->path . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $json) !== false) {
            @rename($tmp, $this->path);
        }
    }
}
