<?php
// classes/RoleModel.php

class RoleModel {
    private $mysqli;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Get all roles
     */
    public function getAll(): array {
        $result = $this->mysqli->query("
            SELECT id, name, description, is_super_admin, created_at, updated_at 
            FROM roles 
            WHERE deleted_at IS NULL 
            ORDER BY is_super_admin DESC, name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get role by ID with permissions
     */
    public function getById(int $id): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT id, name, description, is_super_admin, created_at, updated_at 
            FROM roles 
            WHERE id = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        
        if ($role) {
            $role['permissions'] = $this->getPermissions($id);
        }
        
        return $role ?: null;
    }

    /**
     * Get role by name
     */
    public function getByName(string $name): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT id, name, description, is_super_admin, created_at, updated_at 
            FROM roles 
            WHERE name = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('s', $name);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Create new role
     */
    public function create(array $data): bool {
        $stmt = $this->mysqli->prepare("
            INSERT INTO roles (name, description, is_super_admin, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $isSuperAdmin = isset($data['is_super_admin']) && $data['is_super_admin'] ? 1 : 0;
        
        $stmt->bind_param(
            'ssi',
            $data['name'],
            $data['description'] ?? null,
            $isSuperAdmin
        );
        
        return $stmt->execute();
    }

    /**
     * Update role
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

        if (isset($data['description'])) {
            $fields[] = "description = ?";
            $params[] = $data['description'];
            $types .= 's';
        }

        if (isset($data['is_super_admin'])) {
            $fields[] = "is_super_admin = ?";
            $params[] = $data['is_super_admin'] ? 1 : 0;
            $types .= 'i';
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE roles SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Delete (soft delete) role
     */
    public function delete(int $id): bool {
        $stmt = $this->mysqli->prepare("
            UPDATE roles 
            SET deleted_at = NOW() 
            WHERE id = ? AND is_super_admin = 0
        ");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Get all permissions for a role
     */
    public function getPermissions(int $roleId): array {
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
     * Attach permission to role
     */
    public function attachPermission(int $roleId, int $permissionId): bool {
        $stmt = $this->mysqli->prepare("
            INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param('ii', $roleId, $permissionId);
        return $stmt->execute();
    }

    /**
     * Detach permission from role
     */
    public function detachPermission(int $roleId, int $permissionId): bool {
        $stmt = $this->mysqli->prepare("
            DELETE FROM role_permissions 
            WHERE role_id = ? AND permission_id = ?
        ");
        $stmt->bind_param('ii', $roleId, $permissionId);
        return $stmt->execute();
    }

    /**
     * Check if role has permission
     */
    public function hasPermission(int $roleId, string $permissionName): bool {
        $stmt = $this->mysqli->prepare("
            SELECT 1 FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.name = ? AND p.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('is', $roleId, $permissionName);
        $stmt->execute();
        return (bool)$stmt->get_result()->num_rows;
    }

    /**
     * Attach multiple permissions to role
     */
    public function attachPermissions(int $roleId, array $permissionIds): bool {
        $this->mysqli->begin_transaction();
        
        try {
            // First delete existing permissions
            $stmt = $this->mysqli->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            
            // Then insert new ones
            $stmt = $this->mysqli->prepare("
                INSERT INTO role_permissions (role_id, permission_id, created_at)
                VALUES (?, ?, NOW())
            ");
            
            foreach ($permissionIds as $permissionId) {
                $stmt->bind_param('ii', $roleId, $permissionId);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to attach permission");
                }
            }
            
            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
            return false;
        }
    }

    /**
     * Get roles with permission count
     */
    public function getAllWithCount(): array {
        $result = $this->mysqli->query("
            SELECT 
                r.id, 
                r.name, 
                r.description, 
                r.is_super_admin, 
                r.created_at, 
                r.updated_at,
                COUNT(rp.permission_id) as permission_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            WHERE r.deleted_at IS NULL
            GROUP BY r.id
            ORDER BY r.is_super_admin DESC, r.name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Count total roles
     */
    public function getTotal(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM roles WHERE deleted_at IS NULL");
        return $result ? (int)$result->fetch_assoc()['count'] : 0;
    }

    /**
     * Get roles ordered by ranking (highest first)
     */
    public function getByRanking(): array {
        $result = $this->mysqli->query("
            SELECT id, name, ranking, description, is_super_admin, created_at, updated_at 
            FROM roles 
            WHERE deleted_at IS NULL 
            ORDER BY ranking DESC, name ASC
        ");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Get role ranking
     */
    public function getRanking(int $id): int {
        $stmt = $this->mysqli->prepare("
            SELECT ranking FROM roles WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return $result ? (int)$result['ranking'] : 0;
    }

    /**
     * Update role ranking
     */
    public function setRanking(int $id, int $ranking): bool {
        // Prevent downranking of superadmin
        $stmt = $this->mysqli->prepare("
            SELECT is_super_admin FROM roles WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && $result['is_super_admin'] && $ranking < 100) {
            return false; // Superadmin must have highest ranking
        }

        $stmt = $this->mysqli->prepare("
            UPDATE roles 
            SET ranking = ?, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('ii', $ranking, $id);
        return $stmt->execute();
    }

    /**
     * Get highest ranking role
     */
    public function getHighestRank(): ?array {
        $result = $this->mysqli->query("
            SELECT id, name, ranking FROM roles 
            WHERE deleted_at IS NULL 
            ORDER BY ranking DESC 
            LIMIT 1
        ");
        return $result ? $result->fetch_assoc() : null;
    }

    /**
     * Check if user has higher rank than another user
     */
    public function hasHigherRank(int $userId1Id, int $userId2Id, ?mysqli $userDb = null): bool {
        if ($userDb === null) {
            $userDb = $this->mysqli;
        }

        $stmt = $userDb->prepare("
            SELECT r.ranking FROM user_roles ur
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
            ORDER BY r.ranking DESC
            LIMIT 1
        ");

        // Get user 1's highest ranking
        $stmt->bind_param('i', $userId1Id);
        $stmt->execute();
        $result1 = $stmt->get_result()->fetch_assoc();
        $rank1 = $result1 ? (int)$result1['ranking'] : 0;

        // Get user 2's highest ranking
        $stmt->bind_param('i', $userId2Id);
        $stmt->execute();
        $result2 = $stmt->get_result()->fetch_assoc();
        $rank2 = $result2 ? (int)$result2['ranking'] : 0;

        return $rank1 > $rank2;
    }

    /**
     * Get roles by ranking range
     */
    public function getByRankingRange(int $minRanking, int $maxRanking): array {
        $stmt = $this->mysqli->prepare("
            SELECT id, name, ranking, description FROM roles
            WHERE ranking BETWEEN ? AND ? AND deleted_at IS NULL
            ORDER BY ranking DESC
        ");
        $stmt->bind_param('ii', $minRanking, $maxRanking);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all roles with ranking hierarchy
     */
    public function getWithHierarchy(): array {
        $roles = $this->getByRanking();
        $hierarchy = [];

        foreach ($roles as $role) {
            $hierarchy[] = [
                'id' => $role['id'],
                'name' => $role['name'],
                'ranking' => $role['ranking'],
                'level' => $this->getRankingLevel($role['ranking']),
                'description' => $role['description'],
                'is_super_admin' => $role['is_super_admin']
            ];
        }

        return $hierarchy;
    }

    /**
     * Get ranking level name based on ranking value
     */
    private function getRankingLevel(int $ranking): string {
        if ($ranking >= 100) return 'Super Administrator';
        if ($ranking >= 90) return 'Administrator';
        if ($ranking >= 70) return 'Moderator';
        if ($ranking >= 50) return 'User';
        if ($ranking >= 10) return 'Guest';
        return 'Unknown';
    }

    // ====================== PAGINATION & SEARCH ======================

    /**
     * Get paginated, searched, and sorted roles
     */
    public function getRoles($page = 1, $limit = 20, $search = '', $sort = 'name', $order = 'ASC') {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $allowedSorts = ['id', 'name', 'description', 'created_at', 'updated_at'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'name';
        
        $sql = "SELECT id, name, description, is_super_admin, created_at, updated_at
                FROM roles
                WHERE deleted_at IS NULL";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        $sql .= " ORDER BY is_super_admin DESC, `{$sort}` {$order} LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get total count of roles with optional search filter
     */
    public function getRolesCount($search = '') {
        $sql = "SELECT COUNT(*) as total FROM roles WHERE deleted_at IS NULL";
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $stmt = $this->mysqli->prepare($sql);
            $searchTerm = '%' . $search . '%';
            $stmt->bind_param('ss', $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    
}
