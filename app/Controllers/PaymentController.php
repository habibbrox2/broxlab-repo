<?php

/**
 * controllers/PaymentController.php
 *
 * Dedicated payment routes:
 * - Gateway callbacks (bKash)
 * - Admin payment receipts list/export
 */

$paymentApplicationModel = new ServiceApplicationModel($mysqli);
$paymentServiceModel = new ServiceModel($mysqli);
$paymentUserModel = new UserModel($mysqli);
require_once __DIR__ . '/../Helpers/ServiceOpsHelper.php';

/**
 * bKash gateway callback (POST)
 * POST /payments/bkash/callback
 */
$router->post('/payments/bkash/callback', function () use ($mysqli, $paymentApplicationModel) {
    header('Content-Type: application/json');

    $input = [];
    $raw = file_get_contents('php://input');
    $isSns = false;
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
            if (!empty($decoded['Type']) && !empty($decoded['Message']) && !empty($decoded['Signature'])) {
                $isSns = true;
            }
        }
    }
    $input = array_merge($_POST ?? [], $input, $_GET ?? []);

    $paymentID = '';
    if ($isSns) {
        if (!verifySnsSignature($input)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
            return;
        }

        $msg = json_decode((string)($input['Message'] ?? ''), true);
        if (is_array($msg)) {
            $paymentID = trim((string)($msg['trxID'] ?? $msg['trxId'] ?? $msg['paymentID'] ?? $msg['paymentId'] ?? $msg['merchantInvoiceNumber'] ?? ''));
        }
    } else {
        $paymentID = trim((string)($input['paymentID'] ?? $input['paymentId'] ?? $input['payment_id'] ?? ''));
    }

    $result = processBkashPaymentId($mysqli, $paymentApplicationModel, $paymentID);
    http_response_code((int)$result['code']);
    echo json_encode($result['payload']);
});

/**
 * bKash gateway callback (GET)
 * GET /payments/bkash/callback
 */
$router->get('/payments/bkash/callback', function () use ($mysqli, $paymentApplicationModel) {
    header('Content-Type: application/json');
    $paymentID = trim((string)(($_GET['paymentID'] ?? $_GET['paymentId'] ?? $_GET['payment_id'] ?? '')));

    $result = processBkashPaymentId($mysqli, $paymentApplicationModel, $paymentID);
    http_response_code((int)$result['code']);
    echo json_encode($result['payload']);
});

/**
 * Confirm manual payment from receipts list.
 * POST /admin/applications/receipts/{id}/confirm
 */
$router->post('/admin/applications/receipts/{id}/confirm', ['middleware' => ['auth', 'admin_only']], function ($id) use ($paymentApplicationModel) {
    $returnTo = resolveAdminReceiptsReturnUrl();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect($returnTo);
    }

    $applicationId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $ok = $paymentApplicationModel->updateApplicationPaymentStatus(
        $applicationId,
        'paid',
        $adminId,
        'Manual payment confirmed from receipts list'
    );

    if ($ok) {
        showMessage('Manual payment confirmed successfully', 'success');
    } else {
        showMessage('Failed to confirm manual payment', 'danger');
    }
    redirect($returnTo);
});

/**
 * Update payment status from receipts list.
 * POST /admin/applications/receipts/{id}/payment-status
 */
$router->post('/admin/applications/receipts/{id}/payment-status', ['middleware' => ['auth', 'admin_only']], function ($id) use ($paymentApplicationModel) {
    $returnTo = resolveAdminReceiptsReturnUrl();
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showMessage('Invalid request token', 'danger');
        redirect($returnTo);
    }

    $allowedStatuses = [
        'pending',
        'pending_gateway',
        'initiated',
        'submitted',
        'processing',
        'paid',
        'completed',
        'failed',
        'cancelled',
        'canceled',
        'rejected',
        'refunded',
        'unknown',
    ];

    $status = strtolower(trim((string)($_POST['payment_status'] ?? '')));
    if (!in_array($status, $allowedStatuses, true)) {
        showMessage('Invalid payment status selected', 'danger');
        redirect($returnTo);
    }

    $applicationId = (int)$id;
    $adminId = AuthManager::getCurrentUserId();
    $note = trim((string)($_POST['note'] ?? ''));
    $ok = $paymentApplicationModel->updateApplicationPaymentStatus($applicationId, $status, $adminId, $note);

    if ($ok) {
        showMessage('Payment status updated to ' . ucfirst($status), 'success');
    } else {
        showMessage('Failed to update payment status', 'danger');
    }
    redirect($returnTo);
});

/**
 * Download single receipt as PDF (mPDF).
 * GET /admin/applications/receipts/{id}/download
 */
$router->get('/admin/applications/receipts/{id}/download', ['middleware' => ['auth', 'admin_only']], function ($id) use ($twig, $paymentApplicationModel, $paymentServiceModel, $paymentUserModel) {
    $applicationId = (int)$id;
    if ($applicationId <= 0) {
        http_response_code(400);
        echo 'Invalid application id';
        return;
    }

    $result = $paymentApplicationModel->getReceipts([
        'application_id' => $applicationId
    ], 1, 0);
    $receipt = $result['data'][0] ?? null;
    if (!$receipt) {
        http_response_code(404);
        echo 'Receipt not found';
        return;
    }

    $service = $paymentServiceModel->findById((int)($receipt['service_id'] ?? 0));
    $applicant = $paymentUserModel->findById((int)($receipt['user_id'] ?? 0));

    $applicationData = is_array($receipt['application_data'] ?? null) ? $receipt['application_data'] : [];
    $paymentInfo = [];
    foreach ([
        $applicationData['_payment'] ?? null,
        $applicationData['_Payment'] ?? null,
        $applicationData['payment'] ?? null,
        $applicationData['Payment'] ?? null,
        $applicationData['payment_info'] ?? null,
        $applicationData['PaymentInfo'] ?? null,
    ] as $candidatePayment) {
        if (is_array($candidatePayment)) {
            $paymentInfo = $candidatePayment;
            break;
        }
    }

    if (!isset($paymentInfo['transaction_id']) && !empty($receipt['payment_transaction_id'])) {
        $paymentInfo['transaction_id'] = $receipt['payment_transaction_id'];
    }
    if (!isset($paymentInfo['gateway']) && !empty($receipt['payment_gateway'])) {
        $paymentInfo['gateway'] = $receipt['payment_gateway'];
    }
    if (!isset($paymentInfo['method']) && !empty($receipt['payment_method'])) {
        $paymentInfo['method'] = $receipt['payment_method'];
    }
    if (!isset($paymentInfo['amount']) && isset($receipt['payment_amount']) && $receipt['payment_amount'] !== null) {
        $paymentInfo['amount'] = $receipt['payment_amount'];
    }
    if (!isset($paymentInfo['currency']) && !empty($receipt['payment_currency'])) {
        $paymentInfo['currency'] = $receipt['payment_currency'];
    }
    if (!isset($paymentInfo['status']) && !empty($receipt['payment_status'])) {
        $paymentInfo['status'] = $receipt['payment_status'];
    }

    $formFieldLabels = [];
    $serviceFormFields = $paymentServiceModel->getFormFields((int)($receipt['service_id'] ?? 0));
    foreach ($serviceFormFields as $field) {
        $fieldName = trim((string)($field['form_field_name'] ?? ''));
        if ($fieldName === '') {
            continue;
        }
        $fieldLabel = trim((string)($field['label'] ?? ''));
        $formFieldLabels[$fieldName] = $fieldLabel !== '' ? $fieldLabel : $fieldName;
    }

    $receiptHtml = $twig->render('pdf/service-receipt-bn.twig', [
        'application' => $receipt,
        'service' => $service,
        'applicant' => $applicant,
        'payment' => $paymentInfo,
        'form_field_labels' => $formFieldLabels,
        'generated_at' => date('Y-m-d H:i:s')
    ]);

    $pdfFilename = 'service-application-receipt-' . $applicationId . '-' . date('Ymd_His') . '.pdf';

    generatePdf($receiptHtml, $pdfFilename, [
        'title' => 'Service Application Receipt #' . $applicationId,
        'fail_message' => 'Failed to generate PDF receipt.',
    ]);
});

$router->get('/admin/applications/receipts', ['middleware' => ['auth', 'admin_only']], function () use ($twig, $paymentApplicationModel) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $filters = [];
    if (!empty($_GET['q'])) {
        $filters['q'] = sanitizeInput($_GET['q']);
    }
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = sanitizeInput($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = sanitizeInput($_GET['date_to']);
    }
    if (!empty($_GET['payment_status'])) {
        $filters['payment_status'] = sanitizeInput($_GET['payment_status']);
    }

    $result = $paymentApplicationModel->getReceipts($filters, $limit, $offset);

    echo $twig->render('admin/applications/receipts.twig', [
        'title' => 'Service Application Receipts',
        'applications' => $result['data'],
        'total' => $result['total'],
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil(($result['total'] ?? 0) / $limit),
        'filters' => $filters,
        'current_page' => 'applications-receipts',
        'breadcrumb' => [
            ['url' => '/admin/dashboard', 'label' => 'Admin'],
            ['url' => '/admin/applications', 'label' => 'Applications'],
            ['label' => 'Receipts']
        ]
    ]);
});

/**
 * Export receipts as CSV (honors same filters)
 * GET /admin/applications/receipts/export/csv
 */
$router->get('/admin/applications/receipts/export/csv', ['middleware' => ['auth', 'admin_only']], function () use ($paymentApplicationModel) {
    $filters = [];
    if (!empty($_GET['q'])) {
        $filters['q'] = sanitizeInput($_GET['q']);
    }
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = sanitizeInput($_GET['date_from']);
    }
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = sanitizeInput($_GET['date_to']);
    }
    if (!empty($_GET['payment_status'])) {
        $filters['payment_status'] = sanitizeInput($_GET['payment_status']);
    }

    $result = $paymentApplicationModel->getReceipts($filters, 10000, 0);
    $rows = $result['data'] ?? [];

    $filename = 'application-receipts-' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    echo "\xEF\xBB\xBF";

    fputcsv($out, ['id', 'transaction_id', 'gateway', 'amount', 'currency', 'status', 'user_name', 'user_email', 'service_name', 'submitted_at', 'gateway_response']);

    foreach ($rows as $r) {
        $p = [];
        if (!empty($r['application_data']) && is_array($r['application_data'])) {
            $p = $r['application_data']['_payment']
                ?? $r['application_data']['_Payment']
                ?? $r['application_data']['payment']
                ?? $r['application_data']['Payment']
                ?? ($r['application_data']['payment_info'] ?? ($r['application_data']['PaymentInfo'] ?? []));
        }

        $transaction_id = $r['payment_transaction_id'] ?? ($p['transaction_id'] ?? ($p['paymentID'] ?? ''));
        $gateway = $r['payment_gateway'] ?? ($r['payment_method'] ?? ($p['gateway'] ?? ($p['method'] ?? '')));
        $amount = isset($r['payment_amount']) && $r['payment_amount'] !== null ? (string)$r['payment_amount'] : (isset($p['amount']) ? (string)$p['amount'] : '');
        $currency = $r['payment_currency'] ?? ($p['currency'] ?? '');
        $status = $r['payment_status'] ?? ($p['status'] ?? ($r['status'] ?? ''));
        $gateway_response = $r['payment_gateway_response'] ?? '';
        if ($gateway_response === '' && !empty($p['gateway_response'])) {
            $gateway_response = is_string($p['gateway_response']) ? $p['gateway_response'] : json_encode($p['gateway_response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        fputcsv($out, [
            $r['id'] ?? '',
            $transaction_id,
            $gateway,
            $amount,
            $currency,
            $status,
            $r['user_name'] ?? '',
            $r['user_email'] ?? '',
            $r['service_name'] ?? '',
            $r['created_at'] ?? '',
            $gateway_response
        ]);
    }

    fclose($out);
    exit;
});
