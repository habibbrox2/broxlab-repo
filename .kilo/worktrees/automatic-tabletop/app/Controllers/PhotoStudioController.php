<?php

/** @var Router $router */
/** @var \mysqli $mysqli */
/** @var \Twig\Environment $twig */
global $mysqli, $twig;

if (!function_exists('studioEnsureDirectory')) {
    function studioEnsureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}

if (!function_exists('studioCreateImageFromBinary')) {
    /**
     * @return array{0:GdImage|resource,1:string}|null
     */
    function studioCreateImageFromBinary(string $binary): ?array
    {
        $info = @getimagesizefromstring($binary);
        $image = @imagecreatefromstring($binary);

        if ($info === false || $image === false) {
            return null;
        }

        return [$image, (string)($info['mime'] ?? 'image/png')];
    }
}

if (!function_exists('studioSavePng')) {
    /**
     * @param GdImage|resource $image
     */
    function studioSavePng($image, string $path): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return imagepng($image, $path, 6);
    }
}

if (!function_exists('studioPresetPixels')) {
    function studioPresetPixels(float $millimeters, int $dpi = 300): int
    {
        return max(1, (int)round(($millimeters / 25.4) * $dpi));
    }
}

if (!function_exists('studioPhotoPresets')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function studioPhotoPresets(): array
    {
        $presets = [
            'bd_passport' => [
                'label' => 'Bangladesh Passport',
                'category' => 'Bangladesh',
                'width_mm' => 35,
                'height_mm' => 45,
                'dpi' => 300,
                'description' => 'Neutral light background and centered face for passport use.',
                'safe_area' => ['left' => 0.12, 'right' => 0.12, 'top' => 0.16, 'bottom' => 0.12],
                'head_box' => ['x' => 0.26, 'y' => 0.16, 'width' => 0.48, 'height' => 0.58],
                'background_default' => '#ffffff',
            ],
            'bd_visa' => [
                'label' => 'Bangladesh Visa / Embassy',
                'category' => 'Bangladesh',
                'width_mm' => 40,
                'height_mm' => 50,
                'dpi' => 300,
                'description' => 'Balanced embassy-style crop with extra top margin.',
                'safe_area' => ['left' => 0.1, 'right' => 0.1, 'top' => 0.14, 'bottom' => 0.1],
                'head_box' => ['x' => 0.24, 'y' => 0.14, 'width' => 0.52, 'height' => 0.6],
                'background_default' => '#f8fafc',
            ],
            'bd_job_exam' => [
                'label' => 'Bangladesh Job / Exam',
                'category' => 'Bangladesh',
                'width_mm' => 40,
                'height_mm' => 50,
                'dpi' => 300,
                'description' => 'Common local form photo with slightly taller head framing.',
                'safe_area' => ['left' => 0.11, 'right' => 0.11, 'top' => 0.14, 'bottom' => 0.1],
                'head_box' => ['x' => 0.23, 'y' => 0.13, 'width' => 0.54, 'height' => 0.62],
                'background_default' => '#ffffff',
            ],
            'global_2x2' => [
                'label' => '2 x 2 inch',
                'category' => 'Global',
                'width_mm' => 50.8,
                'height_mm' => 50.8,
                'dpi' => 300,
                'description' => 'Square format for US and many global visa applications.',
                'safe_area' => ['left' => 0.1, 'right' => 0.1, 'top' => 0.12, 'bottom' => 0.1],
                'head_box' => ['x' => 0.21, 'y' => 0.12, 'width' => 0.58, 'height' => 0.64],
                'background_default' => '#ffffff',
            ],
            'global_35x45' => [
                'label' => '35 x 45 mm',
                'category' => 'Global',
                'width_mm' => 35,
                'height_mm' => 45,
                'dpi' => 300,
                'description' => 'Widely accepted Schengen and general visa photo size.',
                'safe_area' => ['left' => 0.12, 'right' => 0.12, 'top' => 0.15, 'bottom' => 0.12],
                'head_box' => ['x' => 0.25, 'y' => 0.14, 'width' => 0.5, 'height' => 0.61],
                'background_default' => '#ffffff',
            ],
            'global_50x50' => [
                'label' => '50 x 50 mm',
                'category' => 'Global',
                'width_mm' => 50,
                'height_mm' => 50,
                'dpi' => 300,
                'description' => 'Square ID photo with broader composition room.',
                'safe_area' => ['left' => 0.09, 'right' => 0.09, 'top' => 0.11, 'bottom' => 0.1],
                'head_box' => ['x' => 0.2, 'y' => 0.12, 'width' => 0.6, 'height' => 0.64],
                'background_default' => '#ffffff',
            ],
        ];

        foreach ($presets as $id => $preset) {
            $presets[$id]['id'] = $id;
            $presets[$id]['output_width'] = studioPresetPixels((float)$preset['width_mm'], (int)$preset['dpi']);
            $presets[$id]['output_height'] = studioPresetPixels((float)$preset['height_mm'], (int)$preset['dpi']);
        }

        return $presets;
    }
}

if (!function_exists('studioBackgroundPresets')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function studioBackgroundPresets(): array
    {
        return [
            'white' => ['label' => 'Clean White', 'mode' => 'color', 'value' => '#ffffff'],
            'soft_gray' => ['label' => 'Soft Gray', 'mode' => 'color', 'value' => '#e5e7eb'],
            'cool_blue' => ['label' => 'Cool Blue', 'mode' => 'gradient', 'value' => ['#eff6ff', '#bfdbfe']],
            'mint' => ['label' => 'Mint Studio', 'mode' => 'gradient', 'value' => ['#ecfeff', '#99f6e4']],
            'slate' => ['label' => 'Slate Studio', 'mode' => 'gradient', 'value' => ['#f8fafc', '#cbd5e1']],
        ];
    }
}

if (!function_exists('studioPageSizes')) {
    /**
     * @return array<string,array<string,string>>
     */
    function studioPageSizes(): array
    {
        return [
            'A4' => ['label' => 'A4', 'css' => '210mm 297mm'],
            'Letter' => ['label' => 'Letter', 'css' => '216mm 279mm'],
            'Legal' => ['label' => 'Legal', 'css' => '216mm 356mm'],
            'A5' => ['label' => 'A5', 'css' => '148mm 210mm'],
            'Tabloid' => ['label' => 'Tabloid', 'css' => '279mm 432mm'],
        ];
    }
}

if (!function_exists('studioPaperCssSize')) {
    function studioPaperCssSize(string $pageSize, string $orientation): string
    {
        $sizes = studioPageSizes();
        $base = $sizes[$pageSize]['css'] ?? $sizes['A4']['css'];
        return $base . ' ' . ($orientation === 'landscape' ? 'landscape' : 'portrait');
    }
}

if (!function_exists('studioResolvePreset')) {
    /**
     * @return array<string,mixed>
     */
    function studioResolvePreset(?string $presetId, ?string $fallbackSize = null): array
    {
        $presets = studioPhotoPresets();
        if ($presetId !== null && isset($presets[$presetId])) {
            return $presets[$presetId];
        }

        if ($fallbackSize) {
            [$width, $height] = array_pad(array_map('floatval', explode('x', $fallbackSize, 2)), 2, 35.0);
            return [
                'id' => 'custom',
                'label' => $fallbackSize . ' mm',
                'width_mm' => $width,
                'height_mm' => $height,
                'dpi' => 300,
                'output_width' => studioPresetPixels($width, 300),
                'output_height' => studioPresetPixels($height, 300),
                'safe_area' => ['left' => 0.1, 'right' => 0.1, 'top' => 0.12, 'bottom' => 0.1],
                'head_box' => ['x' => 0.22, 'y' => 0.14, 'width' => 0.56, 'height' => 0.62],
                'background_default' => '#ffffff',
                'description' => 'Custom studio size.',
            ];
        }

        return $presets['bd_passport'];
    }
}

if (!function_exists('studioColorDistance')) {
    function studioColorDistance(int $r, int $g, int $b, array $target): float
    {
        return sqrt((($r - $target[0]) ** 2) + (($g - $target[1]) ** 2) + (($b - $target[2]) ** 2));
    }
}

if (!function_exists('studioAverageBorderColor')) {
    /**
     * @param GdImage|resource $image
     * @return array{0:int,1:int,2:int}
     */
    function studioAverageBorderColor($image, int $width, int $height): array
    {
        $samples = [];
        $stepX = max(1, intdiv($width, 24));
        $stepY = max(1, intdiv($height, 24));

        for ($x = 0; $x < $width; $x += $stepX) {
            $samples[] = imagecolorat($image, $x, 0);
            $samples[] = imagecolorat($image, $x, max(0, $height - 1));
        }

        for ($y = 0; $y < $height; $y += $stepY) {
            $samples[] = imagecolorat($image, 0, $y);
            $samples[] = imagecolorat($image, max(0, $width - 1), $y);
        }

        $r = 0;
        $g = 0;
        $b = 0;
        $count = max(1, count($samples));

        foreach ($samples as $rgb) {
            $r += ($rgb >> 16) & 0xFF;
            $g += ($rgb >> 8) & 0xFF;
            $b += $rgb & 0xFF;
        }

        return [
            (int)round($r / $count),
            (int)round($g / $count),
            (int)round($b / $count),
        ];
    }
}

if (!function_exists('studioCreateVisitedMask')) {
    /**
     * @param GdImage|resource $sourceImage
     * @return array<int,array<int,bool>>
     */
    function studioCreateVisitedMask($sourceImage, int $width, int $height, array $background, float $tolerance): array
    {
        $visited = array_fill(0, $height, array_fill(0, $width, false));
        $queue = new SplQueue();

        for ($x = 0; $x < $width; $x++) {
            $queue->enqueue([$x, 0]);
            $queue->enqueue([$x, $height - 1]);
        }

        for ($y = 0; $y < $height; $y++) {
            $queue->enqueue([0, $y]);
            $queue->enqueue([$width - 1, $y]);
        }

        while (!$queue->isEmpty()) {
            [$x, $y] = $queue->dequeue();

            if ($x < 0 || $y < 0 || $x >= $width || $y >= $height || $visited[$y][$x]) {
                continue;
            }

            $rgb = imagecolorat($sourceImage, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if (studioColorDistance($r, $g, $b, $background) > $tolerance) {
                continue;
            }

            $visited[$y][$x] = true;
            $queue->enqueue([$x + 1, $y]);
            $queue->enqueue([$x - 1, $y]);
            $queue->enqueue([$x, $y + 1]);
            $queue->enqueue([$x, $y - 1]);
        }

        return $visited;
    }
}

if (!function_exists('studioEdgeDistance')) {
    /**
     * @param array<int,array<int,bool>> $visited
     */
    function studioEdgeDistance(array $visited, int $x, int $y, int $width, int $height): int
    {
        $maxRadius = 3;
        for ($radius = 1; $radius <= $maxRadius; $radius++) {
            for ($offsetY = -$radius; $offsetY <= $radius; $offsetY++) {
                for ($offsetX = -$radius; $offsetX <= $radius; $offsetX++) {
                    $nx = $x + $offsetX;
                    $ny = $y + $offsetY;
                    if ($nx < 0 || $ny < 0 || $nx >= $width || $ny >= $height) {
                        continue;
                    }
                    if ($visited[$ny][$nx]) {
                        return $radius;
                    }
                }
            }
        }

        return $maxRadius + 1;
    }
}

if (!function_exists('studioRemoveBackgroundFromBinary')) {
    /**
     * @return array{cutout:string,mask:?string,error:?string,engine:string,meta:array<string,mixed>}
     */
    function studioRemoveBackgroundFromBinary(string $binary, string $uploadDir): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return [
                'cutout' => '',
                'mask' => null,
                'error' => 'GD image support is unavailable',
                'engine' => 'unavailable',
                'meta' => [],
            ];
        }

        $created = studioCreateImageFromBinary($binary);
        if ($created === null) {
            return [
                'cutout' => '',
                'mask' => null,
                'error' => 'Unsupported image data',
                'engine' => 'invalid',
                'meta' => [],
            ];
        }

        [$sourceImage,] = $created;
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $background = studioAverageBorderColor($sourceImage, $width, $height);
        $tolerance = 58.0;
        $visited = studioCreateVisitedMask($sourceImage, $width, $height, $background, $tolerance);

        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $transparent);

        $mask = imagecreatetruecolor($width, $height);
        $maskBlack = imagecolorallocate($mask, 0, 0, 0);
        $maskWhite = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $maskBlack);

        $subjectPixels = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($visited[$y][$x]) {
                    continue;
                }

                $rgb = imagecolorat($sourceImage, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $edgeDistance = studioEdgeDistance($visited, $x, $y, $width, $height);
                $alpha = 0;
                if ($edgeDistance === 1) {
                    $alpha = 72;
                } elseif ($edgeDistance === 2) {
                    $alpha = 36;
                }

                $color = imagecolorallocatealpha($output, $r, $g, $b, $alpha);
                imagesetpixel($output, $x, $y, $color);
                imagesetpixel($mask, $x, $y, $maskWhite);
                $subjectPixels++;
            }
        }

        if ($subjectPixels < (int)max(100, ($width * $height) * 0.02)) {
            imagedestroy($sourceImage);
            imagedestroy($output);
            imagedestroy($mask);
            return [
                'cutout' => '',
                'mask' => null,
                'error' => 'No clear subject detected',
                'engine' => 'gd_refined',
                'meta' => ['tolerance' => $tolerance],
            ];
        }

        studioEnsureDirectory($uploadDir);
        $cutoutFile = 'cutout_' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.png';
        $maskFile = 'mask_' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.png';

        $cutoutSaved = studioSavePng($output, $uploadDir . $cutoutFile);
        $maskSaved = imagepng($mask, $uploadDir . $maskFile, 6);

        imagedestroy($sourceImage);
        imagedestroy($output);
        imagedestroy($mask);

        if (!$cutoutSaved) {
            return [
                'cutout' => '',
                'mask' => null,
                'error' => 'Failed to save cutout image',
                'engine' => 'gd_refined',
                'meta' => ['tolerance' => $tolerance],
            ];
        }

        return [
            'cutout' => '/uploads/studio/' . $cutoutFile,
            'mask' => $maskSaved ? '/uploads/studio/' . $maskFile : null,
            'error' => null,
            'engine' => 'gd_refined',
            'meta' => [
                'tolerance' => $tolerance,
                'subject_pixels' => $subjectPixels,
                'feathering' => true,
            ],
        ];
    }
}

if (!function_exists('studioSanitizeVariant')) {
    function studioSanitizeVariant(?string $variant): string
    {
        $allowed = ['upload', 'edit', 'print_ready', 'final'];
        $value = strtolower(trim((string)$variant));
        return in_array($value, $allowed, true) ? $value : 'edit';
    }
}

$router->get('/studio', function () use ($twig) {
    $config = [
        'default_preset' => 'bd_passport',
        'presets' => array_values(studioPhotoPresets()),
        'page_sizes' => array_values(studioPageSizes()),
        'background_presets' => array_values(studioBackgroundPresets()),
        'default_print' => [
            'page_size' => 'A4',
            'orientation' => 'portrait',
            'layout' => 'center',
            'spacing_mm' => 4,
        ],
    ];

    echo $twig->render('photo-studio/editor.twig', [
        'page_title' => 'Brox Studio - ID Photo Pro',
        'studio_header_url' => '/studio',
        'studio_config_json' => json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
});

$router->post('/studio/upload', ['middleware' => ['csrf']], function () {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'No image uploaded'], 400);
    }

    $file = $_FILES['image'];
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (string)($imageInfo['mime'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        json_response(['error' => 'Invalid file type'], 400);
    }

    $uploadDir = BASE_PATH . 'public_html/uploads/studio/';
    studioEnsureDirectory($uploadDir);

    $filename = sprintf('upload_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(8)), $allowed[$mime]);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['error' => 'Upload failed'], 500);
    }

    json_response([
        'success' => true,
        'image' => [
            'filename' => $filename,
            'url' => '/uploads/studio/' . $filename,
            'original_name' => $file['name'],
            'variant' => 'upload',
        ],
    ]);
});

$router->post('/studio/save', ['middleware' => ['csrf']], function () {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'No image uploaded'], 400);
    }

    $file = $_FILES['image'];
    $imageInfo = @getimagesize($file['tmp_name']);
    $mime = (string)($imageInfo['mime'] ?? '');
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        json_response(['error' => 'Invalid file type'], 400);
    }

    $variant = studioSanitizeVariant($_POST['variant'] ?? null);
    $uploadDir = BASE_PATH . 'public_html/uploads/studio/';
    studioEnsureDirectory($uploadDir);

    $filename = $variant . '_' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['error' => 'Save failed'], 500);
    }

    json_response([
        'success' => true,
        'image' => [
            'filename' => $filename,
            'url' => '/uploads/studio/' . $filename,
            'variant' => $variant,
        ],
    ]);
});

$router->post('/studio/remove-background', ['middleware' => ['csrf']], function () {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        json_response(['error' => 'No image uploaded'], 400);
    }

    $binary = file_get_contents($_FILES['image']['tmp_name']);
    if ($binary === false) {
        json_response(['error' => 'Failed to read uploaded image'], 500);
    }

    $uploadDir = BASE_PATH . 'public_html/uploads/studio/';
    $result = studioRemoveBackgroundFromBinary($binary, $uploadDir);

    if ($result['error'] !== null) {
        json_response([
            'error' => $result['error'],
            'processing' => [
                'engine' => $result['engine'],
                'meta' => $result['meta'],
            ],
        ], 422);
    }

    json_response([
        'success' => true,
        'cutout_url' => $result['cutout'],
        'mask_url' => $result['mask'],
        'processing' => [
            'engine' => $result['engine'],
            'meta' => $result['meta'],
        ],
    ]);
});

$router->delete('/studio/image', ['middleware' => ['csrf']], function () {
    $payload = json_decode(file_get_contents('php://input'), true);
    $filename = basename((string)($payload['filename'] ?? ''));

    if ($filename === '') {
        json_response(['error' => 'Filename required'], 400);
    }

    $deleted = 0;
    $baseDir = BASE_PATH . 'public_html/uploads/studio/';
    $targets = [
        $baseDir . $filename,
        $baseDir . 'thumbs/thumb_' . $filename,
    ];

    foreach ($targets as $path) {
        if (is_file($path)) {
            unlink($path);
            $deleted++;
        }
    }

    json_response(['success' => true, 'deleted' => $deleted]);
});

$router->post('/studio/print-sheet', ['middleware' => ['csrf']], function () {
    $payload = json_decode(file_get_contents('php://input'), true);
    $images = is_array($payload['images'] ?? null) ? $payload['images'] : [];
    $pageSize = (string)($payload['page_size'] ?? 'A4');
    $orientation = (string)($payload['orientation'] ?? 'portrait');
    $layout = (string)($payload['layout'] ?? 'center');
    $presetId = (string)($payload['photo_preset'] ?? '');
    $fallbackSize = (string)($payload['size'] ?? '35x45');
    $spacingMm = max(0.0, min(12.0, (float)($payload['spacing_mm'] ?? 4)));
    $preset = studioResolvePreset($presetId !== '' ? $presetId : null, $fallbackSize);

    if ($images === []) {
        json_response(['error' => 'No images provided'], 400);
    }

    if (!function_exists('mpdf_render_html_to_string')) {
        json_response(['error' => 'PDF service is unavailable'], 500);
    }

    $paperSize = studioPaperCssSize($pageSize, $orientation);
    $justifyMap = [
        'center' => 'center',
        'wide' => 'flex-start',
        'compact' => 'space-between',
    ];
    $justify = $justifyMap[$layout] ?? 'center';
    $photoWidth = max(10.0, (float)$preset['width_mm']);
    $photoHeight = max(10.0, (float)$preset['height_mm']);
    $sheetTitle = htmlspecialchars((string)$preset['label'], ENT_QUOTES, 'UTF-8');
    $gapCss = number_format($spacingMm, 2, '.', '') . 'mm';

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
        . '@page { size: ' . htmlspecialchars($paperSize, ENT_QUOTES, 'UTF-8') . '; margin: 10mm; }'
        . 'body { font-family: Arial, sans-serif; margin: 0; color: #0f172a; }'
        . '.sheet-head { margin-bottom: 8mm; }'
        . '.sheet-head h1 { margin: 0 0 2mm; font-size: 16pt; }'
        . '.sheet-head p { margin: 0; font-size: 9pt; color: #475569; }'
        . '.sheet { display: flex; flex-wrap: wrap; justify-content: ' . $justify . '; gap: ' . $gapCss . '; }'
        . '.item { width: ' . number_format($photoWidth, 2, '.', '') . 'mm; height: ' . number_format($photoHeight, 2, '.', '') . 'mm; border: 1px solid #cbd5e1; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #fff; box-sizing: border-box; }'
        . '.item img { width: 100%; height: 100%; object-fit: cover; }'
        . '</style></head><body>'
        . '<div class="sheet-head"><h1>Brox Studio Print Sheet</h1><p>' . $sheetTitle . ' | '
        . number_format($photoWidth, 1, '.', '') . ' x ' . number_format($photoHeight, 1, '.', '') . ' mm</p></div>'
        . '<div class="sheet">';

    $validImageCount = 0;
    foreach ($images as $imageUrl) {
        $imageUrl = (string)$imageUrl;
        if (!str_starts_with($imageUrl, '/uploads/studio/')) {
            continue;
        }

        $fullPath = BASE_PATH . 'public_html' . $imageUrl;
        if (!is_file($fullPath)) {
            continue;
        }

        $binary = file_get_contents($fullPath);
        if ($binary === false) {
            continue;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);
        $html .= '<div class="item"><img src="' . htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8') . '" alt="Studio image"></div>';
        $validImageCount++;
    }

    $html .= '</div></body></html>';

    if ($validImageCount === 0) {
        json_response(['error' => 'No valid images found for the print sheet'], 400);
    }

    $uploadDir = BASE_PATH . 'public_html/uploads/studio/';
    studioEnsureDirectory($uploadDir);

    $outputFile = 'print_sheet_' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.pdf';
    $pdfBinary = mpdf_render_html_to_string($html, ['title' => 'Brox Studio Print Sheet']);

    if ($pdfBinary === null || file_put_contents($uploadDir . $outputFile, $pdfBinary) === false) {
        json_response(['error' => 'Failed to generate print sheet'], 500);
    }

    json_response([
        'success' => true,
        'download_url' => '/uploads/studio/' . $outputFile,
        'summary' => [
            'preset' => $preset['id'],
            'label' => $preset['label'],
            'count' => $validImageCount,
            'page_size' => $pageSize,
            'orientation' => $orientation,
        ],
    ]);
});
