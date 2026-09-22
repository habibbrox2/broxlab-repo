<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Ai\AiClient;
use App\Support\BackgroundRemover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public-facing AI photo editing page.
 *
 * Visitors can upload an image, enter a text prompt ("remove the background",
 * "make it vintage", etc.), and receive an AI-generated edit using the
 * configured OpenAI-compatible provider. This wraps OpenAI's /images/edits
 * endpoint and degrades gracefully when no provider is configured.
 */
class PublicPhotoEditController extends Controller
{
    public function __construct(
        protected AiClient $ai,
        protected BackgroundRemover $remover,
    ) {}

    public function index(): View
    {
        return view('pages.photo-edit', [
            'title' => 'AI Photo Edit — এআই ফটো এডিট',
        ]);
    }

    /**
     * Handle the image upload + edit request.
     */
    public function edit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image'  => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'mask'   => ['sometimes', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'prompt' => ['required', 'string', 'max:500'],
            'mode'   => ['sometimes', Rule::in(['edit', 'gen'])],
            'size'   => ['sometimes', 'string', 'max:20'],
        ]);

        // Store the uploaded image to a public temp path so it survives
        // the multipart request to the AI API.
        $path = $request->file('image')->store('photo-edit-temp', 'public');
        $localPath = storage_path('app/public/' . $path);
        $imageUrl  = Storage::url($path); // e.g. /storage/photo-edit-temp/xxx.png

        $mode = ($validated['mode'] ?? 'edit') === 'edit' ? 'edit' : 'gen';
        $size = $validated['size'] ?? '1024x1024';

        // Store the mask (for object removal / inpainting) if one was uploaded.
        $maskPath = null;
        if ($request->hasFile('mask')) {
            $maskPath = $request->file('mask')->store('photo-edit-temp', 'public');
            $maskPath = storage_path('app/public/' . $maskPath);
        }

        $params = [
            'mode'           => $mode,
            'prompt'          => $validated['prompt'],
            'image'           => $localPath,
            'size'            => $size,
            'response_format' => 'b64_json',
        ];

        if ($maskPath !== null) {
            $params['mask'] = $maskPath;
        }

        $result = $this->ai->imageEdit($params);

        if (! $result['ok']) {
            return response()->json([
                'ok'    => false,
                'error' => $result['error'] ?? 'AI edit failed.',
            ], 422);
        }

        return response()->json([
            'ok'        => true,
            'image'     => $result['content'], // b64_json when response_format=b64_json
            'image_url' => $imageUrl,
        ]);
    }

    /**
     * Remove the background from an uploaded image using remove.bg API
     * (or the AI image edit endpoint as fallback).
     */
    public function removeBg(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'size'  => ['sometimes', 'string', 'max:20'],
        ]);

        $path = $request->file('image')->store('photo-edit-temp', 'public');
        $localPath = storage_path('app/public/' . $path);

        $result = $this->remover->remove($localPath, [
            'size' => $validated['size'] ?? 'auto',
            'format' => 'png',
        ]);

        if (! $result['ok']) {
            return response()->json([
                'ok'    => false,
                'error' => $result['error'] ?? 'Background removal failed.',
            ], 422);
        }

        $url = $this->remover->storeResult($result['content'], 'bg-removed');

        return response()->json([
            'ok'      => true,
            'image'   => $result['content'], // base64-encoded PNG
            'image_url' => $url,
        ]);
    }
}
