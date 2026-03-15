<?php

/**
 * classes/ServiceApplicationModel.php
 * 
 * Handles all service application operations
 * - User submissions
 * - Status management
 * - Admin actions & audit logging
 */

class ServiceApplicationModel
{

    private $mysqli;
    private ?array $serviceAppLogColumns = null;
    private ?bool $serviceApplicationPaymentsTableExists = null;
    private const AUDIT_ACTION_TYPE_ALLOWED = [
        'created',
        'status_changed',
        'approved',
        'rejected',
        'processing',
        'activated',
        'note_added',
        'edited',
    ];
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const VALID_STATUSES = [
        'pending',
        'processing',
        'approved',
        'rejected'
    ];

    const STATUS_TRANSITIONS = [
        'pending' => ['processing', 'approved', 'rejected'],
        'processing' => ['approved', 'rejected', 'pending'],
        'approved' => [],
        'rejected' => ['pending']  // Allow reapplication
    ];

    const PRIORITIES = ['low', 'normal', 'high'];

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Read service_application_logs columns (cached).
     * Used to stay compatible with drifted schemas.
     *
     * @return string[]
     */
    private function getServiceApplicationLogColumns(): array
    {
        if ($this->serviceAppLogColumns !== null) {
            return $this->serviceAppLogColumns;
        }

        $this->serviceAppLogColumns = [];
        try {
            $result = $this->mysqli->query("SHOW COLUMNS FROM service_application_logs");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $field = (string)($row['Field'] ?? '');
                    if ($field !== '') {
                        $this->serviceAppLogColumns[] = $field;
                    }
                }
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Unable to read service_application_logs columns: ' . $e->getMessage(), 'DB_ERROR');
            }
            $this->serviceAppLogColumns = [];
        }

        return $this->serviceAppLogColumns;
    }

    /**
     * Check if normalized payment table exists.
     */
    private function hasServiceApplicationPaymentsTable(): bool
    {
        if ($this->serviceApplicationPaymentsTableExists !== null) {
            return $this->serviceApplicationPaymentsTableExists;
        }

        $this->serviceApplicationPaymentsTableExists = false;
        try {
            $result = $this->mysqli->query("SHOW TABLES LIKE 'service_application_payments'");
            if ($result && $result->num_rows > 0) {
                $this->serviceApplicationPaymentsTableExists = true;
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Unable to check service_application_payments table: ' . $e->getMessage(), 'WARNING');
            }
            $this->serviceApplicationPaymentsTableExists = false;
        }

        return $this->serviceApplicationPaymentsTableExists;
    }

    /**
     * Normalize action_type to known enum-safe values.
     */
    private function normalizeAuditActionType(string $actionType): string
    {
        $normalized = strtolower(trim($actionType));
        if ($normalized === '') {
            return 'edited';
        }

        if (in_array($normalized, self::AUDIT_ACTION_TYPE_ALLOWED, true)) {
            return $normalized;
        }

        $map = [
            'payment_status_updated' => 'status_changed',
            'payment_completed' => 'edited',
            'payment_confirmed' => 'edited',
            'manual_payment_confirmed' => 'edited',
            'resubmitted' => 'edited',
        ];

        return $map[$normalized] ?? 'edited';
    }

    /**
     * Persist payment snapshot to normalized payment table (non-blocking fallback).
     */
    public function savePaymentRecord(int $applicationId, array $paymentInfo): bool
    {
        if (!$this->hasServiceApplicationPaymentsTable()) {
            return true;
        }

        $app = $this->findById($applicationId);
        if (!$app) {
            return false;
        }

        $mode = trim((string)($paymentInfo['mode'] ?? ''));
        $gateway = trim((string)($paymentInfo['gateway'] ?? ''));
        $method = trim((string)($paymentInfo['method'] ?? ($paymentInfo['payment_method'] ?? '')));
        $transactionId = trim((string)($paymentInfo['transaction_id'] ?? ($paymentInfo['paymentID'] ?? ($paymentInfo['payment_id'] ?? ''))));
        $senderNumber = trim((string)($paymentInfo['sender_number'] ?? ''));
        $payerName = trim((string)($paymentInfo['payer_name'] ?? ''));
        $receiverAccount = trim((string)($paymentInfo['receiver_account'] ?? ''));
        $currency = strtoupper(trim((string)($paymentInfo['currency'] ?? '')));
        $status = strtolower(trim((string)($paymentInfo['status'] ?? 'pending')));
        $amountRaw = $paymentInfo['amount'] ?? null;
        $amount = is_numeric($amountRaw) ? number_format((float)$amountRaw, 2, '.', '') : null;

        if ($status === '') {
            $status = 'pending';
        }
        if ($transactionId === '' || strtoupper($transactionId) === 'PENDING_GATEWAY') {
            $transactionId = null;
        }
        if ($mode === '') {
            $mode = null;
        }
        if ($gateway === '') {
            $gateway = null;
        }
        if ($method === '') {
            $method = null;
        }
        if ($senderNumber === '') {
            $senderNumber = null;
        }
        if ($payerName === '') {
            $payerName = null;
        }
        if ($receiverAccount === '') {
            $receiverAccount = null;
        }
        if ($currency === '') {
            $currency = null;
        }

        $gatewayResponse = $paymentInfo['gateway_response'] ?? null;
        if (is_array($gatewayResponse) || is_object($gatewayResponse)) {
            $gatewayResponse = json_encode($gatewayResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (!is_string($gatewayResponse)) {
            $gatewayResponse = null;
        }

        $submittedAt = trim((string)($paymentInfo['submitted_at'] ?? ''));
        $completedAt = trim((string)($paymentInfo['completed_at'] ?? ($paymentInfo['paid_at'] ?? '')));
        $paidAt = trim((string)($paymentInfo['paid_at'] ?? ''));

        $isPaid = in_array($status, ['paid', 'completed', 'success', 'succeeded'], true);
        if ($paidAt === '' && $isPaid) {
            $paidAt = $completedAt !== '' ? $completedAt : date('Y-m-d H:i:s');
        }

        $submittedAt = $submittedAt !== '' ? $submittedAt : null;
        $completedAt = $completedAt !== '' ? $completedAt : null;
        $paidAt = $paidAt !== '' ? $paidAt : null;

        $sql = "
            INSERT INTO service_application_payments (
                application_id, user_id, service_id, mode, gateway, payment_method, transaction_id,
                sender_number, payer_name, receiver_account, amount, currency, status, gateway_response,
                submitted_at, paid_at, completed_at, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                service_id = VALUES(service_id),
                mode = VALUES(mode),
                gateway = VALUES(gateway),
                payment_method = VALUES(payment_method),
                transaction_id = VALUES(transaction_id),
                sender_number = VALUES(sender_number),
                payer_name = VALUES(payer_name),
                receiver_account = VALUES(receiver_account),
                amount = VALUES(amount),
                currency = VALUES(currency),
                status = VALUES(status),
                gateway_response = VALUES(gateway_response),
                submitted_at = COALESCE(VALUES(submitted_at), submitted_at),
                paid_at = COALESCE(VALUES(paid_at), paid_at),
                completed_at = COALESCE(VALUES(completed_at), completed_at),
                deleted_at = NULL,
                updated_at = NOW()
        ";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $userId = (int)($app['user_id'] ?? 0);
        $serviceId = (int)($app['service_id'] ?? 0);
        $types = 'iiissssssssssssss';
        $stmt->bind_param(
            $types,
            $applicationId,
            $userId,
            $serviceId,
            $mode,
            $gateway,
            $method,
            $transactionId,
            $senderNumber,
            $payerName,
            $receiverAccount,
            $amount,
            $currency,
            $status,
            $gatewayResponse,
            $submittedAt,
            $paidAt,
            $completedAt
        );

        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Read normalized payment row by application id.
     */
    private function getPaymentRecordByApplicationId(int $applicationId): ?array
    {
        if (!$this->hasServiceApplicationPaymentsTable()) {
            return null;
        }

        $stmt = $this->mysqli->prepare("
            SELECT *
            FROM service_application_payments
            WHERE application_id = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Update payment status for an application and keep payment snapshot in sync.
     */
    public function updateApplicationPaymentStatus(int $applicationId, string $newPaymentStatus, ?int $adminId = null, ?string $note = null): bool
    {
        $app = $this->findById($applicationId);
        if (!$app) {
            return false;
        }

        $status = strtolower(trim($newPaymentStatus));
        if ($status === '') {
            return false;
        }

        $applicationData = is_array($app['application_data']) ? $app['application_data'] : [];
        $payment = is_array($applicationData['_payment'] ?? null) ? $applicationData['_payment'] : [];

        if (empty($payment)) {
            $paymentRow = $this->getPaymentRecordByApplicationId($applicationId);
            if ($paymentRow) {
                $payment = [
                    'mode' => $paymentRow['mode'] ?? null,
                    'gateway' => $paymentRow['gateway'] ?? null,
                    'method' => $paymentRow['payment_method'] ?? null,
                    'transaction_id' => $paymentRow['transaction_id'] ?? null,
                    'sender_number' => $paymentRow['sender_number'] ?? null,
                    'payer_name' => $paymentRow['payer_name'] ?? null,
                    'receiver_account' => $paymentRow['receiver_account'] ?? null,
                    'amount' => $paymentRow['amount'] ?? null,
                    'currency' => $paymentRow['currency'] ?? null,
                    'submitted_at' => $paymentRow['submitted_at'] ?? null,
                    'paid_at' => $paymentRow['paid_at'] ?? null,
                    'completed_at' => $paymentRow['completed_at'] ?? null,
                ];
            }
        }

        $payment['status'] = $status;
        $payment['updated_at'] = date('Y-m-d H:i:s');
        if (empty($payment['submitted_at'])) {
            $payment['submitted_at'] = date('Y-m-d H:i:s');
        }

        if (in_array($status, ['paid', 'completed', 'success', 'succeeded'], true)) {
            if (empty($payment['paid_at'])) {
                $payment['paid_at'] = date('Y-m-d H:i:s');
            }
            if (empty($payment['completed_at'])) {
                $payment['completed_at'] = $payment['paid_at'];
            }
        }

        $applicationData['_payment'] = $payment;
        $json = json_encode($applicationData);

        $stmt = $this->mysqli->prepare("
            UPDATE service_applications
            SET application_data = ?, updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $json, $applicationId);
        $updated = $stmt->execute();
        $stmt->close();
        if (!$updated) {
            return false;
        }

        try {
            $saved = $this->savePaymentRecord($applicationId, $payment);
            if (!$saved && function_exists('logError')) {
                logError('Payment status updated but normalized payment record save failed', 'WARNING', [
                    'application_id' => $applicationId,
                    'payment_status' => $status
                ]);
            }
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Payment status updated but normalized payment record save exception: ' . $e->getMessage(), 'WARNING', [
                    'application_id' => $applicationId,
                    'payment_status' => $status
                ]);
            }
        }

        if (in_array($status, ['paid', 'completed', 'success', 'succeeded'], true) && ($app['status'] ?? '') === self::STATUS_PENDING) {
            $this->changeStatus($applicationId, self::STATUS_PROCESSING, $adminId, null);
        }

        $desc = 'Payment status updated to ' . $status;
        if ($note !== null && trim($note) !== '') {
            $desc .= ' (' . trim($note) . ')';
        }
        $this->logAction($applicationId, $adminId, 'payment_status_updated', $desc, null, null);

        if ($note !== null && trim($note) !== '' && $adminId) {
            $this->addNote($applicationId, $adminId, $note);
        }

        return true;
    }

    // ============================================================================
    // FINDERS
    // ============================================================================

    /**
     * Get application by ID
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_applications 
            WHERE id = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && is_string($result['application_data'])) {
            $result['application_data'] = json_decode($result['application_data'], true);
        }

        return $result ?: null;
    }

    /**
     * Get all applications for a user
     * @param int $userId
     * @param array $filters Optional filters (status, service_id, etc)
     * @return array
     */
    public function getUserApplications(int $userId, array $filters = []): array
    {
        $sql = "
            SELECT sa.*, s.name as service_name, s.slug as service_slug, u.username as approved_by_name
            FROM service_applications sa
            JOIN services s ON sa.service_id = s.id
            LEFT JOIN users u ON sa.approved_by = u.id
            WHERE sa.user_id = ? AND sa.deleted_at IS NULL
        ";

        $params = [$userId];
        $types = 'i';

        if (!empty($filters['status'])) {
            $sql .= " AND sa.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND sa.service_id = ?";
            $params[] = $filters['service_id'];
            $types .= 'i';
        }

        $sql .= " ORDER BY sa.created_at DESC";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        // Decode JSON fields
        foreach ($results as &$row) {
            if (is_string($row['application_data'])) {
                $row['application_data'] = json_decode($row['application_data'], true);
            }
        }

        return $results;
    }

    /**
     * Get single user application for a service (prevent duplicates)
     * @param int $userId
     * @param int $serviceId
     * @return array|null
     */
    public function getUserServiceApplication(int $userId, int $serviceId): ?array
    {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_applications 
            WHERE user_id = ? AND service_id = ? AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('ii', $userId, $serviceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && is_string($result['application_data'])) {
            $result['application_data'] = json_decode($result['application_data'], true);
        }

        return $result ?: null;
    }

    /**
     * Get all applications (admin view)
     * @param array $filters (status, service_id, user_id, priority, date_from, date_to)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllApplications(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $sql = "
            SELECT sa.*, 
                   s.name as service_name, 
                   u.username as user_name, u.email as user_email,
                   approver.username as approved_by_name
            FROM service_applications sa
            JOIN services s ON sa.service_id = s.id
            JOIN users u ON sa.user_id = u.id
            LEFT JOIN users approver ON sa.approved_by = approver.id
            WHERE sa.deleted_at IS NULL
        ";

        $params = [];
        $types = '';

        if (!empty($filters['status'])) {
            $sql .= " AND sa.status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (!empty($filters['service_id'])) {
            $sql .= " AND sa.service_id = ?";
            $params[] = $filters['service_id'];
            $types .= 'i';
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND sa.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND sa.priority = ?";
            $params[] = $filters['priority'];
            $types .= 's';
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sa.created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sa.created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        // Count total
        $countStmt = $this->mysqli->prepare("SELECT COUNT(*) as total FROM ($sql) as temp");
        if (!empty($params)) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();

        // Get paginated results
        $sql .= " ORDER BY sa.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();

        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        // Decode JSON
        foreach ($results as &$row) {
            if (is_string($row['application_data'])) {
                $row['application_data'] = json_decode($row['application_data'], true);
            }
        }

        return [
            'data' => $results,
            'total' => $totalResult['total'] ?? 0,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    // ============================================================================
    // CREATE APPLICATION
    // ============================================================================

    /**
     * Submit new application
     * @param int $userId
     * @param int $serviceId
     * @param array $applicationData Form data
     * @return int|null Application ID or null on failure
     */
    public function submit(int $userId, int $serviceId, array $applicationData): ?int
    {
        $data_json = json_encode($applicationData);
        $source = $_GET['source'] ?? $_POST['source'] ?? 'web';
        $ip_address = getClientIp();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $status = self::STATUS_PENDING;

        $stmt = $this->mysqli->prepare("
            INSERT INTO service_applications (
                user_id, service_id, application_data, status, source, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'iisssss',
            $userId,
            $serviceId,
            $data_json,
            $status,
            $source,
            $ip_address,
            $user_agent
        );

        if ($stmt->execute()) {
            $appId = (int) $this->mysqli->insert_id;

            // Log creation (non-blocking: submission success must not depend on audit logging)
            try {
                $logged = $this->logAction($appId, $userId, 'created', 'Application submitted', null, null);
                if (!$logged && function_exists('logError')) {
                    logError('Service application submit succeeded but audit log failed', 'WARNING', [
                        'application_id' => $appId,
                        'service_id' => $serviceId,
                        'user_id' => $userId,
                        'action_type' => 'created'
                    ]);
                }
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('Service application submit succeeded but audit log exception occurred: ' . $e->getMessage(), 'WARNING', [
                        'application_id' => $appId,
                        'service_id' => $serviceId,
                        'user_id' => $userId,
                        'action_type' => 'created'
                    ]);
                }
            }

            return $appId;
        }

        return null;
    }

    /**
     * Find application by payment transaction id.
     * Uses normalized payment table first, then falls back to JSON search.
     * @param string $transactionId
     * @return array|null
     */
    public function findByPaymentTransactionId(string $transactionId): ?array
    {
        $transactionId = trim($transactionId);
        if ($transactionId === '') {
            return null;
        }

        if ($this->hasServiceApplicationPaymentsTable()) {
            $stmt = $this->mysqli->prepare("
                SELECT sa.*
                FROM service_application_payments sap
                JOIN service_applications sa ON sa.id = sap.application_id
                WHERE sap.transaction_id = ?
                  AND sap.deleted_at IS NULL
                  AND sa.deleted_at IS NULL
                ORDER BY sap.updated_at DESC
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('s', $transactionId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row) {
                    if (is_string($row['application_data'])) {
                        $row['application_data'] = json_decode($row['application_data'], true);
                    }
                    return $row;
                }
            }
        }

        $likeTransactionId = '%"transaction_id":"' . $transactionId . '"%';
        $likePaymentId = '%"paymentID":"' . $transactionId . '"%';
        $stmt = $this->mysqli->prepare("
            SELECT *
            FROM service_applications
            WHERE deleted_at IS NULL
              AND (application_data LIKE ? OR application_data LIKE ?)
            LIMIT 1
        ");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $likeTransactionId, $likePaymentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && is_string($row['application_data'])) {
            $row['application_data'] = json_decode($row['application_data'], true);
        }
        return $row ?: null;
    }

    /**
     * Get application receipts with normalized payment data (fallback to legacy JSON).
     * @param array $filters (q, date_from, date_to, payment_status, application_id)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getReceipts(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $paymentsTableAvailable = $this->hasServiceApplicationPaymentsTable();
        $legacyReceiptCondition = "(sa.application_data LIKE ? OR sa.application_data LIKE ? OR sa.application_data LIKE ? OR sa.application_data LIKE ?)";

        if ($paymentsTableAvailable) {
            $sql = "
                SELECT
                    sa.*,
                    s.name as service_name,
                    u.username as user_name,
                    u.email as user_email,
                    approver.username as approved_by_name,
                    sap.id as payment_id,
                    sap.mode as payment_mode,
                    sap.gateway as payment_gateway,
                    sap.payment_method as payment_method,
                    sap.transaction_id as payment_transaction_id,
                    sap.amount as payment_amount,
                    sap.currency as payment_currency,
                    sap.status as payment_status,
                    sap.submitted_at as payment_submitted_at,
                    sap.paid_at as payment_paid_at,
                    sap.completed_at as payment_completed_at,
                    sap.gateway_response as payment_gateway_response
                FROM service_applications sa
                JOIN services s ON sa.service_id = s.id
                JOIN users u ON sa.user_id = u.id
                LEFT JOIN users approver ON sa.approved_by = approver.id
                LEFT JOIN service_application_payments sap
                    ON sap.application_id = sa.id
                   AND sap.deleted_at IS NULL
                WHERE sa.deleted_at IS NULL
                  AND (sap.id IS NOT NULL OR $legacyReceiptCondition)
            ";
        } else {
            $sql = "
                SELECT
                    sa.*,
                    s.name as service_name,
                    u.username as user_name,
                    u.email as user_email,
                    approver.username as approved_by_name,
                    NULL as payment_id,
                    NULL as payment_mode,
                    NULL as payment_gateway,
                    NULL as payment_method,
                    NULL as payment_transaction_id,
                    NULL as payment_amount,
                    NULL as payment_currency,
                    NULL as payment_status,
                    NULL as payment_submitted_at,
                    NULL as payment_paid_at,
                    NULL as payment_completed_at,
                    NULL as payment_gateway_response
                FROM service_applications sa
                JOIN services s ON sa.service_id = s.id
                JOIN users u ON sa.user_id = u.id
                LEFT JOIN users approver ON sa.approved_by = approver.id
                WHERE sa.deleted_at IS NULL
                  AND $legacyReceiptCondition
            ";
        }

        $params = [
            '%"_payment"%',
            '%"transaction_id"%',
            '%"paymentID"%',
            '%"payment_transaction_id"%'
        ];
        $types = 'ssss';

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sa.created_at) >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sa.created_at) <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }

        if (!empty($filters['application_id'])) {
            $sql .= " AND sa.id = ?";
            $params[] = (int)$filters['application_id'];
            $types .= 'i';
        }

        if (!empty($filters['payment_status'])) {
            $paymentStatus = strtolower(trim((string)$filters['payment_status']));
            if ($paymentsTableAvailable) {
                $sql .= " AND (LOWER(COALESCE(sap.status, '')) = ? OR sa.application_data LIKE ?)";
                $params[] = $paymentStatus;
                $params[] = '%"status":"' . $paymentStatus . '"%';
                $types .= 'ss';
            } else {
                $sql .= " AND sa.application_data LIKE ?";
                $params[] = '%"status":"' . $paymentStatus . '"%';
                $types .= 's';
            }
        }

        if (!empty($filters['q'])) {
            $q = trim((string)$filters['q']);
            $qLike = '%' . $q . '%';
            $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR s.name LIKE ? OR sa.application_data LIKE ?";
            $params[] = $qLike;
            $params[] = $qLike;
            $params[] = $qLike;
            $params[] = $qLike;
            $types .= 'ssss';

            if ($paymentsTableAvailable) {
                $sql .= " OR sap.transaction_id LIKE ? OR sap.gateway LIKE ? OR sap.payment_method LIKE ? OR sap.status LIKE ?";
                $params[] = $qLike;
                $params[] = $qLike;
                $params[] = $qLike;
                $params[] = $qLike;
                $types .= 'ssss';
            }

            if (ctype_digit($q)) {
                $sql .= " OR sa.id = ?";
                $params[] = (int)$q;
                $types .= 'i';
            }
            $sql .= ")";
        }

        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as temp";
        $countStmt = $this->mysqli->prepare($countSql);
        if ($countStmt) {
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $totalResult = $countStmt->get_result()->fetch_assoc();
            $countStmt->close();
        } else {
            $totalResult = ['total' => 0];
        }

        $sql .= " ORDER BY sa.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $this->mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $stmt->close();
        } else {
            $results = [];
        }

        foreach ($results as &$row) {
            if (is_string($row['application_data'])) {
                $row['application_data'] = json_decode($row['application_data'], true);
            }
        }

        return [
            'data' => $results,
            'total' => $totalResult['total'] ?? 0,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Mark payment completed for an application and attach payment info into application_data._payment
     * @param int $applicationId
     * @param array $paymentInfo
     * @return bool
     */
    public function completePayment(int $applicationId, array $paymentInfo): bool
    {
        $app = $this->findById($applicationId);
        if (!$app) return false;

        $applicationData = is_array($app['application_data']) ? $app['application_data'] : [];
        $existingPayment = is_array($applicationData['_payment'] ?? null) ? $applicationData['_payment'] : [];
        $merged = array_replace_recursive($existingPayment, $paymentInfo);
        $merged['status'] = $paymentInfo['status'] ?? ($merged['status'] ?? 'paid');
        if (empty($merged['completed_at']) && in_array(strtolower((string)$merged['status']), ['paid', 'completed', 'success', 'succeeded'], true)) {
            $merged['completed_at'] = date('Y-m-d H:i:s');
        }
        $applicationData['_payment'] = $merged;

        $json = json_encode($applicationData);

        $newStatus = 'processing';

        $stmt = $this->mysqli->prepare("UPDATE service_applications SET application_data = ?, status = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) return false;
        $stmt->bind_param('ssi', $json, $newStatus, $applicationId);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            try {
                $saved = $this->savePaymentRecord($applicationId, $merged);
                if (!$saved && function_exists('logError')) {
                    logError('Payment completed but normalized payment record save failed', 'WARNING', [
                        'application_id' => $applicationId
                    ]);
                }
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('Payment completed but normalized payment record save exception: ' . $e->getMessage(), 'WARNING', [
                        'application_id' => $applicationId
                    ]);
                }
            }

            // Log payment completion
            try {
                $this->logAction($applicationId, null, 'payment_completed', 'Payment completed via gateway', null, null);
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('Failed to log payment completion: ' . $e->getMessage(), 'WARNING');
                }
            }
        }

        return (bool)$ok;
    }
    // ============================================================================
    // STATUS MANAGEMENT
    // ============================================================================

    /**
     * Change application status
     * @param int $applicationId
     * @param string $newStatus
     * @param int|null $adminId
     * @param string|null $reason
     * @return bool
     */
    public function changeStatus(int $applicationId, string $newStatus, ?int $adminId = null, ?string $reason = null): bool
    {
        // Validate status
        if (!in_array($newStatus, self::VALID_STATUSES)) {
            return false;
        }

        $app = $this->findById($applicationId);
        if (!$app) {
            return false;
        }

        // Check transition validity
        if (!$this->isValidTransition($app['status'], $newStatus)) {
            return false;
        }

        $oldStatus = $app['status'];
        $now = date('Y-m-d H:i:s');
        $approved_at = ($newStatus === self::STATUS_APPROVED) ? $now : null;

        $stmt = $this->mysqli->prepare("
            UPDATE service_applications 
            SET status = ?, approved_by = ?, approved_at = ?, rejection_reason = ?, updated_at = NOW()
            WHERE id = ?
        ");

        if ($newStatus === self::STATUS_REJECTED) {
            $stmt->bind_param('sissi', $newStatus, $adminId, $approved_at, $reason, $applicationId);
        } else {
            $stmt->bind_param('sissi', $newStatus, $adminId, $approved_at, $reason, $applicationId);
        }

        if ($stmt->execute()) {
            // Log action (non-blocking: status update success must not depend on audit logging)
            try {
                $logged = $this->logAction(
                    $applicationId,
                    $adminId,
                    'status_changed',
                    "Status changed from $oldStatus to $newStatus",
                    $oldStatus,
                    $newStatus
                );
                if (!$logged && function_exists('logError')) {
                    logError('Service application status changed but audit log failed', 'WARNING', [
                        'application_id' => $applicationId,
                        'admin_id' => $adminId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]);
                }
            } catch (Throwable $e) {
                if (function_exists('logError')) {
                    logError('Service application status changed but audit log exception occurred: ' . $e->getMessage(), 'WARNING', [
                        'application_id' => $applicationId,
                        'admin_id' => $adminId,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus
                    ]);
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Check if status transition is valid
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    public function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        if ($currentStatus === $newStatus) {
            return true;
        }

        return in_array($newStatus, self::STATUS_TRANSITIONS[$currentStatus] ?? []);
    }

    /**
     * Approve application
     * @param int $applicationId
     * @param int $adminId
     * @param string|null $notes
     * @return bool
     */
    public function approve(int $applicationId, ?int $adminId = null, ?string $notes = null): bool
    {
        if (!$this->changeStatus($applicationId, self::STATUS_APPROVED, $adminId)) {
            return false;
        }

        if ($notes) {
            $this->addNote($applicationId, $adminId, $notes);
        }

        return true;
    }


    /**
     * Reject application
     * @param int $applicationId
     * @param int $adminId
     * @param string $reason
     * @return bool
     */
    public function reject(int $applicationId, int $adminId, string $reason): bool
    {
        return $this->changeStatus($applicationId, self::STATUS_REJECTED, $adminId, $reason);
    }

    /**
     * Mark as processing
     * @param int $applicationId
     * @param int $adminId
     * @param string|null $notes
     * @return bool
     */
    public function markProcessing(int $applicationId, int $adminId, ?string $notes = null): bool
    {
        if (!$this->changeStatus($applicationId, self::STATUS_PROCESSING, $adminId)) {
            return false;
        }

        if ($notes) {
            $this->addNote($applicationId, $adminId, $notes);
        }

        return true;
    }

    // ============================================================================
    // ADMIN NOTES & COMMENTS
    // ============================================================================

    /**
     * Add admin note to application
     * @param int $applicationId
     * @param int $adminId
     * @param string $note
     * @return bool
     */
    public function addNote(int $applicationId, int $adminId, string $note): bool
    {
        $stmt = $this->mysqli->prepare("
            UPDATE service_applications 
            SET admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n\n[', NOW(), ' - Admin] ', ?)
            WHERE id = ?
        ");

        $stmt->bind_param('si', $note, $applicationId);
        return $stmt->execute();
    }

    // ============================================================================
    // SERVICE ACTIVATION
    // ============================================================================

    /**
     * Activate service after approval
     * @param int $applicationId
     * @return bool
     */
    public function activateService(int $applicationId): bool
    {
        $app = $this->findById($applicationId);

        if (!$app || $app['status'] !== self::STATUS_APPROVED) {
            return false;
        }

        $stmt = $this->mysqli->prepare("
            UPDATE service_applications 
            SET service_activated = 1, activated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param('i', $applicationId);
        return $stmt->execute();
    }

    /**
     * Check if service is activated for user
     * @param int $userId
     * @param int $serviceId
     * @return bool
     */
    public function isServiceActivated(int $userId, int $serviceId): bool
    {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as cnt FROM service_applications 
            WHERE user_id = ? AND service_id = ? 
            AND status = 'approved' AND service_activated = 1 
            AND deleted_at IS NULL
        ");

        $stmt->bind_param('ii', $userId, $serviceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result['cnt'] > 0;
    }

    // ============================================================================
    // APPLICATION MANAGEMENT (Full workflow with new fields)
    // ============================================================================

    /**
     * Update application status with admin notes and rejection reason
     * @param int $applicationId
     * @param string $newStatus
     * @param int $approvedBy Admin user ID
     * @param string|null $rejectionReason
     * @param string|null $adminNotes
     * @return bool
     */
    public function updateStatus(int $applicationId, string $newStatus, int $approvedBy, ?string $rejectionReason = null, ?string $adminNotes = null): bool
    {
        $app = $this->findById($applicationId);
        if (!$app) return false;

        $oldStatus = $app['status'];

        // Validate transition
        if (!in_array($newStatus, self::VALID_STATUSES)) {
            return false;
        }

        if (!in_array($newStatus, self::STATUS_TRANSITIONS[$oldStatus])) {
            return false;
        }

        $stmt = $this->mysqli->prepare("
            UPDATE service_applications 
            SET status = ?, 
                approved_by = ?, 
                rejection_reason = ?,
                admin_notes = ?,
                approved_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param('sissi', $newStatus, $approvedBy, $rejectionReason, $adminNotes, $applicationId);

        if (!$stmt->execute()) {
            return false;
        }

        // Log action
        $this->logAction($applicationId, $approvedBy, 'status_changed', ucfirst($newStatus), $oldStatus, $newStatus);

        return true;
    }

    /**
     * Set application priority
     * @param int $applicationId
     * @param string $priority (low, normal, high)
     * @return bool
     */
    public function setPriority(int $applicationId, string $priority): bool
    {
        if (!in_array($priority, self::PRIORITIES)) {
            return false;
        }

        $stmt = $this->mysqli->prepare("
            UPDATE service_applications SET priority = ? WHERE id = ?
        ");

        $stmt->bind_param('si', $priority, $applicationId);
        return $stmt->execute();
    }

    /**
     * Add admin notes to application
     * @param int $applicationId
     * @param string $notes
     * @return bool
     */
    public function addAdminNotes(int $applicationId, string $notes): bool
    {
        $stmt = $this->mysqli->prepare("
            UPDATE service_applications SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n', ?) WHERE id = ?
        ");

        $stmt->bind_param('si', $notes, $applicationId);
        return $stmt->execute();
    }

    /**
     * Get application with enriched data (user, service, logs)
     * @param int $applicationId
     * @return array|null
     */
    public function getEnriched(int $applicationId): ?array
    {
        $app = $this->findById($applicationId);

        if (!$app) {
            return null;
        }

        // Get related data
        $serviceStmt = $this->mysqli->prepare("SELECT id, name, slug, category FROM services WHERE id = ?");
        $serviceStmt->bind_param('i', $app['service_id']);
        $serviceStmt->execute();
        $app['service'] = $serviceStmt->get_result()->fetch_assoc();

        $userStmt = $this->mysqli->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $userStmt->bind_param('i', $app['user_id']);
        $userStmt->execute();
        $app['user'] = $userStmt->get_result()->fetch_assoc();

        if ($app['approved_by']) {
            $approverStmt = $this->mysqli->prepare("SELECT id, username, email FROM users WHERE id = ?");
            $approverStmt->bind_param('i', $app['approved_by']);
            $approverStmt->execute();
            $app['approver'] = $approverStmt->get_result()->fetch_assoc();
        }

        // Get audit log
        $app['audit_log'] = $this->getAuditLog($applicationId);

        return $app;
    }

    /**
     * Get applications requiring action (pending, processing)
     * @param int $limit
     * @return array
     */
    public function getRequiringAction(int $limit = 20): array
    {
        $sql = "
            SELECT sa.*, s.name as service_name, u.username as user_name, u.email as user_email
            FROM service_applications sa
            JOIN services s ON sa.service_id = s.id
            JOIN users u ON sa.user_id = u.id
            WHERE sa.status IN ('pending', 'processing') AND sa.deleted_at IS NULL
            ORDER BY sa.priority DESC, sa.created_at ASC
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();

        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        foreach ($results as &$row) {
            if (is_string($row['application_data'])) {
                $row['application_data'] = json_decode($row['application_data'], true);
            }
        }

        return $results;
    }

    /**
     * Get recent applications by status
     * @param string $status
     * @param int $limit
     * @return array
     */
    public function getByStatus(string $status, int $limit = 20): array
    {
        if (!in_array($status, self::VALID_STATUSES)) {
            return [];
        }

        $sql = "
            SELECT sa.*, s.name as service_name, u.username as user_name
            FROM service_applications sa
            JOIN services s ON sa.service_id = s.id
            JOIN users u ON sa.user_id = u.id
            WHERE sa.status = ? AND sa.deleted_at IS NULL
            ORDER BY sa.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('si', $status, $limit);
        $stmt->execute();

        $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        foreach ($results as &$row) {
            if (is_string($row['application_data'])) {
                $row['application_data'] = json_decode($row['application_data'], true);
            }
        }

        return $results;
    }

    // ============================================================================
    // AUDIT LOGGING
    // ============================================================================

    /**
     * Log application action (audit trail)
     * @param int $applicationId
     * @param int|null $userId
     * @param string $actionType
     * @param string $description
     * @param string|null $oldStatus
     * @param string|null $newStatus
     * @return bool
     */
    public function logAction(
        int $applicationId,
        ?int $userId,
        string $actionType,
        string $description,
        ?string $oldStatus = null,
        ?string $newStatus = null
    ): bool {
        $ip_address = getClientIp();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $action = trim($actionType) !== '' ? trim($actionType) : 'edited';
        $normalizedActionType = $this->normalizeAuditActionType($actionType);

        try {
            $availableColumns = $this->getServiceApplicationLogColumns();
            if (empty($availableColumns)) {
                if (function_exists('logError')) {
                    logError('Audit log write skipped: service_application_logs columns unavailable', 'WARNING', [
                        'application_id' => $applicationId,
                        'action_type' => $actionType
                    ]);
                }
                return false;
            }

            $payload = [
                'application_id' => $applicationId,
                'user_id' => $userId,
                'action' => $action,
                'action_type' => $normalizedActionType,
                'description' => $description,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ];

            $columns = [];
            $values = [];
            $types = '';
            foreach ($payload as $column => $value) {
                if (!in_array($column, $availableColumns, true)) {
                    continue;
                }
                $columns[] = $column;
                $values[] = $value;
                $types .= ($column === 'application_id' || $column === 'user_id') ? 'i' : 's';
            }

            if (empty($columns)) {
                if (function_exists('logError')) {
                    logError('Audit log write skipped: no matching columns in service_application_logs', 'WARNING', [
                        'application_id' => $applicationId,
                        'action_type' => $actionType
                    ]);
                }
                return false;
            }

            $quotedColumns = array_map(static function (string $column): string {
                return '`' . $column . '`';
            }, $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));

            $sql = "INSERT INTO `service_application_logs` (" . implode(', ', $quotedColumns) . ") VALUES (" . $placeholders . ")";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                if (function_exists('logError')) {
                    logError('Audit log prepare failed for service_application_logs', 'WARNING', [
                        'application_id' => $applicationId,
                        'action_type' => $actionType
                    ]);
                }
                return false;
            }

            $stmt->bind_param($types, ...$values);
            $ok = $stmt->execute();
            $stmt->close();

            return (bool)$ok;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('Audit log insert failed in service_application_logs: ' . $e->getMessage(), 'WARNING', [
                    'application_id' => $applicationId,
                    'action_type' => $actionType
                ]);
            }
            return false;
        }
    }

    /**
     * Get application audit log
     * @param int $applicationId
     * @return array
     */
    public function getAuditLog(int $applicationId): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT sal.*, u.username 
            FROM service_application_logs sal
            LEFT JOIN users u ON sal.user_id = u.id
            WHERE sal.application_id = ?
            ORDER BY sal.created_at DESC
        ");

        $stmt->bind_param('i', $applicationId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    // ============================================================================
    // STATISTICS
    // ============================================================================

    /**
     * Get application statistics
     * @return array
     */
    public function getStatistics(): array
    {
        $statuses = ['pending', 'processing', 'approved', 'rejected'];
        $stats = [];

        foreach ($statuses as $status) {
            $stmt = $this->mysqli->prepare("
                SELECT COUNT(*) as count FROM service_applications 
                WHERE status = ? AND deleted_at IS NULL
            ");
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stats[$status] = (int) $result['count'];
        }

        $stats['total'] = array_sum($stats);

        return $stats;
    }
}
