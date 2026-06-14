<?php
// controllers/MonetizationController.php
// Online Earning: donations (bKash, Nagad, Card), ad-placement management,
// sponsored-post labels, and a public + admin revenue dashboard.

/** @var Router $router */
/** @var \Twig\Environment $twig */
/** @var \mysqli $mysqli */

$adModel      = new AdvertisementModel($mysqli);
$settingsModel = new AppSettings($mysqli);

// ================================================================
//  PUBLIC  — Donation page
// GET /donate
// ================================================================
$router->get('/donate', function () use ($twig, $adModel, $settingsModel) {
    $settings  = $settingsModel->getSettings();
    $donTotal  = $adModel->getDonationTotal();        // total across all completed
    $donCount  = $adModel->getDonationCount();        // number of contributors
    $recent    = $adModel->getRecentDonations(6);     // last 6 completed

    echo $twig->render('public/donate.twig', [
        'title'               => 'Support Us',
        'donation_total'      => $donTotal,
        'donation_count'      => $donCount,
        'recent_donations'    => $recent,
        'settings'            => $settings,
    ]);
});

// ================================================================
//  PUBLIC  — Donation form submit
// POST /donate
// ================================================================
$router->post('/donate', function () use ($mysqli, $twig, $adModel, $settingsModel) {
    $settings = $settingsModel->getSettings();

    $name      = trim((string)($_POST['donor_name'] ?? ''));
    $email     = trim((string)($_POST['donor_email'] ?? ''));
    $phone     = trim((string)($_POST['donor_phone'] ?? ''));
    $amount    = trim((string)($_POST['donation_amount'] ?? ''));
    $method    = strtolower(trim((string)($_POST['donation_method'] ?? '')));
    $note      = trim((string)($_POST['donor_note'] ?? ''));
    $trxId     = trim((string)($_POST['bkash_trxid'] ?? ''));
    $stripePi  = trim((string)($_POST['stripe_payment_intent'] ?? ''));

    $errors = [];

    if ($name === '')  $errors[] = 'Name is required';
    if ($phone === '') $errors[] = 'Phone number is required';
    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0)
        $errors[] = 'Valid donation amount is required';

    $allowedMethods = ['bkash', 'nagad', 'stripe', 'bank', 'handcash'];
    if (!in_array($method, $allowedMethods, true))
        $errors[] = 'Please select a valid payment method';

    if (!empty($errors)) {
        echo $twig->render('public/donate.twig', [
            'title'         => 'Support Us',
            'errors'        => $errors,
            'old'           => $_POST,
            'donation_total' => $adModel->getDonationTotal(),
            'donation_count' => $adModel->getDonationCount(),
            'recent_donations' => $adModel->getRecentDonations(6),
            'settings'      => $settings,
        ]);
        return;
    }

    $id = $adModel->createDonation([
        'name'         => $name,
        'email'        => $email ?: null,
        'phone'        => $phone,
        'amount'       => (float)$amount,
        'currency'     => 'BDT',
        'method'       => $method,
        'bkash_trxid'  => $trxId ?: null,
        'nagad_trxid'  => trim((string)($_POST['nagad_trxid'] ?? '')),
        'stripe_pi'    => $stripePi ?: null,
        'note'         => $note ?: null,
    ]);

    if ($id === false) {
        echo $twig->render('public/donate.twig', [
            'title'         => 'Support Us',
            'server_error'  => 'Something went wrong. Please try again.',
            'old'           => $_POST,
            'donation_total'=> $adModel->getDonationTotal(),
            'donation_count'=> $adModel->getDonationCount(),
            'recent_donations' => $adModel->getRecentDonations(6),
            'settings'      => $settings,
        ]);
        return;
    }

    logActivity("Donation Submitted", "donation", $id,
        ['amount' => $amount, 'method' => $method, 'name' => $name], 'success');

    echo $twig->render('public/donate.twig', [
        'title'          => 'Support Us',
        'donation_total' => $adModel->getDonationTotal(),
        'donation_count' => $adModel->getDonationCount(),
        'recent_donations' => $adModel->getRecentDonations(6),
        'settings'       => $settings,
        'success'        => "Thank you for your generous donation of ৳" . number_format((float)$amount, 2),
    ]);
});

// ================================================================
//  PUBLIC  — bKash Donate callback (SNS or direct)
// GET/POST /donate/bkash/callback
// ================================================================
$router->get('/donate/bkash/callback', function () use ($mysqli, $twig, $adModel, $settingsModel) {
    header('Content-Type: application/json');
    $payload = getBkashCallbackPayload($mysqli, $adModel);
    http_response_code($payload['httpCode'] ?? 200);
    echo json_encode($payload['data'], JSON_UNESCAPED_UNICODE);
});
$router->post('/donate/bkash/callback', function () use ($mysqli, $twig, $adModel, $settingsModel) {
    header('Content-Type: application/json');
    $payload = getBkashCallbackPayload($mysqli, $adModel);
    http_response_code($payload['httpCode'] ?? 200);
    echo json_encode($payload['data'], JSON_UNESCAPED_UNICODE);
});

/**
 * Shared bKash callback handler
 */
function getBkashCallbackPayload(mysqli $mysqli, AdvertisementModel $adModel): array
{
    $rawPost = file_get_contents('php://input');
    $isSns   = false;
    $input   = [];

    if ($rawPost) {
        $dec = json_decode($rawPost, true);
        if (is_array($dec)) {
            $input = $dec;
            if (!empty($dec['Type']) && !empty($dec['Message']) && !empty($dec['Signature']))
                $isSns = true;
        }
    }
    $input = array_merge($_GET ?? [], $_POST ?? [], $input);

    if ($isSns) {
        if (!verifySnsSignature($input)) {
            return ['httpCode' => 403, 'data' => ['success' => false, 'message' => 'SNS verification failed']];
        }
        $msg = json_decode((string)($input['Message'] ?? ''), true);
        if (is_array($msg)) $input = array_merge($input, $msg);
    }

    $trxId = trim((string)($input['trxID'] ?? $input['trxId'] ?? $input['paymentID'] ?? $input['paymentId'] ?? ''));

    if ($trxId === '') {
        return ['httpCode' => 400, 'data' => ['success' => false, 'message' => 'Missing transaction ID']];
    }

    $gateway = new BkashGateway($mysqli);
    $statusResult = $gateway->queryPayment($trxId);

    if (!$statusResult['success']) {
        return ['httpCode' => 400, 'data' => ['success' => false, 'message' => $statusResult['error'] ?? 'Query failed']];
    }

    $pData  = $statusResult['data'] ?? [];
    $status  = strtolower((string)($pData['status'] ?? ''));
    $trxAmt  = (float)($pData['amount'] ?? 0);

    $invoiceNum = trim((string)($pData['merchantInvoiceNumber'] ?? $input['merchantInvoiceNumber'] ?? ''));
    $donationId = 0;

    if ($invoiceNum !== '' && preg_match('/^don-(\d+)/', $invoiceNum, $m)) {
        $donationId = (int)$m[1];
    }

    if ($donationId > 0) {
        if ($status === 'completed' || $status === 'success' || $status === 'authorized') {
            $completed = $adModel->confirmDonation($donationId, $trxId, $trxAmt);
            $adModel->updatePaymentMeta($donationId, 'bkash_payment_id', $trxId);
            $adModel->updatePaymentMeta($donationId, 'gateway_response', json_encode($pData, JSON_UNESCAPED_UNICODE));
            if ($completed) {
                logActivity("Donation Confirmed", "donation", $donationId, ['trxId' => $trxId], 'success');
            }
            return ['httpCode' => 200, 'data' => ['success' => true, 'message' => 'Donation confirmed', 'donation_id' => $donationId, 'amount' => $trxAmt]];
        } else {
            $adModel->updateDonationStatus($donationId, 'cancelled', "bKash status: {$status}");
            return ['httpCode' => 200, 'data' => ['success' => false, 'message' => "Donation not completed: {$status}", 'donation_id' => $donationId]];
        }
    }

    return ['httpCode' => 200, 'data' => ['success' => true, 'message' => 'Transaction logged but no matching donation found', 'trxId' => $trxId, 'amount' => $trxAmt]];
}

// ================================================================
//  ADMIN  — Revenue Dashboard
// GET /admin/revenue
// ================================================================
$router->get('/admin/revenue', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $adModel, $settingsModel) {
    $settings  = $settingsModel->getSettings();
    $donTotal  = $adModel->getDonationTotal();
    $donCount  = $adModel->getDonationCount();
    $adTotal   = $adModel->getAdRevenueTotal();

    $don  = $adModel->getMonthlyRevenue('donation', 12);
    $paid  = $adModel->getMonthlyRevenue('advertisement', 12);
    $spp  = $adModel->getMonthlyRevenue('sponsored_post', 12);

    $don  = is_array($don)  ? $don  : [];
    $adMonths  = is_array($paid) ? $paid  : [];
    $spMonths  = is_array($spp)  ? $spp  : [];

    $months = [];
    foreach (range(11, 0) as $i) {
        $months[] = date('M Y', strtotime("-$i month"));
    }

    $donSeries  = [];
    $adSeries  = [];
    $spSeries  = [];

    $monthKey = static function (string $m): string {
        return date('Y-m', strtotime($m));
    };
    $donMap   = [];
    $adMap    = [];
    $spMap    = [];

    foreach ($don  as $r) { $donMap[$monthKey($r['period'])] = (float)($r['total'] ?? 0); }
    foreach ($adMonths  as $r) { $adMap[$monthKey($r['period'])]  = (float)($r['total'] ?? 0); }
    foreach ($spMonths  as $r) { $spMap[$monthKey($r['period'])]  = (float)($r['total'] ?? 0); }

    foreach ($months as $m) {
        $k = $monthKey($m);
        $donSeries[]  = $donMap[$k] ?? 0;
        $adSeries[]   = $adMap[$k] ?? 0;
        $spSeries[]   = $spMap[$k] ?? 0;
    }

    echo $twig->render('admin/revenue/dashboard.twig', [
        'title'          => 'Revenue Dashboard',
        'donation_total' => $donTotal,
        'donation_count' => $donCount,
        'ad_revenue'     => $adTotal,
        'total_revenue'  => $donTotal + $adTotal,
        'chart_months'   => $months,
        'donation_series'=> $donSeries,
        'ad_series'      => $adSeries,
        'sponsored_series'=> $spSeries,
        'settings'       => $settings,
    ]);
});

// ================================================================
//  ADMIN  — Donations management list
// GET /admin/revenue/donations
// ================================================================
$router->get('/admin/revenue/donations', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $adModel) {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(100, (int)($_GET['limit'] ?? 20)));
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    $search = trim((string)($_GET['search'] ?? ''));

    $filters = [];
    if ($status !== '' && in_array($status, ['pending','processing','completed','failed','cancelled','refunded'], true)) {
        $filters['status'] = $status;
    }
    if ($search !== '') {
        $filters['search'] = $search;
    }

    $result = $adModel->getDonations($filters, $limit, ($page - 1) * $limit);
    $total  = $adModel->getDonationsCount($filters);

    echo $twig->render('admin/revenue/donations.twig', [
        'title'       => 'Donations',
        'donations'   => $result['data'] ?? [],
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int)ceil(($total ?? 0) / $limit),
        'filters'     => $filters,
    ]);
});

// ================================================================
//  ADMIN  — Sponsorships & Labeled Posts
// GET /admin/revenue/sponsored
// ================================================================
$router->get('/admin/revenue/sponsored', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli, $adModel) {
    $sql = "SELECT sp.*, p.title AS post_title, p.slug AS post_slug
            FROM sponsored_posts sp
            LEFT JOIN posts p ON p.id = sp.post_id
            ORDER BY sp.created_at DESC";
    $res = $mysqli->query($sql);
    $items = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    echo $twig->render('admin/revenue/sponsored.twig', [
        'title'       => 'Sponsored Posts',
        'sponsored'   => $items,
    ]);
});

// ================================================================
//  ADMIN  — Ad Placements list
// GET /admin/revenue/ads
// ================================================================
$router->get('/admin/revenue/ads', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli, $adModel) {
    $sql = "SELECT ap.*, u.username AS created_by_name
            FROM ad_placements ap
            LEFT JOIN users u ON u.id = ap.created_by
            ORDER BY ap.created_at DESC";
    $res   = $mysqli->query($sql);
    $ads   = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    $allPlacements = [
        'homepage_top', 'homepage_sidebar',
        'post_inline', 'post_sidebar',
        'category_page', 'mobile_page',
        'global',
    ];

    echo $twig->render('admin/revenue/ads.twig', [
        'title'         => 'Ad Placements',
        'placements'    => $ads,
        'all_placements'=> $allPlacements,
    ]);
});

// ================================================================
//  ADMIN  — Create / Edit Ad Placement
// POST /admin/revenue/ads/save
// ================================================================
$router->post('/admin/revenue/ads/save', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli, $adModel) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/ads');
        return;
    }

    $id         = (int)($_POST['id'] ?? 0);
    $name       = trim((string)($_POST['name'] ?? ''));
    $slotKey    = sanitize_input($_POST['slot_key'] ?? '');
    $placement  = $_POST['placement'] ?? '';
    $adCode     = trim((string)($_POST['ad_code'] ?? ''));
    $pubName    = trim((string)($_POST['publisher_name'] ?? ''));
    $pubEmail   = trim((string)($_POST['publisher_email'] ?? ''));
    $status     = $_POST['status'] ?? 'active';
    $startDate  = $_POST['start_date'] ?? null;
    $endDate    = $_POST['end_date'] ?? null;

    if ($name === '' || $slotKey === '' || $placement === '') {
        showMessage('Name, Slot Key and Placement are required', 'warning');
        redirect('/admin/revenue/ads');
        return;
    }

    $stmt = $mysqli->prepare(
        "INSERT INTO ad_placements (name, slot_key, placement, ad_code, publisher_name, publisher_email, status, start_date, end_date, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           name=VALUES(name), placement=VALUES(placement), ad_code=VALUES(ad_code),
           publisher_name=VALUES(publisher_name), publisher_email=VALUES(publisher_email),
           status=VALUES(status), start_date=VALUES(start_date), end_date=VALUES(end_date)"
    );
    $currentUser = (int)(AuthManager::getCurrentUserId() ?? 0);
    $stmt->bind_param('sssssssssi', $name, $slotKey, $placement, $adCode, $pubName, $pubEmail, $status, $startDate, $endDate, $currentUser);
    $stmt->execute();
    $stmt->close();

    showMessage(($id > 0 ? 'Updated' : 'Created') . ' ad placement successfully', 'success');
    redirect('/admin/revenue/ads');
});

// ================================================================
//  ADMIN  — Delete Ad Placement
// GET /admin/revenue/ads/delete/{id}
// ================================================================
$router->get('/admin/revenue/ads/delete/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli, $adModel) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/ads');
        return;
    }
    $id = (int)$id;
    $mysqli->query("DELETE FROM ad_placements WHERE id=" . $id);
    showMessage('Ad placement deleted', 'success');
    redirect('/admin/revenue/ads');
});

// ================================================================
//  ADMIN  — Toggle Ad Slot active/paused
// POST /admin/revenue/ads/toggle/{id}
// ================================================================
$router->post('/admin/revenue/ads/toggle/{id}', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/ads');
        return;
    }
    $id    = (int)$id;
    $row   = $mysqli->query("SELECT status FROM ad_placements WHERE id=" . $id)->fetch_assoc();
    if (!$row) {
        showMessage('Ad placement not found', 'danger');
        redirect('/admin/revenue/ads');
        return;
    }
    $newStat = $row['status'] === 'active' ? 'paused' : 'active';
    $stmt = $mysqli->prepare("UPDATE ad_placements SET status=? WHERE id=?");
    $stmt->bind_param('si', $newStat, $id);
    $stmt->execute();
    $stmt->close();
    showMessage("Ad slot " . ($newStat === 'active' ? 'activated' : 'paused'), 'success');
    redirect('/admin/revenue/ads');
});

// ================================================================
//  ADMIN  — Confirm Donation
// POST /admin/revenue/donations/{id}/confirm
// ================================================================
$router->post('/admin/revenue/donations/{id}/confirm', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli, $adModel) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/donations');
        return;
    }
    $id = (int)$id;
    $note = trim((string)($_POST['note'] ?? ''));
    $adminId = (int)(AuthManager::getCurrentUserId() ?? 0);
    $mysqli->query("UPDATE donation_payments SET status='completed', confirmed_by={$adminId}, confirmed_at=NOW() WHERE id={$id}" . ($note ? ", note='" . $mysqli->real_escape_string($note) . "'" : ""));
    showMessage('Donation marked as confirmed', 'success');
    redirect('/admin/revenue/donations');
});

// ================================================================
//  ADMIN  — Reject / Cancel Donation
// POST /admin/revenue/donations/{id}/reject
// ================================================================
$router->post('/admin/revenue/donations/{id}/reject', ['middleware' => ['auth', 'admin_only']], function ($id) use ($mysqli, $adModel) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/donations');
        return;
    }
    $id   = (int)$id;
    $note = trim((string)($_POST['note'] ?? ''));
    $mysqli->query("UPDATE donation_payments SET status='cancelled', note='" . $mysqli->real_escape_string($note) . "', updated_at=NOW() WHERE id=" . $id);
    showMessage('Donation cancelled', 'success');
    redirect('/admin/revenue/donations');
});

// ================================================================
//  ADMIN  — Revenue Settings
// GET /admin/revenue/settings
// ================================================================
$router->get('/admin/revenue/settings', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $mysqli) {
    $rows = $mysqli->query("SELECT * FROM revenue_settings ORDER BY category, setting_key")->fetch_all(MYSQLI_ASSOC);
    $settings = [];
    foreach ($rows as $r) {
        $settings[$r['setting_key']] = $r['setting_value'];
    }
    echo $twig->render('admin/revenue/settings.twig', [
        'title'    => 'Revenue Settings',
        'settings' => $settings,
    ]);
});

// ================================================================
//  ADMIN  — Save Revenue Settings
// POST /admin/revenue/settings
// ================================================================
$router->post('/admin/revenue/settings', ['middleware' => ['auth', 'admin_only']], function () use ($mysqli) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect('/admin/revenue/settings');
        return;
    }
    $updatedBy = (int)(AuthManager::getCurrentUserId() ?? 0);

    $rawKeys = $_POST['setting_key'] ?? [];
    $rawVals = $_POST['setting_value'] ?? [];
    $rawTypes = $_POST['setting_type'] ?? [];
    $rawCats  = $_POST['category'] ?? [];

    if (!is_array($rawKeys)) $rawKeys = [$rawKeys];

    foreach ($rawKeys as $idx => $key) {
        $key   = trim((string)$key);
        $val   = trim((string)($rawVals[$idx] ?? ''));
        $type  = $rawTypes[$idx] ?? 'text';
        $cat   = $rawCats[$idx] ?? 'general';

        if ($key === '') continue;

        $stmt = $mysqli->prepare(
            "INSERT INTO revenue_settings (setting_key, setting_value, setting_type, category, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW()"
        );
        $stmt->bind_param('ssss', $key, $val, $type, $cat);
        $stmt->execute();
        $stmt->close();
    }

    showMessage('Revenue settings saved successfully', 'success');
    redirect('/admin/revenue/settings');
});

// ================================================================
//  PUBLIC  — Get active ad by slot_key (JSON API)
// GET  /api/ads/{slot_key}
// ================================================================
$router->get('/api/ads/{slot_key}', function ($slot_key = '') use ($mysqli, $adModel) {
    header('Content-Type: application/json; charset=utf-8');
    $slot_key = sanitize_input((string)$slot_key);
    $ad       = $adModel->getActiveAdBySlot($slot_key);

    if (!$ad) {
        echo json_encode(['success' => false, 'has_ad' => false]);
        exit;
    }

    if (!empty($ad['id'])) {
        $mysqli->query("UPDATE ad_placements SET impressions=impressions+1 WHERE id=" . (int)$ad['id']);
    }

    echo json_encode([
        'success'      => true,
        'has_ad'       => true,
        'ad_code'      => $ad['ad_code'],
        'slot_key'     => $ad['slot_key'],
        'name'         => $ad['name'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// ================================================================
//  PUBLIC  — Track ad click
// POST /api/ads/{id}/click
// ================================================================
$router->post('/api/ads/{id}/click', function ($id = 0) use ($mysqli) {
    header('Content-Type: application/json; charset=utf-8');
    $id = (int)$id;
    $mysqli->query("UPDATE ad_placements SET clicks=clicks+1 WHERE id=" . $id);
    echo json_encode(['success' => true]);
    exit;
});
