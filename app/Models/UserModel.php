<?php

// classes/UserModel.php



class UserModel {

    private $mysqli;
    private ?array $userColumns = null;



    public function __construct(mysqli $mysqli) {

        $this->mysqli = $mysqli;

    }

    /**
     * Return cached users table columns.
     */
    private function getUserColumns(): array
    {
        if ($this->userColumns !== null) {
            return $this->userColumns;
        }

        $this->userColumns = [];
        $result = $this->mysqli->query("SHOW COLUMNS FROM users");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $this->userColumns[] = $field;
                }
            }
        }

        return $this->userColumns;
    }

    /**
     * Check if a specific column exists in users table.
     */
    private function hasUserColumn(string $column): bool
    {
        return in_array($column, $this->getUserColumns(), true);
    }



    /** -------------------------------

     * Basic Finders

     * ------------------------------*/

    public function findByUsernameOrEmail(string $usernameOrEmail): ?array {

        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1");

        $stmt->bind_param('ss', $usernameOrEmail, $usernameOrEmail);

        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;

    }



    public function findByUsername(string $username): ?array {

        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1");

        $stmt->bind_param('s', $username);

        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;

    }



    public function findByEmail(string $email): ?array {

        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");

        $stmt->bind_param('s', $email);

        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;

    }



    /**

     * Find user by provider ID (OAuth)
     * @param string $provider Provider name (google, facebook, github, etc.)
     * @param string $providerId Provider's user ID
     * @return array|null User record or null if not found
     */
    public function findByProviderUserId(string $provider, string $providerId): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT u.* FROM users u
            INNER JOIN user_linked_accounts ula ON ula.user_id = u.id
            WHERE ula.provider = ? AND ula.provider_user_id = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('ss', $provider, $providerId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }


    public function findById(int $id): ?array
    {
        $sql = "
            SELECT 
                u.*,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.ranking DESC) AS roles,
                MAX(r.is_super_admin) AS is_super_admin
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
            WHERE u.id = ?
            AND u.deleted_at IS NULL
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            return null;
        }

        // Normalize roles
        $user['roles'] = !empty($user['roles'])
            ? explode(',', $user['roles'])
            : [];

        $user['is_super_admin'] = (bool) $user['is_super_admin'];

        return $user;
    }




    public function getAllUsers(): array {

        $result = $this->mysqli->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id DESC");

        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    }



    public function getUserCount(): int {

        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");

        return $result ? (int)$result->fetch_assoc()['count'] : 0;

    }



    /** -------------------------------

     * Create / Update / Delete

     * ------------------------------*/

    public function create(array $data): bool {

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Store all values in variables before binding (cannot pass temporaries by reference)
        $username = $data['username'];
        $email = $data['email'];
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;
        $gender = $data['gender'] ?? null;
        $dob = $data['dob'] ?? null;
        $phone = $data['phone'] ?? null;
        $alternatePhone = $data['alternate_phone'] ?? null;
        $address = $data['address'] ?? null;
        $city = $data['city'] ?? null;
        $state = $data['state'] ?? null;
        $country = $data['country'] ?? null;
        $zipcode = $data['zipcode'] ?? null;
        $profilePic = $data['profile_pic'] ?? null;
        $authProvider = $data['auth_provider'] ?? 'email';
        $role = $data['role'] ?? 'user';
        $status = $data['status'] ?? 'active';

        $hasLegacyRoleColumn = $this->hasUserColumn('role');

        if ($hasLegacyRoleColumn) {
            $stmt = $this->mysqli->prepare("
                INSERT INTO users
                (username, email, password, first_name, last_name, gender, dob, phone, alternate_phone, address, city, state, country, zipcode, profile_pic, auth_provider, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                logError("UserModel::create prepare failed (with role): " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param(
                'ssssssssssssssssss',
                $username,
                $email,
                $hashedPassword,
                $firstName,
                $lastName,
                $gender,
                $dob,
                $phone,
                $alternatePhone,
                $address,
                $city,
                $state,
                $country,
                $zipcode,
                $profilePic,
                $authProvider,
                $role,
                $status
            );
        } else {
            $stmt = $this->mysqli->prepare("
                INSERT INTO users
                (username, email, password, first_name, last_name, gender, dob, phone, alternate_phone, address, city, state, country, zipcode, profile_pic, auth_provider, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {
                logError("UserModel::create prepare failed (no role): " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param(
                'sssssssssssssssss',
                $username,
                $email,
                $hashedPassword,
                $firstName,
                $lastName,
                $gender,
                $dob,
                $phone,
                $alternatePhone,
                $address,
                $city,
                $state,
                $country,
                $zipcode,
                $profilePic,
                $authProvider,
                $status
            );
        }

        if (!$stmt->execute()) {
            logError("UserModel::create execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $stmt->close();

        // Get newly created user ID and assign RBAC role
        $userId = $this->mysqli->insert_id;
        
        if ($userId > 0) {
            // Assign RBAC role based on legacy role value.
            $defaultRoleId = $this->getDefaultRoleId($role);
            if ($defaultRoleId > 0) {
                $this->assignRole($userId, $defaultRoleId);
            }
        }

        return true;

    }

    /**
     * Find a user by Firebase UID
     *
     * @param string $firebaseUid
     * @return array|null
     */
    public function findByFirebaseUid(string $firebaseUid): ?array {
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE firebase_uid = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->bind_param('s', $firebaseUid);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Link a Firebase UID to an existing user
     *
     * @param int $userId
     * @param string $firebaseUid
     * @return bool
     */
    public function linkFirebaseUid(int $userId, string $firebaseUid): bool {
        $stmt = $this->mysqli->prepare("UPDATE users SET firebase_uid = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $firebaseUid, $userId);
        return $stmt->execute();
    }

    /**
     * Create a linked OAuth account record for a user
     * 
     * @param int $userId
     * @param string $provider (google, facebook, github)
     * @param string $providerUserId Provider's user ID (e.g. google UID)
     * @param string $providerEmail Email from provider
     * @param string|null $providerData JSON or picture URL
     * @return bool
     */
    public function createLinkedAccount(int $userId, string $provider, string $providerUserId, string $providerEmail, ?string $providerData = null): bool {
        try {
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_linked_accounts 
                (user_id, provider, provider_user_id, provider_email, provider_data, is_primary, created_at, linked_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                provider_email = VALUES(provider_email),
                provider_data = VALUES(provider_data),
                last_used_at = NOW()
            ");
            
            $stmt->bind_param('issss', $userId, $provider, $providerUserId, $providerEmail, $providerData);
            return $stmt->execute();
        } catch (Throwable $e) {
            logError('UserModel::createLinkedAccount error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new user from Firebase data and return the new user id, or null on failure
     *
     * @param array $data ['uid','email','name','picture']
     * @return int|null
     */
    public function createFromFirebase(array $data): ?int {
        $email = $data['email'] ?? null;
        $uid = $data['uid'] ?? null;
        $displayName = $data['displayName'] ?? null;
        $photoURL = $data['photoURL'] ?? null;
        $provider = $data['provider'] ?? 'unknown';
        $requestedUsername = trim((string)($data['username'] ?? ''));
        $firstName = $data['first_name'] ?? null;
        $lastName = $data['last_name'] ?? null;

        if (!$email || !$uid) return null;

        // Parse display name into first/last name if not already provided
        if (!$firstName && $displayName) {
            $nameParts = array_filter(explode(' ', trim($displayName), 2));
            $firstName = $nameParts[0] ?? null;
            $lastName = $nameParts[1] ?? null;
        }

        // Prefer requested username from client when valid and available.
        if ($requestedUsername !== '' && preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $requestedUsername) && !$this->findByUsername($requestedUsername)) {
            $username = $requestedUsername;
        } else {
            $baseUsername = $displayName
                ? preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(str_replace(' ', '_', $displayName)))
                : explode('@', $email)[0];
            if ($baseUsername === '') {
                $baseUsername = 'user';
            }
            $username = $this->generateUniqueUsernameForCreate($baseUsername);
        }

        // Random password for OAuth users (they won't use it)
        $password = bin2hex(random_bytes(16));

        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'profile_pic' => $photoURL,
            'auth_provider' => $provider,
            'status' => 'active',
        ];

        if (!$this->create($userData)) {
            return null;
        }

        $newUser = $this->findByEmail($email);
        if (!$newUser) return null;

        $userId = (int)$newUser['id'];
        
        // 1. Link firebase_uid to the user
        $this->linkFirebaseUid($userId, $uid);
        
        // 2. Create linked account entry for future provider linking
        $this->createLinkedAccount($userId, $provider, $uid, $email, $photoURL);

        return $userId;
    }

    /**
     * Helper used internally to generate unique usernames when creating users here (avoids dependency on AuthManager helpers)
     */
    private function generateUniqueUsernameForCreate(string $baseUsername): string {
        $username = substr($baseUsername, 0, 30);
        $counter = 1;
        while ($this->findByUsername($username)) {
            $username = substr($baseUsername, 0, 24) . '_' . $counter;
            $counter++;
            if ($counter > 1000) {
                $username = $baseUsername . '_' . bin2hex(random_bytes(3));
                break;
            }
        }
        return $username;
    }

    /**
     * Get role ID from role name
     */
    private function getDefaultRoleId(string $roleName): int {
        $stmt = $this->mysqli->prepare("
            SELECT id FROM roles WHERE name = ? AND deleted_at IS NULL LIMIT 1
        ");
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return (int)$row['id'];
        }
        
        return 0;
    }

    public function updateUser(int $id, array $data): bool {

        if ($id <= 0 || empty($data)) {
            return false;
        }

        $fields = [];

        $params = [];

        $types = '';



        foreach ($data as $key => $value) {

            $fields[] = "$key = ?";

            $params[] = $value;

            $types .= is_int($value) ? 'i' : 's';

        }



        $params[] = $id;

        $types .= 'i';



        $query = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";

        $stmt = $this->mysqli->prepare($query);

        if (!$stmt) {
            logError("UserModel::updateUser prepare failed: " . $this->mysqli->error);
            return false;
        }

        $stmt->bind_param($types, ...$params);

        return $stmt->execute();

    }



    public function updateUserRole(int $id, string $role): bool {

        if ($this->hasUserColumn('role')) {
            $stmt = $this->mysqli->prepare("UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) {
                logError("UserModel::updateUserRole prepare failed: " . $this->mysqli->error);
                return false;
            }
            $stmt->bind_param('si', $role, $id);
            return $stmt->execute();
        }

        $roleId = $this->getDefaultRoleId($role);
        if ($roleId <= 0) {
            return false;
        }

        return $this->assignRoles($id, [$roleId]);

    }



    public function deleteUser(int $id): bool {

        // Soft delete

        $stmt = $this->mysqli->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");

        $stmt->bind_param('i', $id);

        return $stmt->execute();

    }



    /** -------------------------------

     * Auth / Security

     * ------------------------------*/

    public function updateLastLogin(int $userId, ?string $ip = null, ?string $device = null): bool {

        $stmt = $this->mysqli->prepare("

            UPDATE users 

            SET last_login = NOW(), login_ip = ?, login_device = ? 

            WHERE id = ?

        ");

        $stmt->bind_param('ssi', $ip, $device, $userId);

        return $stmt->execute();

    }



    public function setResetToken(string $email, string $token, string $expiry): bool {

        // First get user_id from email
        $userStmt = $this->mysqli->prepare("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $userStmt->bind_param('s', $email);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $user = $userResult->fetch_assoc();
        $userStmt->close();
        
        if (!$user) {
            return false;
        }
        
        // Insert token into password_resets table (correct schema)
        $stmt = $this->mysqli->prepare("
            INSERT INTO password_resets (user_id, token, token_type, expires_at, created_at)
            VALUES (?, ?, 'password_reset', ?, NOW())
        ");
        
        if (!$stmt) {
            logError("Prepare failed in setResetToken: " . $this->mysqli->error);
            return false;
        }
        
        $stmt->bind_param('iss', $user['id'], $token, $expiry);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;

    }



    public function findByResetToken(string $token): ?array {

        // Use password_resets table which has the correct schema
        $stmt = $this->mysqli->prepare("
            SELECT user_id FROM password_resets 
            WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1
        ");
        
        if (!$stmt) {
            logError("Prepare failed in findByResetToken: " . $this->mysqli->error);
            return null;
        }
        
        $stmt->bind_param('s', $token);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        return $result ?: null;

    }



    public function updatePasswordByResetToken(string $token, string $hashedPassword): bool {

        // Find the password reset record (using correct password_resets table)
        $findStmt = $this->mysqli->prepare("
            SELECT user_id FROM password_resets 
            WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1
        ");
        
        if (!$findStmt) {
            logError("Prepare failed in updatePasswordByResetToken: " . $this->mysqli->error);
            return false;
        }
        
        $findStmt->bind_param('s', $token);
        $findStmt->execute();
        $result = $findStmt->get_result();
        $resetRecord = $result->fetch_assoc();
        $findStmt->close();
        
        if (!$resetRecord) {
            logError("Password reset token not found or expired");
            return false;
        }
        
        // Update user password in users table
        $updateStmt = $this->mysqli->prepare("
            UPDATE users 
            SET password = ?, password_changed_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        
        if (!$updateStmt) {
            logError("Prepare failed updating user password: " . $this->mysqli->error);
            return false;
        }
        
        $updateStmt->bind_param('si', $hashedPassword, $resetRecord['user_id']);
        $updateResult = $updateStmt->execute();
        $updateStmt->close();
        
        if (!$updateResult) {
            logError("Failed to update user password");
            return false;
        }
        
        // Mark token as used in password_resets table
        $markStmt = $this->mysqli->prepare("
            UPDATE password_resets 
            SET used = 1, used_at = NOW(), updated_at = NOW() 
            WHERE token = ?
        ");
        
        if (!$markStmt) {
            logError("Prepare failed marking token as used: " . $this->mysqli->error);
            return false;
        }
        
        $markStmt->bind_param('s', $token);
        $markResult = $markStmt->execute();
        $markStmt->close();
        
        return $markResult;

    }



    /** -------------------------------

     * Profile Functions

     * ------------------------------*/

    public function getProfile(int $userId): ?array {

        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;

    }



    /** ================================

     * RBAC - Role & Permission Methods

     * ==================================*/



    /**

     * Get all roles for a user

     */

    public function getRoles(int $userId): array {

        $stmt = $this->mysqli->prepare("

            SELECT r.id, r.name, r.description, r.is_super_admin, r.created_at

            FROM roles r

            INNER JOIN user_roles ur ON r.id = ur.role_id

            WHERE ur.user_id = ? AND r.deleted_at IS NULL

            ORDER BY r.is_super_admin DESC, r.name ASC

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    }



    /**

     * Assign role to user

     */

    public function assignRole(int $userId, int $roleId): bool {

        $stmt = $this->mysqli->prepare("

            INSERT IGNORE INTO user_roles (user_id, role_id, created_at)

            VALUES (?, ?, NOW())

        ");

        $stmt->bind_param('ii', $userId, $roleId);

        return $stmt->execute();

    }



    /**

     * Remove role from user

     */

    public function removeRole(int $userId, int $roleId): bool {

        $stmt = $this->mysqli->prepare("

            DELETE FROM user_roles 

            WHERE user_id = ? AND role_id = ?

        ");

        $stmt->bind_param('ii', $userId, $roleId);

        return $stmt->execute();

    }



    /**

     * Check if user has role

     */

    public function hasRole(int $userId, string $roleName): bool {

        $stmt = $this->mysqli->prepare("

            SELECT 1 FROM user_roles ur

            INNER JOIN roles r ON r.id = ur.role_id

            WHERE ur.user_id = ? AND r.name = ? AND r.deleted_at IS NULL

            LIMIT 1

        ");

        $stmt->bind_param('is', $userId, $roleName);

        $stmt->execute();

        return (bool)$stmt->get_result()->num_rows;

    }



    /**

     * Check if user has any of multiple roles

     */

    public function hasAnyRole(int $userId, array $roleNames): bool {

        if (empty($roleNames)) {

            return false;

        }



        $placeholders = implode(',', array_fill(0, count($roleNames), '?'));

        $query = "

            SELECT 1 FROM user_roles ur

            INNER JOIN roles r ON r.id = ur.role_id

            WHERE ur.user_id = ? AND r.name IN ($placeholders) AND r.deleted_at IS NULL

            LIMIT 1

        ";

        

        $stmt = $this->mysqli->prepare($query);

        $params = array_merge([$userId], $roleNames);

        $types = 'i' . str_repeat('s', count($roleNames));  // FIX: userId is integer

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        return (bool)$stmt->get_result()->num_rows;

    }



    /**

     * Get all permissions for a user (from all their roles)

     */

    public function getPermissions(int $userId): array {

        $stmt = $this->mysqli->prepare("

            SELECT DISTINCT p.id, p.name, p.module, p.description, p.created_at

            FROM permissions p

            INNER JOIN role_permissions rp ON p.id = rp.permission_id

            INNER JOIN roles r ON r.id = rp.role_id

            INNER JOIN user_roles ur ON r.id = ur.role_id

            WHERE ur.user_id = ? AND p.deleted_at IS NULL AND r.deleted_at IS NULL

            ORDER BY p.module ASC, p.name ASC

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    }



    /**

     * Check if user has specific permission

     */

    public function hasPermission(int $userId, string $permissionName): bool {

        $stmt = $this->mysqli->prepare("

            SELECT 1 FROM permissions p

            INNER JOIN role_permissions rp ON p.id = rp.permission_id

            INNER JOIN roles r ON r.id = rp.role_id

            INNER JOIN user_roles ur ON r.id = ur.role_id

            WHERE ur.user_id = ? AND p.name = ? AND p.deleted_at IS NULL AND r.deleted_at IS NULL

            LIMIT 1

        ");

        $stmt->bind_param('is', $userId, $permissionName);

        $stmt->execute();

        return (bool)$stmt->get_result()->num_rows;

    }



    /**

     * Check if user has any of multiple permissions

     */

    public function hasAnyPermission(int $userId, array $permissionNames): bool {

        if (empty($permissionNames)) {

            return false;

        }



        $placeholders = implode(',', array_fill(0, count($permissionNames), '?'));

        $query = "

            SELECT 1 FROM permissions p

            INNER JOIN role_permissions rp ON p.id = rp.permission_id

            INNER JOIN roles r ON r.id = rp.role_id

            INNER JOIN user_roles ur ON r.id = ur.role_id

            WHERE ur.user_id = ? AND p.name IN ($placeholders) AND p.deleted_at IS NULL AND r.deleted_at IS NULL

            LIMIT 1

        ";

        

        $stmt = $this->mysqli->prepare($query);

        $params = array_merge([$userId], $permissionNames);

        $types = 'i' . str_repeat('s', count($permissionNames));  // FIX: userId is integer

        $stmt->bind_param($types, ...$params);

        $stmt->execute();

        return (bool)$stmt->get_result()->num_rows;

    }



    /**

     * Check if user is super admin

     */

    public function isSuperAdmin(int $userId): bool {

        $stmt = $this->mysqli->prepare("

            SELECT 1 FROM roles r

            INNER JOIN user_roles ur ON r.id = ur.role_id

            WHERE ur.user_id = ? AND r.is_super_admin = 1 AND r.deleted_at IS NULL

            LIMIT 1

        ");

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        return (bool)$stmt->get_result()->num_rows;

    }



    /**

     * Assign multiple roles to user (replaces existing)

     */

    public function assignRoles(int $userId, array $roleIds): bool {

        $this->mysqli->begin_transaction();

        

        try {

            // Remove existing roles

            $stmt = $this->mysqli->prepare("DELETE FROM user_roles WHERE user_id = ?");

            $stmt->bind_param('i', $userId);

            $stmt->execute();

            $stmt->close();

            

            // Assign new roles

            if (!empty($roleIds)) {

                $stmt = $this->mysqli->prepare("

                    INSERT INTO user_roles (user_id, role_id, created_at)

                    VALUES (?, ?, NOW())

                ");

                

                foreach ($roleIds as $roleId) {

                    // Rebind parameters for each iteration

                    $userIdRef = $userId;

                    $roleIdRef = $roleId;

                    $stmt->bind_param('ii', $userIdRef, $roleIdRef);

                    if (!$stmt->execute()) {

                        throw new Exception("Failed to assign role");

                    }

                }

                $stmt->close();

            }

            

            $this->mysqli->commit();

            return true;

        } catch (Exception $e) {

            $this->mysqli->rollback();

            return false;

        }

    }



    /**

     * Get users with roles (fetch all users, then roles for each)

     */

    public function getUsersWithRoles(): array {

        $result = $this->mysqli->query("

            SELECT 

                u.id,

                u.username,

                u.email,

                u.first_name,

                u.last_name,

                u.status,

                u.created_at

            FROM users u

            WHERE u.deleted_at IS NULL

            ORDER BY u.id DESC

        ");

        

        $users = [];

        if ($result) {

            while ($user = $result->fetch_assoc()) {

                $user['roles'] = $this->getRoles($user['id']);

                $users[] = $user;

            }

        }

        return $users;

    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCount(): int {
        $sql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
        $result = $this->mysqli->query($sql);
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get new subscribers today
     */
    public function getNewSubscribersToday(): int {
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) as count FROM users 
                WHERE status = 'active' AND DATE(created_at) = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?array {
        $sql = "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    // ============================================================
    // OAUTH ACCOUNT LINKING METHODS
    // ============================================================

    /**
     * Link OAuth account to user
     * Updates both user_linked_accounts and users table
     */
/**
     * Link OAuth account to user
     * Updates both user_linked_accounts and users table
     * 
     * FIXED: Correct column mappings and proper picture handling
     */
    public function linkOAuthAccount(
        int $userId,
        string $provider,
        string $providerUserId,
        ?string $providerEmail = null,
        ?string $providerData = null,
        ?string $providerPicture = null
    ): bool {
        try {
            logError("linkOAuthAccount() called: userId=$userId, provider=$provider, providerUserId=$providerUserId, email=$providerEmail");
            
            // 1. Insert/Update in user_linked_accounts table
            $sql = "INSERT INTO user_linked_accounts 
                    (user_id, provider, provider_user_id, provider_email, provider_data, is_primary, created_at, linked_at)
                    VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                        provider_email = VALUES(provider_email),
                        provider_data = VALUES(provider_data),
                        linked_at = NOW()";
            
            logError("Preparing SQL: $sql");
            $stmt = $this->mysqli->prepare($sql);
            
            if (!$stmt) {
                logError("Failed to prepare statement: " . $this->mysqli->error);
                return false;
            }
            
            $stmt->bind_param("issss", $userId, $provider, $providerUserId, $providerEmail, $providerData);
            
            if (!$stmt->execute()) {
                logError("Error inserting into user_linked_accounts: " . $stmt->error);
                $stmt->close();
                return false;
            }
            
            logError("Successfully inserted into user_linked_accounts for user_id=$userId, provider=$provider");
            $stmt->close();
            
            // 2. Update users table - only update auth_provider and profile_pic
            // Provider-specific IDs are now stored in user_linked_accounts.provider_user_id
            $updateSql = "UPDATE users SET 
                          auth_provider = ?,
                          profile_pic = CASE WHEN profile_pic IS NULL OR profile_pic = '' THEN ? ELSE profile_pic END,
                          updated_at = NOW()
                          WHERE id = ?";
            
            $updateStmt = $this->mysqli->prepare($updateSql);
            
            if (!$updateStmt) {
                logError("Error preparing update statement for user {$userId}: " . $this->mysqli->error);
                return false;
            }
            
            // If provider picture provided, use it; otherwise use empty string
            $picToUse = !empty($providerPicture) ? $providerPicture : '';
            $updateStmt->bind_param('ssi', $provider, $picToUse, $userId);
            
            if (!$updateStmt->execute()) {
                logError("Error updating users table for provider '{$provider}': " . $updateStmt->error);
                $updateStmt->close();
                return false;
            }
            $updateStmt->close();
            
            logError("Successfully updated users table for provider '{$provider}', user_id: {$userId}");
            
            return true;
            
        } catch (Exception $e) {
            logError("Error linking OAuth account: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlink OAuth account from user
     * Removes from both user_linked_accounts and updates users table
     */
    public function unlinkOAuthAccount(int $userId, string $provider): bool {
        try {
            // 1. Delete from user_linked_accounts table
            $sql = "DELETE FROM user_linked_accounts WHERE user_id = ? AND provider = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("is", $userId, $provider);
            
            if (!$stmt->execute()) {
                logError("Error deleting from user_linked_accounts: " . $stmt->error);
                return false;
            }
            $stmt->close();
            
            // 2. Check if any other OAuth accounts remain for this user
            $checkSql = "SELECT COUNT(*) as count FROM user_linked_accounts WHERE user_id = ? AND deleted_at IS NULL";
            $checkStmt = $this->mysqli->prepare($checkSql);
            $checkStmt->bind_param('i', $userId);
            $checkStmt->execute();
            $countResult = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();
            
            // If no more linked accounts, set auth_provider back to 'email'
            if ($countResult['count'] == 0) {
                $updateSql = "UPDATE users SET auth_provider = 'email', updated_at = NOW() WHERE id = ?";
                $updateStmt = $this->mysqli->prepare($updateSql);
                $updateStmt->bind_param('i', $userId);
                
                if (!$updateStmt->execute()) {
                    logError("Error updating users table on unlink: " . $updateStmt->error);
                    return false;
                }
                $updateStmt->close();
            }
            
            return true;
        } catch (Exception $e) {
            logError("Error unlinking OAuth account: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all linked OAuth accounts for user
     */
    public function getLinkedOAuthAccounts(int $userId): array {
        try {
            $sql = "SELECT id, provider, provider_user_id, provider_email, is_primary, linked_at, last_used_at
                    FROM user_linked_accounts
                    WHERE user_id = ?
                    ORDER BY is_primary DESC, linked_at DESC";
            
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $accounts = [];
            while ($row = $result->fetch_assoc()) {
                $accounts[] = $row;
            }
            return $accounts;
        } catch (Exception $e) {
            logError("Error fetching linked OAuth accounts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get linked account by provider and provider ID
     */
    public function getLinkedAccountByProvider(string $provider, string $providerUserId): ?array {
        try {
            $sql = "SELECT id, user_id, provider, provider_user_id, provider_email, is_primary, linked_at
                    FROM user_linked_accounts
                    WHERE provider = ? AND provider_user_id = ?
                    LIMIT 1";
            
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("ss", $provider, $providerUserId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() ?: null;
        } catch (Exception $e) {
            logError("Error fetching linked account by provider: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user has a password set
     */
    public function userHasPassword(int $userId): bool {
        try {
            $sql = "SELECT id FROM users WHERE id = ? AND password IS NOT NULL AND password != '' LIMIT 1";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc() !== null;
        } catch (Exception $e) {
            logError("Error checking user password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user password
     */
    public function updateUserPassword(int $userId, string $hashedPassword): bool {
        try {
            $sql = "UPDATE users SET password = ?, password_changed_at = NOW() WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("si", $hashedPassword, $userId);
            return $stmt->execute();
        } catch (Exception $e) {
            logError("Error updating user password: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if first-time OAuth user needs password setup
     * Returns true if user was created via OAuth and has no password
     */
    public function needsFirstTimePasswordSetup(int $userId): bool {
        try {
            $user = $this->getUserById($userId);
            if (!$user) {
                return false;
            }
            
            // Check if user has OAuth account(s)
            $linkedAccounts = $this->getLinkedOAuthAccounts($userId);
            if (empty($linkedAccounts)) {
                return false;
            }
            
            // Check if user has no password set
            return !$this->userHasPassword($userId);
        } catch (Exception $e) {
            logError("Error checking first-time password setup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark OAuth account as primary login method
     * Updates both user_linked_accounts and users table
     */
    public function setPrimaryOAuthProvider(int $userId, string $provider): bool {
        try {
            // Start transaction
            $this->mysqli->begin_transaction();
            
            // Remove primary from all
            $sql1 = "UPDATE user_linked_accounts SET is_primary = 0 WHERE user_id = ?";
            $stmt1 = $this->mysqli->prepare($sql1);
            $stmt1->bind_param("i", $userId);
            $stmt1->execute();
            
            // Set new primary
            $sql2 = "UPDATE user_linked_accounts SET is_primary = 1 WHERE user_id = ? AND provider = ?";
            $stmt2 = $this->mysqli->prepare($sql2);
            $stmt2->bind_param("is", $userId, $provider);
            $result = $stmt2->execute();
            
            // Update users table auth_provider
            if ($result) {
                // Get the provider_user_id to update users table
                $sql3 = "SELECT provider_user_id FROM user_linked_accounts WHERE user_id = ? AND provider = ? AND is_primary = 1";
                $stmt3 = $this->mysqli->prepare($sql3);
                $stmt3->bind_param("is", $userId, $provider);
                $stmt3->execute();
                $providerResult = $stmt3->get_result();
                
                if ($providerResult && $providerResult->num_rows > 0) {
                    $row = $providerResult->fetch_assoc();
                    $providerUserId = $row['provider_user_id'];
                    
                    // Update auth_provider in users table (only track initial registration provider)
                    $sql4 = "UPDATE users SET auth_provider = ? WHERE id = ? AND auth_provider IS NULL";
                    $stmt4 = $this->mysqli->prepare($sql4);
                    $stmt4->bind_param("si", $provider, $userId);
                    $stmt4->execute();
                }
            }
            
            // Commit transaction
            if ($result) {
                $this->mysqli->commit();
                return true;
            } else {
                $this->mysqli->rollback();
                return false;
            }
        } catch (Exception $e) {
            $this->mysqli->rollback();
            logError("Error setting primary OAuth provider: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update last used timestamp for OAuth account
     */
    public function updateOAuthLastUsed(string $provider, string $providerUserId): bool {
        try {
            $sql = "UPDATE user_linked_accounts SET last_used_at = NOW() WHERE provider = ? AND provider_user_id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("ss", $provider, $providerUserId);
            return $stmt->execute();
        } catch (Exception $e) {
            logError("Error updating OAuth last used: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users with specific role
     * Used by sendNotificationByRole() in fcm.php
     * 
     * @param string $roleName Role name to search
     * @return array List of users with that role
     */
    public function getUsersByRole(string $roleName): array {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT u.id, u.username, u.email, u.status 
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE r.name = ? AND u.deleted_at IS NULL AND u.status = 'active'
            ORDER BY u.username ASC
        ");
        $stmt->bind_param('s', $roleName);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get all users with specific permission
     * Used by sendNotificationByPermission() in fcm.php
     * 
     * @param string $permissionName Permission name to search
     * @return array List of users with that permission
     */
    public function getUsersByPermission(string $permissionName): array {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT u.id, u.username, u.email, u.status 
            FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN role_permissions rp ON ur.role_id = rp.role_id
            INNER JOIN permissions p ON rp.permission_id = p.id
            WHERE p.name = ? AND u.deleted_at IS NULL AND u.status = 'active'
            ORDER BY u.username ASC
        ");
        $stmt->bind_param('s', $permissionName);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // ====================== PAGINATION & SEARCH ======================

    /**
     * Get paginated, searched, and sorted users with their roles
     */
    public function getUsers($page = 1, $limit = 20, $search = '', $sort = 'username', $order = 'ASC', $filters = []) {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $allowedSorts = ['id', 'username', 'email', 'first_name', 'status', 'created_at'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'username';
        
        $sql = "SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.status, u.created_at
                FROM users u
                WHERE u.deleted_at IS NULL";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            $types = 'ssss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'suspended'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        $sql .= " ORDER BY u.`{$sort}` {$order} LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $users = [];
        if ($result) {
            while ($user = $result->fetch_assoc()) {
                $user['roles'] = $this->getRoles($user['id']);
                $users[] = $user;
            }
        }
        return $users;
    }

    /**
     * Get total count of users with optional search/filter
     */
    public function getUsersCount($search = '', $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            $types = 'ssss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'banned', 'pending'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    public function loadUserById(int $id): ?array
    {
        $sql = "
            SELECT 
                u.*,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.is_super_admin DESC, r.name ASC) AS roles,
                MAX(r.is_super_admin) AS is_super_admin
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id AND r.deleted_at IS NULL
            WHERE u.id = ? AND u.deleted_at IS NULL
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) return null;

        // Normalize roles
        $user['roles'] = !empty($user['roles']) ? explode(',', $user['roles']) : [];
        $user['is_super_admin'] = (bool)$user['is_super_admin'];

        // ➤ নতুন: প্রধান role হিসেবে প্রথম role আলাদা field-এ
        $user['role'] = $user['roles'][0] ?? 'guest';

        return $user;
    }

    /**
     * ========================================
     * ACCOUNT CONFLICT DETECTION & RESOLUTION
     * ========================================
     */

    /**
     * Check if email exists with different OAuth provider
     * Returns account-exists-with-different-credential conflict info
     * 
     * @param string $email Email to check
     * @param string $provider Current provider (google, facebook, github)
     * @param string $providerUserId Provider's unique user ID
     * @return array|null Conflict info if exists: ['user_id', 'email', 'existing_providers', 'has_password', ...]
     */
    public function checkAccountConflict(string $email, string $provider, string $providerUserId): ?array
    {
        try {
            $email = trim(strtolower($email));

            // 1. Check if email exists in users table
            $stmt = $this->mysqli->prepare("
                SELECT u.id, u.email, u.password, u.auth_provider, u.created_at
                FROM users u
                WHERE LOWER(u.email) = ? AND u.deleted_at IS NULL
                LIMIT 1
            ");

            if (!$stmt) {
                logError("UserModel::checkAccountConflict - Prepare error: " . $this->mysqli->error);
                return null;
            }

            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                // Email doesn't exist - no conflict
                return null;
            }

            // 2. Check if current provider is already linked to this user
            $stmt = $this->mysqli->prepare("
                SELECT provider, provider_user_id, linked_at
                FROM user_linked_accounts
                WHERE user_id = ? AND provider = ? AND deleted_at IS NULL
                LIMIT 1
            ");

            if (!$stmt) {
                logError("UserModel::checkAccountConflict - Prepare error: " . $this->mysqli->error);
                return null;
            }

            $stmt->bind_param('is', $user['id'], $provider);
            $stmt->execute();
            $linkedResult = $stmt->get_result();
            $linkedAccount = $linkedResult->fetch_assoc();
            $stmt->close();

            // If same provider already linked to same user - no conflict
            if ($linkedAccount && $linkedAccount['provider_user_id'] === $providerUserId) {
                return null;
            }

            // 3. Get all linked providers for this user
            $stmt = $this->mysqli->prepare("
                SELECT DISTINCT provider 
                FROM user_linked_accounts
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY linked_at DESC
            ");

            if (!$stmt) {
                logError("UserModel::checkAccountConflict - Prepare error: " . $this->mysqli->error);
                return null;
            }

            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $linkedResult = $stmt->get_result();
            
            $existingProviders = [];
            while ($row = $linkedResult->fetch_assoc()) {
                $existingProviders[] = $row['provider'];
            }
            $stmt->close();

            // Providers are now only tracked in user_linked_accounts table
            $existingProviders = array_values(array_unique($existingProviders));

            // 4. Check if trying to use a different provider
            if (in_array($provider, $existingProviders, true)) {
                // Provider already exists for this user - no conflict
                return null;
            }

            // CONFLICT DETECTED: Email exists but with different provider(s)
            logError("Account conflict detected - Email: {$email}, Current provider: {$provider}, Existing providers: " . json_encode($existingProviders));

            return [
                'user_id' => (int)$user['id'],
                'email' => $user['email'],
                'existing_providers' => $existingProviders,
                'has_password' => !empty($user['password']),
                'auth_provider' => $user['auth_provider'],
                'current_provider' => $provider,
                'account_age' => $this->getAccountAgeInDays($user['created_at']),
                'conflict_type' => 'account_exists_with_different_credential'
            ];

        } catch (Exception $e) {
            logError("UserModel::checkAccountConflict - Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if account conflict can be safely resolved (not a hijacking attempt)
     * Validates that the linking request is legitimate
     * 
     * @param int $existingUserId ID of existing account
     * @param string $provider New provider to link
     * @param string $email Email being used (must match existing account)
     * @return array ['valid' => bool, 'reason' => string]
     */
    public function validateConflictResolution(int $existingUserId, string $provider, string $email): array
    {
        try {
            // 1. Verify existing user exists and matches email
            $user = $this->findById($existingUserId);
            if (!$user) {
                return ['valid' => false, 'reason' => 'User account not found'];
            }

            if (strtolower($user['email']) !== strtolower($email)) {
                logError("Conflict resolution attempt with mismatched email - User: {$existingUserId}, Expected: {$email}, Got: {$user['email']}");
                return ['valid' => false, 'reason' => 'Email mismatch'];
            }

            // 2. Check if provider is already linked
            $stmt = $this->mysqli->prepare("
                SELECT id FROM user_linked_accounts
                WHERE user_id = ? AND provider = ? AND deleted_at IS NULL
                LIMIT 1
            ");

            if (!$stmt) {
                return ['valid' => false, 'reason' => 'Database error'];
            }

            $stmt->bind_param('is', $existingUserId, $provider);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();

            if ($existing) {
                return ['valid' => false, 'reason' => 'Provider already linked to this account'];
            }

            // 3. Check for rate limiting (prevent brute force linking attempts)
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) as count FROM user_linked_accounts
                WHERE user_id = ? AND linked_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");

            if (!$stmt) {
                return ['valid' => false, 'reason' => 'Database error'];
            }

            $stmt->bind_param('i', $existingUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            // Limit to 5 linking attempts per hour per user
            if ($row['count'] >= 5) {
                logError("Rate limit exceeded for user linking - User: {$existingUserId}");
                return ['valid' => false, 'reason' => 'Too many linking attempts. Please try again later.'];
            }

            return ['valid' => true, 'reason' => 'Conflict resolution is safe to proceed'];

        } catch (Exception $e) {
            logError("UserModel::validateConflictResolution - Exception: " . $e->getMessage());
            return ['valid' => false, 'reason' => 'Server error during validation'];
        }
    }

    /**
     * Helper: Get account age in days
     */
    private function getAccountAgeInDays(?string $createdAt): int
    {
        if (!$createdAt) {
            return 0;
        }
        try {
            $created = new DateTime($createdAt);
            $now = new DateTime();
            return (int)$created->diff($now)->format('%a');
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Check if a specific OAuth provider is already linked to a user
     * Used to determine if conflict check is needed (only on first login with provider)
     * 
     * @param int $userId User ID
     * @param string $provider OAuth provider (google, facebook, github)
     * @return bool True if provider already linked, false otherwise
     */
    public function isProviderLinked(int $userId, string $provider): bool {
        try {
            // Only check user_linked_accounts table (direct columns google_id/facebook_id/github_id are deprecated)
            $stmt = $this->mysqli->prepare(
                "SELECT 1 FROM user_linked_accounts 
                 WHERE user_id = ? AND provider = ? AND deleted_at IS NULL 
                 LIMIT 1"
            );
            
            if (!$stmt) {
                logError("UserModel::isProviderLinked - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('is', $userId, $provider);
            if (!$stmt->execute()) {
                logError("UserModel::isProviderLinked - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }

            $result = $stmt->get_result();
            $isLinked = $result->num_rows > 0;
            $stmt->close();
            
            if ($isLinked) {
                logError("UserModel::isProviderLinked - Provider {$provider} already linked to user {$userId}");
            }

            return $isLinked;

        } catch (Exception $e) {
            logError("UserModel::isProviderLinked - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all linked recovery emails for a user
     * @param int $userId
     * @return array
     */
    public function getLinkedEmails(int $userId): array {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, user_id, email, is_primary, verified, created_at, verified_at 
                FROM user_recovery_emails 
                WHERE user_id = ? AND deleted_at IS NULL
                ORDER BY is_primary DESC, created_at DESC
            ");
            
            if (!$stmt) {
                logError("UserModel::getLinkedEmails - Prepare failed: " . $this->mysqli->error);
                return [];
            }

            $stmt->bind_param('i', $userId);
            
            if (!$stmt->execute()) {
                logError("UserModel::getLinkedEmails - Execute failed: " . $this->mysqli->error);
                $stmt->close();
                return [];
            }

            $result = $stmt->get_result();
            $emails = [];
            
            while ($row = $result->fetch_assoc()) {
                $emails[] = [
                    'id' => (int)$row['id'],
                    'email' => $row['email'],
                    'is_primary' => (bool)$row['is_primary'],
                    'verified' => (bool)$row['verified'],
                    'created_at' => $row['created_at'],
                    'verified_at' => $row['verified_at']
                ];
            }

            $stmt->close();
            return $emails;

        } catch (Exception $e) {
            logError("UserModel::getLinkedEmails - Exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add a new recovery email for a user
     * @param int $userId
     * @param string $email
     * @return array|false Returns email record with token or false on failure
     */
    public function addRecoveryEmail(int $userId, string $email) {
        try {
            $email = strtolower(trim($email));
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                logError("UserModel::addRecoveryEmail - Invalid email format: $email");
                return false;
            }

            // Check if email already exists for this user
            $stmt = $this->mysqli->prepare("
                SELECT id FROM user_recovery_emails 
                WHERE user_id = ? AND email = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) {
                logError("UserModel::addRecoveryEmail - Prepare check failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('is', $userId, $email);
            
            if (!$stmt->execute()) {
                logError("UserModel::addRecoveryEmail - Check execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                logError("UserModel::addRecoveryEmail - Email already linked: $email");
                $stmt->close();
                return false;
            }
            $stmt->close();

            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            
            // Insert new email
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_recovery_emails 
                (user_id, email, is_primary, verified, verification_token, created_at) 
                VALUES (?, ?, 0, 0, ?, NOW())
            ");
            
            if (!$stmt) {
                logError("UserModel::addRecoveryEmail - Insert prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('iss', $userId, $email, $verificationToken);
            
            if (!$stmt->execute()) {
                logError("UserModel::addRecoveryEmail - Insert execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $emailId = $stmt->insert_id;
            $stmt->close();

            return [
                'id' => $emailId,
                'email' => $email,
                'verification_token' => $verificationToken,
                'verified' => false
            ];

        } catch (Exception $e) {
            logError("UserModel::addRecoveryEmail - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a recovery email from user account
     * @param int $userId
     * @param string $email
     * @return bool
     */
    public function removeRecoveryEmail(int $userId, string $email): bool {
        try {
            $email = strtolower(trim($email));

            // Soft delete the email record
            $stmt = $this->mysqli->prepare("
                UPDATE user_recovery_emails 
                SET deleted_at = NOW() 
                WHERE user_id = ? AND email = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) {
                logError("UserModel::removeRecoveryEmail - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('is', $userId, $email);
            
            if (!$stmt->execute()) {
                logError("UserModel::removeRecoveryEmail - Execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected === 0) {
                logError("UserModel::removeRecoveryEmail - Email not found: $email for user $userId");
                return false;
            }

            return true;

        } catch (Exception $e) {
            logError("UserModel::removeRecoveryEmail - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set a recovery email as primary
     * @param int $userId
     * @param string $email
     * @return bool
     */
    public function setPrimaryRecoveryEmail(int $userId, string $email): bool {
        try {
            $email = strtolower(trim($email));

            // Verify email exists and is owned by user
            $stmt = $this->mysqli->prepare("
                SELECT id FROM user_recovery_emails 
                WHERE user_id = ? AND email = ? AND verified = 1 AND deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) {
                logError("UserModel::setPrimaryRecoveryEmail - Verify prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('is', $userId, $email);
            
            if (!$stmt->execute()) {
                logError("UserModel::setPrimaryRecoveryEmail - Verify execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                logError("UserModel::setPrimaryRecoveryEmail - Email not found or not verified: $email");
                $stmt->close();
                return false;
            }
            $stmt->close();

            // Remove primary flag from all emails
            $stmt = $this->mysqli->prepare("
                UPDATE user_recovery_emails 
                SET is_primary = 0 
                WHERE user_id = ? AND deleted_at IS NULL
            ");
            
            if (!$stmt) {
                logError("UserModel::setPrimaryRecoveryEmail - Clear primary prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            // Set new primary
            $stmt = $this->mysqli->prepare("
                UPDATE user_recovery_emails 
                SET is_primary = 1 
                WHERE user_id = ? AND email = ? AND deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) {
                logError("UserModel::setPrimaryRecoveryEmail - Set primary prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('is', $userId, $email);
            
            if (!$stmt->execute()) {
                logError("UserModel::setPrimaryRecoveryEmail - Set primary execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $stmt->close();
            return true;

        } catch (Exception $e) {
            logError("UserModel::setPrimaryRecoveryEmail - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify a recovery email with token
     * @param int $userId
     * @param string $email
     * @param string $token
     * @return bool
     */
    public function verifyRecoveryEmail(int $userId, string $email, string $token): bool {
        try {
            $email = strtolower(trim($email));
            $token = trim($token);

            $stmt = $this->mysqli->prepare("
                UPDATE user_recovery_emails 
                SET verified = 1, verified_at = NOW() 
                WHERE user_id = ? AND email = ? AND verification_token = ? AND verified = 0 AND deleted_at IS NULL
                LIMIT 1
            ");
            
            if (!$stmt) {
                logError("UserModel::verifyRecoveryEmail - Prepare failed: " . $this->mysqli->error);
                return false;
            }

            $stmt->bind_param('iss', $userId, $email, $token);
            
            if (!$stmt->execute()) {
                logError("UserModel::verifyRecoveryEmail - Execute failed: " . $this->mysqli->error);
                $stmt->close();
                return false;
            }

            $affected = $stmt->affected_rows;
            $stmt->close();

            if ($affected === 0) {
                logError("UserModel::verifyRecoveryEmail - Verification failed: invalid token or email");
                return false;
            }

            return true;

        } catch (Exception $e) {
            logError("UserModel::verifyRecoveryEmail - Exception: " . $e->getMessage());
            return false;
        }
    }

}

