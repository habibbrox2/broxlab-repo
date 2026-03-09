<?php
/**
 * Fireworks AI Integration Test Script
 * Tests the connection and configuration of Fireworks AI provider
 */

// Set up logging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Setup paths
$rootDir = dirname(__DIR__);
require_once $rootDir . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable($rootDir);
$dotenv->safeLoad();

// Get database config from environment
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPass = $_ENV['DB_PASS'] ?? '';

require_once $rootDir . '/app/Models/AIProvider.php';

echo "==============================================\n";
echo "Fireworks AI Integration Test\n";
echo "==============================================\n\n";

try {
    // 1. Database Connection
    echo "1. Testing Database Connection...\n";
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error);
    }
    echo "   ✓ Database connected successfully\n\n";

    // 2. Initialize AIProvider Model
    echo "2. Initializing AIProvider Model...\n";
    $aiProvider = new AIProvider($mysqli);
    echo "   ✓ AIProvider model initialized\n\n";

    // 3. Check Fireworks Provider Configuration
    echo "3. Checking Fireworks AI Configuration...\n";
    $config = AIProvider::getProviderConfig('fireworks');
    if ($config) {
        echo "   ✓ Fireworks AI config found:\n";
        echo "     - Name: " . $config['name'] . "\n";
        echo "     - Endpoint: " . $config['endpoint'] . "\n";
        echo "     - Auth Type: " . $config['auth_type'] . "\n";
        echo "     - Supports Streaming: " . ($config['supports_streaming'] ? 'Yes' : 'No') . "\n";
        echo "     - Models Available: " . count($config['models']) . "\n";
    } else {
        throw new Exception("Fireworks AI configuration not found");
    }
    echo "\n";

    // 4. Check Fireworks Provider in Database
    echo "4. Checking Fireworks Provider in Database...\n";
    $provider = $aiProvider->getByName('fireworks');
    if ($provider) {
        echo "   ✓ Fireworks provider found in database:\n";
        echo "     - ID: " . $provider['id'] . "\n";
        echo "     - Display Name: " . $provider['display_name'] . "\n";
        echo "     - Status: " . ($provider['is_active'] ? 'Active' : 'Inactive') . "\n";
        echo "     - Default: " . ($provider['is_default'] ? 'Yes' : 'No') . "\n";
        echo "     - API Endpoint: " . $provider['api_endpoint'] . "\n";
    } else {
        echo "   ⚠ Fireworks provider not yet in database (will be added when first accessed)\n";
    }
    echo "\n";

    // 5. Check API Key
    echo "5. Checking Fireworks API Key...\n";
    $apiKey = $aiProvider->getSetting('fireworks_api_key', '');
    $envKey = getenv('FIREWORKS_API_KEY');
    
    if ($apiKey) {
        $masked = substr($apiKey, 0, 4) . '...' . substr($apiKey, -4);
        echo "   ✓ API Key found in database: " . $masked . "\n";
    } elseif ($envKey) {
        $masked = substr($envKey, 0, 4) . '...' . substr($envKey, -4);
        echo "   ✓ API Key found in environment: " . $masked . "\n";
    } else {
        echo "   ✗ No API Key configured\n";
        echo "     Please configure the API key through:\n";
        echo "     - Admin Panel: /admin/ai-settings\n";
        echo "     - Environment Variable: FIREWORKS_API_KEY\n";
    }
    echo "\n";

    // 6. Test Connection
    echo "6. Testing Fireworks AI Connection...\n";
    
    if (!$apiKey && !$envKey) {
        echo "   ⚠ Skipping connection test (no API key configured)\n";
    } else {
        $connected = false;
        foreach ($config['models'] as $modelKey => $label) {
            echo "   Trying model: " . $modelKey . "\n";
            $result = $aiProvider->testConnection('fireworks', $modelKey);
            if ($result['success']) {
                echo "   ✓ Connection successful with model {$modelKey}!\n";
                echo "     - Response: " . $result['response'] . "\n";
                if (isset($result['usage'])) {
                    echo "     - Usage: " . json_encode($result['usage']) . "\n";
                }
                $connected = true;
                break;
            } else {
                echo "     ✗ Error: " . $result['error'] . "\n";
                if (strpos($result['error'], 'NOT_FOUND') !== false) {
                    echo "       -> Model not deployed or inaccessible.\n";
                }
            }
        }
        if (!$connected) {
            echo "   ✗ All configured models failed.\n";
            echo "     Please deploy at least one Fireworks model and update settings.\n";
        }
    }
    echo "\n";

    // 7. Display Available Models
    echo "7. Available Fireworks AI Models:\n";
    $models = $config['models'];
    $count = 0;
    foreach ($models as $modelId => $modelName) {
        $count++;
        if ($count <= 5 || $count > count($models) - 2) {
            echo "   - " . $modelId . ": " . $modelName . "\n";
        } elseif ($count === 6) {
            echo "   ... (" . (count($models) - 7) . " more models) ...\n";
        }
    }
    echo "   Total: " . count($models) . " models\n\n";

    // 8. Summary
    echo "==============================================\n";
    echo "Integration Status Summary:\n";
    echo "==============================================\n";
    echo "✓ Fireworks AI Support: Yes\n";
    echo ($provider ? "✓" : "⚠") . " Provider in Database: " . ($provider ? 'Yes' : 'Pending') . "\n";
    echo ($provider && $provider['is_active'] ? "✓" : "✗") . " Provider Active: " . ($provider && $provider['is_active'] ? 'Yes' : 'No') . "\n";
    echo ($apiKey || $envKey ? "✓" : "✗") . " API Key Configured: " . ($apiKey || $envKey ? 'Yes' : 'No') . "\n";
    
    if ($apiKey || $envKey) {
        if ($result['success'] ?? false) {
            echo "✓ Connection Test: Passed\n";
        } else {
            echo "✗ Connection Test: Failed\n";
            echo "   Error: " . ($result['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "⚠ Connection Test: Skipped (no API key)\n";
    }
    
    echo "\n";
    
    // 9. Next Steps
    echo "Next Steps:\n";
    if (!($apiKey || $envKey)) {
        echo "1. Set your Fireworks API key\n";
        echo "   - Go to /admin/ai-settings\n";
        echo "   - Enter API key under 'Fireworks AI API Key'\n";
        echo "   - Or set FIREWORKS_API_KEY in .env file\n";
    }
    echo "2. Set Fireworks as default provider if desired\n";
    echo "3. Use in frontend: /admin/ai-settings (Frontend AI Provider)\n";
    echo "4. Use in AutoContent: /admin/ai-settings (Backend AI Provider)\n";
    echo "\nDocumentation: https://docs.fireworks.ai/getting-started/quickstart\n";
    echo "\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Test completed successfully!\n";
exit(0);
?>
