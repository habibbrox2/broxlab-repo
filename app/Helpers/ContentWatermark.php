<?php
/**
 * Content Image Watermark Helpers
 * Provides minimal functions required by controllers to watermark images embedded in HTML content.
 */

if (!function_exists('applyWatermark')) {
    function applyWatermark(string $imagePath, string $watermarkPath): string {
        if (!file_exists($watermarkPath) || !file_exists($imagePath)) {
            return $imagePath;
        }

        $info = @getimagesize($imagePath);
        if (!$info) return $imagePath;

        list($width, $height, $type) = $info;

        switch ($type) {
            case IMAGETYPE_JPEG:
                $img = @imagecreatefromjpeg($imagePath);
                break;
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($imagePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($imagePath);
                } else {
                    return $imagePath;
                }
                break;
            default:
                return $imagePath;
        }

        if (!$img) return $imagePath;

        $wm = @imagecreatefrompng($watermarkPath);
        if (!$wm) {
            imagedestroy($img);
            return $imagePath;
        }

        $wm_width = imagesx($wm);
        $wm_height = imagesy($wm);

        $dest_x = max(0, $width - $wm_width - 10);
        $dest_y = max(0, $height - $wm_height - 10);

        imagecopy($img, $wm, $dest_x, $dest_y, 0, 0, $wm_width, $wm_height);

        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($img, $imagePath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($img, $imagePath);
                break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) imagewebp($img, $imagePath, 90);
                break;
        }

        imagedestroy($img);
        imagedestroy($wm);

        return $imagePath;
    }
}

if (!function_exists('watermarkContentImages')) {
    function watermarkContentImages(string $htmlContent): string {
        $publicRoot = dirname(__DIR__) . '/public_html';
        $legacyPublicRoot = str_replace('/public_html', '/public', $publicRoot);
        $wm_path = $publicRoot . '/assets/watermark.png';
        if (!file_exists($wm_path)) {
            $legacyWatermark = $legacyPublicRoot . '/assets/watermark.png';
            if (file_exists($legacyWatermark)) {
                $wm_path = $legacyWatermark;
            }
        }

        if (!file_exists($wm_path)) {
            return $htmlContent; // No watermark available
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
        libxml_clear_errors();

        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = trim((string)$img->getAttribute('src'));

            // Only handle local uploads
            if (
                !str_starts_with($src, '/uploads/')
                && !str_starts_with($src, '/public/uploads/')
                && !str_starts_with($src, '/public_html/uploads/')
            ) {
                continue;
            }

            // Normalize path to filesystem path
            $abs_path = null;
            $candidates = [];
            if (str_starts_with($src, '/public_html/')) {
                $relative = substr($src, strlen('/public_html'));
                $candidates[] = $publicRoot . $relative;
                $candidates[] = $legacyPublicRoot . $relative;
            } elseif (str_starts_with($src, '/public/')) {
                $relative = substr($src, strlen('/public'));
                $candidates[] = $publicRoot . $relative;
                $candidates[] = $legacyPublicRoot . $relative;
            } elseif (str_starts_with($src, '/uploads/')) {
                $candidates[] = $publicRoot . $src;
                $candidates[] = $legacyPublicRoot . $src;
            }

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $abs_path = $candidate;
                    break;
                }
            }

            if ($abs_path !== null) {
                applyWatermark($abs_path, $wm_path);
                // leave src unchanged
            }
        }

        $body = $doc->getElementsByTagName('body')->item(0);
        $newHtml = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $newHtml .= $doc->saveHTML($child);
            }
        }
        return $newHtml ?: $htmlContent;
    }
}

// Backwards compatible wrapper used by bootstrap to ensure upload dirs
if (!function_exists('ensureUploadDirectories')) {
    function ensureUploadDirectories(): void {
        if (function_exists('initializeUploadDirectories')) {
            initializeUploadDirectories();
        }
    }
}
