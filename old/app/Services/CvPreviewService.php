<?php

/**
 * CvPreviewService — CV Preview System
 *
 * Provides live preview rendering with A4 page simulation,
 * zoom support, print-ready output, and template preview.
 * Delegates core rendering to CvRendererService.
 *
 * @package BroxLab
 * @version 3.0.0
 */

declare(strict_types=1);

class CvPreviewService
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
    //  LIVE PREVIEW
    // ========================================================================

    /**
     * Render a full-page preview with A4 simulation.
     *
     * @param int   $profileId     CV profile ID
     * @param int   $userId        Requesting user (ownership check)
     * @param array $options       {
     *     @var string|null $template_slug  Override template
     *     @var float       $zoom           Zoom level 0.5–2.0 (default 1.0)
     *     @var bool        $print_mode     Print-ready mode (no A4 wrapper)
     * }
     * @return array{success: bool, html?: string, data?: array, error?: string}
     */
    public function renderPreview(int $profileId, int $userId, array $options = []): array
    {
        $options['for_preview'] = true;
        $result = $this->renderer->render($profileId, $userId, $options);

        if (!$result['success']) {
            return $result;
        }

        $html = $result['html'];
        $data = $result['data'] ?? [];

        // Print mode: return clean HTML without A4 wrapper
        if (!empty($options['print_mode'])) {
            return [
                'success' => true,
                'html' => $html,
                'data' => $data,
            ];
        }

        // Wrap in A4 page with zoom controls
        $zoom = min(2.0, max(0.5, (float)($options['zoom'] ?? 1.0)));
        $completionScore = (int)($data['meta']['completion_score'] ?? 0);
        $atsScore = (int)($data['meta']['ats_score'] ?? 0);

        $wrappedHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV Preview</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #e5e7eb;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 40px 20px;
    font-family: system-ui, -apple-system, sans-serif;
    min-height: 100vh;
  }
  .preview-toolbar {
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 12px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    justify-content: center;
    width: 100%;
    max-width: 800px;
  }
  .preview-toolbar button, .preview-toolbar select {
    padding: 6px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s;
  }
  .preview-toolbar button:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
  }
  .preview-toolbar .zoom-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
  }
  .preview-toolbar .score-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
  }
  .score-badge.good { background: #d1fae5; color: #065f46; }
  .score-badge.warn { background: #fef3c7; color: #92400e; }
  .score-badge.poor { background: #fee2e2; color: #991b1b; }
  .a4-page {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    transform-origin: top center;
    padding: 20mm 15mm;
    position: relative;
    transition: transform 0.2s ease;
  }
  @media print {
    body { background: white; padding: 0; }
    .preview-toolbar { display: none !important; }
    .a4-page { box-shadow: none; padding: 0; }
  }
  @media (max-width: 800px) {
    body { padding: 10px; }
    .a4-page { width: 100%; min-height: auto; transform: none; padding: 15px; }
  }
</style>
</head>
<body>
<div class="preview-toolbar">
  <span class="zoom-label">🔍 Zoom:</span>
  <button onclick="zoomIn()" title="Zoom In">➕</button>
  <span id="zoom-level" style="font-size:13px;min-width:48px;text-align:center;">{$zoom}×</span>
  <button onclick="zoomOut()" title="Zoom Out">➖</button>
  <button onclick="zoomReset()" title="Reset Zoom">↺ Reset</button>
  <span style="color:#d1d5db;">|</span>
  <button onclick="window.print()" title="Print Preview">🖨️ Print</button>
  {$this->getScoreBadge('Completion', $completionScore)}
  {$this->getScoreBadge('ATS', $atsScore)}
</div>
<div class="a4-page" id="cv-preview-content" style="transform: scale({$zoom});">
{$html}
</div>
<script>
  const page = document.getElementById('cv-preview-content');
  let zoom = {$zoom};
  function applyZoom() {
    page.style.transform = 'scale(' + zoom + ')';
    document.getElementById('zoom-level').textContent = zoom.toFixed(1) + '×';
  }
  function zoomIn() { zoom = Math.min(2.0, zoom + 0.1); applyZoom(); }
  function zoomOut() { zoom = Math.max(0.5, zoom - 0.1); applyZoom(); }
  function zoomReset() { zoom = 1.0; applyZoom(); }
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === '=') { e.preventDefault(); zoomIn(); }
    if (e.ctrlKey && e.key === '-') { e.preventDefault(); zoomOut(); }
    if (e.ctrlKey && e.key === '0') { e.preventDefault(); zoomReset(); }
  });
</script>
</body>
</html>
HTML;

        return [
            'success' => true,
            'html' => $wrappedHtml,
            'data' => $data,
        ];
    }

    // ========================================================================
    //  TEMPLATE PREVIEW (without user data — for marketplace)
    // ========================================================================

    /**
     * Render a generic preview of a template using sample data.
     * Used in the template marketplace for "Preview Template" feature.
     */
    public function renderTemplatePreview(string $templateSlug, array $options = []): array
    {
        // Build sample data contract
        $sampleData = $this->buildSampleData($templateSlug);

        $templateService = new CvTemplateService($this->mysqli);
        $template = $templateService->getBySlug($templateSlug);
        $templateFile = 'cv/templates/' . $templateSlug . '.twig';
        $filesystemTemplate = function_exists('cvTemplateGet') ? cvTemplateGet($templateSlug) : null;

        if (!$template) {
            if (!$filesystemTemplate || !empty($filesystemTemplate['deleted_at']) || (!empty($filesystemTemplate['status']) && $filesystemTemplate['status'] === 'disabled')) {
                return ['success' => false, 'error' => "Template '{$templateSlug}' not found"];
            }
            $template = [
                'slug' => $templateSlug,
                'name' => $filesystemTemplate['name'] ?? $templateSlug,
                'version' => $filesystemTemplate['version'] ?? '1.0.0',
                'status' => $filesystemTemplate['status'] ?? 'active',
            ];
        } elseif ($template['status'] !== 'active') {
            return ['success' => false, 'error' => "Template '{$templateSlug}' not found"];
        }

        $meta = [
            'template' => [
                'name' => $template['name'] ?? $templateSlug,
                'slug' => $templateSlug,
                'version' => $template['version'] ?? '1.0.0',
            ],
            'is_preview' => true,
            'is_pdf' => false,
            'is_sample' => true,
            'render_date' => date('Y-m-d H:i:s'),
        ];

        $renderData = array_merge($sampleData, ['meta' => $meta]);

        try {
            $twig = $this->getTwig();
            $html = $twig->render($templateFile, $renderData);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Template render failed: ' . $e->getMessage()];
        }

        // Wrap in preview modal HTML
        $zoom = min(2.0, max(0.5, (float)($options['zoom'] ?? 1.0)));

        $wrapped = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preview: {$template['name']}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
    font-family: system-ui, sans-serif;
  }
  .a4-page {
    width: 210mm;
    min-height: 297mm;
    background: white;
    box-shadow: 0 4px 24px rgba(0,0,0,0.12);
    transform: scale({$zoom});
    transform-origin: top center;
    padding: 20mm 15mm;
  }
  @media (max-width: 800px) {
    .a4-page { width: 100%; min-height: auto; transform: none; padding: 15px; }
  }
</style>
</head>
<body>
<div class="a4-page">
{$html}
</div>
</body>
</html>
HTML;

        return [
            'success' => true,
            'html' => $wrapped,
            'template' => $template,
        ];
    }

    // ========================================================================
    //  HELPERS
    // ========================================================================

    private function getScoreBadge(string $label, int $score): string
    {
        $class = 'poor';
        if ($score >= 70) $class = 'good';
        elseif ($score >= 40) $class = 'warn';

        return '<span class="score-badge ' . $class . '">' . htmlspecialchars($label) . ': ' . min(100, max(0, $score)) . '%</span>';
    }

    private function getTwig(): \Twig\Environment
    {
        // Reuse the global twig instance from the app context
        global $twig;
        if ($twig instanceof \Twig\Environment) {
            return $twig;
        }

        // Fallback: create a minimal Twig loader
        $loader = new \Twig\Loader\FilesystemLoader(dirname(__DIR__, 1) . '/Views');
        return new \Twig\Environment($loader, ['debug' => false, 'auto_reload' => true]);
    }

    /**
     * Build sample data for template preview in the marketplace.
     */
    private function buildSampleData(string $templateSlug): array
    {
        return [
            'cv' => [
                'title' => 'Sample CV',
                'full_name' => 'Alex Morgan',
                'job_title' => 'Product Engineer',
                'email' => 'alex.morgan@example.com',
                'phone' => '+1 (555) 123-4567',
                'location' => 'Remote',
                'address' => 'Remote',
                'professional_summary' => 'Product engineer with 8+ years of experience building digital products, improving workflows, and shipping reliable web applications.',
                'website' => 'example.com',
                'linkedin' => 'linkedin.com/in/example',
                'github' => 'github.com/example',
                'photo' => '',
            ],
            'sections' => [
                [
                    'type' => 'summary',
                    'title' => 'Professional Summary',
                    'items' => [
                        ['content' => 'Product engineer with 8+ years of experience in full-stack development, specializing in scalable web applications, process improvement, and collaborative delivery. Proven track record of supporting teams and shipping practical solutions.'],
                    ],
                ],
                [
                    'type' => 'experience',
                    'title' => 'Work Experience',
                    'items' => [
                        [
                            'company' => 'Northstar Labs',
                            'position' => 'Product Engineer',
                            'location' => 'Remote',
                            'start_date' => 'Jan 2022',
                            'end_date' => 'Present',
                            'is_current' => 1,
                            'description' => "- Built internal and customer-facing tools to support product operations\n- Improved performance and reliability across core application workflows\n- Collaborated with designers, product managers, and engineers to deliver releases\n- Helped document processes and review code with the engineering team",
                        ],
                        [
                            'company' => 'Brightline Studio',
                            'position' => 'Full Stack Developer',
                            'location' => 'Hybrid',
                            'start_date' => 'Mar 2019',
                            'end_date' => 'Dec 2021',
                            'is_current' => 0,
                            'description' => "- Developed and maintained frontend and backend features for a multi-user platform\n- Improved deployment workflows and automated repeatable tasks\n- Worked on APIs and integrations for external services",
                        ],
                        [
                            'company' => 'ClearPath Digital',
                            'position' => 'Junior Developer',
                            'location' => 'On-site',
                            'start_date' => 'Jun 2017',
                            'end_date' => 'Feb 2019',
                            'is_current' => 0,
                            'description' => "- Built responsive websites and application features using PHP and JavaScript\n- Collaborated with design and content teams to implement polished interfaces\n- Wrote tests and supported bug fixes during product launches",
                        ],
                    ],
                ],
                [
                    'type' => 'education',
                    'title' => 'Education',
                    'items' => [
                        [
                            'institution' => 'Metropolitan State University',
                            'degree' => 'Bachelor of Science',
                            'field' => 'Computer Science',
                            'start_date' => '2013',
                            'end_date' => '2017',
                            'gpa' => '3.8',
                        ],
                    ],
                ],
                [
                    'type' => 'skills',
                    'title' => 'Skills',
                    'items' => [
                        'technical' => ['JavaScript', 'TypeScript', 'React', 'Node.js', 'PHP', 'Python', 'Docker', 'PostgreSQL', 'REST APIs', 'Git'],
                        'soft' => ['Team Collaboration', 'Agile Workflow', 'Documentation', 'Code Review'],
                        'language' => ['English (Native)', 'Spanish (Professional)'],
                    ],
                    'flat_items' => [
                        ['category' => 'technical', 'name' => 'JavaScript', 'level' => 'Expert'],
                        ['category' => 'technical', 'name' => 'TypeScript', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'React', 'level' => 'Expert'],
                        ['category' => 'technical', 'name' => 'Node.js', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'REST APIs', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'Docker', 'level' => 'Intermediate'],
                        ['category' => 'soft', 'name' => 'Team Collaboration', 'level' => 'Advanced'],
                        ['category' => 'soft', 'name' => 'Agile Workflow', 'level' => 'Advanced'],
                    ],
                ],
                [
                    'type' => 'languages',
                    'title' => 'Languages',
                    'items' => [
                        ['name' => 'English', 'proficiency' => 'native'],
                        ['name' => 'Spanish', 'proficiency' => 'fluent'],
                        ['name' => 'Bengali', 'proficiency' => 'native'],
                    ],
                ],
                [
                    'type' => 'projects',
                    'title' => 'Projects',
                    'items' => [
                        [
                            'name' => 'Project Atlas',
                            'description' => 'A reporting dashboard built to visualize operational data and track key product metrics.',
                            'technologies' => 'React, JavaScript, Charts, APIs',
                            'url' => 'example.com/atlas',
                        ],
                        [
                            'name' => 'Workflow Helper',
                            'description' => 'A small internal tool that reduced repetitive manual tasks for a support team.',
                            'technologies' => 'Python, APIs, Automation',
                            'url' => '',
                        ],
                    ],
                ],
                [
                    'type' => 'certifications',
                    'title' => 'Certifications',
                    'items' => [
                        ['name' => 'Web Application Development Certificate', 'organization' => 'Online Learning Platform', 'date' => '2023'],
                        ['name' => 'Cloud Fundamentals Certificate', 'organization' => 'Training Institute', 'date' => '2022'],
                    ],
                ],
                [
                    'type' => 'references',
                    'title' => 'References',
                    'items' => [
                        ['name' => 'Casey Miller', 'title' => 'Engineering Manager', 'email' => 'casey.miller@example.com', 'phone' => '+1 (555) 987-6543', 'company' => 'Northstar Labs'],
                    ],
                ],
            ],
            'extra' => ['template_slug' => $templateSlug],
        ];
    }
}
