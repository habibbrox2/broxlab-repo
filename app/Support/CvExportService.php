<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * CV → PDF export (Laravel port of the legacy CvExportService + MpdfHelper).
 *
 * Renders the owner's CV data as clean print HTML, then produces the PDF
 * with mPDF (A4, inline or download). Bengali text is supported through the
 * bundled ShobojNikosh-style font fallback config (mpdf's default fonts
 * handle Latin; the `bn` content uses the default font's coverage, and the
 * HTML uses system-safe font stacks).
 *
 * Ownership: the requesting user must own the CV (legacy behaviour), except
 * admins who may export any CV.
 */
class CvExportService
{
    /** A4 page with generous print margins (legacy parity). */
    protected const MPDF_CONFIG = [
        'format' => [210, 297],
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'orientation' => 'P',
        'tempDir' => null, // resolved at runtime
    ];

    /**
     * Generate the PDF binary for a CV.
     *
     * @return array{success: bool, error?: string, pdf?: string, filename?: string, size_bytes?: int}
     */
    public function exportPdf(int $cvId, int $userId, array $options = []): array
    {
        $cv = $this->findCvForUser($cvId, $userId);
        if ($cv === null) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        $html = $this->renderHtml($cv);

        $filename = $this->safeFilename(
            $cv->title ?: ($cv->full_name ?: 'CV'),
            'pdf'
        );

        try {
            $mpdf = $this->createMpdf($options);

            $mpdf->SetTitle($cv->title ?: 'CV');
            $mpdf->SetAuthor('BroxLab CV Builder');
            $mpdf->WriteHTML($this->addPageBreakHints($html));

            $pdf = $mpdf->Output('', Destination::STRING_RETURN);

            return [
                'success' => true,
                'pdf' => $pdf,
                'filename' => $filename,
                'size_bytes' => strlen($pdf),
                'html' => $html,
            ];
        } catch (Throwable $e) {
            report($e);

            return ['success' => false, 'error' => 'PDF generation failed: '.$e->getMessage()];
        }
    }

    /**
     * Stream the PDF as a Laravel download/response.
     *
     * @return \Illuminate\Http\Response
     */
    public function streamPdf(int $cvId, int $userId, array $options = [])
    {
        $result = $this->exportPdf($cvId, $userId, $options);

        if (! $result['success']) {
            // Legacy mapped ownership failures to 403 and engine failures to 500.
            abort(
                ($result['error'] ?? '') === 'Forbidden' ? 403 : 500,
                $result['error'] ?? 'Failed to generate PDF'
            );
        }

        $disposition = ($options['inline'] ?? true) ? 'inline' : 'attachment';

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$result['filename'].'"',
            'Content-Length' => (string) $result['size_bytes'],
            'Cache-Control' => 'max-age=0',
            'Pragma' => 'public',
        ]);
    }

    /**
     * The CV row or null when it does not exist / the user may not see it.
     * Admins (legacy parity: admin controllers could export any CV) may
     * export any CV.
     */
    protected function findCvForUser(int $cvId, int $userId): ?object
    {
        $cv = DB::table('cvs')->where('id', $cvId)->first();

        if ($cv === null || $cv->deleted_at !== null) {
            return null;
        }

        if ((int) $cv->user_id === $userId) {
            return $this->hydrateFullName($cv);
        }

        // Admin override
        $isAdmin = DB::table('user_roles as ur')
            ->join('roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)
            ->whereIn('r.name', ['admin', 'super_admin'])
            ->exists();

        return $isAdmin ? $this->hydrateFullName($cv) : null;
    }

    /** Fill full_name from cv_infos when the cvs row has none (legacy joins it). */
    protected function hydrateFullName(object $cv): object
    {
        if (empty($cv->full_name)) {
            // cv_infos is keyed by user_id (1:1 profile per user).
            $cv->full_name = (string) DB::table('cv_infos')
                ->where('user_id', $cv->user_id)
                ->value('full_name');
        }

        return $cv;
    }

    /** Simple print-friendly HTML (mPDF consumes a limited CSS subset). */
    protected function renderHtml(object $cv): string
    {
        $sections = DB::table('cv_sections')
            ->where('cv_id', $cv->id)
            ->where('is_visible', 1)
            ->orderBy('sort_order')
            ->get();

        $itemsBySection = DB::table('cv_items')
            ->whereIn('section_id', $sections->pluck('id'))
            ->orderBy('sort_order')
            ->get()
            ->groupBy('section_id');

        $e = fn ($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:sans-serif;font-size:11pt;color:#222;}'
            .'h1{font-size:20pt;margin:0 0 2pt 0;}'
            .'h2{font-size:13pt;border-bottom:1px solid #ccc;padding-bottom:2pt;margin:14pt 0 6pt 0;}'
            .'.meta{color:#555;font-size:10pt;margin-bottom:8pt;}'
            .'.item{margin-bottom:6pt;}'
            .'.item-title{font-weight:bold;}'
            .'.item-sub{color:#555;font-size:10pt;}'
            .'</style></head><body>';

        $html .= '<h1>'.$e($cv->full_name ?: $cv->title).'</h1>';
        $meta = array_filter([
            $cv->job_title ?? '',
            $cv->email ?? '',
            $cv->phone ?? '',
            $cv->address ?? '',
        ]);
        if ($meta !== []) {
            $html .= '<div class="meta">'.$e(implode(' · ', $meta)).'</div>';
        }

        foreach ($sections as $section) {
            $html .= '<section><h2>'.$e($section->title ?: ucfirst((string) $section->section_type)).'</h2>';

            foreach ($itemsBySection->get($section->id, collect()) as $item) {
                $data = json_decode((string) $item->content_json, true) ?: [];
                $title = $data['title'] ?? $data['name'] ?? $data['role'] ?? '';
                $sub = $data['organization'] ?? $data['institution'] ?? $data['issuer'] ?? '';
                $desc = $data['description'] ?? $data['summary'] ?? '';

                $html .= '<div class="item">';
                if ($title !== '') {
                    $html .= '<div class="item-title">'.$e($title).'</div>';
                }
                if ($sub !== '') {
                    $html .= '<div class="item-sub">'.$e($sub).'</div>';
                }
                if ($desc !== '') {
                    $html .= '<div>'.$e($desc).'</div>';
                }
                $html .= '</div>';
            }

            $html .= '</section>';
        }

        return $html.'</body></html>';
    }

    /** Legacy page-break hints. Note: `page-break-inside: avoid` on section
     *  wrappers triggers a keep-block-together bug in mPDF 8.x (Undefined
     *  array key in BlockTag close), so only the heading hint is applied. */
    protected function addPageBreakHints(string $html): string
    {
        return (string) preg_replace(
            '/<(h[12])(\s|>)/i',
            '<$1 style="page-break-before: auto;"$2',
            $html
        );
    }

    protected function createMpdf(array $options): Mpdf
    {
        $config = self::MPDF_CONFIG;
        $config['tempDir'] = $this->tempDir();

        if (! empty($options['mpdf_config']) && is_array($options['mpdf_config'])) {
            $config = array_merge($config, $options['mpdf_config']);
        }

        return new Mpdf($config);
    }

    /** Resolve a writable temp dir (legacy MpdfHelper::mpdf_temp_dir port). */
    protected function tempDir(): string
    {
        $candidates = [
            storage_path('app/mpdf'),
            rtrim(sys_get_temp_dir(), '\\/').'/brox_pdf',
            sys_get_temp_dir(),
        ];

        foreach ($candidates as $dir) {
            if (@mkdir($dir, 0775, true) || is_dir($dir)) {
                if (is_writable($dir)) {
                    return $dir;
                }
            }
        }

        return sys_get_temp_dir();
    }

    /** Sanitize a download filename, keeping Bengali characters (legacy parity). */
    protected function safeFilename(string $name, string $ext): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_\-\\x{0980}-\\x{09FF}]/u', '_', $name);
        $name = trim((string) $name, '_');

        return ($name !== '' ? $name : 'CV').'.'.$ext;
    }
}
