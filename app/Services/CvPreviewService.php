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
                'full_name' => 'John Doe',
                'job_title' => 'Senior Software Engineer',
                'email' => 'john.doe@example.com',
                'phone' => '+1 (555) 123-4567',
                'address' => 'San Francisco, CA',
                'website' => 'johndoe.dev',
                'linkedin' => 'linkedin.com/in/johndoe',
                'github' => 'github.com/johndoe',
                'photo' => '',
            ],
            'sections' => [
                [
                    'type' => 'summary',
                    'title' => 'Professional Summary',
                    'items' => [
                        ['content' => 'Innovative software engineer with 8+ years of experience in full-stack development, specializing in scalable web applications, cloud architecture, and team leadership. Proven track record of delivering high-impact solutions that drive business growth and improve user experience.'],
                    ],
                ],
                [
                    'type' => 'experience',
                    'title' => 'Work Experience',
                    'items' => [
                        [
                            'company' => 'TechCorp Inc.',
                            'position' => 'Senior Software Engineer',
                            'location' => 'San Francisco, CA',
                            'start_date' => 'Jan 2022',
                            'end_date' => 'Present',
                            'is_current' => 1,
                            'description' => "• Led a team of 5 engineers in building a microservices-based platform serving 2M+ users\n• Reduced API latency by 40% through query optimization and caching strategies\n• Architected and deployed cloud-native solutions on AWS using ECS and Lambda\n• Mentored junior developers through code reviews and pair programming sessions",
                        ],
                        [
                            'company' => 'StartupXYZ',
                            'position' => 'Full Stack Developer',
                            'location' => 'New York, NY',
                            'start_date' => 'Mar 2019',
                            'end_date' => 'Dec 2021',
                            'is_current' => 0,
                            'description' => "• Developed and maintained React.js frontend with Node.js backend serving 500K+ daily active users\n• Implemented CI/CD pipelines resulting in 60% faster deployment cycles\n• Designed RESTful APIs and GraphQL endpoints for third-party integrations",
                        ],
                        [
                            'company' => 'WebAgency Pro',
                            'position' => 'Junior Developer',
                            'location' => 'Austin, TX',
                            'start_date' => 'Jun 2017',
                            'end_date' => 'Feb 2019',
                            'is_current' => 0,
                            'description' => "• Built responsive web applications using PHP, Laravel, and Vue.js\n• Collaborated with design team to implement pixel-perfect UIs\n• Wrote unit and integration tests achieving 85% code coverage",
                        ],
                    ],
                ],
                [
                    'type' => 'education',
                    'title' => 'Education',
                    'items' => [
                        [
                            'institution' => 'University of California, Berkeley',
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
                        'technical' => ['JavaScript', 'TypeScript', 'React', 'Node.js', 'PHP', 'Python', 'AWS', 'Docker', 'PostgreSQL', 'MongoDB'],
                        'soft' => ['Team Leadership', 'Agile/Scrum', 'Technical Writing', 'Code Review'],
                        'language' => ['English (Native)', 'Spanish (Professional)'],
                    ],
                    'flat_items' => [
                        ['category' => 'technical', 'name' => 'JavaScript', 'level' => 'Expert'],
                        ['category' => 'technical', 'name' => 'TypeScript', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'React', 'level' => 'Expert'],
                        ['category' => 'technical', 'name' => 'Node.js', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'AWS', 'level' => 'Advanced'],
                        ['category' => 'technical', 'name' => 'Docker', 'level' => 'Intermediate'],
                        ['category' => 'soft', 'name' => 'Team Leadership', 'level' => 'Advanced'],
                        ['category' => 'soft', 'name' => 'Agile/Scrum', 'level' => 'Advanced'],
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
                            'name' => 'OpenSource Analytics Dashboard',
                            'description' => 'A real-time analytics dashboard built with React and D3.js, processing 10M+ events daily with sub-second query times.',
                            'technologies' => 'React, D3.js, WebSocket, Redis',
                            'url' => 'github.com/example/analytics',
                        ],
                        [
                            'name' => 'Cloud Cost Optimizer',
                            'description' => 'Automated AWS cost optimization tool that reduced infrastructure costs by 35% through intelligent resource scaling.',
                            'technologies' => 'Python, AWS Lambda, Terraform',
                            'url' => '',
                        ],
                    ],
                ],
                [
                    'type' => 'certifications',
                    'title' => 'Certifications',
                    'items' => [
                        ['name' => 'AWS Solutions Architect Professional', 'organization' => 'Amazon Web Services', 'date' => '2023'],
                        ['name' => 'Google Cloud Professional Developer', 'organization' => 'Google Cloud', 'date' => '2022'],
                    ],
                ],
                [
                    'type' => 'references',
                    'title' => 'References',
                    'items' => [
                        ['name' => 'Jane Smith', 'title' => 'Engineering Director', 'email' => 'jane.smith@techcorp.com', 'phone' => '+1 (555) 987-6543', 'company' => 'TechCorp Inc.'],
                    ],
                ],
            ],
            'extra' => ['template_slug' => $templateSlug],
        ];
    }
}
