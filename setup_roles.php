<?php

require_once __DIR__ . '/Config/Db.php';

global $mysqli;

if (!$mysqli) {
    die("Database connection failed\n");
}

echo "Setting up roles...\n";

// Insert default roles
$roles = [
    ['name' => 'Super Admin', 'ranking' => 100, 'description' => 'Full system access', 'is_super_admin' => 1],
    ['name' => 'Admin', 'ranking' => 50, 'description' => 'Administrative access', 'is_super_admin' => 0],
    ['name' => 'Moderator', 'ranking' => 25, 'description' => 'Content moderation', 'is_super_admin' => 0],
    ['name' => 'User', 'ranking' => 1, 'description' => 'Regular user', 'is_super_admin' => 0],
];

foreach ($roles as $role) {
    $stmt = $mysqli->prepare("INSERT IGNORE INTO roles (name, ranking, description, is_super_admin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sisi', $role['name'], $role['ranking'], $role['description'], $role['is_super_admin']);
    $stmt->execute();
    echo "Inserted role: {$role['name']}\n";
}

echo "Setting up user roles...\n";

// Assign super admin role to user ID 1
$stmt = $mysqli->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) SELECT 1, id FROM roles WHERE name = 'Super Admin'");
$stmt->execute();
echo "Assigned Super Admin role to user ID 1\n";

echo "Roles setup complete!\n";
