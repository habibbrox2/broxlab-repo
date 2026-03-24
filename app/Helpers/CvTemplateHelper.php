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
        return __DIR__ . '/../Views/cv/templates';
    }
}

if (!function_exists('cvTemplateGetMetadataPath')) {
    /**
     * Get the absolute path to the templates metadata JSON file.
     */
    function cvTemplateGetMetadataPath(): string
    {
        return __DIR__ . '/../../storage/cv-templates/templates.json';
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
