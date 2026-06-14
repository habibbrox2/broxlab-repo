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
        return isset($template['status']) && $template['status'] === 'disabled';
    }
}

if (!function_exists('cvTemplateGetAll')) {
    /**
     * Get all templates with metadata merged from filesystem and JSON.
     * Returns array of slug => metadata array.
     */
    function cvTemplateGetAll(): array
    {
        $dir = cvTemplateGetDirectory();
        $metadata = cvTemplateReadMetadata();
        $templates = [];

        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'twig') {
                    $slug = pathinfo($file, PATHINFO_FILENAME);
                    if ($slug === '' || $slug[0] === '_') {
                        continue;
                    }

                    $templates[$slug] = $metadata['templates'][$slug] ?? [
                        'name' => ucfirst($slug),
                        'description' => '',
                        'status' => 'active',
                        'is_custom' => false,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        return $templates;
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

        // Check for required files
        $hasConfig = false;
        $hasTemplate = false;
        $hasPreview = false;
        $hasThumbnail = false;
        $configContent = '';
        $templateContent = '';

        for ($i = 0; $i < $zip->numEntries; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'] ?? '';

            // Skip directories
            if (substr($name, -1) === '/') {
                continue;
            }

            $basename = basename($name);

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
                        file_put_contents($targetPath, $content);
                        $previewWritten = true;
                    }
                } elseif (strpos($basename, 'thumbnail') === 0 && !$thumbnailWritten) {
                    $targetPath = $mediaDir . '/' . $slug . '-thumbnail.' . $ext;
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($targetPath, $content);
                        $thumbnailWritten = true;
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
     * Delete a custom template (file + metadata + media).
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

        $tplPath = cvTemplateGetDirectory() . '/' . $slug . '.twig';
        if (file_exists($tplPath)) {
            @unlink($tplPath);
        }

        $mediaDir = dirname(cvTemplateGetMetadataPath()) . '/media';
        $patterns = [
            $mediaDir . '/' . $slug . '-preview.*',
            $mediaDir . '/' . $slug . '-thumbnail.*'
        ];
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
        }

        $metadata = cvTemplateReadMetadata();
        unset($metadata['templates'][$slug]);
        cvTemplateWriteMetadata($metadata);

        return ['success' => true, 'message' => 'Template "' . $template['name'] . '" deleted.'];
    }
}
