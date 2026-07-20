<?php

/**
 * CV Template Helper
 *
 * Manages CV template metadata and file operations.
 * Templates are stored as .twig files in app/Views/cv/templates/
 * Metadata is stored in storage/cv-templates/templates.json
 */

declare(strict_types=1);

if (!function_exists('cvTemplateGetDirectory')) {
    /**
     * Get the absolute path to the CV templates directory.
     */
    function cvTemplateGetDirectory(): string
    {
        return dirname(__DIR__, 1) . '/Views/cv/templates';
    }
}

if (!function_exists('cvTemplateGetMetadataPath')) {
    /**
     * Get the absolute path to the templates metadata JSON file.
     */
    function cvTemplateGetMetadataPath(): string
    {
        return dirname(__DIR__, 2) . '/storage/cv-templates/templates.json';
    }
}

if (!function_exists('cvTemplateValidateSlug')) {
    /**
     * Validate a template slug.
     * Must be lowercase alphanumeric with hyphens, no leading underscore, max 50 chars.
     */
    function cvTemplateValidateSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1
            && strlen($slug) <= 50
            && $slug[0] !== '_';
    }
}

if (!function_exists('cvTemplateReadMetadata')) {
    /**
     * Read template metadata from JSON file.
     * Returns array with 'templates' key containing slug => metadata.
     */
    function cvTemplateReadMetadata(): array
    {
        $path = cvTemplateGetMetadataPath();
        if (!file_exists($path)) {
            return ['templates' => []];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            error_log("Failed to read CV template metadata from $path");
            return ['templates' => []];
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON in CV template metadata: " . json_last_error_msg());
            return ['templates' => []];
        }

        return is_array($data) && isset($data['templates']) && is_array($data['templates'])
            ? $data
            : ['templates' => []];
    }
}

if (!function_exists('cvTemplateWriteMetadata')) {
    /**
     * Write template metadata to JSON file.
     */
    function cvTemplateWriteMetadata(array $data): bool
    {
        $path = cvTemplateGetMetadataPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log("Failed to create CV template metadata directory: $dir");
                return false;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log("Failed to encode CV template metadata JSON");
            return false;
        }

        if (file_put_contents($path, $json) === false) {
            error_log("Failed to write CV template metadata to $path");
            return false;
        }

        return true;
    }
}

if (!function_exists('cvTemplateIsDisabled')) {
    /**
     * Check if a template is disabled.
     */
    function cvTemplateIsDisabled(string $slug): bool
    {
        $metadata = cvTemplateReadMetadata();
        $template = $metadata['templates'][$slug] ?? null;
        return !empty($template['deleted_at'])
            || (isset($template['status']) && $template['status'] === 'disabled');
    }
}

if (!function_exists('cvTemplateGetAll')) {
    /**
     * Get all templates with metadata merged from filesystem and JSON.
     * Scans both .twig files AND subdirectories containing index.html.
     * Returns array of slug => metadata array.
     */
    function cvTemplateGetAll(bool $includeDeleted = false): array
    {
        $dir = cvTemplateGetDirectory();
        $metadata = cvTemplateReadMetadata();
        $templates = [];

        // Auto-seed from marketplace templates.json (merge, never overwrite existing metadata)
        static $seeded = false;
        if (!$seeded) {
            $marketplaceJson = $dir . '/templates.json';
            if (file_exists($marketplaceJson)) {
                $marketTemplates = json_decode(file_get_contents($marketplaceJson), true);
                if (is_array($marketTemplates)) {
                    $existing = $metadata['templates'] ?? [];
                    $dirty = false;
                    foreach ($marketTemplates as $t) {
                        $slug = $t['id'] ?? '';
                        if ($slug === '' || isset($existing[$slug])) continue;
                        $existing[$slug] = [
                            'name' => $t['name'] ?? ucfirst($slug),
                            'description' => $t['description'] ?? '',
                            'category' => $t['category'] ?? 'Professional',
                            'primary_color' => $t['primaryColor'] ?? '#6B7280',
                            'best_for' => $t['bestFor'] ?? '',
                            'font' => $t['font'] ?? 'Inter',
                            'version' => $t['version'] ?? '1.0.0',
                            'is_premium' => !empty($t['is_premium']),
                            'status' => 'active',
                            'is_custom' => false,
                            'is_html' => true,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                        $dirty = true;
                    }
                    if ($dirty) {
                        $metadata['templates'] = $existing;
                        cvTemplateWriteMetadata($metadata);
                    }
                }
            }
            $seeded = true;
        }

        if (is_dir($dir)) {
            $entries = scandir($dir);

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $path = $dir . '/' . $entry;

                // Case 1: .twig file
                if (is_file($path) && pathinfo($entry, PATHINFO_EXTENSION) === 'twig') {
                    $slug = pathinfo($entry, PATHINFO_FILENAME);
                    if ($slug === '' || $slug[0] === '_') {
                        continue;
                    }

                    $template = $metadata['templates'][$slug] ?? [
                        'name' => ucfirst($slug),
                        'description' => '',
                        'status' => 'active',
                        'is_custom' => false,
                        'is_html' => false,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    // Ensure twig templates have is_html = false
                    $template['is_html'] = false;

                    if (!$includeDeleted && !empty($template['deleted_at'])) {
                        continue;
                    }

                    $templates[$slug] = $template;
                }

                // Case 2: Subdirectory with index.html
                if (is_dir($path) && file_exists($path . '/index.html')) {
                    $slug = $entry;
                    if ($slug === '' || $slug[0] === '_') {
                        continue;
                    }

                    $template = $metadata['templates'][$slug] ?? [
                        'name' => ucwords(str_replace(['-', '_'], ' ', $slug)),
                        'description' => 'HTML CV template',
                        'category' => 'Professional',
                        'status' => 'active',
                        'is_custom' => false,
                        'is_html' => true,
                        'primary_color' => '#6B7280',
                        'version' => '1.0.0',
                        'is_premium' => false,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    // Ensure html templates have is_html = true
                    $template['is_html'] = true;

                    if (!$includeDeleted && !empty($template['deleted_at'])) {
                        continue;
                    }

                    $templates[$slug] = $template;
                }
            }
        }

        // Add computed fields: layout_type and thumbnail_url
        $thumbBaseUrl = '/api/cv/thumbnail/';
        foreach ($templates as $slug => &$tmpl) {
            $tmpl['layout_type'] = cvTemplateGetLayoutType($slug);
            $tmpl['thumbnail_url'] = $thumbBaseUrl . urlencode($slug) . '.svg';
        }
        unset($tmpl);

        return $templates;
    }
}

if (!function_exists('cvTemplateRestore')) {
    /**
     * Restore a soft-deleted template.
     */
    function cvTemplateRestore(string $slug): array
    {
        $template = cvTemplateGet($slug);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found.'];
        }

        if (empty($template['deleted_at']) && (empty($template['status']) || $template['status'] !== 'disabled')) {
            return ['success' => false, 'message' => 'Template is not deleted.'];
        }

        $metadata = cvTemplateReadMetadata();
        $metadata['templates'][$slug]['status'] = 'active';
        unset($metadata['templates'][$slug]['deleted_at']);
        $metadata['templates'][$slug]['updated_at'] = date('Y-m-d H:i:s');

        if (!cvTemplateWriteMetadata($metadata)) {
            return ['success' => false, 'message' => 'Failed to restore template metadata.'];
        }

        return ['success' => true, 'message' => 'Template "' . ($template['name'] ?? $slug) . '" restored.'];
    }
}

if (!function_exists('cvTemplateGet')) {
    /**
     * Get metadata for a specific template.
     */
    function cvTemplateGet(string $slug): ?array
    {
        $templates = cvTemplateGetAll();
        return $templates[$slug] ?? null;
    }
}

if (!function_exists('cvTemplateCreate')) {
    /**
     * Create a new template by cloning an existing one.
     */
    function cvTemplateCreate(string $slug, string $name, string $description, string $baseTemplate, ?string $profession = null): bool
    {
        if (!cvTemplateValidateSlug($slug)) {
            return false;
        }

        $templates = cvTemplateGetAll();
        if (isset($templates[$slug])) {
            return false; // Already exists
        }

        if (!isset($templates[$baseTemplate])) {
            return false; // Base template doesn't exist
        }

        // Copy the base template file
        $dir = cvTemplateGetDirectory();
        $basePath = $dir . '/' . $baseTemplate . '.twig';
        $newPath = $dir . '/' . $slug . '.twig';

        if (!file_exists($basePath) || !copy($basePath, $newPath)) {
            return false;
        }

        // Update metadata
        $metadata = cvTemplateReadMetadata();
        $metadata['templates'][$slug] = [
            'name' => $name,
            'description' => $description,
            'profession' => $profession,
            'status' => 'active',
            'is_custom' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return cvTemplateWriteMetadata($metadata);
    }
}

if (!function_exists('cvTemplateUpdate')) {
    /**
     * Update template metadata and/or content.
     */
    function cvTemplateUpdate(string $slug, ?string $name = null, ?string $description = null, ?string $profession = null, ?string $content = null): bool
    {
        $templates = cvTemplateGetAll();
        if (!isset($templates[$slug])) {
            return false;
        }

        $metadata = cvTemplateReadMetadata();
        $template = &$metadata['templates'][$slug];

        if ($name !== null) {
            $template['name'] = $name;
        }
        if ($description !== null) {
            $template['description'] = $description;
        }
        if ($profession !== null) {
            $template['profession'] = $profession;
        }
        $template['updated_at'] = date('Y-m-d H:i:s');

        if (!cvTemplateWriteMetadata($metadata)) {
            return false;
        }

        // Update content if provided
        if ($content !== null) {
            $path = cvTemplateGetDirectory() . '/' . $slug . '.twig';
            if (file_put_contents($path, $content) === false) {
                error_log("Failed to write CV template content to $path");
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('cvTemplateToggleStatus')) {
    /**
     * Toggle template status between active and disabled.
     */
    function cvTemplateToggleStatus(string $slug): bool
    {
        $templates = cvTemplateGetAll();
        if (!isset($templates[$slug])) {
            return false;
        }

        $metadata = cvTemplateReadMetadata();
        $currentStatus = $metadata['templates'][$slug]['status'] ?? 'active';
        $newStatus = $currentStatus === 'active' ? 'disabled' : 'active';

        $metadata['templates'][$slug]['status'] = $newStatus;
        $metadata['templates'][$slug]['updated_at'] = date('Y-m-d H:i:s');

        return cvTemplateWriteMetadata($metadata);
    }
}

if (!function_exists('cvTemplateGetLayoutType')) {
    /**
     * Determine the layout type for a template based on slug.
     * Maps to: sidebar-left, sidebar-right, single-column, banner-header
     */
    function cvTemplateGetLayoutType(string $slug): string
    {
        $sidebarRight = ['dark-professional', 'sidebar-right'];
        $singleColumn = ['apple-style', 'minimal-white', 'luxury', 'elegant-gold',
            'japanese-minimal', 'ats-friendly', 'magazine-layout', 'card-based',
            'two-timeline', 'infographic', 'magazine', 'swiss-style'];
        $bannerHeader = ['swiss-style'];

        if (in_array($slug, $sidebarRight, true)) return 'sidebar-right';
        if (in_array($slug, $singleColumn, true)) return 'single-column';
        if (in_array($slug, $bannerHeader, true)) return 'banner-header';
        return 'sidebar-left';
    }
}

if (!function_exists('cvTemplateGenerateThumbnailSvg')) {
    /**
     * Generate an SVG thumbnail for a template based on its layout type and colors.
     * Creates a miniature A4 representation showing the template's layout and color scheme.
     * Returns the raw SVG string for inline use or caching.
     */
    function cvTemplateGenerateThumbnailSvg(array $template): string
    {
        $slug = $template['slug'] ?? '';
        $name = $template['name'] ?? ucfirst($slug);
        $color = $template['primary_color'] ?? '#6B7280';
        $layout = $template['layout_type'] ?? cvTemplateGetLayoutType($slug);

        // Parse color to hex (strip #)
        $hexColor = ltrim($color, '#');
        $isDark = function ($hex) {
            $hex = ltrim($hex, '#');
            if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return ($r * 0.299 + $g * 0.587 + $b * 0.114) < 128;
        };
        $textColor = $isDark($hexColor) ? '#ffffff' : '#1e293b';

        // SVG dimensions (A4 proportions, scaled for thumbnail)
        $w = 140;
        $h = 196;
        $pad = 6;
        $r = 6;
        $innerW = $w - 2 * $pad;
        $innerH = $h - 2 * $pad;

        // Layout-specific geometry
        switch ($layout) {
            case 'sidebar-right':
                $sideW = 30;
                $mainX = $pad;
                $mainW = $innerW - $sideW;
                $sideX = $pad + $mainW;
                break;
            case 'single-column':
                $headerH = 36;
                $sideW = 0;
                $mainX = $pad;
                $mainW = $innerW;
                $sideX = $pad;
                break;
            case 'banner-header':
                $headerH = 28;
                $sideW = 0;
                $mainX = $pad;
                $mainW = $innerW;
                $sideX = $pad;
                break;
            default: // sidebar-left
                $sideW = 30;
                $mainX = $pad + $sideW;
                $mainW = $innerW - $sideW;
                $sideX = $pad;
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">';
        $svg .= '<defs>';
        $svg .= '<filter id="thumb-shadow"><feDropShadow dx="0" dy="1" stdDeviation="2" flood-opacity="0.15"/></filter>';
        $svg .= '<linearGradient id="thumb-bg" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="' . $color . '" stop-opacity="0.08"/><stop offset="100%" stop-color="' . $color . '" stop-opacity="0.02"/></linearGradient>';
        $svg .= '</defs>';

        // Card background
        $svg .= '<rect x="' . $pad . '" y="' . $pad . '" width="' . $innerW . '" height="' . $innerH . '" rx="' . $r . '" fill="url(#thumb-bg)" stroke="#e2e8f0" stroke-width="0.8" filter="url(#thumb-shadow)"/>';

        if ($layout === 'sidebar-left' || $layout === 'sidebar-right') {
            // Sidebar
            $svg .= '<rect x="' . $sideX . '" y="' . ($pad + 0.5) . '" width="' . $sideW . '" height="' . ($innerH - 1) . '" rx="' . ($layout === 'sidebar-left' ? ($r - 0.5) . ' 0 0 ' . ($r - 0.5) : '0 ' . ($r - 0.5) . ' ' . ($r - 0.5) . ' 0') . '" fill="' . $color . '"/>';
            // Sidebar content lines
            $lineY = $pad + 14;
            for ($i = 0; $i < 6; $i++) {
                $lineW = $sideW - 10;
                $svg .= '<rect x="' . ($sideX + 5) . '" y="' . $lineY . '" width="' . $lineW . '" height="2" rx="1" fill="' . $textColor . '" opacity="0.25"/>';
                $lineY += 8;
            }
            // Sidebar avatar circle
            $svg .= '<circle cx="' . ($sideX + $sideW / 2) . '" cy="' . ($pad + 24) . '" r="7" fill="' . $textColor . '" opacity="0.15"/>';

            // Main content area
            $mainTop = $pad + 10;
            // Title bar
            $svg .= '<rect x="' . ($mainX + 4) . '" y="' . $mainTop . '" width="' . ($mainW - 20) . '" height="4" rx="2" fill="' . $color . '" opacity="0.4"/>';
            $svg .= '<rect x="' . ($mainX + 4) . '" y="' . ($mainTop + 7) . '" width="' . ($mainW - 40) . '" height="2" rx="1" fill="' . $color . '" opacity="0.2"/>';
            // Content text lines
            $lineY = $mainTop + 16;
            for ($i = 0; $i < 8; $i++) {
                $lineW = $mainW - 10 - ($i === 3 ? 20 : ($i === 5 ? 14 : 0));
                $svg .= '<rect x="' . ($mainX + 4) . '" y="' . $lineY . '" width="' . $lineW . '" height="2" rx="1" fill="#cbd5e1" opacity="0.6"/>';
                $lineY += 6;
            }
            // Section separator
            $svg .= '<rect x="' . ($mainX + 4) . '" y="' . ($lineY + 2) . '" width="' . ($mainW - 8) . '" height="0.5" rx="0.25" fill="#e2e8f0"/>';
            // More content
            $lineY += 7;
            for ($i = 0; $i < 4; $i++) {
                $lineW = $mainW - 10 - ($i === 1 ? 18 : 0);
                $svg .= '<rect x="' . ($mainX + 4) . '" y="' . $lineY . '" width="' . $lineW . '" height="2" rx="1" fill="#cbd5e1" opacity="0.6"/>';
                $lineY += 6;
            }
        } elseif ($layout === 'single-column' || $layout === 'banner-header') {
            // Header area
            $hdrH = ($layout === 'single-column') ? 36 : 28;
            $svg .= '<rect x="' . ($pad + 0.5) . '" y="' . ($pad + 0.5) . '" width="' . ($innerW - 1) . '" height="' . $hdrH . '" rx="' . ($r - 0.5) . ' ' . ($r - 0.5) . ' 0 0" fill="' . $color . '"/>';
            // Header text
            $svg .= '<rect x="' . ($pad + 12) . '" y="' . ($pad + 10) . '" width="60" height="4" rx="2" fill="' . $textColor . '" opacity="0.6"/>';
            $svg .= '<rect x="' . ($pad + 12) . '" y="' . ($pad + 17) . '" width="40" height="2" rx="1" fill="' . $textColor . '" opacity="0.35"/>';

            // Content area (below header)
            $mainTop = $pad + $hdrH + 8;
            for ($i = 0; $i < 12; $i++) {
                $lineW = $innerW - 16 - ($i === 2 ? 30 : ($i === 7 ? 20 : 0));
                $svg .= '<rect x="' . ($pad + 8) . '" y="' . $mainTop . '" width="' . $lineW . '" height="2" rx="1" fill="#cbd5e1" opacity="0.6"/>';
                $mainTop += 6;
            }
        }

        // Template name badge at bottom
        $nameColor = $isDark($hexColor) ? 'rgba(255,255,255,0.85)' : 'rgba(30,41,59,0.7)';
        $svg .= '<rect x="' . ($pad + 2) . '" y="' . ($h - $pad - 16) . '" width="' . ($innerW - 4) . '" height="14" rx="3" fill="' . $color . '" opacity="0.15"/>';
        $nameDisplay = htmlspecialchars(strlen($name) > 18 ? substr($name, 0, 16) . '..' : $name, ENT_QUOTES, 'UTF-8');
        $svg .= '<text x="' . ($w / 2) . '" y="' . ($h - $pad - 6) . '" text-anchor="middle" font-family="system-ui,sans-serif" font-size="6" font-weight="600" fill="' . $nameColor . '">' . $nameDisplay . '</text>';

        $svg .= '</svg>';
        return $svg;
    }
}

if (!function_exists('cvTemplateGetProfessionSummaries')) {
    /**
     * Get default professional summaries for different professions.
     */
    function cvTemplateGetProfessionSummaries(): array
    {
        return [
            'software-engineer' => 'Innovative software engineer with 5+ years of experience in full-stack development, specializing in scalable web applications and modern technologies. Proven track record of delivering high-quality code and leading development teams.',
            'product-manager' => 'Results-driven product manager with expertise in product lifecycle management, user experience design, and data-driven decision making. Skilled in cross-functional collaboration and driving product growth.',
            'data-scientist' => 'Analytical data scientist with strong background in machine learning, statistical analysis, and data visualization. Experienced in extracting insights from complex datasets to drive business decisions.',
            'marketing-manager' => 'Strategic marketing manager with comprehensive experience in digital marketing, brand management, and campaign optimization. Proven ability to develop and execute marketing strategies that drive revenue growth.',
            'sales-executive' => 'Dynamic sales executive with proven track record in B2B sales, relationship building, and revenue generation. Skilled in consultative selling and exceeding sales targets in competitive markets.',
            'hr-manager' => 'Experienced HR manager specializing in talent acquisition, employee development, and organizational culture. Committed to fostering inclusive workplaces and implementing effective HR strategies.',
            'accountant' => 'Detail-oriented accountant with expertise in financial reporting, tax compliance, and financial analysis. Strong analytical skills with a focus on accuracy and regulatory compliance.',
            'teacher' => 'Dedicated educator with passion for student development and innovative teaching methodologies. Experienced in curriculum development, classroom management, and fostering positive learning environments.',
            'doctor' => 'Compassionate healthcare professional with extensive clinical experience and commitment to patient-centered care. Skilled in diagnosis, treatment, and preventive medicine.',
            'lawyer' => 'Accomplished legal professional with expertise in corporate law, litigation, and regulatory compliance. Strong analytical and advocacy skills with a focus on achieving client objectives.'
        ];
    }
}

// ========== ZIP PACKAGE VALIDATION & EXTRACTION ==========

if (!function_exists('cvTemplateValidateZipPackage')) {
    /**
     * Validate a ZIP package for template installation.
     * Expected ZIP structure:
     *   template.zip
     *   +-- config.json              (required - metadata)
     *   +-- template.twig            (required - Twig template)
     *   +-- preview.png              (optional - preview image, max 2MB)
     *   +-- thumbnail.png            (optional - thumbnail, max 512KB)
     *
     * @param string $zipPath Path to the uploaded ZIP file
     * @return array{success: bool, errors: string[], warnings: string[], config: array}
     */
    function cvTemplateValidateZipPackage(string $zipPath): array
    {
        $result = [
            'success' => false,
            'errors' => [],
            'warnings' => [],
            'config' => []
        ];

        if (!class_exists('ZipArchive')) {
            $result['errors'][] = 'ZipArchive extension is not available on this server.';
            return $result;
        }

        if (!file_exists($zipPath) || !is_file($zipPath)) {
            $result['errors'][] = 'ZIP file not found.';
            return $result;
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($zipPath, ZipArchive::RDONLY);
        if ($openResult !== true) {
            $result['errors'][] = 'Failed to open ZIP file (error code: ' . $openResult . ').';
            return $result;
        }

        // Check for required files and reject nested paths.
        $hasConfig = false;
        $hasTemplate = false;
        $hasPreview = false;
        $hasThumbnail = false;
        $configContent = '';
        $templateContent = '';
        $allowedRoots = ['config.json', 'template.twig', 'preview.png', 'preview.jpg', 'preview.jpeg', 'preview.webp', 'thumbnail.png', 'thumbnail.jpg', 'thumbnail.jpeg'];

        for ($i = 0; $i < $zip->numEntries; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';

            // Skip directories
            if (substr($name, -1) === '/') {
                continue;
            }

            $basename = basename($name);
            $normalized = trim(str_replace('\\', '/', $name), '/');
            if ($normalized === '' || strpos($normalized, '../') !== false || strpos($normalized, '/..') !== false) {
                $result['errors'][] = 'ZIP contains unsafe file paths.';
                continue;
            }
            if ($normalized !== $basename || !in_array($basename, $allowedRoots, true)) {
                $result['errors'][] = 'ZIP must contain only root-level template files.';
                continue;
            }

            switch ($basename) {
                case 'config.json':
                    $hasConfig = true;
                    $configContent = $zip->getFromIndex($i);
                    if ($configContent === false) {
                        $result['errors'][] = 'Failed to read config.json from ZIP.';
                    }
                    break;
                case 'template.twig':
                    $hasTemplate = true;
                    $templateContent = $zip->getFromIndex($i);
                    if ($templateContent === false) {
                        $result['errors'][] = 'Failed to read template.twig from ZIP.';
                    }
                    break;
                case 'preview.png':
                case 'preview.jpg':
                case 'preview.jpeg':
                case 'preview.webp':
                    $hasPreview = true;
                    $previewSize = $stat['size'] ?? 0;
                    if ($previewSize > 2 * 1024 * 1024) {
                        $result['warnings'][] = 'Preview image exceeds 2MB (' . round($previewSize / 1024 / 1024, 1) . 'MB). It will be resized.';
                    }
                    break;
                case 'thumbnail.png':
                case 'thumbnail.jpg':
                case 'thumbnail.jpeg':
                    $hasThumbnail = true;
                    $thumbSize = $stat['size'] ?? 0;
                    if ($thumbSize > 512 * 1024) {
                        $result['warnings'][] = 'Thumbnail exceeds 512KB (' . round($thumbSize / 1024, 1) . 'KB). It will be resized.';
                    }
                    break;
            }
        }

        $zip->close();

        // Validate required files
        if (!$hasConfig) {
            $result['errors'][] = 'ZIP must contain a config.json file with template metadata.';
        }
        if (!$hasTemplate) {
            $result['errors'][] = 'ZIP must contain a template.twig file with the CV template.';
        }

        // If no required files, return early
        if (!$hasConfig || !$hasTemplate) {
            return $result;
        }

        // Parse and validate config.json
        $config = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['errors'][] = 'config.json contains invalid JSON: ' . json_last_error_msg();
            return $result;
        }

        if (empty($config['name'])) {
            $result['errors'][] = 'config.json must contain a "name" field.';
        } elseif (!is_string($config['name']) || strlen(trim($config['name'])) === 0) {
            $result['errors'][] = 'config.json "name" must be a non-empty string.';
        }

        if (empty($config['slug'])) {
            $result['errors'][] = 'config.json must contain a "slug" field.';
        } elseif (!cvTemplateValidateSlug($config['slug'])) {
            $result['errors'][] = 'config.json "slug" must be lowercase alphanumeric with hyphens, no underscore prefix, max 50 chars.';
        }

        // Check for slug conflicts
        if (!empty($config['slug']) && cvTemplateValidateSlug($config['slug'])) {
            $existing = cvTemplateGetAll();
            if (isset($existing[$config['slug']])) {
                $result['errors'][] = 'Template slug "' . htmlspecialchars($config['slug']) . '" already exists. Choose a different slug.';
            }
        }

        // Validate template.twig content (basic check)
        if ($hasTemplate && !empty($templateContent)) {
            if (strpos($templateContent, '{{') === false && strpos($templateContent, '{%') === false) {
                $result['warnings'][] = 'template.twig contains no Twig syntax. This may not be a valid CV template.';
            }
            if (stripos($templateContent, '<!DOCTYPE') === false && stripos($templateContent, '<html') === false) {
                $result['warnings'][] = 'template.twig does not appear to contain HTML markup.';
            }
        }

        // Optional fields
        if (empty($config['description'])) {
            $result['warnings'][] = 'config.json has no "description" field. A generic description will be used.';
        }

        if (!empty($config['category'])) {
            $validCategories = ['modern', 'minimal', 'ats-friendly', 'professional', 'creative', 'executive', 'technical', 'academic'];
            if (!in_array(strtolower($config['category']), $validCategories)) {
                $result['warnings'][] = 'Unknown category "' . htmlspecialchars($config['category']) . '". Valid: ' . implode(', ', $validCategories);
            }
        }

        $result['success'] = empty($result['errors']);
        $result['config'] = $config;
        $result['_template_content'] = $templateContent;

        return $result;
    }
}

if (!function_exists('cvTemplateExtractZipPackage')) {
    /**
     * Extract a validated ZIP package to install a new template.
     *
     * @param string $zipPath Path to the uploaded ZIP file
     * @param array $validation Result from cvTemplateValidateZipPackage
     * @return array{success: bool, message: string, slug?: string}
     */
    function cvTemplateExtractZipPackage(string $zipPath, array $validation): array
    {
        $config = $validation['config'] ?? [];
        $slug = $config['slug'] ?? '';
        $templateContent = $validation['_template_content'] ?? '';

        if (empty($slug) || empty($templateContent)) {
            return ['success' => false, 'message' => 'Invalid validation data.'];
        }

        $tplDir = cvTemplateGetDirectory();
        $mediaDir = dirname(cvTemplateGetMetadataPath()) . '/media';

        // Ensure directories exist
        if (!is_dir($tplDir)) {
            if (!mkdir($tplDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create templates directory.'];
            }
        }
        if (!is_dir($mediaDir)) {
            if (!mkdir($mediaDir, 0755, true)) {
                return ['success' => false, 'message' => 'Failed to create media directory.'];
            }
        }

    

        // Write template.twig
        $tplPath = $tplDir . '/' . $slug . '.twig';
        if (file_exists($tplPath)) {
            return ['success' => false, 'message' => 'Template already exists on disk.'];
        }
        if (file_put_contents($tplPath, $templateContent) === false) {
            return ['success' => false, 'message' => 'Failed to write template file.'];
        }

        // Extract preview/thumbnail images from ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $previewWritten = false;
            $thumbnailWritten = false;

            for ($i = 0; $i < $zip->numEntries; $i++) {
                $name = $zip->statIndex($i)['name'] ?? '';
                $basename = basename($name);

                if (substr($name, -1) === '/') {
                    continue;
                }

                $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                $allowedImgExts = ['png', 'jpg', 'jpeg', 'webp'];
                if (!in_array($ext, $allowedImgExts)) {
                    continue;
                }

                if (strpos($basename, 'preview') === 0 && !$previewWritten) {
                    $targetPath = $mediaDir . '/' . $slug . '-preview.' . $ext;
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        if (file_put_contents($targetPath, $content) !== false) {
                            $previewWritten = true;
                        }
                    }
                } elseif (strpos($basename, 'thumbnail') === 0 && !$thumbnailWritten) {
                    $targetPath = $mediaDir . '/' . $slug . '-thumbnail.' . $ext;
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        if (file_put_contents($targetPath, $content) !== false) {
                            $thumbnailWritten = true;
                        }
                    }
                }
            }
            $zip->close();
        }

        // Update metadata
        $metadata = cvTemplateReadMetadata();
        $metadata['templates'][$slug] = [
            'name' => $config['name'] ?? ucfirst($slug),
            'description' => $config['description'] ?? '',
            'category' => $config['category'] ?? 'custom',
            'profession' => $config['profession'] ?? null,
            'features' => $config['features'] ?? [],
            'best_for' => $config['best_for'] ?? '',
            'version' => $config['version'] ?? '1.0.0',
            'author' => $config['author'] ?? 'Unknown',
            'status' => 'active',
            'is_custom' => true,
            'installed_via' => 'zip',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if (!cvTemplateWriteMetadata($metadata)) {
            return ['success' => false, 'message' => 'Template extracted but metadata save failed.'];
        }

        return [
            'success' => true,
            'message' => 'Template "' . htmlspecialchars($config['name'] ?? $slug) . '" installed successfully.',
            'slug' => $slug
        ];
    }
}

if (!function_exists('cvTemplateDelete')) {
    /**
     * Soft-delete a custom template.
     * Built-in templates cannot be deleted (non-custom).
     *
     * @param string $slug Template slug to delete
     * @return array{success: bool, message: string}
     */
    function cvTemplateDelete(string $slug): array
    {
        $template = cvTemplateGet($slug);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found.'];
        }

        if (empty($template['is_custom'])) {
            return ['success' => false, 'message' => 'Built-in templates cannot be deleted. Use Disable instead.'];
        }

        $metadata = cvTemplateReadMetadata();
        $metadata['templates'][$slug]['status'] = 'disabled';
        $metadata['templates'][$slug]['deleted_at'] = date('Y-m-d H:i:s');
        $metadata['templates'][$slug]['updated_at'] = date('Y-m-d H:i:s');
        if (!cvTemplateWriteMetadata($metadata)) {
            return ['success' => false, 'message' => 'Failed to update template metadata.'];
        }

        return ['success' => true, 'message' => 'Template "' . $template['name'] . '" deleted.'];
    }
}
