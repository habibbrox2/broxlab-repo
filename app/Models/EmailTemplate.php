<?php
// classes/EmailTemplate.php

/**
 * Email Template Model
 * ==================
 * Manages email templates for sending dynamic emails
 */
class EmailTemplate {
    private $mysqli;
    private static $templates = [];

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all templates
     */
    public function getAll($includeInactive = false): array {
        $query = "SELECT * FROM email_templates WHERE deleted_at IS NULL";
        if (!$includeInactive) {
            $query .= " AND is_active = 1";
        }
        $query .= " ORDER BY created_at DESC";

        $result = $this->mysqli->query($query);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get template by slug
     */
    public function getBySlug(string $slug): ?array {
        // Check cache first
        if (isset(self::$templates[$slug])) {
            return self::$templates[$slug];
        }

        $stmt = $this->mysqli->prepare("
            SELECT id, name, subject, body, variables, is_active
            FROM email_templates
            WHERE slug = ? AND deleted_at IS NULL AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Cache it
        if ($template) {
            $template['variables'] = json_decode($template['variables'] ?? '[]', true);
            self::$templates[$slug] = $template;
        }

        return $template ?: null;
    }

    /**
     * Get template by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM email_templates
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $template = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($template) {
            $template['variables'] = json_decode($template['variables'] ?? '[]', true);
        }

        return $template ?: null;
    }

    /**
     * Create new template
     */
    public function create(array $data): int|false {
        $stmt = $this->mysqli->prepare("
            INSERT INTO email_templates 
            (name, slug, subject, body, variables, description, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            logError("Template create prepare error: " . $this->mysqli->error);
            return false;
        }

        $slug = $this->generateSlug($data['name']);
        $variables = isset($data['variables']) && is_array($data['variables']) 
            ? json_encode($data['variables']) 
            : null;
        $createdBy = function_exists('getCurrentUserId') ? getCurrentUserId() : null;

        $stmt->bind_param(
            'sssss si',
            $data['name'],
            $slug,
            $data['subject'],
            $data['body'],
            $variables,
            $data['description'] ?? null,
            $createdBy
        );

        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            
            // Clear template cache
            self::$templates = [];
            
            return $id;
        }

        $stmt->close();
        return false;
    }

    /**
     * Update template
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['subject'])) {
            $fields[] = "subject = ?";
            $params[] = $data['subject'];
            $types .= 's';
        }

        if (isset($data['body'])) {
            $fields[] = "body = ?";
            $params[] = $data['body'];
            $types .= 's';
        }

        if (isset($data['variables'])) {
            $variables = is_array($data['variables']) ? json_encode($data['variables']) : $data['variables'];
            $fields[] = "variables = ?";
            $params[] = $variables;
            $types .= 's';
        }

        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
            $types .= 's';
        }

        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = $data['is_active'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_by = ?";
        $params[] = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
        $types .= 'i';

        $fields[] = "updated_at = NOW()";
        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE email_templates SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            logError("Template update prepare error: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        // Clear cache
        self::$templates = [];

        return $success;
    }

    /**
     * Delete template (soft delete)
     */
    public function delete(int $id): bool {
        $stmt = $this->mysqli->prepare("
            UPDATE email_templates 
            SET deleted_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        // Clear cache
        self::$templates = [];

        return $success;
    }

    /**
     * Render template with variables
     */
    public function render(string $slug, array $variables = []): string {
        $template = $this->getBySlug($slug);
        if (!$template) {
            logError("Email template not found: $slug");
            return '';
        }

        $body = $template['body'];
        $subject = $template['subject'];

        // Replace variables
        foreach ($variables as $key => $value) {
            $placeholder = '{{' . strtoupper($key) . '}}';
            $body = str_replace($placeholder, $value, $body);
            $subject = str_replace($placeholder, $value, $subject);
        }

        return $body;
    }

    /**
     * Get rendered subject
     */
    public function renderSubject(string $slug, array $variables = []): string {
        $template = $this->getBySlug($slug);
        if (!$template) {
            return '';
        }

        $subject = $template['subject'];

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . strtoupper($key) . '}}';
            $subject = str_replace($placeholder, $value, $subject);
        }

        return $subject;
    }

    /**
     * Validate template variables
     */
    public function validateVariables(string $body, array $requiredVars): bool {
        foreach ($requiredVars as $var) {
            $placeholder = '{{' . strtoupper($var) . '}}';
            if (strpos($body, $placeholder) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate slug from name
     */
    private function generateSlug(string $name): string {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug;
    }

    /**
     * Get template variables as string
     */
    public function getVariablesString(int $id): string {
        $template = $this->getById($id);
        if (!$template || empty($template['variables'])) {
            return '';
        }
        return implode(', ', $template['variables']);
    }

    /**
     * Clear cache
     */
    public function clearCache(): void {
        self::$templates = [];
    }
}
