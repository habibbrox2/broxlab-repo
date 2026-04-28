<?php
$file = 'app/Views/public/home.twig';
$data = file_get_contents($file);

// Fix remaining corrupted sequences
$fixes = array(
    'à¦" à¦¸à¦®à§Ÿ' => 'ও সময়',
    'à¦²à§‹à¦¡ à¦¹à¦šà§à¦›à§‡' => 'লোড হচ্ছে',
    'à§³' => '৳',
    'â€¦' => '…',
);

foreach ($fixes as $corrupted => $proper) {
    $count = substr_count($data, $corrupted);
    $data = str_replace($corrupted, $proper, $data);
    if ($count > 0) echo "Replaced: $count instances\n";
}

file_put_contents($file, $data);
echo "✅ Fixed all remaining sequences\n";
