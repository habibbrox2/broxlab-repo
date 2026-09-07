<?php

/**
 * CvTemplateService — Template Marketplace & Management
 * 
 * Handles template CRUD, marketplace browsing, favorites, 
 * template compatibility metadata, and ZIP package installation.
 * 
 * @package BroxLab
 * @version 3.0.0
 */

declare(strict_types=1);

class CvTemplateService
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // ========================================================================
    //  TEMPLATE CRUD
    // ========================================================================

    /**
     * Get all active templates for the marketplace.
     */
    public function getActiveTemplates(): array
    {
        $result = $this->mysqli->query(
            "SELECT id, slug, name, description, category, status, version, is_free, price, is_premium, supported_sections, features, tags, preview_images, best_for, author, installed_via, thumbnail, created_at, updated_at
             FROM cv_templates WHERE status = 'active' ORDER BY is_premium ASC, name ASC"
        );
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $row = $this->decodeJsonFields($row);
            $templates[] = $row;
        }
        return $templates;
    }

    /**
     * Get a template by ID.
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, slug, name, description, category, status, version, is_free, price, is_premium, supported_sections, features, tags, preview_images, best_for, author, installed_via, thumbnail, created_at, updated_at FROM cv_templates WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $this->decodeJsonFields($row) : null;
    }

    /**
     * Get a template by slug.
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->mysqli->prepare("SELECT id, slug, name, description, category, status, version, is_free, price, is_premium, supported_sections, features, tags, preview_images, best_for, author, installed_via, thumbnail, created_at, updated_at FROM cv_templates WHERE slug = ? LIMIT 1");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $this->decodeJsonFields($row) : null;
    }

    /**
     * Create a new template.
     */
    public function create(array $data): ?int
    {
        $slug = $data['slug'] ?? '';
        if (!$this->validateSlug($slug)) {
            return null;
        }

        // Check unique slug
        if ($this->getBySlug($slug)) {
            return null;
        }

        $name = $data['name'] ?? ucfirst($slug);
        $description = $data['description'] ?? '';
        $category = $data['category'] ?? 'custom';
        $status = $data['status'] ?? 'draft';
        $version = $data['version'] ?? '1.0.0';
        $isFree = !empty($data['is_free']) ? 1 : 0;
        $price = (float)($data['price'] ?? 0);
        $isPremium = !empty($data['is_premium']) ? 1 : 0;
        $supportedSections = json_encode($data['supported_sections'] ?? [
            'personal', 'contact', 'summary', 'education', 'experience', 
            'skills', 'projects', 'certificates', 'languages', 'references', 'custom_sections'
        ]);
        $features = json_encode($data['features'] ?? []);
        $tags = json_encode($data['tags'] ?? []);
        $previewImages = json_encode($data['preview_images'] ?? []);
        $bestFor = $data['best_for'] ?? '';
        $author = $data['author'] ?? 'Admin';
        $installedVia = $data['installed_via'] ?? 'admin';

        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_templates (slug, name, description, category, status, version, is_free, price, is_premium, 
             supported_sections, features, tags, preview_images, best_for, author, installed_via)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssssiidsssssss',
            $slug, $name, $description, $category, $status, $version,
            $isFree, $price, $isPremium,
            $supportedSections, $features, $tags, $previewImages,
            $bestFor, $author, $installedVia
        );

        return $stmt->execute() ? (int)$this->mysqli->insert_id : null;
    }

    /**
     * Update template metadata.
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        $types = '';

        $allowedFields = ['name', 'description', 'category', 'status', 'version', 
                          'is_free', 'price', 'is_premium', 'best_for', 'author',
                          'supported_sections', 'features', 'tags', 'preview_images',
                          'thumbnail'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                // Encode arrays to JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                if (in_array($field, ['is_free', 'is_premium'])) {
                    $value = $value ? 1 : 0;
                    $types .= 'i';
                } elseif (in_array($field, ['price'])) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $fields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE cv_templates SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Toggle template status (active/disabled).
     */
    public function toggleStatus(int $id): bool
    {
        $template = $this->getById($id);
        if (!$template) return false;

        $newStatus = $template['status'] === 'active' ? 'disabled' : 'active';
        $stmt = $this->mysqli->prepare("UPDATE cv_templates SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $newStatus, $id);
        return $stmt->execute();
    }

    /**
     * Delete a template.
     */
    public function delete(int $id): bool
    {
        $template = $this->getById($id);
        if (!$template) return false;

        $stmt = $this->mysqli->prepare("DELETE FROM cv_templates WHERE id = ?");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();

        if ($success) {
            logActivity("CV Template Deleted", "cv_template", $id, 
                ['slug' => $template['slug'], 'name' => $template['name']], 'success');
        }

        return $success;
    }

    // ========================================================================
    //  MARKETPLACE
    // ========================================================================

    /**
     * Get templates grouped by category.
     */
    public function getGroupedByCategory(): array
    {
        $templates = $this->getActiveTemplates();
        $grouped = [];
        foreach ($templates as $t) {
            $cat = $t['category'] ?? 'other';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = ['category' => $cat, 'templates' => [], 'count' => 0];
            }
            $grouped[$cat]['templates'][] = $t;
            $grouped[$cat]['count']++;
        }
        return $grouped;
    }

    /**
     * Search templates by keyword.
     */
    public function search(string $query): array
    {
        $like = '%' . $query . '%';
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, description, category, status, version, is_free, price, is_premium, supported_sections, features, tags, preview_images, best_for, author, installed_via, thumbnail, created_at, updated_at
             FROM cv_templates 
             WHERE status = 'active' 
             AND (name LIKE ? OR description LIKE ? OR category LIKE ? OR tags LIKE ?)
             ORDER BY is_premium ASC, name ASC"
        );
        $stmt->bind_param('ssss', $like, $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $templates[] = $this->decodeJsonFields($row);
        }
        return $templates;
    }

    /**
     * Get all categories with template counts.
     */
    public function getCategories(): array
    {
        $result = $this->mysqli->query(
            "SELECT category, COUNT(*) as count 
             FROM cv_templates WHERE status = 'active' 
             GROUP BY category ORDER BY category ASC"
        );
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        return $categories;
    }

    // ========================================================================
    //  COMPATIBILITY
    // ========================================================================

    /**
     * Check if a template supports a given section.
     */
    public function supportsSection(int $templateId, string $sectionType): bool
    {
        $template = $this->getById($templateId);
        if (!$template || empty($template['supported_sections'])) {
            return in_array($sectionType, ['personal', 'contact', 'summary', 'education', 'experience', 'skills']);
        }
        return in_array($sectionType, (array)$template['supported_sections']);
    }

    /**
     * Get sections supported by a template.
     */
    public function getSupportedSections(int $templateId): array
    {
        $template = $this->getById($templateId);
        if (!$template || empty($template['supported_sections'])) {
            return ['personal', 'contact', 'summary', 'education', 'experience', 'skills'];
        }
        return (array)$template['supported_sections'];
    }

    // ========================================================================
    //  STATISTICS
    // ========================================================================

    public function getStatistics(): array
    {
        $stats = [];

        $result = $this->mysqli->query("SELECT COUNT(*) as c FROM cv_templates");
        $stats['total'] = $result->fetch_assoc()['c'] ?? 0;

        $result = $this->mysqli->query("SELECT COUNT(*) as c FROM cv_templates WHERE status = 'active'");
        $stats['active'] = $result->fetch_assoc()['c'] ?? 0;

        $result = $this->mysqli->query("SELECT COUNT(*) as c FROM cv_templates WHERE is_premium = 1");
        $stats['premium'] = $result->fetch_assoc()['c'] ?? 0;

        $result = $this->mysqli->query("SELECT COUNT(*) as c FROM user_cv_templates");
        $stats['user_selections'] = $result->fetch_assoc()['c'] ?? 0;

        return $stats;
    }

    // ========================================================================
    //  ADMIN
    // ========================================================================

    /**
     * Get ALL templates (active, disabled, draft) — for admin views.
     * Returns array keyed by slug.
     */
    public function getAllTemplates(): array
    {
        $result = $this->mysqli->query(
            "SELECT id, slug, name, description, category, status, version, is_free, price, is_premium, supported_sections, features, tags, preview_images, best_for, author, installed_via, thumbnail, created_at, updated_at
             FROM cv_templates ORDER BY is_premium ASC, name ASC"
        );
        $templates = [];
        while ($row = $result->fetch_assoc()) {
            $row = $this->decodeJsonFields($row);
            $templates[$row['slug']] = $row;
        }
        return $templates;
    }

    // ========================================================================
    //  HELPERS
    // ========================================================================

    private function validateSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1
            && strlen($slug) <= 100
            && $slug[0] !== '_';
    }

    private function decodeJsonFields(array $row): array
    {
        $jsonFields = ['tags', 'preview_images', 'supported_sections', 'features'];
        foreach ($jsonFields as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$field] = $decoded;
                }
            }
        }
        return $row;
    }
}
