<?php
/**
 * Permissions.php
 * 
 * Centralized permission name constants for the BroxLab RBAC system.
 * 
 * Naming convention: PERM_{MODULE}_{ACTION}
 * 
 * Usage:
 *   AuthorizationService::getInstance()->requirePermission(Permissions::PERM_USERS_CREATE);
 *   $auth->can(Permissions::PERM_USERS_EDIT);
 * 
 * This is the SINGLE SOURCE OF TRUTH for permission names.
 * Every permission check MUST use these constants — never inline strings.
 * 
 * @package BroxLab
 * @version 1.0.0
 */
class Permissions {

    // ═══════════════════════════════════════════════════════════════
    // USER MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /** Create new user accounts */
    const PERM_USERS_CREATE = 'users.create';

    /** Edit existing user accounts */
    const PERM_USERS_EDIT   = 'users.edit';

    /** Delete user accounts */
    const PERM_USERS_DELETE = 'users.delete';

    // ═══════════════════════════════════════════════════════════════
    // ROLE MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /** Create new roles */
    const PERM_ROLES_CREATE = 'roles.create';

    /** Edit existing roles */
    const PERM_ROLES_EDIT   = 'roles.edit';

    /** Delete roles */
    const PERM_ROLES_DELETE = 'roles.delete';

    /** Assign roles to users */
    const PERM_ROLES_ASSIGN = 'roles.assign';

    // ═══════════════════════════════════════════════════════════════
    // PERMISSION MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    /** Create new permission entries */
    const PERM_PERMISSIONS_CREATE = 'permissions.create';

    /** Edit existing permission entries */
    const PERM_PERMISSIONS_EDIT   = 'permissions.edit';

    /** Delete permission entries */
    const PERM_PERMISSIONS_DELETE = 'permissions.delete';
}
