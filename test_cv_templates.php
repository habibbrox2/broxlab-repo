<?php
require_once __DIR__ . '/app/Helpers/CvTemplateHelper.php';
require_once __DIR__ . '/app/Controllers/CvController.php';

echo "Testing CvTemplateHelper functions...\n";

// Test getting all templates
$templates = cvTemplateGetAll();
echo "Found " . count($templates) . " templates\n";

// Test allowlist
$allowlist = cvGetTemplateAllowlist();
echo "Allowlist: " . implode(', ', $allowlist) . "\n";

echo "Tests completed.\n";
