<?php
$data = file_get_contents('app/Views/public/home.twig');

$hasLoading = strpos($data, 'লোড হচ্ছে') !== false;
$hasBanglaWithTime = strpos($data, 'বাংলা তারিখ ও সময়') !== false;
$hasCorrupted1 = strpos($data, 'à¦²à§‹à¦¡') !== false;
$hasCorrupted2 = strpos($data, 'à¦') !== false;

echo "লোড হচ্ছে present: " . ($hasLoading ? "YES" : "NO") . "\n";
echo "বাংলা তারিখ ও সময় present: " . ($hasBanglaWithTime ? "YES" : "NO") . "\n";
echo "Corrupted à¦²à§‹à¦¡ present: " . ($hasCorrupted1 ? "YES" : "NO") . "\n";
echo "Any corrupted à¦ present: " . ($hasCorrupted2 ? "YES" : "NO") . "\n";
