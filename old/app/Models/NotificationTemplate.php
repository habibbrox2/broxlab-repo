<?php
/**
 * NotificationTemplate Model
 * 
 * Handles notification template management and rendering
 * Similar to EmailTemplate but for multi-channel notifications
 */

class NotificationTemplate
{
    private mysqli $db;
    private array $template = [];

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Get template by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_templates 
            WHERE slug = ? AND is_active = 1 AND deleted_at IS NULL
            LIMIT 1
        ");
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        $stmt->close();

        return $template;
    }

    /**
     * Get template by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_templates 
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $template = $result->fetch_assoc();
        $stmt->close();

        return $template;
    }

    /**
     * Get all active templates
     */
    public function getActive(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, slug, title, body, variables, channels, description, is_active
            FROM notification_templates 
            WHERE is_active = 1 AND deleted_at IS NULL
            ORDER BY name ASC
            LIMIT ?
        ");
        
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $templates = [];

        while ($row = $result->fetch_assoc()) {
            if (!empty($row['variables'])) {
                $row['variables'] = json_decode($row['variables'], true) ?? [];
            } else {
                $row['variables'] = [];
            }
            if (!empty($row['channels'])) {
                $row['channels'] = json_decode($row['channels'], true) ?? [];
            } else {
                $row['channels'] = [];
            }
            $templates[] = $row;
        }

        $stmt->close();
        return $templates;
    }

    /**
     * Get all templates (admin)
     */
    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM notification_templates 
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $templates = [];

        while ($row = $result->fetch_assoc()) {
            // Decode JSON fields
            if ($row['variables']) {
                $row['variables'] = json_decode($row['variables'], true) ?? [];
            }
            if ($row['channels']) {
                $row['channels'] = json_decode($row['channels'], true) ?? [];
            }
            $templates[] = $row;
        }

        $stmt->close();
        return $templates;
    }

    /**
     * Get total count
     */
    public function getCount(): int
    {
        $result = $this->db->query("
            SELECT COUNT(*) as count FROM notification_templates 
            WHERE deleted_at IS NULL
        ");
        
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Render template title with variables
     */
    public function renderTitle(string $slug, array $variables = []): string
    {
        $template = $this->getBySlug($slug);
        
        if (!$template) {
            return '';
        }

        return $this->replaceVariables($template['title'], $variables);
    }

    /**
     * Render template body with variables
     */
    public function render(string $slug, array $variables = []): string
    {
        $template = $this->getBySlug($slug);
        
        if (!$template) {
            return '';
        }

        return $this->replaceVariables($template['body'], $variables);
    }

    /**
     * Get template variables
     */
    public function getVariables(string $slug): array
    {
        $template = $this->getBySlug($slug);
        
        if (!$template || !$template['variables']) {
            return [];
        }

        return json_decode($template['variables'], true) ?? [];
    }

    /**
     * Get template channels
     */
    public function getChannels(string $slug): array
    {
        $template = $this->getBySlug($slug);
        
        if (!$template || !$template['channels']) {
            return [];
        }

        return json_decode($template['channels'], true) ?? [];
    }

    /**
     * Create new template
     */
    public function create(array $data, int $userId = 0): ?int
    {
        $variables = isset($data['variables']) && is_array($data['variables']) 
            ? json_encode($data['variables']) 
            : '{}';
        
        $channels = isset($data['channels']) && is_array($data['channels']) 
            ? json_encode($data['channels']) 
            : '[]';

        $stmt = $this->db->prepare("
            INSERT INTO notification_templates 
            (name, slug, title, body, variables, channels, description, is_active, icon, color, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param(
            'sssssssiss',
            $data['name'],
            $data['slug'],
            $data['title'],
            $data['body'],
            $variables,
            $channels,
            $data['description'] ?? null,
            $data['is_active'] ?? 1,
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $userId
        );

        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        }

        $stmt->close();
        return null;
    }

    /**
     * Update template
     */
    public function update(int $id, array $data, int $userId = 0): bool
    {
        $variables = isset($data['variables']) && is_array($data['variables']) 
            ? json_encode($data['variables']) 
            : null;
        
        $channels = isset($data['channels']) && is_array($data['channels']) 
            ? json_encode($data['channels']) 
            : null;

        $stmt = $this->db->prepare("
            UPDATE notification_templates SET
            name = COALESCE(?, name),
            title = COALESCE(?, title),
            body = COALESCE(?, body),
            variables = COALESCE(?, variables),
            channels = COALESCE(?, channels),
            description = COALESCE(?, description),
            is_active = COALESCE(?, is_active),
            icon = COALESCE(?, icon),
            color = COALESCE(?, color),
            updated_by = ?
            WHERE id = ? AND deleted_at IS NULL
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            'sssssssisii',
            $data['name'] ?? null,
            $data['title'] ?? null,
            $data['body'] ?? null,
            $variables,
            $channels,
            $data['description'] ?? null,
            $data['is_active'] ?? null,
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $userId,
            $id
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Soft delete template
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notification_templates 
            SET deleted_at = NOW() 
            WHERE id = ? AND deleted_at IS NULL
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Restore soft-deleted template
     */
    public function restore(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notification_templates 
            SET deleted_at = NULL 
            WHERE id = ?
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Toggle active status
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE notification_templates 
            SET is_active = NOT is_active 
            WHERE id = ? AND deleted_at IS NULL
        ");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Replace variables in template text
     */
    private function replaceVariables(string $text, array $variables = []): string
    {
        foreach ($variables as $key => $value) {
            // Handle nested arrays/objects
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $placeholder = '{{' . $key . '}}';
            $text = str_replace($placeholder, (string)$value, $text);
        }

        // Remove any unused placeholders
        $text = preg_replace('/\{\{[A-Z_]+\}\}/', '', $text);

        return $text;
    }
}
?>
