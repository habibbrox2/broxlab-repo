<?php
// classes/PermissionModel.php

class PermissionModel {
    private $mysqli;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all permissions
     */
    public function getAll(): array {
        $result = $this->mysqli->query("
            SELECT id, name, module, description, created_at, updated_at 
            FROM permissions 
            WHERE deleted_at IS NULL 
            ORDER BY module ASC, name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get permissions grouped by module
     */
    public function getAllGroupedByModule(): array {
        $result = $this->mysqli->query("
            SELECT id, name, module, description, created_at
            FROM permissions 
            WHERE deleted_at IS NULL 
            ORDER BY module ASC, name ASC
        ");
        
        $grouped = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!isset($grouped[$row['module']])) {
                    $grouped[$row['module']] = [];
                }
                $grouped[$row['module']][] = $row;
            }
        }
        
        return $grouped;
    }

    /**
     * Get permission by ID
     */
    public function getById(int $id): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT id, name, module, description, created_at, updated_at 
            FROM permissions 
            WHERE id = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Get permission by name
     */
    public function getByName(string $name): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT id, name, module, description, created_at, updated_at 
            FROM permissions 
            WHERE name = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Create new permission
     */
    public function create(array $data): bool {
        $stmt = $this->mysqli->prepare("
            INSERT INTO permissions (name, module, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            'sss',
            $data['name'],
            $data['module'],
            $data['description'] ?? null
        );
        
        return $stmt->execute();
    }

    /**
     * Update permission
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [];
        $types = '';

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
            $types .= 's';
        }

        if (isset($data['module'])) {
            $fields[] = "module = ?";
            $params[] = $data['module'];
            $types .= 's';
        }

        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
            $types .= 's';
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE permissions SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Delete (soft delete) permission
     */
    public function delete(int $id): bool {
        $stmt = $this->mysqli->prepare("
            UPDATE permissions 
            SET deleted_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Get permissions for a role
     */
    public function getByRole(int $roleId): array {
        $stmt = $this->mysqli->prepare("
            SELECT p.id, p.name, p.module, p.description, p.created_at
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.deleted_at IS NULL
            ORDER BY p.module ASC, p.name ASC
        ");
        $stmt->bind_param('i', $roleId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all modules available
     */
    public function getModules(): array {
        $result = $this->mysqli->query("
            SELECT DISTINCT module 
            FROM permissions 
            WHERE deleted_at IS NULL 
            ORDER BY module ASC
        ");
        
        $modules = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $modules[] = $row['module'];
            }
        }
        
        return $modules;
    }

    /**
     * Get total permissions
     */
    public function getTotal(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM permissions WHERE deleted_at IS NULL");
        return $result ? (int)$result->fetch_assoc()['count'] : 0;
    }

    /**
     * Search permissions
     */
    public function search(string $query): array {
        $query = '%' . $query . '%';
        $stmt = $this->mysqli->prepare("
            SELECT id, name, module, description, created_at
            FROM permissions
            WHERE (name LIKE ? OR description LIKE ? OR module LIKE ?) 
            AND deleted_at IS NULL
            ORDER BY module ASC, name ASC
        ");
        $stmt->bind_param('sss', $query, $query, $query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
