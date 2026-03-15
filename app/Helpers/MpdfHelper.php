<?php
/**
 * helpers/MpdfHelper.php
 *
 * Shared mPDF helpers for configuration and PDF generation.
 */

if (!function_exists('mpdf_is_writable_dir')) {
    /**
     * Check if a directory is writable by creating a temporary file.
     */
    function mpdf_is_writable_dir(string $dir): bool
    {
        if ($dir === '') {
            return false;
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        $probeFile = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . '.__mpdf_write_probe';
        $written = @file_put_contents($probeFile, 'ok');
        if ($written === false) {
            return false;
        }
        @unlink($probeFile);

        return true;
    }
}

if (!function_exists('mpdf_temp_dir')) {
    /**
     * Resolve and ensure writable temp directory for mPDF.
     */
    function mpdf_temp_dir(): string
    {
        $configuredBase = trim((string)mpdf_env_value('MPDF_TEMP_DIR', ''));
        $defaultBase = defined('TEMP_DIR') ? (string) TEMP_DIR : (dirname(__DIR__, 2) . '/storage/tmp');
        $systemBase = sys_get_temp_dir();

        $candidates = [];
        if ($configuredBase !== '') {
            $candidates[] = $configuredBase;
        }
        $candidates[] = $defaultBase;
        $candidates[] = rtrim($systemBase, '\\/') . DIRECTORY_SEPARATOR . 'broxbhai-tmp';
        $candidates[] = $systemBase;

        foreach ($candidates as $candidate) {
            $candidate = rtrim((string)$candidate, '\\/');
            if ($candidate === '') {
                continue;
            }

            // mPDF may create an internal "mpdf" subdirectory under tempDir.
            $mpdfInternalDir = rtrim($candidate, '\\/') . DIRECTORY_SEPARATOR . 'mpdf';
            if (mpdf_is_writable_dir($candidate) && mpdf_is_writable_dir($mpdfInternalDir)) {
                return $candidate;
            }
        }

        // Last fallback: try the application temp directory even if probes failed.
        return rtrim((string)$defaultBase, '\\/');
    }
}

if (!function_exists('mpdf_default_config')) {
    /**
     * Return default mPDF configuration with optional overrides.
     *
     * @param array<string,mixed> $overrides
     * @return array<string,mixed>
     */
    function mpdf_default_config(array $overrides = []): array
    {
        $tempDir = mpdf_temp_dir();
        $fontPath = __DIR__ . '/../public_html/assets/fonts';
        $fontSolaiman = 'SolaimanLipi.ttf';
        $fontNikosh = 'Nikosh.ttf';
        $fontHelvetica = 'Helvetica.ttf';

        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'] ?? [];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'] ?? [];

        $customFontData = [];
        if (is_dir($fontPath)) {
            $customFontData['solaimanlipi'] = [
                'R' => $fontSolaiman,
                'B' => file_exists($fontPath . '/SolaimanLipi-Bold.ttf') ? 'SolaimanLipi-Bold.ttf' : $fontSolaiman,
                'useOTL' => 0xFF,
            ];
            $customFontData['nikosh'] = [
                'R' => $fontNikosh,
                'useOTL' => 0xFF,
            ];
            $customFontData['helvetica'] = [
                'R' => $fontHelvetica,
                'useOTL' => 0xFF,
            ];
        }

        $config = [
            'tempDir' => $tempDir,
            'format' => [210, 297],
            'orientation' => 'P',
            'fontDir' => is_dir($fontPath) ? array_merge($fontDirs, [$fontPath]) : $fontDirs,
            'fontdata' => $fontData + $customFontData,
            'default_font' => isset($customFontData['solaimanlipi']) ? 'solaimanlipi' : 'dejavusans',
            'margin_left' => 5,
            'margin_right' => 5,
            'margin_top' => 5,
            'margin_bottom' => 5,
            'compress' => true,
            'dpi' => 96,
            'img_dpi' => 96,
        ];

        return array_merge($config, $overrides);
    }
}

if (!function_exists('mpdf_optimize_html')) {
    /**
     * Basic HTML optimization before PDF rendering.
     */
    function mpdf_optimize_html(string $html): string
    {
        // Remove non-conditional HTML comments.
        $optimized = preg_replace('/<!--(?!\\[if).*?-->/s', '', $html) ?? $html;

        // Collapse whitespace between tags only (safe for content text/pre blocks).
        $optimized = preg_replace('/>\\s+</', '><', $optimized) ?? $optimized;

        return $optimized;
    }
}

if (!function_exists('mpdf_env_value')) {
    /**
     * Read env value with $_ENV/getenv fallback.
     *
     * @param mixed $default
     * @return mixed
     */
    function mpdf_env_value(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== null && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }
}

if (!function_exists('mpdf_output_destination')) {
    /**
     * Resolve mPDF output destination from options + .env.
     *
     * Env:
     * - MPDF_OUTPUT_MODE=download|inline
     */
    function mpdf_output_destination(array $options = []): string
    {
        if (isset($options['destination'])) {
            $destination = strtoupper(trim((string)$options['destination']));
            if ($destination === 'I' || $destination === (string)\Mpdf\Output\Destination::INLINE) {
                return \Mpdf\Output\Destination::INLINE;
            }
            if ($destination === 'D' || $destination === (string)\Mpdf\Output\Destination::DOWNLOAD) {
                return \Mpdf\Output\Destination::DOWNLOAD;
            }
        }

        $outputMode = strtolower(trim((string)mpdf_env_value('MPDF_OUTPUT_MODE', 'download')));
        if (in_array($outputMode, ['inline', 'preview', 'i'], true)) {
            return \Mpdf\Output\Destination::INLINE;
        }

        return \Mpdf\Output\Destination::DOWNLOAD;
    }
}

if (!function_exists('mpdf_create_instance')) {
    /**
     * Create an mPDF instance from shared config.
     *
     * @param array<string,mixed> $configOverrides
     */
    function mpdf_create_instance(array $configOverrides = []): ?\Mpdf\Mpdf
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return null;
        }

        try {
            return new \Mpdf\Mpdf(mpdf_default_config($configOverrides));
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('mPDF instance create failed: ' . $e->getMessage());
            } else {
                error_log('mPDF instance create failed: ' . $e->getMessage());
            }
            return null;
        }
    }
}

if (!function_exists('mpdf_apply_runtime_optimizations')) {
    /**
     * Apply runtime optimization flags on mPDF instance.
     */
    function mpdf_apply_runtime_optimizations(\Mpdf\Mpdf $mpdf): void
    {
        if (method_exists($mpdf, 'SetCompression')) {
            $mpdf->SetCompression(true);
        }

        if (property_exists($mpdf, 'showImageErrors')) {
            $mpdf->showImageErrors = false;
        }
        if (property_exists($mpdf, 'packTableData')) {
            $mpdf->packTableData = true;
        }
        if (property_exists($mpdf, 'simpleTables')) {
            $mpdf->simpleTables = true;
        }
    }
}

if (!function_exists('mpdf_download_html')) {
    /**
     * Generate and download a PDF from HTML.
     *
     * @param array<string,mixed> $options Supports:
     * - title: string
     * - config: array<string,mixed>
     * - optimize: bool (default true)
     * - destination: 'I'|'D' (optional override)
     */
    function mpdf_download_html(string $html, string $filename, array $options = []): bool
    {
        $title = trim((string)($options['title'] ?? ''));
        $configOverrides = is_array($options['config'] ?? null) ? $options['config'] : [];
        $optimize = !array_key_exists('optimize', $options) || (bool)$options['optimize'];
        $htmlToRender = $optimize ? mpdf_optimize_html($html) : $html;
        $destination = mpdf_output_destination($options);

        $mpdf = mpdf_create_instance($configOverrides);
        if (!$mpdf) {
            return false;
        }

        try {
            if ($optimize) {
                mpdf_apply_runtime_optimizations($mpdf);
            }

            if ($title !== '') {
                $mpdf->SetTitle($title);
            }

            $mpdf->WriteHTML($htmlToRender);
            $mpdf->Output($filename, $destination);
            return true;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('mPDF render/download failed: ' . $e->getMessage());
            } else {
                error_log('mPDF render/download failed: ' . $e->getMessage());
            }
            return false;
        }
    }
}

if (!function_exists('generatePdf')) {
    /**
     * Convenience wrapper for direct controller usage:
     * generatePdf($htmlContent, "application_copy");
     *
     * @param array<string,mixed> $options Supports:
     * - title: string
     * - destination: 'I'|'D' (optional)
     * - optimize: bool
     * - config: array<string,mixed>
     * - auto_exit: bool (default true)
     * - clean_buffer_before_pdf: bool (default true)
     * - flush_with_return: bool (default false, uses ob_get_flush)
     * - fail_message: string
     * - fail_status: int
     */
    function generatePdf(string $htmlContent, string $filename = 'document', array $options = []): bool
    {
        $name = trim($filename);
        if ($name === '') {
            $name = 'document';
        }
        if (!preg_match('/\.pdf$/i', $name)) {
            $name .= '.pdf';
        }

        // Allow URL query override: ?output=inline|download
        if (!isset($options['destination'])) {
            $output = strtolower(trim((string)($_GET['output'] ?? '')));
            if (in_array($output, ['inline', 'preview', 'i'], true)) {
                $options['destination'] = 'I';
            } elseif (in_array($output, ['download', 'd'], true)) {
                $options['destination'] = 'D';
            }
        }

        $autoExit = !array_key_exists('auto_exit', $options) || (bool)$options['auto_exit'];
        $cleanBufferBeforePdf = !array_key_exists('clean_buffer_before_pdf', $options) || (bool)$options['clean_buffer_before_pdf'];
        $flushWithReturn = !empty($options['flush_with_return']);

        // Ensure active buffer exists so we can safely manage/clean output before PDF headers.
        if (ob_get_level() === 0) {
            ob_start();
        }

        if ($cleanBufferBeforePdf && ob_get_level() > 0) {
            $bufferContent = ob_get_contents();
            if ($bufferContent !== false && trim((string)$bufferContent) !== '') {
                if (function_exists('logDebug')) {
                    logDebug('Output buffer cleaned before PDF generation', [
                        'filename' => $name,
                        'discarded_bytes' => strlen((string)$bufferContent),
                    ]);
                }
            }
            ob_clean();
        }

        $ok = mpdf_download_html($htmlContent, $name, $options);
        if ($ok) {
            if ($autoExit) {
                if (ob_get_level() > 0) {
                    if ($flushWithReturn) {
                        // Send buffer and close it, optionally returning sent data.
                        ob_get_flush();
                    } else {
                        ob_end_flush();
                    }
                }
                exit;
            }
            return true;
        }

        $statusCode = (int)($options['fail_status'] ?? 500);
        $failMessage = trim((string)($options['fail_message'] ?? 'Failed to generate PDF.'));
        if ($failMessage === '') {
            $failMessage = 'Failed to generate PDF.';
        }

        http_response_code($statusCode > 0 ? $statusCode : 500);
        if (ob_get_level() > 0) {
            ob_clean();
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $failMessage;

        if ($autoExit) {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            exit;
        }

        return false;
    }
}
