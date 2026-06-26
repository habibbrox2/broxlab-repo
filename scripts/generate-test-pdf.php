<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
$env = $_ENV + $_SERVER;

// Bootstrap Twig
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../app/Views');
$twig = new \Twig\Environment($loader, ['cache' => false, 'auto_reload' => true]);
require_once __DIR__ . '/../Config/Functions.php';

// Add the bn and en filters manually (avoids registerTwigHelpers needing DB)
$twig->addFilter(new \Twig\TwigFilter('bn', function ($value) {
    if ($value === null || $value === '') return $value;
    return enToBnDigits((string) $value);
}));
$twig->addFilter(new \Twig\TwigFilter('en', function ($value) {
    if ($value === null || $value === '') return $value;
    return bnToEnDigits((string) $value);
}));

// Sample data (English numerals — the |bn filter will convert)
$testData = [
    'form_number' => 'বাংলাদেশ ফরম নং ৫৪৬২ (সংশোধিত)',
    'khata_number' => '882',
    'mouza' => 'সেনাইল',
    'jl_number' => '205',
    'upazila' => 'ধামরাই',
    'district' => 'ঢাকা',
    'owners' => [
        [
            'name' => 'আয়ুবুর রহমান',
            'father_or_husband_name' => 'মোঃ বারেক',
            'mothers_name' => 'মোছাঃ রহিমা খাতুন',
            'address' => 'গ্রাম-সেনাইল, থানা-ধামরাই, জেলা-ঢাকা',
            'share' => '৪ আনা',
            'nid_number' => '1234567890',
        ],
    ],
    'dag_number' => '125',
    'land_type' => 'ধানি',
    'total_land_area' => '0.50 শতক',
    'dag_area_shottangsho' => '50',
    'khash_area_shottangsho' => '25',
    'inherited_area' => '0.125 শতক',
    'agriculture_area' => '0.25',
    'non_agriculture_area' => '0.25',
    'total_dag_area' => '0.50',
    'single_area' => '0.25',
    'khash_area' => '0.25',
    'land_development_tax' => '150 টাকা',
    'total_dag_area_summary_acre' => '0.50',
    'total_dag_area_summary_shottangsho' => '50',
    'total_land_words' => '0 একর 0.50 শতক',
    'remarks' => 'পূর্ববর্তী খতিয়ান: 342',
    'application_no' => '7432601',
    'application_date' => '21-08-2023',
    'mutation_case_no' => '2,832(IX-I)/2023-24',
    'online_dcr_no' => '23261420502832',
    'proposed_date' => '24/09/2023',
    'kanungo_date' => '25/09/2023',
    'approved_date' => '28/09/2023',
];

$templateData = [
    'data' => $testData,
    'qr_data_uri' => null,
    'images' => [
        'sign_rezaul' => '/assets/images/kharij/sign-rezaul.png',
        'sign_julfikar' => '/assets/images/kharij/sign-julfikar.png',
        'sign_aminul' => '/assets/images/kharij/sign-aminul.png',
        'organisation_seal' => '/assets/images/kharij/seal.png',
        'red_seal' => '/assets/images/kharij/red-seal.png',
    ],
];

echo "1. Rendering Twig template...\n";
$html = $twig->render('pdf/kharij-form.twig', $templateData);
echo "   HTML: " . strlen($html) . " bytes\n";

file_put_contents(__DIR__ . '/../public_html/test-pdf-preview.html', $html);
echo "   ✓ Saved: test-pdf-preview.html\n";

echo "2. Generating PDF via mPDF...\n";
require_once __DIR__ . '/../app/Helpers/MpdfHelper.php';

$mpdf = mpdf_create_instance([
    'format' => 'A4',
    'orientation' => 'L',
    'margin_left' => 10, 'margin_right' => 10,
    'margin_top' => 6, 'margin_bottom' => 10,
]);
if (!$mpdf) { die("mPDF init failed\n"); }
mpdf_apply_runtime_optimizations($mpdf);
if (method_exists($mpdf, 'SetDefaultFontSize')) $mpdf->SetDefaultFontSize(9.5);

$mpdf->SetHTMLFooter('<table width="100%"><tr><td width="333px"></td><td width="445px" align="center"></td><td width="333px" align="right">{PAGENO}/{nbpg}</td></tr></table>');
$mpdf->SetTitle('খারিজ ফর্ম - টেস্ট');
$mpdf->WriteHTML(mpdf_optimize_html($html));

$outputPath = __DIR__ . '/../public_html/test-kharij-output.pdf';
$mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);
echo "   ✓ Saved: test-kharij-output.pdf\n";
$size = file_exists($outputPath) ? filesize($outputPath) : 0;
echo "   Size: " . $size . " bytes\n";

echo "\n✅ Test PDF generated!\n";
