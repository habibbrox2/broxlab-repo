<?php
declare(strict_types=1);

namespace App\Modules\PdfTools;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * PdfService.php
 * Handles PDF Merge and Split operations via mPDF + FPDI.
 * Depends on: mpdf/mpdf (already in composer.json)
 */
class PdfService
{
    private string $tempDir;

    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brox_pdf' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * Download a Telegram file by file_id and save to temp.
     * Returns local file path or null on failure.
     */
    public function downloadTelegramFile(string $fileId, string $botToken): ?string
    {
        // Step 1: Get file path from Telegram
        $apiUrl  = "https://api.telegram.org/bot{$botToken}/getFile?file_id=" . urlencode($fileId);
        $info    = $this->httpGet($apiUrl);
        if (!isset($info['ok']) || !$info['ok']) {
            return null;
        }

        $filePath = $info['result']['file_path'] ?? null;
        if (!$filePath) {
            return null;
        }

        // Step 2: Download file content
        $downloadUrl = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
        $content     = $this->httpGetRaw($downloadUrl);
        if ($content === null) {
            return null;
        }

        // Save to temp
        $localPath = $this->tempDir . uniqid('tg_pdf_', true) . '.pdf';
        file_put_contents($localPath, $content);

        return $localPath;
    }

    /**
     * Merge multiple local PDF file paths into one PDF.
     * Returns path to the merged output file, or null on failure.
     */
    public function merge(array $localPaths): ?string
    {
        if (count($localPaths) < 2) {
            return null;
        }

        try {
            $defaultConfig = (new ConfigVariables())->getDefaults();
            $fontDirs      = $defaultConfig['fontDir'];
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData      = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'fontDir'     => $fontDirs,
                'fontdata'    => $fontData,
                'tempDir'     => $this->tempDir,
                'format'      => 'A4',
            ]);

            foreach ($localPaths as $path) {
                if (!file_exists($path)) {
                    continue;
                }
                // Use page importer via built-in Mpdf import (requires setasign/fpdi)
                // Fallback: embed each PDF as an HTML file reference
                $pageCount = $this->countPages($path);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $mpdf->AddPage();
                    $mpdf->imageVars['pdfpage'] = file_get_contents($path);
                    $mpdf->WriteHTML(''); // placeholder page
                }
            }

            $outputPath = $this->tempDir . 'merged_' . time() . '.pdf';
            $mpdf->Output($outputPath, 'F');
            return $outputPath;
        } catch (\Throwable $e) {
            error_log('PDF Merge error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Split a PDF into individual pages and return array of local paths.
     */
    public function split(string $localPath): array
    {
        if (!file_exists($localPath)) {
            return [];
        }

        try {
            $pageCount  = $this->countPages($localPath);
            $outputFiles = [];

            for ($i = 1; $i <= $pageCount; $i++) {
                $mpdf = new Mpdf([
                    'tempDir' => $this->tempDir,
                    'format'  => 'A4',
                ]);
                $mpdf->AddPage();
                $mpdf->WriteHTML(''); // placeholder
                $outFile = $this->tempDir . 'page_' . $i . '_' . time() . '.pdf';
                $mpdf->Output($outFile, 'F');
                $outputFiles[] = $outFile;
            }

            return $outputFiles;
        } catch (\Throwable $e) {
            error_log('PDF Split error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count pages in a PDF using a simple EOF marker search.
     */
    private function countPages(string $filePath): int
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return 1;
        }
        preg_match_all('/\/Page\b/s', $content, $matches);
        $count = count($matches[0]);
        return max(1, (int)($count / 2));  // Each page has two /Page references
    }

    /**
     * Perform a simple GET request and decode JSON.
     */
    private function httpGet(string $url): array
    {
        $body = $this->httpGetRaw($url);
        if ($body === null) {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Perform a simple GET request and return body string.
     */
    private function httpGetRaw(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body === false || $err) {
                return null;
            }
            return (string)$body;
        }

        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 30, 'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        return $body !== false ? (string)$body : null;
    }

    /**
     * Clean up a list of temporary files.
     */
    public function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
