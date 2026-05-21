<?php
/**
 * ChatMessageService - Message normalization, image handling, text extraction
 * Extracted from AISystemController.php for modularity
 */

class ChatMessageService
{
    /**
     * Extract text from message content (supports string or array format)
     */
    public static function extractText($content): string
    {
        if (is_string($content)) {
            return trim($content);
        }
        if (!is_array($content)) {
            return '';
        }
        $parts = [];
        foreach ($content as $part) {
            if (!is_array($part)) {
                continue;
            }
            if (($part['type'] ?? '') !== 'text') {
                continue;
            }
            $text = $part['text'] ?? '';
            if (is_string($text) && trim($text) !== '') {
                $parts[] = trim($text);
            }
        }
        return trim(implode("\n", $parts));
    }

    /**
     * Normalize messages array with validation
     */
    public static function normalizeMessages($messages, int $maxMessages, int $maxChars, ?string &$error = null): array
    {
        if (!is_array($messages)) {
            $error = 'Messages array is required';
            return [];
        }

        $out = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = $msg['role'] ?? '';
            if (!in_array($role, ['user', 'assistant'], true)) {
                continue;
            }
            $content = $msg['content'] ?? '';
            $normalizedContent = null;
            $contentLen = 0;
            if (is_string($content)) {
                $content = trim($content);
                if ($content === '') {
                    continue;
                }
                $normalizedContent = $content;
                $contentLen = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
            } elseif (is_array($content)) {
                $parts = [];
                $textLen = 0;
                foreach ($content as $part) {
                    if (!is_array($part)) {
                        continue;
                    }
                    $type = $part['type'] ?? '';
                    if ($type === 'text') {
                        $text = $part['text'] ?? '';
                        if (!is_string($text)) {
                            continue;
                        }
                        $text = trim($text);
                        if ($text === '') {
                            continue;
                        }
                        $parts[] = ['type' => 'text', 'text' => $text];
                        $textLen += function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
                        continue;
                    }
                    if ($type === 'image_url') {
                        $image = $part['image_url'] ?? [];
                        $url = $image['url'] ?? '';
                        if (!is_string($url) || trim($url) === '') {
                            continue;
                        }
                        $artifact = ['url' => trim($url)];
                        if (!empty($image['name']) && is_string($image['name'])) {
                            $artifact['name'] = trim($image['name']);
                        }
                        if (!empty($image['mime']) && is_string($image['mime'])) {
                            $artifact['mime'] = trim($image['mime']);
                        }
                        if (isset($image['size']) && (is_int($image['size']) || is_numeric($image['size']))) {
                            $artifact['size'] = (int)$image['size'];
                        }
                        $parts[] = ['type' => 'image_url', 'image_url' => $artifact];
                        continue;
                    }
                    if ($type === 'file') {
                        $file = $part['file'] ?? [];
                        if (!is_array($file)) {
                            continue;
                        }
                        $fileData = $file['file_data'] ?? $file['fileData'] ?? '';
                        $fileId = $file['file_id'] ?? $file['fileId'] ?? '';
                        $filename = $file['filename'] ?? $file['name'] ?? '';
                        if (!is_string($filename) || trim($filename) === '') {
                            $filename = 'attachment';
                        }
                        $artifact = ['filename' => trim($filename)];
                        if (is_string($fileData) && trim($fileData) !== '') {
                            $artifact['file_data'] = trim($fileData);
                        } elseif (is_string($fileId) && trim($fileId) !== '') {
                            $artifact['file_id'] = trim($fileId);
                        } else {
                            continue;
                        }
                        $parts[] = ['type' => 'file', 'file' => $artifact];
                    }
                }
                if (empty($parts)) {
                    continue;
                }
                $normalizedContent = $parts;
                $contentLen = $textLen;
            } else {
                continue;
            }
            $len = $contentLen;
            if ($len > $maxChars) {
                $error = 'Message too long';
                return [];
            }
            $normalized = ['role' => $role, 'content' => $normalizedContent];
            if (
                $role === 'assistant' &&
                isset($msg['annotations']) &&
                is_array($msg['annotations']) &&
                !empty($msg['annotations'])
            ) {
                $normalized['annotations'] = $msg['annotations'];
            }
            $out[] = $normalized;
        }

        if (empty($out)) {
            $error = 'No valid messages';
            return [];
        }

        if (count($out) > $maxMessages) {
            $out = array_slice($out, -$maxMessages);
        }

        return $out;
    }

    /**
     * Get the last user message text
     */
    public static function lastUserMessage(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? '') === 'user') {
                return self::extractText($messages[$i]['content'] ?? '');
            }
        }
        return '';
    }

    /**
     * Extract image references from messages
     */
    public static function extractImageReferences(array $messages): array
    {
        $refs = [];
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['content']) || !is_array($msg['content'])) {
                continue;
            }
            foreach ($msg['content'] as $part) {
                if (!is_array($part) || ($part['type'] ?? '') !== 'image_url') {
                    continue;
                }
                $image = $part['image_url'] ?? [];
                $url = $image['url'] ?? null;
                if (!$url) {
                    continue;
                }
                $detail = $image['detail'] ?? 'high';
                if (!in_array($detail, ['low', 'high', 'original', 'auto'], true)) {
                    $detail = 'high';
                }
                $refs[] = [
                    'url' => $url,
                    'name' => $image['name'] ?? null,
                    'mime' => $image['mime'] ?? null,
                    'size' => isset($image['size']) ? (int)$image['size'] : null,
                    'detail' => $detail,
                    'is_base64' => strpos($url, 'data:') === 0,
                    'is_url' => strpos($url, 'http') === 0
                ];
            }
        }
        return $refs;
    }

    /**
     * Merge image references deduplicating by URL
     */
    public static function mergeImageReferences(array $existing, array $incoming): array
    {
        $merged = [];
        $seen = [];
        foreach (array_merge($existing, $incoming) as $ref) {
            if (!is_array($ref) || empty($ref['url'])) {
                continue;
            }
            $url = (string)$ref['url'];
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $merged[] = [
                'url' => $url,
                'name' => $ref['name'] ?? null,
                'mime' => $ref['mime'] ?? null,
                'size' => isset($ref['size']) ? (int)$ref['size'] : null,
                'detail' => $ref['detail'] ?? 'high',
                'is_base64' => $ref['is_base64'] ?? (strpos($url, 'data:') === 0),
                'is_url' => $ref['is_url'] ?? (strpos($url, 'http') === 0)
            ];
        }
        return $merged;
    }

    /**
     * Check if messages contain image content
     */
    public static function hasImageContent(array $messages): bool
    {
        foreach ($messages as $msg) {
            if (!is_array($msg) || empty($msg['content']) || !is_array($msg['content'])) {
                continue;
            }
            foreach ($msg['content'] as $part) {
                if (!is_array($part) || ($part['type'] ?? '') !== 'image_url') {
                    continue;
                }
                $url = $part['image_url']['url'] ?? null;
                if (is_string($url) && trim($url) !== '') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Append image context to system prompt
     */
    public static function appendImageContext(string $prompt, array $imageRefs): string
    {
        if (empty($imageRefs)) {
            return $prompt;
        }

        $lines = ["\n\n[IMAGE CONTEXT]", '- The user included screenshot or image attachments in this conversation. Use the attached visuals to answer their questions as accurately as possible.'];
        foreach ($imageRefs as $img) {
            $line = '- ' . ($img['name'] ? ($img['name'] . ': ') : 'Image: ') . ($img['url'] ?? '');
            $metaParts = [];
            if (!empty($img['mime'])) {
                $metaParts[] = $img['mime'];
            }
            if (!empty($img['size'])) {
                $metaParts[] = $img['size'] . ' bytes';
            }
            if (!empty($img['detail'])) {
                $metaParts[] = 'detail: ' . $img['detail'];
            }
            if (!empty($metaParts)) {
                $line .= ' (' . implode(', ', $metaParts) . ')';
            }
            $lines[] = $line;
        }
        $lines[] = '- If direct image understanding is not available, rely on the extracted OCR text and do not invent details beyond what is visible.';

        return $prompt . "\n" . implode("\n", $lines);
    }

    /**
     * Build vision-compatible message content
     */
    public static function buildVisionMessages(array $messages, array $imageRefs): array
    {
        if (empty($imageRefs)) {
            return $messages;
        }

        $builtMessages = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                $builtMessages[] = $msg;
                continue;
            }
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            if (is_string($content) && !empty($imageRefs) && $role === 'user') {
                $newContent = [];
                if (!empty(trim($content))) {
                    $newContent[] = ['type' => 'text', 'text' => $content];
                }
                foreach ($imageRefs as $img) {
                    $imageData = ['url' => $img['url'], 'detail' => $img['detail'] ?? 'high'];
                    if (!empty($img['name'])) {
                        $imageData['name'] = $img['name'];
                    }
                    $newContent[] = ['type' => 'image_url', 'image_url' => $imageData];
                }
                $builtMessages[] = ['role' => $role, 'content' => $newContent];
            } else {
                $builtMessages[] = $msg;
            }
        }
        return $builtMessages;
    }

    /**
     * Check if a model supports vision/images
     */
    public static function supportsVision(?string $model): bool
    {
        if (empty($model)) {
            return true;
        }
        $modelLower = strtolower($model);
        $visionIndicators = [
            'vision', 'gpt-4o', 'gpt-4-vision', 'gpt-4-turbo',
            'claude-3', 'claude-3.5', 'claude-3.7', 'gemini',
            'llama-3.2', 'qwen2-vl', 'pixtral', 'minimax', 'deepseek-vl'
        ];
        foreach ($visionIndicators as $indicator) {
            if (strpos($modelLower, $indicator) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Encode local image as base64 data URL
     */
    public static function encodeImageBase64(string $filePath): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }
        $mimeTypes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp', 'gif' => 'image/gif'
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? 'image/png';
        $data = file_get_contents($filePath);
        if ($data === false) {
            return null;
        }
        return "data:{$mime};base64," . base64_encode($data);
    }

    /**
     * Download and encode remote image as base64
     */
    public static function encodeRemoteImage(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'follow_location' => 1,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($data);
        if (strpos($mime, 'image/') !== 0) {
            return null;
        }
        return "data:{$mime};base64," . base64_encode($data);
    }
}
