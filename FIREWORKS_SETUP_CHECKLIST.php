#!/usr/bin/env php
<?php
/**
 * Fireworks AI Setup Checklist
 * Interactive checklist to guide through setup
 */

// Colors for terminal output
const RESET = "\033[0m";
const GREEN = "\033[92m";
const RED = "\033[91m";
const YELLOW = "\033[93m";
const BLUE = "\033[94m";
const BOLD = "\033[1m";

echo BOLD . BLUE . "╔════════════════════════════════════════════════════╗\n" . RESET;
echo BOLD . BLUE . "║   Fireworks AI Integration Setup Checklist        ║\n" . RESET;
echo BOLD . BLUE . "╚════════════════════════════════════════════════════╝\n" . RESET;
echo "\n";

$steps = [
    [
        'name' => 'Verify Fireworks AI Provider Configuration',
        'description' => 'Ensure Fireworks AI is properly configured in the system',
        'verification' => [
            'command' => 'Check database for Fireworks AI provider',
            'expected' => 'Provider found in database'
        ],
        'documentation' => 'FIREWORKS_VERIFICATION_REPORT.md'
    ],
    [
        'name' => 'Activate Fireworks AI Provider',
        'description' => 'Enable Fireworks AI in the admin panel',
        'steps' => [
            '1. Open http://localhost/admin/ai-settings',
            '2. Find "Fireworks AI" in the AI Providers table',
            '3. Toggle the Status switch to "Active"',
            '4. Verify the status shows "✓ Active" in green'
        ],
        'verification' => [
            'indicator' => 'Status toggle shows green checkmark',
            'expectedTime' => '1 minute'
        ],
        'screenshot' => 'Admin panel → AI Settings → Providers table'
    ],
    [
        'name' => 'Verify API Key',
        'description' => 'Confirm Fireworks AI API key is configured',
        'steps' => [
            '1. In the AI Settings page, scroll down to "API Keys" section',
            '2. Look for "Fireworks AI API Key" field',
            '3. You should see "●●●●...●●●●" (masked key)',
            '4. If empty, paste your API key and click "Save Settings"'
        ],
        'verification' => [
            'indicator' => 'Masked API key displayed',
            'expectedTime' => '2 minutes'
        ],
        'getApiKey' => 'https://console.fireworks.ai → API Keys section'
    ],
    [
        'name' => 'Test Connection to Fireworks AI',
        'description' => 'Verify the connection to Fireworks API is working',
        'steps' => [
            '1. Go back to AI Providers table at /admin/ai-settings',
            '2. Find the Fireworks AI row',
            '3. Click the "Test" button',
            '4. In the modal, select a model (start with "llama-v3-8b-instruct")',
            '5. Click "Run Test" button',
            '6. Wait 5-10 seconds for the result'
        ],
        'verification' => [
            'success' => 'Green message: "Connection successful!"',
            'failure' => '"Model not found" or other error'
        ],
        'expectedTime' => '5-10 minutes',
        'troubleshooting' => 'If 404 error appears, check Fireworks account for model availability'
    ],
    [
        'name' => 'Configure Default Provider (Optional)',
        'description' => 'Set Fireworks AI as your default provider',
        'steps' => [
            '1. Go to /admin/ai-settings → General Settings section',
            '2. Set "Frontend AI (Assistant)" to desired provider',
            '3. Set "Backend AI Provider (AutoContent)" to desired provider',
            '4. Choose your preferred "Default Model"',
            '5. Click "Save Settings" button'
        ],
        'options' => [
            '- Frontend: Puter.js (default), Fireworks, OpenRouter, etc.',
            '- Backend: Fireworks, OpenRouter, Kilo.ai, etc.',
            'Recommended: Frontend=Puter.js, Backend=Fireworks'
        ],
        'expectedTime' => '2-3 minutes'
    ],
    [
        'name' => 'Run Integration Test Script',
        'description' => 'Automated verification of the entire setup',
        'command' => 'php scripts/test-fireworks-integration.php',
        'expectedOutput' => [
            '✓ Database connected successfully',
            '✓ Fireworks AI config found',
            '✓ Fireworks provider found in database',
            '✓ API Key found in database',
            '✓ Connection Test: Passed (if active and models available)'
        ],
        'location' => 'Run from within project root directory',
        'expectedTime' => '1 minute'
    ],
    [
        'name' => 'Start Using Fireworks AI',
        'description' => 'Begin generating content with Fireworks AI',
        'features' => [
            '✓ Website Assistant Chat - Uses Fireworks AI if configured',
            '✓ AutoContent Generation - For scraping & content creation',
            '✓ Admin Tasks - Any task using configured default provider'
        ],
        'howTo' => [
            'For Assistant: Chat will use selected frontend provider',
            'For AutoContent: Generation uses backend provider',
            'For Admin: Use configured default or switch per-request'
        ]
    ]
];

echo BOLD . "Setup Checklist:" . RESET . " " . count($steps) . " steps\n\n";

$completed = 0;
foreach ($steps as $index => $step) {
    $num = $index + 1;
    echo BOLD . "[$num/" . count($steps) . "] {$step['name']}" . RESET . "\n";
    echo "     {$step['description']}\n\n";

    // Show steps
    if (isset($step['steps'])) {
        echo "     " . YELLOW . "Steps:" . RESET . "\n";
        foreach ($step['steps'] as $s) {
            echo "       " . $s . "\n";
        }
        echo "\n";
    }

    // Show command
    if (isset($step['command'])) {
        echo "     " . YELLOW . "Command:" . RESET . "\n";
        echo "       " . BLUE . $step['command'] . RESET . "\n\n";
    }

    // Show options
    if (isset($step['options'])) {
        echo "     " . YELLOW . "Options:" . RESET . "\n";
        foreach ($step['options'] as $opt) {
            echo "       " . $opt . "\n";
        }
        echo "\n";
    }

    // Show expected output
    if (isset($step['expectedOutput'])) {
        echo "     " . YELLOW . "Expected Output:" . RESET . "\n";
        foreach ($step['expectedOutput'] as $output) {
            echo "       " . GREEN . $output . RESET . "\n";
        }
        echo "\n";
    }

    // Show verification
    if (isset($step['verification'])) {
        echo "     " . YELLOW . "Verification:" . RESET . "\n";
        foreach ($step['verification'] as $key => $value) {
            if (is_array($value)) {
                echo "       " . ucfirst($key) . ":\n";
                foreach ($value as $v) {
                    echo "         - " . $v . "\n";
                }
            } else {
                echo "       " . ucfirst($key) . ": " . $value . "\n";
            }
        }
        echo "\n";
    }

    // Show expected time
    if (isset($step['expectedTime'])) {
        echo "     " . YELLOW . "Expected Time:" . RESET . " " . $step['expectedTime'] . "\n\n";
    }

    // Show documentation
    if (isset($step['documentation'])) {
        echo "     " . BLUE . "📖 Documentation: " . $step['documentation'] . RESET . "\n\n";
    }

    // Show API key info
    if (isset($step['getApiKey'])) {
        echo "     " . BLUE . "🔑 Get API Key: " . $step['getApiKey'] . RESET . "\n\n";
    }

    // Show troubleshooting
    if (isset($step['troubleshooting'])) {
        echo "     " . YELLOW . "⚠️  Troubleshooting: " . $step['troubleshooting'] . RESET . "\n\n";
    }

    echo "\n";
}

echo BOLD . BLUE . "═════════════════════════════════════════════════════" . RESET . "\n\n";

echo "📊 Summary:\n";
echo "  Total Steps: " . BOLD . count($steps) . RESET . "\n";
echo "  Estimated Time: " . BOLD . "15-20 minutes" . RESET . "\n";
echo "  Difficulty: " . BOLD . "Easy" . RESET . "\n\n";

echo "✨ After completing all steps:\n";
echo "  1. Fireworks AI will be fully operational\n";
echo "  2. You can generate content using Fireworks models\n";
echo "  3. Website assistant can use Fireworks AI\n";
echo "  4. AutoContent can use Fireworks for generation\n";
echo "  5. All features will automatically use your configured provider\n\n";

echo "📚 Additional Resources:\n";
echo "  • Setup Guide: FIREWORKS_AI_SETUP.md\n";
echo "  • Verification Report: FIREWORKS_VERIFICATION_REPORT.md\n";
echo "  • Test Script: php scripts/test-fireworks-integration.php\n";
echo "  • Admin Panel: http://localhost/admin/ai-settings\n";
echo "  • Fireworks Docs: https://docs.fireworks.ai\n\n";

echo GREEN . "✓ Setup Checklist Complete!" . RESET . "\n";
echo "  Follow the steps above and you'll be ready to go!\n\n";
?>
