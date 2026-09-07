<?php

/**
 * CvExportService — CV Export (PDF / DOCX)
 *
 * Handles PDF generation using mPDF and DOCX generation using PhpWord.
 * Delegates HTML rendering to CvRendererService.
 * Integrates with existing MpdfHelper and DocxHelper.
 *
 * @package BroxLab
 * @version 3.0.0
 */

declare(strict_types=1);

class CvExportService
{
    private mysqli $mysqli;
    private CvRendererService $renderer;
    private CvProfileService $profileService;

    public function __construct(mysqli $mysqli, \Twig\Environment $twig)
    {
        $this->mysqli = $mysqli;
        $this->renderer = new CvRendererService($mysqli, $twig);
        $this->profileService = new CvProfileService($mysqli);
    }

    // ========================================================================
    //  PDF EXPORT
    // ========================================================================

    /**
     * Export a CV profile to PDF.
     *
     * @param int   $profileId     CV profile ID
     * @param int   $userId        Requesting user (ownership check)
     * @param array $options       {
     *     @var string|null $template_slug  Override template
     *     @var string|null $filename       Output filename (without .pdf)
     *     @var bool        $inline         Display inline (true) vs download (false)
     *     @var array       $mpdf_config    Additional mPDF configuration
     * }
     * @return array{success: bool, error?: string, filepath?: string, html?: string}
     */
    public function exportPdf(int $profileId, int $userId, array $options = []): array
    {
        // 1. Verify ownership and get data
        if (!$this->profileService->belongsToUser($profileId, $userId)) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        // 2. Render HTML
        $result = $this->renderer->renderForPdf($profileId, $userId, $options);
        if (!$result['success']) {
            return $result;
        }

        $html = $result['html'];
        $data = $result['data'] ?? [];

        // 3. Determine filename
        $cvName = !empty($data['cv']['full_name']) ? $data['cv']['full_name'] . '_CV' : 'CV_Export';
        $filename = !empty($options['filename']) ? $options['filename'] : $cvName;
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $filename);
        $filename = trim($filename, '_');

        // 4. Build mPDF config
        $mpdfConfig = [
            'format' => [210, 297], // A4
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'orientation' => 'P',
        ];

        if (!empty($options['mpdf_config']) && is_array($options['mpdf_config'])) {
            $mpdfConfig = array_merge($mpdfConfig, $options['mpdf_config']);
        }

        // 5. Generate PDF via MpdfHelper
        require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

        $destination = !empty($options['inline'])
            ? \Mpdf\Output\Destination::INLINE
            : \Mpdf\Output\Destination::DOWNLOAD;

        $mpdf = mpdf_create_instance($mpdfConfig);
        if (!$mpdf) {
            return ['success' => false, 'error' => 'Failed to initialize PDF engine'];
        }

        try {
            mpdf_apply_runtime_optimizations($mpdf);

            $fullName = $data['cv']['full_name'] ?? 'CV';
            $mpdf->SetTitle($fullName);
            $mpdf->SetAuthor('BroxLab CV Builder');

            // Handle page breaks
            $processedHtml = $this->addPageBreakHints($html);

            $mpdf->WriteHTML($processedHtml);

            // Output to string or directly
            if (!empty($options['return_html'])) {
                return [
                    'success' => true,
                    'html' => $html,
                    'data' => $data,
                ];
            }

            $pdfString = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

            return [
                'success' => true,
                'filename' => $filename . '.pdf',
                'data' => $pdfString,
                'html' => $html,
                'size_bytes' => strlen($pdfString),
            ];

        } catch (\Throwable $e) {
            if (function_exists('logError')) {
                logError('PDF Export failed', ['profile_id' => $profileId, 'error' => $e->getMessage()]);
            }
            return ['success' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Stream PDF to the browser for download.
     */
    public function streamPdf(int $profileId, int $userId, array $options = []): void
    {
        $result = $this->exportPdf($profileId, $userId, $options);

        if (!$result['success']) {
            http_response_code(500);
            header('Content-Type: text/plain');
            echo $result['error'] ?? 'Failed to generate PDF';
            exit;
        }

        $filename = $result['filename'] ?? 'CV.pdf';

        // Clean any output buffers
        if (ob_get_level() > 0) {
            ob_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($options['inline'] ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
        header('Content-Length: ' . $result['size_bytes']);
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        echo $result['data'];
        exit;
    }

    // ========================================================================
    //  DOCX EXPORT
    // ========================================================================

    /**
     * Export a CV profile to DOCX.
     *
     * @param int   $profileId     CV profile ID
     * @param int   $userId        Requesting user
     * @param array $options       {
     *     @var string|null $filename  Output filename
     * }
     * @return array{success: bool, error?: string, filepath?: string}
     */
    public function exportDocx(int $profileId, int $userId, array $options = []): array
    {
        if (!$this->profileService->belongsToUser($profileId, $userId)) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        $cvData = $this->profileService->getFullCvData($profileId);
        if (empty($cvData['profile'])) {
            return ['success' => false, 'error' => 'CV profile not found'];
        }

        // Build sections in the format DocxHelper expects
        $sections = $this->buildDocxSections($cvData);

        $filename = $options['filename'] ?? ($cvData['profile']['title'] ?? 'CV');
        $filename = preg_replace('/[^a-zA-Z0-9_\-\x{0980}-\x{09FF}]/u', '_', $filename);
        $filename = trim($filename, '_');

        // Use existing DocxHelper
        require_once dirname(__DIR__, 1) . '/Helpers/DocxHelper.php';

        try {
            ob_start();
            cvGenerateDocx($cvData['profile'], $sections, $filename, $options);
            $docxContent = ob_get_clean();

            return [
                'success' => true,
                'filename' => $filename . '.docx',
                'data' => $docxContent,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'DOCX generation failed: ' . $e->getMessage()];
        }
    }

    // ========================================================================
    //  BULK EXPORT
    // ========================================================================

    /**
     * Export multiple CVs as a ZIP archive.
     *
     * @param int[] $profileIds
     * @param int   $userId
     * @param array $options
     * @return array{success: bool, error?: string, zip_path?: string, count?: int}
     */
    public function exportBulkPdf(array $profileIds, int $userId, array $options = []): array
    {
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'error' => 'ZipArchive extension required'];
        }

        $zipPath = sys_get_temp_dir() . '/cv-bulk-export-' . uniqid() . '.zip';
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return ['success' => false, 'error' => 'Failed to create ZIP archive'];
        }

        $exported = 0;
        foreach ($profileIds as $profileId) {
            $result = $this->exportPdf($profileId, $userId, $options);
            if ($result['success'] && !empty($result['data'])) {
                $zip->addFromString($result['filename'], $result['data']);
                $exported++;
            }
        }

        $zip->close();

        if ($exported === 0) {
            @unlink($zipPath);
            return ['success' => false, 'error' => 'No CVs could be exported'];
        }

        return [
            'success' => true,
            'zip_path' => $zipPath,
            'count' => $exported,
        ];
    }

    // ========================================================================
    //  HELPERS
    // ========================================================================

    /**
     * Add CSS page break hints to HTML for multi-page PDF support.
     */
    private function addPageBreakHints(string $html): string
    {
        // Add page-break-before to each section heading
        $html = preg_replace(
            '/<(h[12]|div class="section-header"[^>]*)>/i',
            '<$1 style="page-break-before: auto;">',
            $html
        );

        // Ensure sections don't break awkwardly
        $html = str_replace(
            '<section',
            '<section style="page-break-inside: avoid;"',
            $html
        );

        return $html;
    }

    /**
     * Build sections array in the format DocxHelper expects.
     */
    private function buildDocxSections(array $cvData): array
    {
        $sections = [];

        // Personal info as summary section
        $profile = $cvData['profile'] ?? [];
        $sections[] = [
            'title' => 'Personal Information',
            'section_type' => 'summary',
            'items' => [
                ['content' => ['text' => $profile['title'] ?? '']],
            ],
        ];

        // Education
        if (!empty($cvData['educations'])) {
            $items = [];
            foreach ($cvData['educations'] as $edu) {
                $items[] = ['content' => $edu];
            }
            $sections[] = [
                'title' => 'Education',
                'section_type' => 'education',
                'items' => $items,
            ];
        }

        // Experience
        if (!empty($cvData['experiences'])) {
            $items = [];
            foreach ($cvData['experiences'] as $exp) {
                $items[] = ['content' => $exp];
            }
            $sections[] = [
                'title' => 'Work Experience',
                'section_type' => 'experience',
                'items' => $items,
            ];
        }

        // Skills
        if (!empty($cvData['skills'])) {
            $items = [];
            foreach ($cvData['skills'] as $skill) {
                $items[] = ['content' => $skill];
            }
            $sections[] = [
                'title' => 'Skills',
                'section_type' => 'skills',
                'items' => $items,
            ];
        }

        // Projects
        if (!empty($cvData['projects'])) {
            $items = [];
            foreach ($cvData['projects'] as $proj) {
                $items[] = ['content' => $proj];
            }
            $sections[] = [
                'title' => 'Projects',
                'section_type' => 'projects',
                'items' => $items,
            ];
        }

        // Certifications
        if (!empty($cvData['certifications'])) {
            $items = [];
            foreach ($cvData['certifications'] as $cert) {
                $items[] = ['content' => $cert];
            }
            $sections[] = [
                'title' => 'Certifications',
                'section_type' => 'certifications',
                'items' => $items,
            ];
        }

        // Languages
        if (!empty($cvData['languages'])) {
            $items = [];
            foreach ($cvData['languages'] as $lang) {
                $items[] = ['content' => $lang];
            }
            $sections[] = [
                'title' => 'Languages',
                'section_type' => 'skills',
                'items' => $items,
            ];
        }

        return $sections;
    }
}
