<?php
$data = file_get_contents('app/Views/public/home.twig');

// Find lines with corrupted Bangla
$lines = explode("\n", $data);
foreach ($lines as $i => $line) {
    if (strpos($line, 'à¦') !== false && $i >= 174 && $i <= 200) {
        echo "Line " . ($i + 1) . ":\n";
        echo "Hex: " . bin2hex(substr($line, 0, 200)) . "\n";
        echo "Text: " . trim($line) . "\n\n";
    }
}
