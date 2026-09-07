<?php

/**
 * CvRendererService — Dynamic CV Rendering Engine
 * 
 * Separates CV data from templates entirely.
 * Input: CV Data + Selected Template
 * Output: Rendered HTML (preview or PDF)
 * 
 * All templates receive the same data contract — no template 
 * contains hardcoded user data.
 * 
 * @package BroxLab
 * @version 3.0.0
 */

declare(strict_types=1);

class CvRendererService
{
    private mysqli $mysqli;
    private CvProfileService $profileService;
    private CvTemplateService $templateService;

    /** @var \Twig\Environment */
    private $twig;

    public function __construct(mysqli $mysqli, \Twig\Environment $twig)
    {
        $this->mysqli = $mysqli;
        $this->twig = $twig;
        $this->profileService = new CvProfileService($mysqli);
        $this->templateService = new CvTemplateService($mysqli);
    }

    // ========================================================================
    //  MAIN RENDER METHOD
    // ========================================================================

    /**
     * Render a CV profile with the selected template.
     * 
     * @param int   $profileId     The CV profile ID
     * @param int   $userId        The requesting user (for ownership check)
     * @param array $options       {
     *     @var string|null $template_slug  Override template slug
     *     @var bool        $for_preview    Whether this is a preview (vs. final)
     *     @var bool        $for_pdf        Whether this is for PDF export
     *     @var float       $zoom           Zoom level (0.5–2.0)
     * }
     * @return array{success: bool, html?: string, data?: array, error?: string}
     */
    public function render(int $profileId, int $userId, array $options = []): array
    {
        // 1. Get CV data
        $cvData = $this->profileService->getFullCvData($profileId);
        if (empty($cvData['profile'])) {
            return ['success' => false, 'error' => 'CV profile not found'];
        }

        // 2. Verify ownership
        if (!$this->profileService->belongsToUser($profileId, $userId)) {
            return ['success' => false, 'error' => 'Forbidden'];
        }

        // 3. Determine template
        $templateSlug = $options['template_slug'] ?? null;
        if (!$templateSlug && !empty($cvData['template'])) {
            $templateSlug = $cvData['template']['slug'];
        }
        if (!$templateSlug) {
            $templateSlug = 'modern';
        }

        $template = $this->templateService->getBySlug($templateSlug);
        if (!$template || $template['status'] !== 'active') {
            return ['success' => false, 'error' => "Template '{$templateSlug}' not found or inactive"];
        }

        // 4. Build standardized data contract
        $renderData = $this->buildDataContract($cvData, $template, $options);

        // 5. Render with Twig template
        $templateFile = 'cv/templates/' . $templateSlug . '.twig';
        try {
            $html = $this->twig->render($templateFile, $renderData);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Template rendering failed: ' . $e->getMessage(),
                'data' => $renderData,
            ];
        }

        return [
            'success' => true,
            'html' => $html,
            'data' => $renderData,
        ];
    }

    // ========================================================================
    //  DATA CONTRACT BUILDER
    // ========================================================================

    /**
     * Build a standardized data contract that ALL templates receive.
     * No template should access raw database fields — only this contract.
     */
    private function buildDataContract(array $cvData, array $template, array $options): array
    {
        $profile = $cvData['profile'] ?? [];
        $templateMeta = [
            'name' => $template['name'] ?? '',
            'slug' => $template['slug'] ?? '',
            'version' => $template['version'] ?? '1.0.0',
            'category' => $template['category'] ?? '',
            'features' => $template['features'] ?? [],
            'supported_sections' => $template['supported_sections'] ?? [],
        ];

        // Build personal info from profile
        $personal = [
            'full_name' => $profile['title'] ?? '',
            'job_title' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'website' => '',
            'linkedin' => '',
            'github' => '',
            'photo' => '',
        ];

        // Extract from social links
        $socialLinks = $cvData['social_links'] ?? [];
        foreach ($socialLinks as $link) {
            $platform = $link['platform'] ?? '';
            $url = $link['url'] ?? '';
            switch ($platform) {
                case 'linkedin': $personal['linkedin'] = $url; break;
                case 'github':   $personal['github'] = $url; break;
                case 'website':
                case 'portfolio': $personal['website'] = $url; break;
            }
        }

        // Build sections
        $sections = [];

        // Profile summary section (built from personal info + system data)
        $sections[] = [
            'type' => 'personal',
            'title' => 'Personal Information',
            'items' => [$personal],
        ];

        // Contact section (from social links)
        if (!empty($socialLinks)) {
            $sections[] = [
                'type' => 'contact',
                'title' => 'Contact',
                'items' => $socialLinks,
            ];
        }

        // Professional summary (always included so every template shows it by default)
        $summary = $profile['professional_summary'] ?? '';
        $sections[] = [
            'type' => 'summary',
            'title' => 'Professional Summary',
            'items' => [['content' => $summary]],
        ];

        // Education
        $educations = $cvData['educations'] ?? [];
        if (!empty($educations) && $this->sectionSupported($templateMeta, 'education')) {
            $sections[] = [
                'type' => 'education',
                'title' => 'Education',
                'items' => $educations,
            ];
        }

        // Experience
        $experiences = $cvData['experiences'] ?? [];
        if (!empty($experiences) && $this->sectionSupported($templateMeta, 'experience')) {
            $sections[] = [
                'type' => 'experience',
                'title' => 'Work Experience',
                'items' => $experiences,
            ];
        }

        // Skills (grouped by category)
        $skills = $cvData['skills'] ?? [];
        if (!empty($skills) && $this->sectionSupported($templateMeta, 'skills')) {
            $grouped = ['technical' => [], 'soft' => [], 'language' => []];
            foreach ($skills as $skill) {
                $cat = $skill['category'] ?? 'technical';
                if (!isset($grouped[$cat])) $grouped[$cat] = [];
                $grouped[$cat][] = $skill;
            }
            $sections[] = [
                'type' => 'skills',
                'title' => 'Skills',
                'items' => $grouped,
                'flat_items' => $skills,
            ];
        }

        // Languages
        $languages = $cvData['languages'] ?? [];
        if (!empty($languages) && $this->sectionSupported($templateMeta, 'languages')) {
            $sections[] = [
                'type' => 'languages',
                'title' => 'Languages',
                'items' => $languages,
            ];
        }

        // Projects
        $projects = $cvData['projects'] ?? [];
        if (!empty($projects) && $this->sectionSupported($templateMeta, 'projects')) {
            $sections[] = [
                'type' => 'projects',
                'title' => 'Projects',
                'items' => $projects,
            ];
        }

        // Certifications
        $certifications = $cvData['certifications'] ?? [];
        if (!empty($certifications) && $this->sectionSupported($templateMeta, 'certifications')) {
            $sections[] = [
                'type' => 'certifications',
                'title' => 'Certifications',
                'items' => $certifications,
            ];
        }

        // References
        $references = $cvData['references'] ?? [];
        if (!empty($references) && $this->sectionSupported($templateMeta, 'references')) {
            $sections[] = [
                'type' => 'references',
                'title' => 'References',
                'items' => $references,
            ];
        }

        // Custom sections
        $customSections = $cvData['custom_sections'] ?? [];
        if (!empty($customSections)) {
            $sections[] = [
                'type' => 'custom_sections',
                'title' => 'Additional Sections',
                'items' => $customSections,
            ];
        }

        // Build meta
        $meta = [
            'template' => $templateMeta,
            'is_preview' => !empty($options['for_preview']),
            'is_pdf' => !empty($options['for_pdf']),
            'completion_score' => (int)($profile['completion_score'] ?? 0),
            'ats_score' => 0, // Will be populated by CvAiHelper
            'render_date' => date('Y-m-d H:i:s'),
            'render_version' => '3.0.0',
        ];

        return [
            'cv' => $personal,
            'sections' => $sections,
            'meta' => $meta,
            'extra' => $options, // Pass options through for debugging
        ];
    }

    // ========================================================================
    //  PREVIEW RENDER (with A4 simulation wrapper)
    // ========================================================================

    /**
     * Render for PDF export (clean HTML without A4 wrapper).
     * Preview rendering with A4 simulation is handled by CvPreviewService.
     */
    public function renderForPdf(int $profileId, int $userId, array $options = []): array
    {
        $options['for_pdf'] = true;
        $options['for_preview'] = false;
        return $this->render($profileId, $userId, $options);
    }

    // ========================================================================
    //  HELPERS
    // ========================================================================

    /**
     * Check if a template supports a given section type.
     */
    private function sectionSupported(array $templateMeta, string $sectionType): bool
    {
        $supported = $templateMeta['supported_sections'] ?? [];
        if (empty($supported)) {
            return true; // All sections supported by default
        }
        return in_array($sectionType, $supported);
    }
}
