<?php

/**
 * app/Helpers/CvHelper.php
 * 
 * Shared CV helper functions extracted from CvController.php.
 * These are used across multiple CV controllers, services, and views.
 */

// ═════════════════════════════════════════════════════════════════════════════
// TEMPLATE ALLOWLIST & RESOLUTION
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('cvGetTemplateAllowlist')) {
    function cvGetTemplateAllowlist(): array
    {
        $userId = getCurrentUserId();

        // Admin sees all templates (including disabled)
        if ($userId && function_exists('isAdminUser') && isAdminUser()) {
            $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
            $files = glob($dir . '/*.twig') ?: [];
            $templates = [];
            foreach ($files as $file) {
                $name = basename($file, '.twig');
                if ($name === '' || $name[0] === '_') continue;
                $templates[] = $name;
            }
            $templates = array_values(array_unique($templates));
            sort($templates);
            return $templates;
        }

        // Guest (unauthenticated) sees only 'minimal'
        if (!$userId) {
            $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
            $files = glob($dir . '/*.twig') ?: [];
            $allTemplates = [];
            foreach ($files as $file) {
                $name = basename($file, '.twig');
                if ($name === '' || $name[0] === '_') continue;
                $allTemplates[] = $name;
            }
            if (in_array('minimal', $allTemplates, true)) {
                return ['minimal'];
            }
            return [];
        }

        // Authenticated regular users: normal behavior
        $dir = dirname(__DIR__, 1) . '/Views/cv/templates';
        $files = glob($dir . '/*.twig') ?: [];
        $templates = [];
        foreach ($files as $file) {
            $name = basename($file, '.twig');
            if ($name === '' || $name[0] === '_') continue;
            if (function_exists('cvTemplateIsDisabled') && cvTemplateIsDisabled($name)) continue;
            $templates[] = $name;
        }
        $templates = array_values(array_unique($templates));
        sort($templates);
        return $templates;
    }
}

if (!function_exists('cvResolveTemplate')) {
    function cvResolveTemplate(?string $requested, ?string $cvTemplate, array $allowlist, string $default = 'modern'): string
    {
        $requested = is_string($requested) ? trim($requested) : '';
        $cvTemplate = is_string($cvTemplate) ? trim($cvTemplate) : '';
        if ($requested !== '' && in_array($requested, $allowlist, true)) return $requested;
        if ($cvTemplate !== '' && in_array($cvTemplate, $allowlist, true)) return $cvTemplate;
        return in_array($default, $allowlist, true) ? $default : ($allowlist[0] ?? $default);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// SECTION & LAYOUT HELPERS
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('cvDefaultSectionTypes')) {
    function cvDefaultSectionTypes(): array
    {
        return [
            'summary' => 'Professional Summary',
            'experience' => 'Work Experience',
            'education' => 'Education',
            'skills' => 'Skills',
        ];
    }
}

if (!function_exists('cvBuilderSectionBlueprints')) {
    function cvBuilderSectionBlueprints(): array
    {
        return [
            'summary' => ['title' => 'Summary', 'steps' => ['personal', 'summary']],
            'experience' => ['title' => 'Work Experience', 'steps' => ['experience']],
            'education' => ['title' => 'Education', 'steps' => ['education']],
            'skills' => ['title' => 'Skills', 'steps' => ['skills']],
            'languages' => ['title' => 'Languages', 'steps' => ['languages']],
            'social_links' => ['title' => 'Social Links', 'steps' => ['social_links']],
            'custom_sections' => ['title' => 'Custom Sections', 'steps' => ['custom_sections']],
            'references' => ['title' => 'References', 'steps' => ['references']],
        ];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// A4 PREVIEW HTML RENDERER
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('cvRenderA4PreviewHtml')) {
    function cvRenderA4PreviewHtml(string $innerHtml, string $templateSlug, int $cvId, float $zoom = 1.0, int $completionScore = 0): string
    {
        $templates = function_exists('cvGetTemplateAllowlist') ? cvGetTemplateAllowlist() : ['modern', 'minimal', 'ats', 'professional'];
        $templateOptions = '';
        foreach ($templates as $t) {
            $sel = $t === $templateSlug ? ' selected' : '';
            $label = ucfirst($t);
            $templateOptions .= "<option value=\"{$t}\"{$sel}>{$label}</option>";
        }
        $scoreClass = 'poor';
        if ($completionScore >= 70) $scoreClass = 'good';
        elseif ($completionScore >= 40) $scoreClass = 'warn';

        return <<<A4HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CV Preview — {$templateSlug}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #e5e7eb; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; }
  .preview-toolbar { position: sticky; top: 0; z-index: 100; background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 12px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; justify-content: center; width: 100%; max-width: 900px; }
  .preview-toolbar button, .preview-toolbar select { padding: 6px 14px; border: 1px solid #d1d5db; border-radius: 6px; background: white; font-size: 13px; cursor: pointer; transition: all 0.15s; }
  .preview-toolbar button:hover { background: #f3f4f6; border-color: #9ca3af; }
  .preview-toolbar .zoom-label { font-size: 13px; color: #6b7280; font-weight: 500; }
  .preview-toolbar .score-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
  .score-badge.good { background: #d1fae5; color: #065f46; }
  .score-badge.warn { background: #fef3c7; color: #92400e; }
  .score-badge.poor { background: #fee2e2; color: #991b1b; }
  .a4-page { width: 210mm; min-height: 297mm; background: white; box-shadow: 0 4px 24px rgba(0,0,0,0.12); transform-origin: top center; transition: transform 0.2s ease; }
  @media print { body { background: white; padding: 0; } .preview-toolbar { display: none !important; } .a4-page { box-shadow: none; } }
  @media (max-width: 800px) { body { padding: 10px; } .a4-page { width: 100%; min-height: auto; transform: none !important; padding: 0; } }
</style>
</head>
<body>
<div class="preview-toolbar">
  <span class="zoom-label">Template:</span>
  <select id="template-select" onchange="window.parent.postMessage({type:'template-change', template:this.value}, '*')">{$templateOptions}</select>
  <span style="color:#d1d5db;">|</span>
  <span class="zoom-label">Zoom:</span>
  <button onclick="zoomIn()" title="Zoom In">+</button>
  <span id="zoom-level" style="font-size:13px;min-width:48px;text-align:center;">{$zoom}x</span>
  <button onclick="zoomOut()" title="Zoom Out">-</button>
  <button onclick="zoomReset()" title="Reset Zoom">Reset</button>
  <span style="color:#d1d5db;">|</span>
  <button onclick="window.print()" title="Print Preview">Print</button>
  <span class="score-badge {$scoreClass}">Completion: {$completionScore}%</span>
</div>
<div class="a4-page" id="cv-preview-content" style="transform: scale({$zoom});">{$innerHtml}</div>
</body>
</html>
A4HTML;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// DATA MERGE HELPER
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('cvMergeContent')) {
    function cvMergeContent(array $base, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if ($value === null) { unset($base[$key]); continue; }
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = cvMergeContent($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// BUILDER DATA TO SECTIONS CONVERTER
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('cvBuildSectionsFromCvData')) {
    /**
     * Convert structured CV payload into the sections+items array format expected by CV templates.
     *
     * @param array $builderData The structured CV payload decoded into an array.
     * @param array $personalInfo Optional personal info from cv_infos table.
     * @return array Array of sections, each with 'id', 'title', 'section_type', 'is_visible', 'items'.
     */
    function cvBuildSectionsFromCvData(array $builderData, array $personalInfo = []): array
    {
        $sections = [];
        $idx = 0;

        // ── Summary Section ──
        $summaryText = $builderData['summary']['professional_summary'] ?? '';
        $objective = $builderData['summary']['career_objective'] ?? '';
        $personal = $builderData['personal'] ?? [];
        if (!empty($personalInfo)) {
            $personal = array_merge($personal, $personalInfo);
        }
        if (!empty($summaryText) || !empty($objective) || !empty($personal)) {
            $idx++;
            $summaryContent = [
                'full_name' => $personal['full_name'] ?? $personalInfo['full_name'] ?? '',
                'job_title' => $personal['job_title'] ?? '',
                'email' => $personal['email'] ?? $personalInfo['email'] ?? '',
                'phone' => $personal['phone'] ?? $personalInfo['phone'] ?? '',
                'address' => $personal['address'] ?? $personalInfo['address'] ?? '',
                'website' => $personal['website'] ?? $personalInfo['website'] ?? '',
                'linkedin' => $personal['linkedin'] ?? $personalInfo['linkedin'] ?? '',
                'github' => $personal['github'] ?? $personalInfo['github'] ?? '',
                'summary' => $summaryText,
                'objective' => $objective,
                'text' => $summaryText,
            ];
            $sections[] = [
                'id' => $idx,
                'title' => 'Professional Summary',
                'section_type' => 'summary',
                'is_visible' => 1,
                'items' => [['id' => 1, 'content' => array_filter($summaryContent, fn($v) => $v !== '' && $v !== null)]],
            ];
        }

        // ── Experience Section ──
        $experienceEntries = $builderData['experience'] ?? [];
        if (!empty($experienceEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($experienceEntries as $entry) {
                if (empty($entry['company'])) continue;
                $itemId++;
                $items[] = [
                    'id' => $itemId,
                    'content' => [
                        'company' => $entry['company'] ?? '',
                        'position' => $entry['position'] ?? '',
                        'location' => $entry['location'] ?? '',
                        'start_date' => $entry['start_date'] ?? '',
                        'end_date' => $entry['end_date'] ?? '',
                        'is_current' => !empty($entry['is_current']) ? 1 : 0,
                        'description' => $entry['responsibilities'] ?? $entry['description'] ?? '',
                    ],
                ];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Work Experience', 'section_type' => 'experience', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Education Section ──
        $educationEntries = $builderData['education'] ?? [];
        if (!empty($educationEntries)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($educationEntries as $entry) {
                if (empty($entry['institution'])) continue;
                $itemId++;
                $items[] = [
                    'id' => $itemId,
                    'content' => [
                        'institution' => $entry['institution'] ?? '',
                        'degree' => $entry['degree'] ?? '',
                        'field' => $entry['field'] ?? '',
                        'start_date' => $entry['start_year'] ?? $entry['start_date'] ?? '',
                        'end_date' => $entry['end_year'] ?? $entry['end_date'] ?? '',
                        'gpa' => $entry['gpa'] ?? '',
                    ],
                ];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Education', 'section_type' => 'education', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Skills Section ──
        $technical = (array)($builderData['skills']['technical'] ?? []);
        $soft = (array)($builderData['skills']['soft'] ?? []);
        if (!empty($technical) || !empty($soft)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($technical as $skill) {
                $skill = trim((string)$skill);
                if ($skill !== '') { $itemId++; $items[] = ['id' => $itemId, 'content' => ['name' => $skill]]; }
            }
            foreach ($soft as $skill) {
                $skill = trim((string)$skill);
                if ($skill !== '') { $itemId++; $items[] = ['id' => $itemId, 'content' => ['name' => $skill]]; }
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Skills', 'section_type' => 'skills', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Languages Section ──
        $languages = (array)($builderData['languages'] ?? []);
        if (!empty($languages)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($languages as $lang) {
                $name = trim((string)($lang['name'] ?? $lang ?? ''));
                if ($name === '') continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['name' => $name, 'proficiency' => $lang['proficiency'] ?? '']];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Languages', 'section_type' => 'languages', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Social Links Section ──
        $socialLinks = (array)($builderData['social_links'] ?? []);
        if (!empty($socialLinks)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($socialLinks as $link) {
                $platform = trim((string)($link['platform'] ?? ''));
                $url = trim((string)($link['url'] ?? ''));
                if ($platform === '' && $url === '') continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => ['platform' => $platform, 'url' => $url]];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'Social Links', 'section_type' => 'social_links', 'is_visible' => 1, 'items' => $items];
            }
        }

        // ── Custom Sections ──
        $customSections = (array)($builderData['custom_sections'] ?? []);
        if (!empty($customSections)) {
            foreach ($customSections as $cs) {
                $title = trim((string)($cs['title'] ?? ''));
                if ($title === '') continue;
                $idx++;
                $csItems = (array)($cs['items'] ?? []);
                $items = [];
                $itemId = 0;
                foreach ($csItems as $csi) {
                    $content = trim((string)($csi['content'] ?? $csi ?? ''));
                    if ($content === '') continue;
                    $itemId++;
                    $items[] = ['id' => $itemId, 'content' => ['text' => $content]];
                }
                $sections[] = [
                    'id' => $idx,
                    'title' => $title,
                    'section_type' => 'custom_sections',
                    'is_visible' => (int)($cs['is_visible'] ?? 1),
                    'items' => $items ?: [['id' => 1, 'content' => ['text' => '']]],
                ];
            }
        }

        // ── References Section ──
        $references = (array)($builderData['references'] ?? []);
        if (!empty($references)) {
            $idx++;
            $items = [];
            $itemId = 0;
            foreach ($references as $ref) {
                $name = trim((string)($ref['name'] ?? ''));
                if ($name === '') continue;
                $itemId++;
                $items[] = ['id' => $itemId, 'content' => [
                    'name' => $name,
                    'title' => $ref['title'] ?? '',
                    'organization' => $ref['organization'] ?? '',
                    'email' => $ref['email'] ?? '',
                    'phone' => $ref['phone'] ?? '',
                    'relationship' => $ref['relationship'] ?? '',
                ]];
            }
            if (!empty($items)) {
                $sections[] = ['id' => $idx, 'title' => 'References', 'section_type' => 'references', 'is_visible' => 1, 'items' => $items];
            }
        }

        return $sections;
    }
}
