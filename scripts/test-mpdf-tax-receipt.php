<?php
/**
 * Test script to verify mPDF can render tax-receipt.twig without errors.
 * Usage: php scripts/test-mpdf-tax-receipt.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Helpers/MpdfHelper.php';

// Simple HTML similar to what the tax-receipt template produces
$html = '<html><head>
<style>
body { font-family: solaimanlipi, sans-serif; font-size: 13px; }
table { border-collapse: collapse; width: 100%; }
td, th { border: 1px dotted #000; padding: 4px; }
</style>
</head><body>
<h2 style="text-align:center;">ভূমি উন্নয়ন কর পরিশোধ রসিদ</h2>
<p>পরীক্ষামূলক PDF — DIVISION BY ZERO TEST</p>
<table>
<tr><th colspan="8" style="text-align:center;">আদায়ের বিবরণ</th></tr>
<tr>
<td>তিন বৎসরের ঊর্ধ্বের বকেয়া</td>
<td>গত তিন বৎসরের বকেয়া</td>
<td>বকেয়ার জরিমানা</td>
<td>হাল দাবি</td>
<td>মোট দাবি</td>
<td>মোট আদায়</td>
<td>মোট বকেয়া</td>
<td>মন্তব্য</td>
</tr>
<tr>
<td>১৫২</td><td>৬৫</td><td>১০০</td><td>২০</td><td>৩৩৭</td><td>৩৩৭</td><td>০</td><td></td>
</tr>
</table>
<p style="text-align:center;">এই দাখিলা ইলেক্ট্রনিকভাবে তৈরি করা হয়েছে</p>
</body></html>';

echo "Creating mPDF instance...\n";
$mpdf = mpdf_create_instance([
    'format' => 'A4',
    'orientation' => 'P',
    'margin_left' => 5,
    'margin_right' => 5,
    'margin_top' => 4,
    'margin_bottom' => 5,
]);

if (!$mpdf) {
    echo "FAILED: mpdf_create_instance returned null\n";
    exit(1);
}

echo "mPDF instance OK\n";

// Apply optimizations (the original culprit)
if (property_exists($mpdf, 'packTableData')) $mpdf->packTableData = true;
if (property_exists($mpdf, 'simpleTables')) $mpdf->simpleTables = true;

echo "Writing HTML...\n";
$mpdf->WriteHTML($html);

echo "Generating PDF...\n";
$pdf = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

echo "SUCCESS: PDF generated - " . strlen($pdf) . " bytes\n";
echo "No 'Division by zero' error occurred!\n";

// Also test with the actual Twig template
require __DIR__ . '/../Config/Twig.php';
if (function_exists('getTwigEnvironment')) {
    try {
        $twig = getTwigEnvironment();
        echo "\nTesting Twig template render...\n";
        $templateData = [
            'data' => [
                'khata_number' => 'TEST-001',
                'mouza' => 'বোয়াইল',
                'jl_number' => '214',
                'upazila' => 'ধামরাই',
                'district' => 'ঢাকা',
                'holding_number' => '2026-100020',
                'dakhila_serial' => '261426126601',
                'total_amount_words' => 'তিন শত টাকা',
                'last_payment_year' => '২০২৫-২০২৬',
                'challan_number' => '2526-0057',
                'receipt_date' => '৭ জ্যৈষ্ঠ',
                'receipt_date_en' => '২১ মে',
                'dakhila_page' => '১/১',
                'owners' => [['name' => 'পরীক্ষামূলক', 'share' => '১']],
                'dags' => [['dag_number' => '247', 'land_type' => 'নাল', 'dag_area_shottangsho' => '১০']],
                'tax_arrears_over_3_years' => '১৫২',
                'tax_arrears_last_3_years' => '৬৫',
                'tax_penalty' => '১০০',
                'tax_current_demand' => '২০',
                'tax_total_demand' => '৩৩৭',
                'tax_total_collected' => '৩৩৭',
                'tax_total_arrears' => '০',
                'total_dag_area_summary_shottangsho' => '১০',
            ],
            'qr_data_uri' => null,
            'hash' => 'test-hash',
            'verification_url' => 'http://localhost:8080/ldtax-gov-bd/dakhila-print/test-hash',
        ];
        $html = $twig->render('pdf/tax-receipt.twig', $templateData);
        echo "Twig render OK: " . strlen($html) . " bytes\n";
        
        // Now render with mPDF
        $mpdf2 = mpdf_create_instance([
            'format' => 'A4', 'orientation' => 'P',
            'margin_left' => 5, 'margin_right' => 5,
            'margin_top' => 4, 'margin_bottom' => 5,
        ]);
        if ($mpdf2) {
            if (property_exists($mpdf2, 'packTableData')) $mpdf2->packTableData = true;
            if (property_exists($mpdf2, 'simpleTables')) $mpdf2->simpleTables = true;
            $mpdf2->WriteHTML($html);
            $pdf2 = $mpdf2->Output('', \Mpdf\Output\Destination::STRING_RETURN);
            echo "SUCCESS: Full template PDF generated - " . strlen($pdf2) . " bytes\n";
            echo "No 'Division by zero' error!\n";
        }
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        exit(1);
    }
} else {
    echo "\nTwig environment function not found (likely needs app bootstrap). Skipping full template test.\n";
}
