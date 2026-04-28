# Fix corrupted Bangla in home.twig
$file = 'app\Views\public\home.twig'
[byte[]]$bytes = [IO.File]::ReadAllBytes($file)
$text = [System.Text.Encoding]::UTF8.GetString($bytes)

# Replace corrupted sequences
$text = $text.Replace('à¦¬à¦¾à¦‚à¦²à¦¾ à¦¤à¦¾à¦°à¦¿à¦– à¦" à¦¸à¦®à§Ÿ', 'বাংলা তারিখ ও সময়')
$text = $text.Replace('à¦¬à¦¾à¦‚à¦²à¦¾ à¦¤à¦¾à¦°à¦¿à¦–', 'বাংলা তারিখ')
$text = $text.Replace('à¦¬à¦¾à¦‚à¦²à¦¾ à¦¸à¦®à§Ÿ', 'বাংলা সময়')
$text = $text.Replace('à¦²à§‹à¦¡ à¦¹à¦šà§à¦›à§‡', 'লোড হচ্ছে')

# Write as UTF-8 without BOM
$utf8NoBOM = New-Object System.Text.UTF8Encoding($false)
[IO.File]::WriteAllText($file, $text, $utf8NoBOM)

Write-Host "✅ Fixed Bangla in $file"
