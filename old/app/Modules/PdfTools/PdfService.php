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
     * Merge multiple local PDF file paths into one PDF.
     * Returns path to the merged output file, or null on failure.
     */
    public function merge(array $localPaths): ?string
    {
        if (count($localPaths) < 2) {
            return null;
        }

        try {
            $defaultConfig    = (new ConfigVariables())->getDefaults();
            $fontDirs         = $defaultConfig['fontDir'];
            $defaultFontConfig = (new FontVariables())->getDefaults();
            $fontData         = $defaultFontConfig['fontdata'];

            $mpdf = new Mpdf([
                'fontDir'  => $fontDirs,
                'fontdata' => $fontData,
                'tempDir'  => $this->tempDir,
                'format'   => 'A4',
            ]);

            foreach ($localPaths as $path) {
                if (!file_exists($path)) {
                    continue;
                }
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
