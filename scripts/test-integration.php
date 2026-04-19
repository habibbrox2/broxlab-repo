<?php
/**
 * Test AI and MCP integration
 * This script tests that the PHP app can interact with the Node.js server
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'tdhuedhn_broxbhai');
define('DB_PASS', ',EnTio1PtqI-&M&D');
define('DB_NAME', 'tdhuedhn_broxbhai');

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');

// Test 1: Check if columns exist
echo "=== Test 1: Checking table structure ===\n";
$result = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'ai_provider'");
echo "ai_provider column exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "\n";

$result = $mysqli->query("SHOW COLUMNS FROM app_settings LIKE 'mcp_server_url'");
echo "mcp_server_url column exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "\n";

// Test 2: Read current settings
echo "\n=== Test 2: Reading current settings ===\n";
$result = $mysqli->query("SELECT ai_provider, ai_api_key, ai_model, mcp_server_url, mcp_api_key FROM app_settings LIMIT 1");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Current AI Provider: " . ($row['ai_provider'] ?? 'NULL') . "\n";
    echo "Current AI Model: " . ($row['ai_model'] ?? 'NULL') . "\n";
    echo "Current MCP Server URL: " . ($row['mcp_server_url'] ?? 'NULL') . "\n";
} else {
    echo "Error reading settings: " . $mysqli->error . "\n";
}

// Test 3: Test Node.js API endpoints
echo "\n=== Test 3: Testing Node.js API ===\n";

// Test GET /admin/settings/ai
$ch = curl_init('http://localhost:3000/admin/settings/ai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer test-token',
    'X-Admin-ID: 1'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET /admin/settings/ai - HTTP $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "  Provider: " . ($data['provider'] ?? 'NULL') . "\n";
    echo "  Model: " . ($data['model'] ?? 'NULL') . "\n";
    echo "  API Key length: " . (isset($data['apiKey']) ? strlen($data['apiKey']) : 0) . " chars\n";
} else {
    echo "  Response: $response\n";
}

// Test POST /admin/settings/ai with Ollama
echo "\nTest 4: Saving Ollama settings via API\n";
$ch = curl_init('http://localhost:3000/admin/settings/ai');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer test-token',
    'X-Admin-ID: 1',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'provider' => 'ollama',
    'apiKey' => 'test-key-123',
    'model' => 'llama2'
]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST /admin/settings/ai - HTTP $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "  Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "  Message: " . ($data['message'] ?? 'N/A') . "\n";
} else {
    echo "  Response: $response\n";
}

// Test GET /admin/settings/mcp
echo "\nTest 5: GET /admin/settings/mcp\n";
$ch = curl_init('http://localhost:3000/admin/settings/mcp');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer test-token',
    'X-Admin-ID: 1'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "GET /admin/settings/mcp - HTTP $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "  Server URL: " . ($data['serverUrl'] ?? 'NULL') . "\n";
    echo "  API Key length: " . (isset($data['apiKey']) ? strlen($data['apiKey']) : 0) . " chars\n";
} else {
    echo "  Response: $response\n";
}

// Test POST /admin/settings/mcp
echo "\nTest 6: Saving MCP settings via API\n";
$ch = curl_init('http://localhost:3000/admin/settings/mcp');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer test-token',
    'X-Admin-ID: 1',
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'serverUrl' => 'http://localhost:8080',
    'apiKey' => 'mcp-secret-key-456'
]));
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST /admin/settings/mcp - HTTP $httpCode\n";
if ($httpCode === 200) {
    $data = json_decode($response, true);
    echo "  Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
    echo "  Message: " . ($data['message'] ?? 'N/A') . "\n";
} else {
    echo "  Response: $response\n";
}

echo "\n=== All tests completed ===\n";