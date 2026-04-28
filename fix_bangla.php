<?php
$file = 'app/Views/public/home.twig';
$data = file_get_contents($file);

// These are the UTF-8 byte sequences that got saved as Latin-1
// We need to fix the encoding by reading them properly and rewriting
$fixes = array(
    // Corrupted: "বাংলা তারিখ ও সময়" saved wrong
    'à¦¬à¦¾à¦‚à¦²à¦¾ à¦¤à¦¾à¦°à¦¿à¦– à¦" à¦¸à¦®à§Ÿ' => 'বাংলা তারিখ ও সময়',
    'à¦¬à¦¾à¦‚à¦²à¦¾ à¦¤à¦¾à¦°à¦¿à¦–' => 'বাংলা তারিখ',
    'à¦¬à¦¾à¦‚à¦²à¦¾ à¦¸à¦®à§Ÿ' => 'বাংলা সময়',
    'à¦²à§‹à¦¡ à¦¹à¦šà§à¦›à§‡' => 'লোড হচ্ছে',
);

foreach ($fixes as $corrupted => $proper) {
    $data = str_replace($corrupted, $proper, $data);
    echo "Replaced: " . strlen($corrupted) . " bytes\n";
}

file_put_contents($file, $data);
echo "✅ Fixed Bangla in home.twig\n";
