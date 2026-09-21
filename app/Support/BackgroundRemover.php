<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Background removal service — integrates with remove.bg API
 * (https://www.remove.bg/api) for high-quality automatic background removal.
 *
 * Also falls back to the configured AI provider when no remove.bg key is set,
 * using a "remove the background" prompt via the image edit endpoint.
 */
class BackgroundRemover
{
    /**
     * Remove the background from an image file.
     *
     * @param  string  $imagePath  Absolute path to the source image on disk.
     * @param  array{size?:string,format?:string,type?:string}  $options
     * @return array{ok:bool,content:?string,error:?string,status:?int}
     */
    public function remove(string $imagePath, array $options = []): array
    {
        $apiKey = (string) config('scraper.remove_bg_api_key', '');

        // Primary: remove.bg API
        if ($apiKey !== '') {
            return $this->removeViaRemoveBg($imagePath, $apiKey, $options);
        }

        // Fallback: AI image edit endpoint with "remove background" prompt.
        // If no AI provider is configured, fail with a clear message.
        if (! config('app.ai_enabled', false)) {
            return $this->fail('No background removal method configured. Set REMOVE_BG_API_KEY or enable an AI provider.');
        }

        return $this->removeViaAi($imagePath, $options);
    }

    /**
     * Use the remove.bg API to strip the background.
     */
    protected function removeViaRemoveBg(string $imagePath, string $apiKey, array $options): array
    {
        try {
            $size = $options['size'] ?? 'auto';
            $format = $options['format'] ?? 'png';

            // remove.bg accepts either an image file or URL. We send the file.
            if (! is_readable($imagePath)) {
                return $this->fail('Could not open image file.');
            }

            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->acceptJson()
                ->asMultipart()
                ->attach('image_file', file_get_contents($imagePath), 'image.' . $format)
                ->attach('size', $size)
                ->attach('format', $format)
                ->attach('type', $options['type'] ?? 'auto')
                ->post('https://api.remove.bg/v1.0/removebg');

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'content' => null,
                    'error' => 'remove.bg API error: ' . $response->status() . ' ' . Str::limit((string) $response->body(), 300),
                    'status' => $response->status(),
                ];
            }

            $json = $response->json();

            // remove.bg returns { "image_b64" => "..." } when response_format=json
            // or raw image bytes (PNG) by default.
            if (isset($json['image_b64'])) {
                return [
                    'ok' => true,
                    'content' => $json['image_b64'], // base64-encoded PNG
                    'error' => null,
                    'status' => $response->status(),
                ];
            }

            // Raw image bytes — encode to base64
            $body = $response->body();

            return [
                'ok' => true,
                'content' => base64_encode($body),
                'error' => null,
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'content' => null,
                'error' => $e->getMessage(),
                'status' => null,
            ];
        }
    }

    /**
     * Fallback: use the AI image edit endpoint with "remove background" prompt.
     */
    protected function removeViaAi(string $imagePath, array $options): array
    {
        // Delegate to the AI image edit method with a background-removal prompt.
        /** @var \App\Support\Ai\AiClient $ai */
        $ai = app(\App\Support\Ai\AiClient::class);

        return $ai->imageEdit([
            'mode' => 'edit',
            'prompt' => 'remove the background, make it transparent, isolated subject on transparent background',
            'image' => $imagePath,
            'size' => $options['size'] ?? '1024x1024',
            'response_format' => 'b64_json',
        ]);
    }

    /**
     * Store a base64-encoded PNG to the public temp storage and return its URL.
     */
    public function storeResult(string $base64, string $prefix = 'bg-removed'): ?string
    {
        $data = base64_decode($base64, true);

        if ($data === false) {
            return null;
        }

        $filename = $prefix . '-' . uniqid() . '.png';
        $path = 'photo-edit-temp/' . $filename;

        Storage::disk('public')->put($path, $data);

        // Clean up old temp files (older than 1 day)
        $this->cleanup();

        return Storage::url($path);
    }

    /**
     * Return a failure result array.
     */
    protected function fail(string $message): array
    {
        return [
            'ok'      => false,
            'content' => null,
            'error'   => $message,
            'status'  => null,
        ];
    }

    /**
     * Remove temp files older than 24 hours from photo-edit-temp.
     */
    protected function cleanup(): void
    {
        try {
            $files = Storage::disk('public')->files('photo-edit-temp');
            $cutoff = now()->subDay()->timestamp;

            foreach ($files as $file) {
                if ((Storage::disk('public')->lastModified($file) ?? 0) < $cutoff) {
                    Storage::disk('public')->delete($file);
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore cleanup errors
        }
    }
}
