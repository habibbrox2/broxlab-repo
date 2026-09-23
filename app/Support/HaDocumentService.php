<?php

namespace App\Support;

use App\Models\HaServiceDocument;
use App\Models\HaServiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private document storage for digital service requests.
 *
 * Files never land in public storage: they are stored under a random
 * directory on the private 'local' disk and are only reachable through
 * authorized, audited download endpoints or temporary signed URLs.
 */
class HaDocumentService
{
    public const DISK = 'local';

    public const MAX_BYTES = 5 * 1024 * 1024; // 5 MB

    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    /** Store one or many uploaded files for a request. Accepts nested field arrays. */
    public function storeAll(HaServiceRequest $request, array $files): int
    {
        $count = 0;
        foreach ($files as $file) {
            if (is_array($file)) {
                $count += $this->storeAll($request, $file);
                continue;
            }
            if ($file instanceof UploadedFile && $file->isValid()) {
                $this->store($request, $file);
                $count++;
            }
        }

        return $count;
    }

    public function store(HaServiceRequest $request, UploadedFile $file): HaServiceDocument
    {
        $this->assertValid($file);

        $uuid = (string) Str::uuid();
        $path = "service-documents/{$request->id}/{$uuid}";

        $file->storeAs(dirname($path), basename($path), ['disk' => self::DISK]);

        $doc = HaServiceDocument::create([
            'service_request_id' => $request->id,
            'storage_path' => $path,
            'original_name' => substr($file->getClientOriginalName() ?: 'document', 0, 255),
            'mime' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ]);

        ActivityLogger::log('service_document', $doc->id, 'service_document.upload', [
            'request' => $request->tracking_id,
            'name' => $doc->original_name,
        ]);

        return $doc;
    }

    /** MIME + extension + size validation; rejects disguised executables. */
    public function assertValid(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('File exceeds 5 MB limit.');
        }

        $mime = (string) $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension() ?: '');

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new \InvalidArgumentException("File type [{$mime}] is not allowed.");
        }

        if ($ext !== '' && $ext !== self::ALLOWED_MIMES[$mime]) {
            throw new \InvalidArgumentException('File extension does not match its content.');
        }
    }

    /** Authorized inline download via local disk. Caller must already authorize. */
    public function stream(HaServiceDocument $doc): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($doc->storage_path), 404);

        ActivityLogger::log('service_document', $doc->id, 'service_document.download', [
            'request' => $doc->request->tracking_id ?? null,
            'by' => auth()->id(),
        ]);

        return Storage::disk(self::DISK)->download($doc->storage_path, $doc->original_name);
    }

    /** Temporary signed URL (15 min) for portal pages; still logged. */
    public function temporaryUrl(HaServiceDocument $doc, int $minutes = 15): string
    {
        ActivityLogger::log('service_document', $doc->id, 'service_document.signed_url', [
            'request' => $doc->request->tracking_id ?? null,
            'by' => auth()->id(),
        ]);

        // Local driver has no native signed URLs; route through a signed endpoint.
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'ha.documents.signed',
            now()->addMinutes($minutes),
            ['document' => $doc->id]
        );
    }

    public function delete(HaServiceDocument $doc): void
    {
        Storage::disk(self::DISK)->delete($doc->storage_path);
        $doc->delete();
    }
}
