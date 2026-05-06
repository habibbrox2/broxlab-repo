<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to capture any output
ob_start();

// Start session first
session_start();

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['logged_in'] = true;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = 'test_csrf_token';

// Simulate the admin AI chat request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/admin/ai/chat';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simulate JSON input
$jsonInput = '{"messages":[{"role":"user","content":"Hello, can you help me with admin tasks?"}]}';

// Set a global variable for testing
$_TEST_INPUT = json_decode($jsonInput, true);

// Bootstrap the application
require_once __DIR__ . '/public_html/index.php';

// Get the output
$output = ob_get_clean();
echo "Test completed. Output:\n" . $output . "\n";
