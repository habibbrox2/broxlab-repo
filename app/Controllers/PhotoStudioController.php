<?php

/** @var Router $router */
/** @var \mysqli $mysqli */
/** @var \Twig\Environment $twig */
global $mysqli, $twig;

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

if (!function_exists('studioEnsureDirectory')) {
    function studioEnsureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
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

if (!function_exists('studioAverageBorderColor')) {
    /**
     * @param GdImage|resource $image
     * @return array{0:int,1:int,2:int}
     */
    function studioAverageBorderColor($image, int $width, int $height): array
    {
        $samples = [];
        $sampleStepX = max(1, intdiv($width, 20));
        $sampleStepY = max(1, intdiv($height, 20));

        for ($x = 0; $x < $width; $x += $sampleStepX) {
            $samples[] = imagecolorat($image, $x, 0);
            $samples[] = imagecolorat($image, $x, $height - 1);
        }

        for ($y = 0; $y < $height; $y += $sampleStepY) {
            $samples[] = imagecolorat($image, 0, $y);
            $samples[] = imagecolorat($image, $width - 1, $y);
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

if (!function_exists('studioColorDistance')) {
    function studioColorDistance(int $r, int $g, int $b, array $target): float
    {
        return sqrt((($r - $target[0]) ** 2) + (($g - $target[1]) ** 2) + (($b - $target[2]) ** 2));
    }
}

if (!function_exists('studioRemoveBackgroundFromBinary')) {
    /**
     * @return array{cutout:string,mask:?string,error:?string}
     */
    function studioRemoveBackgroundFromBinary(string $binary, string $uploadDir): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return ['cutout' => '', 'mask' => null, 'error' => 'GD image support is unavailable'];
        }

        $created = studioCreateImageFromBinary($binary);
        if ($created === null) {
            return ['cutout' => '', 'mask' => null, 'error' => 'Unsupported image data'];
        }

        [$sourceImage,] = $created;
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $background = studioAverageBorderColor($sourceImage, $width, $height);
        $tolerance = 52.0;

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

            $visited[$y][$x] = true;
            $rgb = imagecolorat($sourceImage, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;

            if (studioColorDistance($r, $g, $b, $background) > $tolerance) {
                continue;
            }

            $queue->enqueue([$x + 1, $y]);
            $queue->enqueue([$x - 1, $y]);
            $queue->enqueue([$x, $y + 1]);
            $queue->enqueue([$x, $y - 1]);
        }

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
                $rgb = imagecolorat($sourceImage, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if ($visited[$y][$x]) {
                    imagesetpixel($output, $x, $y, $transparent);
                    continue;
                }

                $subjectPixels++;
                $color = imagecolorallocatealpha($output, $r, $g, $b, 0);
                imagesetpixel($output, $x, $y, $color);
                imagesetpixel($mask, $x, $y, $maskWhite);
            }
        }

        if ($subjectPixels < (int)max(100, ($width * $height) * 0.02)) {
            imagedestroy($sourceImage);
            imagedestroy($output);
            imagedestroy($mask);
            return ['cutout' => '', 'mask' => null, 'error' => 'No clear subject detected'];
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
            return ['cutout' => '', 'mask' => null, 'error' => 'Failed to save cutout image'];
        }

        return [
            'cutout' => '/uploads/studio/' . $cutoutFile,
            'mask' => $maskSaved ? '/uploads/studio/' . $maskFile : null,
            'error' => null,
        ];
    }
}

if (!function_exists('studioPaperCssSize')) {
    function studioPaperCssSize(string $pageSize, string $orientation): string
    {
        $sizes = [
            'A4' => '210mm 297mm',
            'Legal' => '216mm 356mm',
            'Letter' => '216mm 279mm',
            'A5' => '148mm 210mm',
            'B5' => '176mm 250mm',
            'Tabloid' => '279mm 432mm',
        ];

        $base = $sizes[$pageSize] ?? $sizes['A4'];
        return $base . ' ' . ($orientation === 'landscape' ? 'landscape' : 'portrait');
    }
}

$router->get('/studio', function () use ($twig) {
    echo $twig->render('photo-studio/editor.twig', [
        'page_title' => 'Brox Studio - Photo Editor',
        'studio_header_url' => '/studio',
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

    $filename = sprintf('studio_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(8)), $allowed[$mime]);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['error' => 'Upload failed'], 500);
    }

    json_response([
        'success' => true,
        'image' => [
            'filename' => $filename,
            'url' => '/uploads/studio/' . $filename,
            'original_name' => $file['name'],
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

    $uploadDir = BASE_PATH . 'public_html/uploads/studio/';
    studioEnsureDirectory($uploadDir);

    $filename = 'edited_' . date('Ymd_His_') . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        json_response(['error' => 'Save failed'], 500);
    }

    json_response([
        'success' => true,
        'image' => [
            'filename' => $filename,
            'url' => '/uploads/studio/' . $filename,
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
        json_response(['error' => $result['error']], 422);
    }

    json_response([
        'success' => true,
        'cutout_url' => $result['cutout'],
        'mask_url' => $result['mask'],
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
        if (file_exists($path)) {
            unlink($path);
            $deleted++;
        }
    }

    json_response(['success' => true, 'deleted' => $deleted]);
});

$router->post('/studio/print-sheet', ['middleware' => ['csrf']], function () {
    $payload = json_decode(file_get_contents('php://input'), true);
    $images = $payload['images'] ?? [];
    $pageSize = (string)($payload['page_size'] ?? 'A4');
    $orientation = (string)($payload['orientation'] ?? 'portrait');
    $layout = (string)($payload['layout'] ?? 'center');
    $photoSize = (string)($payload['size'] ?? '40x50');

    if (!is_array($images) || $images === []) {
        json_response(['error' => 'No images provided'], 400);
    }

    if (!function_exists('mpdf_render_html_to_string')) {
        json_response(['error' => 'PDF service is unavailable'], 500);
    }

    [$photoWidth, $photoHeight] = array_pad(array_map('intval', explode('x', $photoSize, 2)), 2, 40);
    $paperSize = studioPaperCssSize($pageSize, $orientation);
    $justify = $layout === 'wide' ? 'flex-start' : 'center';

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page { size: ' . htmlspecialchars($paperSize, ENT_QUOTES, 'UTF-8') . '; margin: 10mm; } body { font-family: Arial, sans-serif; margin: 0; } .sheet { display: flex; flex-wrap: wrap; justify-content: ' . $justify . '; gap: 4mm; } .item { width: ' . $photoWidth . 'mm; height: ' . $photoHeight . 'mm; border: 1px solid #d4d4d8; overflow: hidden; display: flex; align-items: center; justify-content: center; } .item img { width: 100%; height: 100%; object-fit: cover; }</style></head><body><div class="sheet">';

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
    ]);
});
