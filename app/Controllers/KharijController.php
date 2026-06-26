<?php

/**
 * app/Controllers/KharijController.php
 *
 * Kharij (Land Transfer) Document Controller — procedural format.
 * Handles PDF generation, verification, listing, and streaming.
 *
 * Routes:
 *   Admin (auth + admin_only):
 *     GET  /admin/kharij                      → List records
 *     GET  /admin/kharij/create                → Create form
 *     POST /admin/kharij/generate              → Generate PDF and save
 *     GET  /admin/kharij/edit/{id}           → Edit form
 *     POST /admin/kharij/update/{id}         → Update record
 *     POST /admin/kharij/delete/{id}         → Soft delete record
 *     GET  /admin/kharij/image-download/{id} → Download first page as PNG
 *
 *   Public (no auth):
 *     GET /mutation-land/qr-vk/{hash}                      → Verify page
 *     GET /mutation-land-gov-bd/QrScanner/KhatianDownload/{hash} → PDF download
 */

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ============================================================
// HELPER: Generate QR code as data URI
// ============================================================

$kharijGenerateQr = function (string $hash): ?string {
    try {
        $verificationUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/mutation-land/qr-vk/' . $hash;
        $qrCode = new QrCode($verificationUrl);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        return 'data:image/png;base64,' . base64_encode($result->getString());
    } catch (Throwable $e) {
        if (function_exists('logError')) {
            logError('QR generation failed: ' . $e->getMessage());
        }
        return null;
    }
};

// ============================================================
// HELPER: Stream PDF with inline/attachment toggle
// ============================================================

$kharijStreamPdf = function (array $data, bool $inline = true) use ($twig): void {
    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

    $html = $twig->render('pdf/kharij-form.twig', $data);

    $mpdf = mpdf_create_instance([
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 6,
        'margin_bottom' => 10,
    ]);

    if (!$mpdf) {
        http_response_code(500);
        echo 'Failed to initialize PDF engine';
        exit;
    }

    mpdf_apply_runtime_optimizations($mpdf);

    // Apply default font size for consistent rendering across page breaks
    if (method_exists($mpdf, 'SetDefaultFontSize')) {
        $mpdf->SetDefaultFontSize(9.5);
    }

    // Watermark — shown on all pages behind content
    $watermarkPath = realpath(__DIR__ . '/../../public_html/assets/images/kharij/watermark.png');
    if ($watermarkPath && is_file($watermarkPath) && method_exists($mpdf, 'SetWatermarkImage')) {
        $mpdf->SetWatermarkImage($watermarkPath, 0.18, 172);
        $mpdf->showWatermarkImage = true;
        $mpdf->watermarkImgBehind = true;
    }

    // Page number footer
    $mpdf->SetHTMLFooter('
        <table width="100%">
            <tr>
                <td width="333px"></td>
                <td width="445px" align="center"></td>
                <td width="333px" align="right">{PAGENO}/{nbpg}</td>
            </tr>
        </table>
    ');

    $filename = 'kharij-' . ($data['data']['khata_number'] ?? 'form') . '.pdf';
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.\\\\x{0980}-\\\\x{09FF}]/u', '_', $filename);

    $mpdf->SetTitle('খারিজ ফরম - ' . ($data['data']['khata_number'] ?? ''));
    $mpdf->SetAuthor('BroxLab Kharij System');
    $mpdf->SetSubject('খারিজ (হস্তান্তর) ডকুমেন্ট');
    $mpdf->WriteHTML(mpdf_optimize_html($html));

    $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

    if (ob_get_level() > 0) {
        ob_clean();
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    echo $pdfBinary;
    exit;
};

// ============================================================
// HELPER: Stream first page as PNG image
// ============================================================

$kharijStreamImage = function (array $data, int $dpi = 150) use ($twig): void {
    require_once dirname(__DIR__, 1) . '/Helpers/MpdfHelper.php';

    $html = $twig->render('pdf/kharij-form.twig', $data);

    $mpdf = mpdf_create_instance([
        'format' => 'A4',
        'orientation' => 'L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 6,
        'margin_bottom' => 10,
    ]);

    if (!$mpdf) {
        http_response_code(500);
        echo 'Failed to initialize PDF engine';
        exit;
    }

    mpdf_apply_runtime_optimizations($mpdf);

    if (method_exists($mpdf, 'SetDefaultFontSize')) {
        $mpdf->SetDefaultFontSize(9.5);
    }

    $watermarkPath = realpath(__DIR__ . '/../../public_html/assets/images/kharij/watermark.png');
    if ($watermarkPath && is_file($watermarkPath) && method_exists($mpdf, 'SetWatermarkImage')) {
        $mpdf->SetWatermarkImage($watermarkPath, 0.18, 172);
        $mpdf->showWatermarkImage = true;
        $mpdf->watermarkImgBehind = true;
    }

    $mpdf->SetTitle('খারিজ ফরম - ' . ($data['data']['khata_number'] ?? ''));
    $mpdf->WriteHTML(mpdf_optimize_html($html));

    $pdfBinary = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

    if (!$pdfBinary) {
        http_response_code(500);
        echo 'Failed to generate PDF content';
        exit;
    }

    $imageBinary = pdf_to_image($pdfBinary, $dpi);

    if (!$imageBinary) {
        http_response_code(500);
        echo 'Failed to convert PDF to image. Required: pdftoppm or ImageMagick.';
        exit;
    }

    if (ob_get_level() > 0) {
        ob_clean();
    }

    $filename = 'kharij-' . ($data['data']['khata_number'] ?? 'form') . '.png';
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.\\\\x{0980}-\\\\x{09FF}]/u', '_', $filename);

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($imageBinary));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    echo $imageBinary;
    exit;
};

// ============================================================
// ADMIN ROUTE: List all kharij records
// ============================================================

$router->get('/admin/kharij', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $kharijModel = new KharijModel($mysqli);

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $search = trim($_GET['search'] ?? '');

    $result = $kharijModel->getPaginated($page, $limit, $search);
    $totalPages = $limit > 0 ? (int)ceil($result['total'] / $limit) : 0;

    echo $twig->render('kharij/list.twig', [
        'records' => $result['records'],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total' => $result['total'],
            'per_page' => $limit,
            'search' => $search,
        ],
        'title' => 'খারিজ রেকর্ড',
        'current_page' => 'kharij',
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'home'],
            ['label' => 'খারিজ', 'icon' => 'file-text'],
        ],
    ]);
    exit;
});

// ============================================================
// ADMIN ROUTE: Create form
// ============================================================

$router->get('/admin/kharij/create', ['middleware' => ['auth', 'admin_only']], function () use ($twig) {
    echo $twig->render('kharij/create.twig', [
        'title' => 'নতুন খারিজ তৈরি করুন',
        'current_page' => 'kharij',
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
        'data' => [
            'khata_number' => '৮৮২',
            'mouza' => 'সেনাইল',
            'jl_number' => '২০৫',
            'upazila' => 'ধামরাই',
            'district' => 'ঢাকা',
            'owner_name' => 'আয়ুবুর রহমান',
            'father_or_husband_name' => 'মোঃ বারেক',
            'mothers_name' => 'খাদেজা',
            'owner_address' => 'গ্রাম-সেনাইল, থানা-ধামরাই, জেলা-ঢাকা',
            'share' => '৪.০০০',
            'dag_number' => '১২৫',
            'land_type' => 'নাল',
            'total_land_area' => '০.৫০',
            'dag_area_shottangsho' => '0',
            'khash_area_shottangsho' => '0',
            'inherited_area' => '০.১২৫',
            'agriculture_area' => '0',
            'non_agriculture_area' => '0',
            'total_dag_area' => '0',
            'single_area' => '0',
            'khash_area' => '0',
            'land_development_tax' => '১০',
            'total_dag_area_summary_acre' => '0',
            'total_dag_area_summary_shottangsho' => '0',
            'total_land_words' => '',
            'remarks' => 'পূর্ববর্তী খতিয়ান: ৩৪২',
            'application_no' => '৭৪৩২৬০১',
            'application_date' => '২১-০৮-২০২৩',
            'mutation_case_no' => '২,৮৩২(IX-I)/২০২৩-২৪',
            'online_dcr_no' => '23261420502832',
        ],
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'home'],
            ['label' => 'খারিজ', 'url' => '/admin/kharij', 'icon' => 'file-text'],
            ['label' => 'নতুন', 'icon' => 'plus'],
        ],
    ]);
    exit;
});

// ============================================================
// ADMIN ROUTE: Edit form
// ============================================================

$router->get('/admin/kharij/edit/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli) {
    $kharijModel = new KharijModel($mysqli);
    $record = $kharijModel->findById((int)$id);

    if (!$record) {
        showMessage('Kharij record not found.', 'danger');
        header('Location: /admin/kharij');
        exit;
    }

    echo $twig->render('kharij/edit.twig', [
        'title' => 'খারিজ সম্পাদনা করুন',
        'current_page' => 'kharij',
        'record' => $record,
        'data' => $record['data'],
        'csrf_token' => $_SESSION['csrf_token'] ?? '',
        'breadcrumbs' => [
            ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'home'],
            ['label' => 'খারিজ', 'url' => '/admin/kharij', 'icon' => 'file-text'],
            ['label' => 'সম্পাদনা', 'icon' => 'edit'],
        ],
    ]);
    exit;
});

// ============================================================
// ADMIN ROUTE: Update record
// ============================================================

$router->post('/admin/kharij/update/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($twig, $mysqli) {
    $kharijModel = new KharijModel($mysqli);
    $id = (int)$id;
    $record = $kharijModel->findById($id);

    if (!$record) {
        showMessage('Kharij record not found.', 'danger');
        header('Location: /admin/kharij');
        exit;
    }

    $data = [
        'khata_number' => sanitize_input($_POST['khata_number'] ?? ''),
        'mouza' => sanitize_input($_POST['mouza'] ?? ''),
        'jl_number' => sanitize_input($_POST['jl_number'] ?? ''),
        'upazila' => sanitize_input($_POST['upazila'] ?? ''),
        'district' => sanitize_input($_POST['district'] ?? ''),
        'dag_number' => sanitize_input($_POST['dag_number'] ?? ''),
        'land_type' => sanitize_input($_POST['land_type'] ?? ''),
        'total_land_area' => sanitize_input($_POST['total_land_area'] ?? ''),
        'dag_area_shottangsho' => sanitize_input($_POST['dag_area_shottangsho'] ?? ''),
        'khash_area_shottangsho' => sanitize_input($_POST['khash_area_shottangsho'] ?? ''),
        'village_road' => sanitize_input($_POST['village_road'] ?? ''),
        'inherited_area' => sanitize_input($_POST['inherited_area'] ?? ''),
        'land_development_tax' => sanitize_input($_POST['land_development_tax'] ?? ''),
        'remarks' => sanitize_input($_POST['remarks'] ?? ''),
        'form_number' => sanitize_input($_POST['form_number'] ?? 'বাংলাদেশ ফরম নং ৫৪৬২ (সংশোধিত)'),
        'application_no' => sanitize_input($_POST['application_no'] ?? ''),
        'application_date' => sanitize_input($_POST['application_date'] ?? date('d/m/Y')),
        'mutation_case_no' => sanitize_input($_POST['mutation_case_no'] ?? ''),
        'mutation_share' => sanitize_input($_POST['mutation_share'] ?? ''),
        'online_dcr_no' => sanitize_input($_POST['online_dcr_no'] ?? ''),
        'post_office' => sanitize_input($_POST['post_office'] ?? ''),
        'total_share' => sanitize_input($_POST['total_share'] ?? '১.০০০'),
        'agriculture_area' => sanitize_input($_POST['agriculture_area'] ?? ''),
        'non_agriculture_area' => sanitize_input($_POST['non_agriculture_area'] ?? ''),
        'total_dag_area' => sanitize_input($_POST['total_dag_area'] ?? ''),
        'single_area' => sanitize_input($_POST['single_area'] ?? ''),
        'khash_area' => sanitize_input($_POST['khash_area'] ?? ''),
        'total_land_words' => sanitize_input($_POST['total_land_words'] ?? ''),
        'total_dag_area_summary_acre' => sanitize_input($_POST['total_dag_area_summary_acre'] ?? ''),
        'total_dag_area_summary_shottangsho' => sanitize_input($_POST['total_dag_area_summary_shottangsho'] ?? ''),
        'proposed_date' => sanitize_input($_POST['proposed_date'] ?? ''),
        'kanungo_date' => sanitize_input($_POST['kanungo_date'] ?? ''),
        'approved_date' => sanitize_input($_POST['approved_date'] ?? ''),
        'deed_number' => sanitize_input($_POST['deed_number'] ?? ''),
        'deed_date' => sanitize_input($_POST['deed_date'] ?? ''),
        'mutation_date' => sanitize_input($_POST['mutation_date'] ?? ''),
        'court_order_no' => sanitize_input($_POST['court_order_no'] ?? ''),
        'court_order_date' => sanitize_input($_POST['court_order_date'] ?? ''),
    ];

    // Handle multiple owners (dynamic array format)
    if (isset($_POST['owners']) && is_array($_POST['owners'])) {
        $owners = [];
        foreach ($_POST['owners'] as $owner) {
            $owners[] = [
                'name' => sanitize_input($owner['name'] ?? ''),
                'father_or_husband_name' => sanitize_input($owner['father_or_husband_name'] ?? ''),
                'mothers_name' => sanitize_input($owner['mothers_name'] ?? ''),
                'address' => sanitize_input($owner['address'] ?? ''),
                'share' => sanitize_input($owner['share'] ?? ''),
                'nid_number' => sanitize_input($owner['nid_number'] ?? ''),
            ];
        }
        $data['owners'] = $owners;
        // Remove legacy single fields
        unset($data['owner_name'], $data['father_or_husband_name'], $data['mothers_name'], $data['owner_address'], $data['share'], $data['nid_number']);
    } else {
        // Legacy single-field fallback
        $data['owner_name'] = sanitize_input($_POST['owner_name'] ?? '');
        $data['father_or_husband_name'] = sanitize_input($_POST['father_or_husband_name'] ?? '');
        $data['mothers_name'] = sanitize_input($_POST['mothers_name'] ?? '');
        $data['owner_address'] = sanitize_input($_POST['owner_address'] ?? '');
        $data['share'] = sanitize_input($_POST['share'] ?? '');
        $data['nid_number'] = sanitize_input($_POST['nid_number'] ?? '');
    }

    $updated = $kharijModel->update($id, $data);

    if ($updated) {
        logActivity("Kharij Record Updated", "kharij", $id, ['khata_number' => $data['khata_number']], 'success');
        showMessage('Kharij record updated successfully.', 'success');
    } else {
        showMessage('No changes were made.', 'info');
    }

    header('Location: /admin/kharij/edit/' . $id);
    exit;
});

// ============================================================
// ADMIN ROUTE: Delete record (soft delete)
// ============================================================

$router->post('/admin/kharij/delete/{id}', ['middleware' => ['auth', 'admin_only', 'csrf']], function ($id) use ($mysqli) {
    $kharijModel = new KharijModel($mysqli);
    $id = (int)$id;

    if ($kharijModel->softDelete($id)) {
        logActivity("Kharij Record Deleted", "kharij", $id, [], 'success');
        showMessage('Kharij record deleted successfully.', 'success');
    } else {
        showMessage('Failed to delete kharij record.', 'danger');
    }

    header('Location: /admin/kharij');
    exit;
});

// ============================================================
// ADMIN ROUTE: Generate PDF and save record
// ============================================================

$router->post('/admin/kharij/generate', ['middleware' => ['auth', 'admin_only', 'csrf']], function () use ($twig, $mysqli, $kharijGenerateQr, $kharijStreamPdf) {
    $kharijModel = new KharijModel($mysqli);

    $data = [
        'khata_number' => sanitize_input($_POST['khata_number'] ?? ''),
        'mouza' => sanitize_input($_POST['mouza'] ?? ''),
        'jl_number' => sanitize_input($_POST['jl_number'] ?? ''),
        'upazila' => sanitize_input($_POST['upazila'] ?? ''),
        'district' => sanitize_input($_POST['district'] ?? ''),
        'dag_number' => sanitize_input($_POST['dag_number'] ?? ''),
        'land_type' => sanitize_input($_POST['land_type'] ?? ''),
        'total_land_area' => sanitize_input($_POST['total_land_area'] ?? ''),
        'dag_area_shottangsho' => sanitize_input($_POST['dag_area_shottangsho'] ?? ''),
        'khash_area_shottangsho' => sanitize_input($_POST['khash_area_shottangsho'] ?? ''),
        'village_road' => sanitize_input($_POST['village_road'] ?? ''),
        'inherited_area' => sanitize_input($_POST['inherited_area'] ?? ''),
        'land_development_tax' => sanitize_input($_POST['land_development_tax'] ?? ''),
        'remarks' => sanitize_input($_POST['remarks'] ?? ''),
        'form_number' => sanitize_input($_POST['form_number'] ?? 'বাংলাদেশ ফরম নং ৫৪৬২ (সংশোধিত)'),
        'application_no' => sanitize_input($_POST['application_no'] ?? ''),
        'application_date' => sanitize_input($_POST['application_date'] ?? date('d-m-Y')),
        'mutation_case_no' => sanitize_input($_POST['mutation_case_no'] ?? ''),
        'mutation_share' => sanitize_input($_POST['mutation_share'] ?? ''),
        'online_dcr_no' => sanitize_input($_POST['online_dcr_no'] ?? ''),
        'post_office' => sanitize_input($_POST['post_office'] ?? ''),
        'total_share' => sanitize_input($_POST['total_share'] ?? '১.০০০'),
        'agriculture_area' => sanitize_input($_POST['agriculture_area'] ?? ''),
        'non_agriculture_area' => sanitize_input($_POST['non_agriculture_area'] ?? ''),
        'total_dag_area' => sanitize_input($_POST['total_dag_area'] ?? ''),
        'single_area' => sanitize_input($_POST['single_area'] ?? ''),
        'khash_area' => sanitize_input($_POST['khash_area'] ?? ''),
        'total_land_words' => sanitize_input($_POST['total_land_words'] ?? ''),
        'total_dag_area_summary_acre' => sanitize_input($_POST['total_dag_area_summary_acre'] ?? ''),
        'total_dag_area_summary_shottangsho' => sanitize_input($_POST['total_dag_area_summary_shottangsho'] ?? ''),
        'proposed_date' => sanitize_input($_POST['proposed_date'] ?? ''),
        'kanungo_date' => sanitize_input($_POST['kanungo_date'] ?? ''),
        'approved_date' => sanitize_input($_POST['approved_date'] ?? ''),
        'deed_number' => sanitize_input($_POST['deed_number'] ?? ''),
        'deed_date' => sanitize_input($_POST['deed_date'] ?? ''),
        'mutation_date' => sanitize_input($_POST['mutation_date'] ?? ''),
        'court_order_no' => sanitize_input($_POST['court_order_no'] ?? ''),
        'court_order_date' => sanitize_input($_POST['court_order_date'] ?? ''),
    ];

    // Handle multiple owners (dynamic array format)
    if (isset($_POST['owners']) && is_array($_POST['owners'])) {
        $owners = [];
        foreach ($_POST['owners'] as $owner) {
            $owners[] = [
                'name' => sanitize_input($owner['name'] ?? ''),
                'father_or_husband_name' => sanitize_input($owner['father_or_husband_name'] ?? ''),
                'mothers_name' => sanitize_input($owner['mothers_name'] ?? ''),
                'address' => sanitize_input($owner['address'] ?? ''),
                'share' => sanitize_input($owner['share'] ?? ''),
                'nid_number' => sanitize_input($owner['nid_number'] ?? ''),
            ];
        }
        $data['owners'] = $owners;
    } else {
        // Legacy single-field fallback
        $data['owner_name'] = sanitize_input($_POST['owner_name'] ?? '');
        $data['father_or_husband_name'] = sanitize_input($_POST['father_or_husband_name'] ?? '');
        $data['mothers_name'] = sanitize_input($_POST['mothers_name'] ?? '');
        $data['owner_address'] = sanitize_input($_POST['owner_address'] ?? '');
        $data['share'] = sanitize_input($_POST['share'] ?? '');
        $data['nid_number'] = sanitize_input($_POST['nid_number'] ?? '');
    }

    // Save to database
    $hash = $kharijModel->generateHash();
    $generatedBy = $_SESSION['username'] ?? $_SESSION['user_id'] ?? 'System';
    $recordId = $kharijModel->create($data, $hash, $generatedBy);

    if (!$recordId) {
        showMessage('Failed to save kharij record', 'danger');
        header('Location: /admin/kharij/create');
        exit;
    }

    // Prepare data for template
    $qrDataUri = $kharijGenerateQr($hash);
    $templateData = [
        'data' => $data,
        'qr_data_uri' => $qrDataUri,
        'images' => [
            'sign_rezaul' => '/assets/images/kharij/sign-rezaul.png',
            'sign_julfikar' => '/assets/images/kharij/sign-julfikar.png',
            'sign_aminul' => '/assets/images/kharij/sign-aminul.png',
            'organisation_seal' => '/assets/images/kharij/seal.png',
            'red_seal' => '/assets/images/kharij/red-seal.png',
        ],
    ];

    // Stream PDF as download (attachment)
    $kharijStreamPdf($templateData, false);
    exit;
});

// ============================================================
// PUBLIC ROUTE: Verify page (no auth required)
// GET /mutation-land/qr-vk/{hash}
// ============================================================

$router->get('/mutation-land/qr-vk/{hash}', function ($hash) use ($twig, $mysqli) {
    $kharijModel = new KharijModel($mysqli);
    $record = $kharijModel->findByHash($hash);

    $recordData = null;
    if ($record) {
        $recordData = $record['data'];
        $recordData['_generated_by'] = $record['generated_by'];
        $recordData['_created_at'] = $record['created_at'];
    }

    $today = new DateTime();
    $dayOfWeek = (int)$today->format('w');
    $bnDayNames = ['রবিবার', 'সোমবার', 'মঙ্গলবার', 'বুধবার', 'বৃহস্পতিবার', 'শুক্রবার', 'শনিবার'];
    $bnDay = $bnDayNames[$dayOfWeek] ?? 'বৃহস্পতিবার';
    $bnDayNum = enToBnDigits($today->format('j'));
    $bnMonth = enToBnMonth($today->format('F'));
    $bnYear = enToBnDigits($today->format('Y'));
    $currentDateBn = $bnDay . ', ' . $bnDayNum . ' ' . $bnMonth . ' ' . $bnYear;

    echo $twig->render('kharij/verify.twig', [
        'record' => $recordData,
        'hash' => $hash,
        'page_title' => 'খারিজ যাচাইকরণ',
        'current_date_bn' => $currentDateBn,
    ]);
    exit;
});

// ============================================================
// ADMIN ROUTE: Download first page as PNG image
// GET /admin/kharij/image-download/{id}
// ============================================================

$router->get('/admin/kharij/image-download/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $mysqli, $kharijGenerateQr, $kharijStreamImage) {
    $kharijModel = new KharijModel($mysqli);
    $id = (int)$id;
    $record = $kharijModel->findById($id);

    if (!$record) {
        http_response_code(404);
        echo 'Kharij record not found';
        exit;
    }

    $data = $record['data'];
    $qrDataUri = $kharijGenerateQr($record['hash'] ?? '');
    $templateData = [
        'data' => $data,
        'qr_data_uri' => $qrDataUri,
        'images' => [
            'sign_rezaul' => '/assets/images/kharij/sign-rezaul.png',
            'sign_julfikar' => '/assets/images/kharij/sign-julfikar.png',
            'sign_aminul' => '/assets/images/kharij/sign-aminul.png',
            'organisation_seal' => '/assets/images/kharij/seal.png',
            'red_seal' => '/assets/images/kharij/red-seal.png',
        ],
    ];

    $kharijStreamImage($templateData, 150);
    exit;
});

// ============================================================
// PUBLIC ROUTE: PDF Download (no auth required)
// GET /mutation-land-gov-bd/QrScanner/KhatianDownload/{hash}
// ============================================================

$router->get('/mutation-land-gov-bd/QrScanner/KhatianDownload/{hash}', function ($hash) use ($mysqli, $kharijStreamPdf, $kharijGenerateQr) {
    $kharijModel = new KharijModel($mysqli);
    $record = $kharijModel->findByHash($hash);

    if (!$record) {
        http_response_code(404);
        echo 'Record not found';
        exit;
    }

    $data = $record['data'];
    $qrDataUri = $kharijGenerateQr($hash);
    $templateData = [
        'data' => $data,
        'qr_data_uri' => $qrDataUri,
        'images' => [
            'sign_rezaul' => '/assets/images/kharij/sign-rezaul.png',
            'sign_julfikar' => '/assets/images/kharij/sign-julfikar.png',
            'sign_aminul' => '/assets/images/kharij/sign-aminul.png',
            'organisation_seal' => '/assets/images/kharij/seal.png',
            'red_seal' => '/assets/images/kharij/red-seal.png',
        ],
    ];

    // Check if download is explicitly requested via query string
    $isDownload = (isset($_GET['download']) && $_GET['download'] === '1');

    // Preview inline in browser, or force download if ?download=1
    $kharijStreamPdf($templateData, !$isDownload);
    exit;
});
