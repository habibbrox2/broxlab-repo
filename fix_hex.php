<?php
$file = 'app/Views/public/home.twig';
$data = file_get_contents($file);

// Map of hex byte patterns to replacement
$fixes = array(
    // Line 178: c3a0c2a6e2809c20c3a0c2a6c2b8c3a0c2a6c2aec3a0c2a7c5b8 => "ও সময়"
    "\xc3\xa0\xc2\xa6\xe2\x80\x9c \xc3\xa0\xc2\xa6\xc2\xb8\xc3\xa0\xc2\xa6\xc2\xae\xc3\xa0\xc2\xa7\xc5\xb8" => "ও সময়",

    // Line 188 & 198: c3a0c2a6c2b2c3a0c2a7e280b9c3a0c2a6c2a120c3a0c2a6c2b9c3a0c2a6c5a1c3a0c2a7c28dc3a0c2a6e280bac3a0c2a7e280a1 => "লোড হচ্ছে"
    "\xc3\xa0\xc2\xa6\xc2\xb2\xc3\xa0\xc2\xa7\xe2\x80\xb9\xc3\xa0\xc2\xa6\xc2\xa1 \xc3\xa0\xc2\xa6\xc2\xb9\xc3\xa0\xc2\xa6\xc5\xa1\xc3\xa0\xc2\xa7\xc2\x8d\xc3\xa0\xc2\xa6\xe2\x80\xba\xc3\xa0\xc2\xa7\xe2\x80\xa1" => "লোড হচ্ছে",
);

foreach ($fixes as $corrupted => $proper) {
    $count = substr_count($data, $corrupted);
    $data = str_replace($corrupted, $proper, $data);
    echo "Replaced: $count instances\n";
}

file_put_contents($file, $data);
echo "✅ Fixed corrupted Bangla\n";
