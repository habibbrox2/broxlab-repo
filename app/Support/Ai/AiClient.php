<?php

declare(strict_types=1);

namespace App\Support\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Minimal OpenAI-compatible chat client. Works unchanged against OpenRouter
 * (https://openrouter.ai/api/v1) and any OpenAI-compatible endpoint because
 * both expose POST {base_url}/chat/completions with a bearer token.
 */
class AiClient
{
    public function __construct(
        protected ?AiProviderRepository $repository = null,
    ) {
        $this->repository ??= app(AiProviderRepository::class);
    }

    /**
     * @param  array<int, array{role:string,content:string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{ok:bool,content:?string,error:?string,status:?int,model:?string,usage:array<string,mixed>}
     */
    public function chat(array $messages, ?array $provider = null, array $options = []): array
    {
        $provider ??= $this->repository->default();

        if ($provider === null) {
            return $this->failure('No AI provider configured. Add one under AI System → Providers.');
        }

        if (($provider['enabled'] ?? true) === false) {
            return $this->failure('AI provider is disabled.');
        }

        $apiKey = $this->repository->revealKey($provider);
        if ($apiKey === '') {
            return $this->failure('AI provider has no API key.');
        }

        $driver = (string) ($provider['driver'] ?? AiProviderRepository::DRIVER_OPENAI_COMPATIBLE);
        $baseUrl = rtrim((string) ($provider['base_url'] ?? ''), '/');
        if ($baseUrl === '') {
            $baseUrl = AiProviderRepository::DRIVER_BASE_URLS[$driver]
                ?? AiProviderRepository::DRIVER_BASE_URLS[AiProviderRepository::DRIVER_OPENAI_COMPATIBLE];
        }

        $model = trim((string) ($options['model'] ?? ($provider['model'] ?? '')));
        if ($model === '') {
            $model = 'openai/gpt-4o-mini';
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($options['temperature'] ?? ($provider['temperature'] ?? 0.3)),
            'max_tokens' => (int) ($options['max_tokens'] ?? ($provider['max_tokens'] ?? 1024)),
        ];

        $headers = (array) ($provider['headers'] ?? []);
        if ($driver === AiProviderRepository::DRIVER_OPENROUTER) {
            // OpenRouter attribution headers (optional but recommended).
            $headers['HTTP-Referer'] ??= (string) config('app.url', 'https://broxlab.online');
            $headers['X-Title'] ??= (string) config('app.name', 'BroxLab');
        }

        try {
            $response = Http::withToken($apiKey)
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout((int) ($options['timeout'] ?? 60))
                ->post($baseUrl . '/chat/completions', $payload);
        } catch (\Throwable $e) {
            return $this->failure($e->getMessage());
        }

        if (! $response->successful()) {
            return $this->failure(
                'HTTP ' . $response->status() . ': ' . Str::limit((string) $response->body(), 300),
                $response->status()
            );
        }

        $json = $response->json();
        $content = data_get($json, 'choices.0.message.content');

        return [
            'ok' => true,
            'content' => is_string($content) ? $content : '',
            'error' => null,
            'status' => $response->status(),
            'model' => data_get($json, 'model', $model),
            'usage' => (array) data_get($json, 'usage', []),
        ];
    }

    /**
     * Round-trip check used by the admin "Test" button.
     *
     * @param  array<string, mixed>  $provider
     * @return array{ok:bool,error:?string,message:?string,model:?string,latency_ms:int}
     */
    public function test(array $provider): array
    {
        $started = microtime(true);

        $result = $this->chat([
            ['role' => 'system', 'content' => 'You are a connectivity probe. Reply with exactly: pong'],
            ['role' => 'user', 'content' => 'ping'],
        ], $provider, ['max_tokens' => 16, 'temperature' => 0]);

        $latency = (int) round((microtime(true) - $started) * 1000);

        return [
            'ok' => $result['ok'],
            'error' => $result['error'],
            'message' => $result['ok'] ? trim((string) $result['content']) : null,
            'model' => $result['model'],
            'latency_ms' => $latency,
        ];
    }

    /**
     * @return array{ok:bool,content:?string,error:?string,status:?int,model:?string,usage:array<string,mixed>}
     */
    protected function failure(string $error, ?int $status = null): array
    {
        return [
            'ok' => false,
            'content' => null,
            'error' => $error,
            'status' => $status,
            'model' => null,
            'usage' => [],
        ];
    }
}
