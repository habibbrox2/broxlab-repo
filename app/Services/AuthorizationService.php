<?php
/**
 * AuthorizationService.php
 * 
 * Centralized authorization service providing a clean API for permission
 * and role checks across middleware, controllers, and services.
 * 
 * This service is the single source of truth for authorization decisions.
 * Every sensitive operation should go through this service.
 * 
 * @package BroxLab
 * @version 1.0.0
 */

class AuthorizationService {

    /**
     * Permission constants moved to app/Permissions.php
     * @see Permissions
     */
    
    private static ?AuthorizationService $instance = null;
    private ?UserModel $userModel = null;
    private ?int $currentUserId = null;
    
    /**
     * Private constructor - use getInstance()
     */
    private function __construct() {
        global $mysqli;
        
        if (isset($mysqli) && $mysqli instanceof mysqli) {
            $this->userModel = new UserModel($mysqli);
        }
        
        $this->currentUserId = AuthManager::getCurrentUserId();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set the user ID for checks (useful when acting on behalf of another user)
     */
    public function forUser(int $userId): self {
        $this->currentUserId = $userId;
        return $this;
    }
    
    /**
     * Get the current user ID being checked
     */
    public function getUserId(): ?int {
        return $this->currentUserId;
    }
    
    /**
     * Check if a user has a specific permission
     * 
     * @param string $permission Permission name (e.g., 'users.delete')
     * @param int|null $userId User ID to check (defaults to current user)
     * @return bool
     */
    public function can(string $permission, ?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        
        // Super admins have all permissions
        if ($this->userModel->isSuperAdmin($userId)) {
            return true;
        }
        
        return $this->userModel->hasPermission($userId, $permission);
    }
    
    /**
     * Check if a user does NOT have a specific permission (inverse of can)
     */
    public function cannot(string $permission, ?int $userId = null): bool {
        return !$this->can($permission, $userId);
    }
    
    /**
     * Check if user has any of the given permissions
     * 
     * @param array $permissions List of permission names
     * @param int|null $userId User ID to check
     * @return bool
     */
    public function canAny(array $permissions, ?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        
        // Super admins have all permissions
        if ($this->userModel->isSuperAdmin($userId)) {
            return true;
        }
        
        return $this->userModel->hasAnyPermission($userId, $permissions);
    }
    
    /**
     * Check if user can access all the given permissions
     * 
     * @param array $permissions List of permission names
     * @param int|null $userId User ID to check
     * @return bool
     */
    public function canAll(array $permissions, ?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        
        // Super admins have all permissions
        if ($this->userModel->isSuperAdmin($userId)) {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if (!$this->userModel->hasPermission($userId, $permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Require a specific permission - throws ForbiddenException if not granted
     * 
     * @param string $permission Permission name
     * @param string|null $message Custom error message
     * @param string $errorCode Machine-readable error code
     * @throws ForbiddenException
     */
    public function requirePermission(string $permission, ?string $message = null, string $errorCode = 'PERMISSION_DENIED'): void {
        if (!$this->can($permission)) {
            throw new ForbiddenException(
                $message ?? "Permission required: {$permission}",
                $errorCode,
                ['required_permission' => $permission, 'user_id' => $this->currentUserId]
            );
        }
    }
    
    /**
     * Require any of the given permissions
     * 
     * @param array $permissions List of permission names
     * @param string|null $message Custom error message
     * @throws ForbiddenException
     */
    public function requireAnyPermission(array $permissions, ?string $message = null): void {
        if (!$this->canAny($permissions)) {
            $permStr = implode(', ', $permissions);
            throw new ForbiddenException(
                $message ?? "One of the following permissions required: {$permStr}",
                'PERMISSION_DENIED',
                ['required_permissions' => $permissions, 'user_id' => $this->currentUserId]
            );
        }
    }
    
    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role, ?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        
        return $this->userModel->hasRole($userId, $role);
    }
    
    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles, ?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        
        return $this->userModel->hasAnyRole($userId, $roles);
    }
    
    /**
     * Require a specific role - throws ForbiddenException if not granted
     * 
     * @param string $role Role name
     * @param string|null $message Custom error message
     * @throws ForbiddenException
     */
    public function requireRole(string $role, ?string $message = null): void {
        $userId = $this->currentUserId;
        if (!$userId || !$this->userModel) {
            throw new ForbiddenException('Authentication required', 'AUTH_REQUIRED');
        }
        
        // Super admins bypass role checks
        if ($this->userModel->isSuperAdmin($userId)) {
            return;
        }
        
        if (!$this->userModel->hasRole($userId, $role)) {
            throw new ForbiddenException(
                $message ?? "Role required: {$role}",
                'ROLE_DENIED',
                ['required_role' => $role, 'user_id' => $userId]
            );
        }
    }
    
    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(?int $userId = null): bool {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return false;
        }
        return $this->userModel->isSuperAdmin($userId);
    }
    
    /**
     * Get all permissions for the current user
     */
    public function getPermissions(?int $userId = null): array {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return [];
        }
        return $this->userModel->getPermissions($userId);
    }
    
    /**
     * Get all roles for the current user
     */
    public function getRoles(?int $userId = null): array {
        $userId = $userId ?? $this->currentUserId;
        if (!$userId || !$this->userModel) {
            return [];
        }
        return $this->userModel->getRoles($userId);
    }
    
    /**
     * Verify the current user is not trying to modify their own super admin status
     * 
     * @param int $targetUserId The user being modified
     * @throws ForbiddenException if trying to self-demote
     */
    public function requireNotSelf(int $targetUserId): void {
        if ($this->currentUserId === $targetUserId) {
            throw new ForbiddenException(
                'You cannot perform this action on your own account.',
                'SELF_ACTION_DENIED'
            );
        }
    }
    
    /**
     * Verify a target user exists
     * 
     * @param int $targetUserId
     * @throws ForbiddenException if user not found
     */
    public function requireUserExists(int $targetUserId): void {
        if (!$this->userModel || !$this->userModel->findById($targetUserId)) {
            throw new ForbiddenException('User not found.', 'USER_NOT_FOUND');
        }
    }
    
    /**
     * Log an authorization failure for audit
     */
    public function logFailure(string $action, string $reason, array $context = []): void {
        logActivity(
            "Authorization Failed: {$action} - {$reason}",
            'security',
            $this->currentUserId,
            array_merge($context, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]),
            'failure'
        );
    }
}
