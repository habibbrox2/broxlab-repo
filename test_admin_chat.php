<?php

require_once __DIR__ . '/Config/Db.php';
require_once __DIR__ . '/Config/Functions.php';

// Simulate admin session
$_SESSION['user_id'] = 1; // Simulate admin user
$_SESSION['user_role'] = 'admin';

// Test admin AI chat
$input = [
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, can you help me?']
    ],
    'stream' => false
];

require_once __DIR__ . '/app/Controllers/AISystemController.php';

// Call the admin chat handler
aiChatHandleRequest($input, $GLOBALS['mysqli'], true, true);
