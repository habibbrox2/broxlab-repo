<?php

/**
 * app/Services/CvPaymentService.php
 * 
 * CV Premium Template Payment Service
 * Handles bKash Checkout API integration for BDT 50 premium template purchases.
 * Supports both bKash Checkout API (auto-confirm) and manual payment flow (fallback).
 */
 
class CvPaymentService
{
    private mysqli $mysqli;
    private ?BkashGateway $bkashGateway = null;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    private function getBkashGateway(): BkashGateway
    {
        if ($this->bkashGateway === null) {
            require_once dirname(__DIR__, 1) . '/Services/BkashGateway.php';
            $this->bkashGateway = new BkashGateway($this->mysqli);
        }
        return $this->bkashGateway;
    }

    public function getCallbackBaseUrl(): string
    {
        if (function_exists('getAppUrl')) {
            return rtrim(getAppUrl(), '/');
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    public function validatePremiumTemplate(string $slug): bool
    {
        $premiumSlugs = ['executive'];
        if (in_array($slug, $premiumSlugs, true)) {
            return true;
        }
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM cv_templates WHERE slug = ? AND is_premium = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (bool)$exists;
    }

    public function getTemplatePrice(string $slug): float
    {
        $prices = ['executive' => 50];
        return (float)($prices[$slug] ?? 50);
    }

    public function checkExistingPurchase(int $userId, string $slug): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, status FROM cv_template_purchases 
             WHERE user_id = ? AND template_slug = ? AND deleted_at IS NULL 
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bind_param('is', $userId, $slug);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            return ['exists' => true, 'status' => $existing['status'], 'purchase_id' => (int)$existing['id']];
        }
        return ['exists' => false, 'status' => null, 'purchase_id' => null];
    }

    public function createPurchase(int $userId, string $slug, string $method, string $phone, float $amount = 50): ?int
    {
        $stmt = $this->mysqli->prepare(
            "INSERT INTO cv_template_purchases 
             (user_id, template_slug, amount, currency, payment_method, phone_number, status) 
             VALUES (?, ?, ?, 'BDT', ?, ?, 'pending')"
        );
        $stmt->bind_param('isdss', $userId, $slug, $amount, $method, $phone);
        $ok = $stmt->execute();
        $id = $ok ? (int)$stmt->insert_id : null;
        $stmt->close();
        if ($id) {
            logActivity(
                "Premium Template Purchase Initiated",
                "cv-template-purchase", $id,
                ['user_id' => $userId, 'template' => $slug, 'method' => $method, 'amount' => $amount],
                'success'
            );
        }
        return $id;
    }

    /**
     * Initiate bKash Checkout API payment.
     * Creates a purchase record and calls bKash API to get a checkout URL.
     */
    public function initiateBkashCheckout(int $userId, string $slug, string $phone): array
    {
        if (!$this->validatePremiumTemplate($slug)) {
            return ['success' => false, 'error' => 'Invalid or non-premium template'];
        }

        $existing = $this->checkExistingPurchase($userId, $slug);
        if ($existing['exists']) {
            if ($existing['status'] === 'completed') {
                return ['success' => false, 'error' => 'Already purchased', 'purchased' => true];
            }
            if ($existing['status'] === 'pending') {
                return ['success' => false, 'error' => 'Pending purchase exists', 'pending' => true];
            }
        }

        $amount = $this->getTemplatePrice($slug);
        $purchaseId = $this->createPurchase($userId, $slug, 'bkash', $phone, $amount);
        if (!$purchaseId) {
            return ['success' => false, 'error' => 'Failed to create purchase record'];
        }

        // Set up bKash checkout
        $callbackUrl = $this->getCallbackBaseUrl() . '/payments/cv/bkash/callback';
        $merchantInvoice = 'CV-' . $purchaseId . '-' . time();

        // Store gateway invoice reference
        $invStmt = $this->mysqli->prepare(
            "UPDATE cv_template_purchases SET gateway_invoice = ? WHERE id = ?"
        );
        $invStmt->bind_param('si', $merchantInvoice, $purchaseId);
        $invStmt->execute();
        $invStmt->close();

        // Call bKash Checkout API
        $result = $this->getBkashGateway()->createPayment(
            (string)$amount, 'BDT', 'sale', $merchantInvoice, $callbackUrl
        );

        if (!empty($result['success'])) {
            $bkashPaymentId = $result['paymentID'] ?? $result['data']['paymentID'] ?? '';
            $checkoutUrl = $result['data']['bkashURL'] ?? null;

            if ($bkashPaymentId) {
                $pidStmt = $this->mysqli->prepare(
                    "UPDATE cv_template_purchases SET gateway_payment_id = ?, status = 'pending_gateway' WHERE id = ?"
                );
                $pidStmt->bind_param('si', $bkashPaymentId, $purchaseId);
                $pidStmt->execute();
                $pidStmt->close();
            }

            logActivity(
                "bKash Checkout Created", "cv-template-purchase", $purchaseId,
                ['template' => $slug, 'amount' => $amount, 'paymentID' => $bkashPaymentId],
                'success'
            );

            return [
                'success' => true,
                'purchase_id' => $purchaseId,
                'amount' => $amount,
                'paymentID' => $bkashPaymentId,
                'checkout_url' => $checkoutUrl,
                'merchant_invoice' => $merchantInvoice,
            ];
        }

        // bKash API failed — fall back to manual payment flow
        logActivity(
            "bKash Checkout Failed — falling back to manual", "cv-template-purchase", $purchaseId,
            ['error' => $result['error'] ?? 'Unknown'], 'warning'
        );

        return [
            'success' => true,
            'purchase_id' => $purchaseId,
            'amount' => $amount,
            'checkout_failed' => true,
            'manual_fallback' => true,
            'merchant_number' => $this->getMerchantNumber('bkash'),
            'error_detail' => $result['error'] ?? 'Gateway unavailable',
        ];
    }

    /**
     * Handle bKash callback after user completes payment on bKash checkout page.
     * bKash calls back with paymentID. We execute the payment and mark as completed.
     */
    public function handleBkashCallback(array $input): array
    {
        $paymentID = trim((string)($input['paymentID'] ?? $input['paymentId'] ?? $input['payment_id'] ?? ''));
        if ($paymentID === '') {
            return ['success' => false, 'code' => 400, 'message' => 'paymentID is required'];
        }

        // Check if we already processed this payment
        $alreadyCompleted = $this->findPurchaseByGatewayPaymentId($paymentID);
        if ($alreadyCompleted && $alreadyCompleted['status'] === 'completed') {
            return [
                'success' => true, 'code' => 200,
                'message' => 'Payment already completed',
                'purchase_id' => (int)$alreadyCompleted['id'],
                'transaction_id' => $alreadyCompleted['transaction_id'] ?? $paymentID,
            ];
        }

        // Execute payment via bKash API
        $exec = $this->getBkashGateway()->executePayment($paymentID);

        if (!empty($exec['success'])) {
            $trxID = $exec['data']['trxID'] ?? $exec['data']['transactionId'] ?? $paymentID;

            // Find purchase by gateway_payment_id first
            $purchase = $this->findPurchaseByGatewayPaymentId($paymentID);
            if ($purchase) {
                $this->markCompleted($purchase['id'], $trxID, $exec['raw'] ?? $exec['data'] ?? []);
                logActivity(
                    "Premium Template Auto-Confirmed via bKash Checkout",
                    "cv-template-purchase", $purchase['id'],
                    ['template' => $purchase['template_slug'], 'trxID' => $trxID],
                    'success'
                );
                return [
                    'success' => true, 'code' => 200,
                    'message' => 'Payment completed!',
                    'purchase_id' => (int)$purchase['id'],
                    'transaction_id' => $trxID,
                ];
            }

            // Fallback: try merchantInvoiceNumber
            $merchantInvoice = $exec['data']['merchantInvoiceNumber'] ?? '';
            if ($merchantInvoice && preg_match('/^CV-(\d+)-/', $merchantInvoice, $m)) {
                $pid = (int)$m[1];
                $this->markCompleted($pid, $trxID, $exec['raw'] ?? $exec['data'] ?? []);
                logActivity(
                    "Premium Template Auto-Confirmed via bKash (invoice fallback)",
                    "cv-template-purchase", $pid,
                    ['merchant_invoice' => $merchantInvoice, 'trxID' => $trxID],
                    'success'
                );
                return [
                    'success' => true, 'code' => 200,
                    'message' => 'Payment completed!',
                    'purchase_id' => $pid,
                    'transaction_id' => $trxID,
                ];
            }

            // Purchase record not found — create one via webhook/callback orphan handling
            return [
                'success' => true, 'code' => 200,
                'message' => 'Payment confirmed but purchase record not linked. Admin will resolve.',
                'transaction_id' => $trxID,
                'paymentID' => $paymentID,
                'needs_admin_review' => true,
            ];
        }

        // Payment execution failed
        $error = $exec['error'] ?? 'Payment execution failed';
        $statusCode = ($exec['status'] ?? 500) >= 400 ? (int)$exec['status'] : 400;

        // If it's already been executed (status 200 but error), try querying status
        if ($exec['status'] === 200 || strpos($error, 'already') !== false) {
            $queryResult = $this->getBkashGateway()->queryPayment($paymentID);
            if (!empty($queryResult['success']) && ($queryResult['data']['transactionStatus'] ?? '') === 'Completed') {
                $trxID = $queryResult['data']['trxID'] ?? $paymentID;
                $purchase = $this->findPurchaseByGatewayPaymentId($paymentID);
                if ($purchase) {
                    $this->markCompleted($purchase['id'], $trxID, $queryResult['raw'] ?? []);
                    return [
                        'success' => true, 'code' => 200,
                        'message' => 'Payment verified after re-check',
                        'purchase_id' => (int)$purchase['id'],
                        'transaction_id' => $trxID,
                    ];
                }
            }
        }

        return ['success' => false, 'code' => $statusCode, 'message' => $error, 'paymentID' => $paymentID];
    }

    /**
     * Handle bKash callback redirect (GET).
     * User is redirected here after completing payment on bKash page.
     */
    public function handleCallbackRedirect(array $query): array
    {
        $status = $query['status'] ?? '';
        $paymentID = $query['paymentID'] ?? '';

        if ($status === 'success' && $paymentID !== '') {
            return $this->handleBkashCallback(['paymentID' => $paymentID]);
        }

        if ($status === 'cancel' || $status === 'failure') {
            // User cancelled or payment failed — mark purchase as cancelled
            if ($paymentID !== '') {
                $purchase = $this->findPurchaseByGatewayPaymentId($paymentID);
                if ($purchase) {
                    $updateStmt = $this->mysqli->prepare(
                        "UPDATE cv_template_purchases SET status = 'cancelled', updated_at = NOW() WHERE id = ?"
                    );
                    $updateStmt->bind_param('i', $purchase['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
            return [
                'success' => false, 'code' => 400,
                'message' => 'Payment was cancelled or failed',
                'status' => $status,
                'paymentID' => $paymentID,
            ];
        }

        return [
            'success' => false, 'code' => 400,
            'message' => 'Invalid callback parameters',
            'received' => $query,
        ];
    }

    public function markCompleted(int $purchaseId, string $transactionId, array $gatewayData = []): void
    {
        $gatewayJson = !empty($gatewayData) ? json_encode($gatewayData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $this->mysqli->prepare(
            "UPDATE cv_template_purchases 
             SET status = 'completed', transaction_id = ?, confirmed_at = NOW(), updated_at = NOW(), admin_notes = ? 
             WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->bind_param('ssi', $transactionId, $gatewayJson, $purchaseId);
        $stmt->execute();
        $stmt->close();
    }

    private function findPurchaseByGatewayPaymentId(string $paymentID): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, template_slug, status, transaction_id FROM cv_template_purchases 
             WHERE gateway_payment_id = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->bind_param('s', $paymentID);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $purchase ?: null;
    }

    public function getMerchantNumber(string $method): string
    {
        $numbers = [
            'bkash'  => '01XXXXXXXXX',
            'nagad'  => '01XXXXXXXXX',
            'rocket' => '01XXXXXXXXX',
        ];
        return $numbers[$method] ?? '01XXXXXXXXX';
    }
}
