<?php
declare(strict_types=1);
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createUnsafeImmutable($projectRoot);
$dotenv->safeLoad();
require_once $projectRoot . '/Config/Constants.php';
$tz = $_ENV['APP_TIMEZONE'] ?? 'Asia/Dhaka';
if (!empty($tz)) date_default_timezone_set($tz);
foreach ([CACHE_DIR, TEMP_DIR] as $d) { if (!is_dir($d)) @mkdir($d, 0775, true); }
require_once $projectRoot . '/app/Helpers/MpdfHelper.php';
if (session_status() === PHP_SESSION_NONE) { $_SESSION = []; }

$loader = new \Twig\Loader\FilesystemLoader($projectRoot . '/app/Views');
$twig = new \Twig\Environment($loader, ['cache' => false, 'autoescape' => 'html']);
$twig->addExtension(new \Twig\Extension\StringLoaderExtension());
$twig->addGlobal('csrf_token', 'test');
$twig->addGlobal('current_page', 'kharij');

$data = [
    'data' => [
        'khata_number'=>'882','mouza'=>'Senail','jl_number'=>'205',
        'upazila'=>'Dhamrai','district'=>'Dhaka',
        'owner_name'=>'Ayubur Rahman','father_or_husband_name'=>'Md. Barek',
        'mothers_name'=>'—','owner_address'=>'Senail, Dhamrai, Dhaka',
        'share'=>'4 ana','dag_number'=>'125','land_development_tax'=>'150 Tk',
        'agriculture_area'=>'0','non_agriculture_area'=>'0',
        'total_dag_area'=>'0','dag_area_shottangsho'=>'0',
        'single_area'=>'0','khash_area'=>'0','khash_area_shottangsho'=>'0',
        'remarks'=>'Test','total_share'=>'1.000',
        'total_dag_area_summary_acre'=>'0','total_dag_area_summary_shottangsho'=>'0',
        'total_land_words'=>'Zero',
        'proposed_date'=>'24/09/2023','kanungo_date'=>'09/09/2023',
        'approved_date'=>'09/09/2023','form_number'=>'Form 5462',
    ],
    'qr_data_uri' => null,
    'images' => [
        'sign_rezaul' => '/assets/images/kharij/sign-rezaul.png',
        'sign_julfikar' => '/assets/images/kharij/sign-julfikar.png',
        'sign_aminul' => '/assets/images/kharij/sign-aminul.png',
        'organisation_seal' => '/assets/images/kharij/seal.png',
        'red_seal' => '/assets/images/kharij/red-seal.png',
    ],
];

$html = $twig->render('pdf/kharij-form.twig', $data);
$mpdf = mpdf_create_instance(['format'=>'A4','orientation'=>'L','margin_left'=>10,'margin_right'=>10,'margin_top'=>6,'margin_bottom'=>10]);
if (!$mpdf) { echo "FAIL: mPDF init error\n"; exit(1); }
mpdf_apply_runtime_optimizations($mpdf);
$wp = realpath($projectRoot . '/public_html/assets/images/kharij/watermark.png');
if ($wp && is_file($wp) && method_exists($mpdf, 'SetWatermarkImage')) {
    $mpdf->SetWatermarkImage($wp, 0.18, 175);
    $mpdf->showWatermarkImage = true;
    $mpdf->watermarkImgBehind = true;
    echo "Watermark: $wp\n";
}
$footer = '<table width="100%" style="border-top:none;font-family:solaimanlipi,nikosh,sans-serif;font-size:9.5pt;font-weight:bold;"><tr><td width="333px"></td><td width="445px" align="center"></td><td width="333px" align="right">{PAGENO} / {nbpg}</td></tr></table>';
$mpdf->SetHTMLFooter($footer);
$mpdf->SetTitle('KHARIJ TEST');
$mpdf->WriteHTML(mpdf_optimize_html($html));
$out = $projectRoot . '/public_html/test-kharij-output.pdf';
$mpdf->Output($out, \Mpdf\Output\Destination::FILE);
if (file_exists($out)) {
    echo "\nOK: test-kharij-output.pdf (" . filesize($out) . " bytes)\n";
    echo "Open: " . realpath($out) . "\n";
} else {
    echo "\nFAIL: PDF not created\n";
}
