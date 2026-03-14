<?php
declare(strict_types=1);

/**
 * Service/payment helper operations shared by multiple controllers.
 *
 * Canonical naming in this file uses camelCase.
 * Legacy snake_case wrappers are kept for backward compatibility.
 */

if (!function_exists('sanitizeServiceRedirectUrl')) {
    /**
     * Validate and normalize service redirect URL.
     * Allows absolute internal paths and http/https URLs.
     */
    function sanitizeServiceRedirectUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^/(?!/)#', $url)) {
            return $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}

if (!function_exists('brox_upload_web_path_to_fs_path')) {
    /**
     * Resolve upload public path to filesystem path.
     *
     * Legacy naming kept intentionally (`brox_*`) for compatibility.
     */
    function brox_upload_web_path_to_fs_path(?string $webPath): ?string
    {
        $webPath = trim((string)$webPath);
        if ($webPath === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $webPath);
        $uploadsBaseUrl = function_exists('brox_get_uploads_base_url')
            ? brox_get_uploads_base_url()
            : '/uploads';
        $uploadsBaseUrlNoSlash = ltrim($uploadsBaseUrl, '/');

        if (strpos($normalized, $uploadsBaseUrl . '/') === 0) {
            $relative = substr($normalized, strlen($uploadsBaseUrl . '/'));
        } elseif (strpos($normalized, $uploadsBaseUrlNoSlash . '/') === 0) {
            $relative = substr($normalized, strlen($uploadsBaseUrlNoSlash . '/'));
        } else {
            return null;
        }

        $basePath = function_exists('brox_get_uploads_base_path')
            ? brox_get_uploads_base_path()
            : '';
        if ($basePath === '') {
            return null;
        }

        $relative = ltrim($relative, '/');
        return rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}

if (!function_exists('verifySnsSignature')) {
    /**
     * Verify SNS-style callback signature.
     */
    function verifySnsSignature(array $payload): bool
    {
        if (empty($payload['Signature']) || empty($payload['SigningCertURL']) || empty($payload['SignatureVersion'])) {
            return false;
        }

        if ((string)$payload['SignatureVersion'] !== '1') {
            return false;
        }

        $certUrl = (string)$payload['SigningCertURL'];
        $u = parse_url($certUrl);
        if ($u === false || !in_array(strtolower((string)($u['scheme'] ?? '')), ['https'], true)) {
            return false;
        }

        $host = strtolower((string)($u['host'] ?? ''));
        if (strpos($host, 'amazonaws.com') === false && strpos($host, 'bka.sh') === false && strpos($host, 'bkash') === false) {
            return false;
        }

        $cert = @file_get_contents($certUrl);
        if ($cert === false) {
            return false;
        }

        $type = (string)($payload['Type'] ?? '');
        $stringToSign = '';
        if ($type === 'Notification') {
            $fields = ['Message', 'MessageId'];
            if (!empty($payload['Subject'])) {
                $fields[] = 'Subject';
            }
            $fields = array_merge($fields, ['Timestamp', 'TopicArn', 'Type']);
        } elseif ($type === 'SubscriptionConfirmation' || $type === 'UnsubscribeConfirmation') {
            $fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        } else {
            return false;
        }

        foreach ($fields as $f) {
            if (isset($payload[$f])) {
                $stringToSign .= $f . "\n" . $payload[$f] . "\n";
            }
        }

        $signature = base64_decode((string)$payload['Signature']);
        if ($signature === false) {
            return false;
        }

        $pubkey = openssl_get_publickey($cert);
        if ($pubkey === false) {
            return false;
        }

        $verified = openssl_verify($stringToSign, $signature, $pubkey, OPENSSL_ALGO_SHA1);
        openssl_free_key($pubkey);
        return $verified === 1;
    }
}

if (!function_exists('processBkashPaymentId')) {
    /**
     * Execute/query bKash payment and synchronize application payment status.
     *
     * @return array{code:int,payload:array}
     */
    function processBkashPaymentId(mysqli $mysqli, ServiceApplicationModel $appModel, string $paymentId): array
    {
        if ($paymentId === '') {
            return [
                'code' => 400,
                'payload' => ['success' => false, 'message' => 'paymentID is required']
            ];
        }

        try {
            require_once __DIR__ . '/BkashGateway.php';
            $bk = new BkashGateway($mysqli);

            $exec = $bk->executePayment($paymentId);
            if (!empty($exec['success'])) {
                $status = 'paid';
                $paymentInfo = [
                    'transaction_id' => $paymentId,
                    'gateway' => 'bkash',
                    'status' => $status,
                    'gateway_response' => $exec['raw'] ?? $exec['data'] ?? $exec,
                    'completed_at' => date('Y-m-d H:i:s')
                ];
            } else {
                $q = $bk->queryPayment($paymentId);
                $ok = !empty($q['success']);
                $status = $ok ? ($q['data']['paymentStatus'] ?? 'paid') : 'unknown';
                $paymentInfo = [
                    'transaction_id' => $paymentId,
                    'gateway' => 'bkash',
                    'status' => $status,
                    'gateway_response' => $q['raw'] ?? $q['data'] ?? $q,
                    'completed_at' => date('Y-m-d H:i:s')
                ];
            }

            $app = $appModel->findByPaymentTransactionId($paymentId);
            if ($app) {
                $updated = $appModel->completePayment((int)$app['id'], $paymentInfo);
                if ($updated) {
                    return [
                        'code' => 200,
                        'payload' => [
                            'success' => true,
                            'message' => 'Application payment completed',
                            'application_id' => (int)$app['id']
                        ]
                    ];
                }
            }

            return [
                'code' => 200,
                'payload' => [
                    'success' => true,
                    'message' => 'Payment info received',
                    'payment' => $paymentInfo
                ]
            ];
        } catch (Throwable $e) {
            return [
                'code' => 500,
                'payload' => [
                    'success' => false,
                    'message' => 'Error processing callback',
                    'error' => $e->getMessage()
                ]
            ];
        }
    }
}

if (!function_exists('resolveAdminReceiptsReturnUrl')) {
    /**
     * Normalize return URL for admin receipts flows.
     */
    function resolveAdminReceiptsReturnUrl(string $fallback = '/admin/applications/receipts'): string
    {
        $candidate = trim((string)($_POST['return_to'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));
        if ($candidate === '') {
            return $fallback;
        }

        $parsed = parse_url($candidate);
        if ($parsed !== false && isset($parsed['path'])) {
            $path = (string)($parsed['path'] ?? '');
            $query = isset($parsed['query']) ? ('?' . $parsed['query']) : '';
            $candidate = $path . $query;
        }

        if (strpos($candidate, '/admin/applications/receipts') !== 0) {
            return $fallback;
        }

        return $candidate;
    }
}

if (!function_exists('getServiceGuestApplicantUserId')) {
    /**
     * Return a persistent fallback user ID used for guest service applications.
     */
    function getServiceGuestApplicantUserId(mysqli $mysqli): int
    {
        static $cachedId = null;
        if ($cachedId !== null) {
            return $cachedId;
        }

        $email = 'guest.applicant@broxbhai.local';
        $username = 'guest_applicant';

        $findStmt = $mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $findStmt->bind_param('s', $email);
        $findStmt->execute();
        $existing = $findStmt->get_result()->fetch_assoc();
        $findStmt->close();

        if ($existing && !empty($existing['id'])) {
            $cachedId = (int)$existing['id'];
            return $cachedId;
        }

        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $insertStmt = $mysqli->prepare("
            INSERT INTO users (
                username, email, password, auth_provider, status, email_verified, created_at, updated_at
            ) VALUES (?, ?, ?, 'email', 'active', 1, NOW(), NOW())
        ");
        $insertStmt->bind_param('sss', $username, $email, $passwordHash);
        $ok = $insertStmt->execute();
        $guestId = $ok ? (int)$mysqli->insert_id : 0;
        $insertStmt->close();

        if ($guestId <= 0) {
            $fallback = $mysqli->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetch_assoc();
            $cachedId = (int)($fallback['id'] ?? 1);
            return $cachedId;
        }

        $roleRow = $mysqli->query("SELECT id FROM roles WHERE name = 'user' LIMIT 1");
        if ($roleRow) {
            $role = $roleRow->fetch_assoc();
            if (!empty($role['id'])) {
                $roleId = (int)$role['id'];
                $roleStmt = $mysqli->prepare("
                    INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())
                ");
                $roleStmt->bind_param('ii', $guestId, $roleId);
                $roleStmt->execute();
                $roleStmt->close();
            }
        }

        $cachedId = $guestId;
        return $cachedId;
    }
}

if (!function_exists('getDefaultServicePaymentMethods')) {
    function getDefaultServicePaymentMethods(): array
    {
        return [
            ['key' => 'bkash', 'label' => 'bKash'],
            ['key' => 'nagad', 'label' => 'Nagad'],
            ['key' => 'rocket', 'label' => 'Rocket'],
        ];
    }
}

if (!function_exists('normalizeServicePaymentMethods')) {
    function normalizeServicePaymentMethods($rawMethods): array
    {
        $rawMethods = is_array($rawMethods) ? $rawMethods : [];
        $normalized = [];
        $seen = [];
        $disallowedKeys = ['bank_transfer', 'card', 'cash', 'other_gateway'];

        foreach ($rawMethods as $row) {
            if (is_string($row)) {
                $keyRaw = $row;
                $labelRaw = $row;
            } elseif (is_array($row)) {
                $keyRaw = (string)($row['key'] ?? '');
                $labelRaw = (string)($row['label'] ?? '');
            } else {
                continue;
            }

            $key = strtolower(trim($keyRaw));
            $key = preg_replace('/[^a-z0-9_]+/', '_', $key);
            $key = trim((string)$key, '_');
            if ($key === '' || isset($seen[$key]) || in_array($key, $disallowedKeys, true)) {
                continue;
            }

            $label = trim($labelRaw);
            if ($label === '') {
                $label = ucwords(str_replace('_', ' ', $key));
            }

            $seen[$key] = true;
            $normalized[] = ['key' => $key, 'label' => $label];
        }

        if (empty($normalized)) {
            return getDefaultServicePaymentMethods();
        }

        return $normalized;
    }
}

if (!function_exists('getConfiguredServicePrice')) {
    function getConfiguredServicePrice(array $service): float
    {
        if (isset($service['price']) && is_numeric($service['price'])) {
            return round((float)$service['price'], 2);
        }

        $metadataRaw = $service['metadata'] ?? null;
        $metadata = [];
        if (is_array($metadataRaw)) {
            $metadata = $metadataRaw;
        } elseif (is_string($metadataRaw) && $metadataRaw !== '') {
            $metadata = json_decode($metadataRaw, true) ?: [];
        }

        if (isset($metadata['_service_price']) && is_numeric($metadata['_service_price'])) {
            return round((float)$metadata['_service_price'], 2);
        }

        return 0.0;
    }
}

if (!function_exists('getConfiguredServiceRedirectUrl')) {
    function getConfiguredServiceRedirectUrl(array $service): string
    {
        $redirect = trim((string)($service['redirect_url'] ?? ''));
        if ($redirect !== '') {
            return $redirect;
        }

        $metadataRaw = $service['metadata'] ?? null;
        $metadata = [];
        if (is_array($metadataRaw)) {
            $metadata = $metadataRaw;
        } elseif (is_string($metadataRaw) && $metadataRaw !== '') {
            $metadata = json_decode($metadataRaw, true) ?: [];
        }

        return trim((string)($metadata['_redirect_url'] ?? ''));
    }
}

if (!function_exists('hasServiceReceiptAccess')) {
    function hasServiceReceiptAccess(array $app, ?int $currentUserId): bool
    {
        $isOwner = $currentUserId && (int)($app['user_id'] ?? 0) === (int)$currentUserId;
        $sessionReceiptIds = $_SESSION['service_receipts'] ?? [];
        $hasSessionAccess = in_array((int)($app['id'] ?? 0), array_map('intval', (array)$sessionReceiptIds), true);

        return $isOwner || $hasSessionAccess;
    }
}

if (!function_exists('extractServiceReceiptPaymentInfo')) {
    function extractServiceReceiptPaymentInfo(array $app): array
    {
        $applicationData = is_array($app['application_data'] ?? null) ? $app['application_data'] : [];
        $paymentCandidates = [
            $applicationData['_payment'] ?? null,
            $applicationData['_Payment'] ?? null,
            $applicationData['payment'] ?? null,
            $applicationData['Payment'] ?? null,
            $applicationData['payment_info'] ?? null,
            $applicationData['PaymentInfo'] ?? null,
        ];
        $paymentInfo = [];
        foreach ($paymentCandidates as $candidate) {
            if (is_array($candidate)) {
                $paymentInfo = $candidate;
                break;
            }
        }

        if (!isset($paymentInfo['transaction_id']) && !empty($app['payment_transaction_id'])) {
            $paymentInfo['transaction_id'] = $app['payment_transaction_id'];
        }
        if (!isset($paymentInfo['gateway']) && !empty($app['payment_gateway'])) {
            $paymentInfo['gateway'] = $app['payment_gateway'];
        }
        if (!isset($paymentInfo['method']) && !empty($app['payment_method'])) {
            $paymentInfo['method'] = $app['payment_method'];
        }
        if (!isset($paymentInfo['amount']) && isset($app['payment_amount']) && $app['payment_amount'] !== null) {
            $paymentInfo['amount'] = $app['payment_amount'];
        }
        if (!isset($paymentInfo['currency']) && !empty($app['payment_currency'])) {
            $paymentInfo['currency'] = $app['payment_currency'];
        }
        if (!isset($paymentInfo['status']) && !empty($app['payment_status'])) {
            $paymentInfo['status'] = $app['payment_status'];
        }

        return $paymentInfo;
    }
}
