<?php
// classes/AdvertisementModel.php
// Online Monetisation: ad placements, sponsored content, donation tracking,
// revenue analytics.

class AdvertisementModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    // ================================================================
    //  SECTION 1 — ADVERTISEMENT INQUIRIES  (existing – preserved)
    // ================================================================

    /**
     * Create a new advertisement inquiry
     */
    public function createInquiry(string $name, string $email, string $company,
                                   string $budget, string $message, string $ip): int|false
    {
        try {
            $sql = "INSERT INTO advertisement_inquiries
                    (name, email, company, budget, message, ip_address, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->mysqli->prepare($sql);
            if (!$stmt) {
                logError("AdvertisementModel::createInquiry - Prepare failed: " . $this->mysqli->error);
                return false;
            }
            $stmt->bind_param("ssssss", $name, $email, $company, $budget, $message, $ip);
            if (!$stmt->execute()) {
                logError("AdvertisementModel::createInquiry - Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        } catch (Exception $e) {
            logError("AdvertisementModel::createInquiry - " . $e->getMessage());
            return false;
        }
    }

    public function getAllInquiries(): array
    {
        try {
            $res = $this->mysqli->query("SELECT id, name, email, company, budget, message, ip_address, created_at FROM advertisement_inquiries ORDER BY created_at DESC");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            logError($e->getMessage());
            return [];
        }
    }

    public function getInquiryById(int $id): ?array
    {
        try {
            $stmt = $this->mysqli->prepare("SELECT id, name, email, company, budget, message, ip_address, created_at FROM advertisement_inquiries WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (Exception $e) {
            logError($e->getMessage());
            return null;
        }
    }

    // ================================================================
    //  SECTION 2 — DONATIONS
    // ================================================================

    /**
     * Create a new donation record
     */
    public function createDonation(array $data): int|false
    {
        $sql = "INSERT INTO donation_payments
                (donor_name, donor_email, donor_phone, amount, currency,
                 method, bkash_trxid, nagad_trxid, stripe_payment_intent,
                 note, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError("AdvertisementModel::createDonation - Prepare failed: " . $this->mysqli->error);
            return false;
        }

        $name   = $data['name']   ?? '';
        $email  = $data['email']  ?? null;
        $phone  = $data['phone']  ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $cur    = $data['currency'] ?? 'BDT';
        $method = $data['method'] ?? '';
        $bti    = $data['bkash_trxid'] ?? null;
        $nti    = $data['nagad_trxid'] ?? null;
        $spi    = $data['stripe_pi'] ?? null;
        $note   = $data['note'] ?? null;

        $stmt->bind_param('sssdssssss',
            $name, $email, $phone, $amount, $cur, $method, $bti, $nti, $spi, $note
        );

        if (!$stmt->execute()) {
            logError("AdvertisementModel::createDonation - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Confirm a pending donation and mark as completed
     */
    public function confirmDonation(int $id, string $trxId, float $amount): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE donation_payments
             SET status='completed', bkash_trxid=?, updated_at=NOW()
             WHERE id=? AND status='pending'"
        );
        $stmt->bind_param('si', $trxId, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Update a donation's status
     */
    public function updateDonationStatus(int $id, string $status, ?string $note = null): bool
    {
        if ($note !== null) {
            $stmt = $this->mysqli->prepare(
                "UPDATE donation_payments SET status=?, note=?, updated_at=NOW() WHERE id=?"
            );
            $stmt->bind_param('ssi', $status, $note, $id);
        } else {
            $stmt = $this->mysqli->prepare(
                "UPDATE donation_payments SET status=?, updated_at=NOW() WHERE id=?"
            );
            $stmt->bind_param('si', $status, $id);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Extra key-value metadata for a donation (gateway_response, etc.)
     */
    public function updatePaymentMeta(int $id, string $key, mixed $value): bool
    {
        $stmt = $this->mysqli->prepare(
            "UPDATE donation_payments
             SET note = CONCAT(IFNULL(note,''), '\n[{$key}] ', ?), updated_at=NOW()
             WHERE id=?"
        );
        $val = (string)$value;
        $stmt->bind_param('si', $val, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Get all donations with optional filters
     */
    public function getDonations(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];
        $types  = '';

        if (!empty($filters['status'])) {
            $where[]  = "status = ?";
            $types   .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(donor_name LIKE ? OR donor_email LIKE ? OR donor_phone LIKE ?)";
            $types   .= 'sss';
            $kw = '%' . $filters['search'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }

        $sql = "SELECT id, donor_name, donor_email, donor_phone, amount, currency, method, bkash_trxid, nagad_trxid, stripe_payment_intent, note, status, created_at FROM donation_payments";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $params[] = $limit;
        $types   .= 'ii';
        $params[] = $offset;

        $stmt = $this->mysqli->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getDonationsCount(array $filters = []): int
    {
        $where = [];
        $params = [];
        $types  = '';

        if (!empty($filters['status'])) {
            $where[]  = "status = ?";
            $types   .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(donor_name LIKE ? OR donor_email LIKE ? OR donor_phone LIKE ?)";
            $types   .= 'sss';
            $kw = '%' . $filters['search'] . '%';
            $params[] = $kw; $params[] = $kw; $params[] = $kw;
        }

        $sql = "SELECT COUNT(*) AS cnt FROM donation_payments";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);

        $stmt = $this->mysqli->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int)($row['cnt'] ?? 0);
    }

    /** Sum of all completed donations */
    public function getDonationTotal(): float
    {
        $res = $this->mysqli->query(
            "SELECT SUM(amount) AS total FROM donation_payments WHERE status='completed'"
        );
        $row = $res->fetch_assoc();
        return (float)($row['total'] ?? 0);
    }

    /** Count of all completed donors */
    public function getDonationCount(): int
    {
        $res = $this->mysqli->query(
            "SELECT COUNT(*) AS cnt FROM donation_payments WHERE status='completed'"
        );
        $row = $res->fetch_assoc();
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * Get N most recent completed donation records
     */
    public function getRecentDonations(int $limit = 6, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, donor_name, amount, method, status, created_at
             FROM donation_payments
             WHERE status='completed'
             ORDER BY created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Get donation total grouped by month (for chart)
     * @return array[]  [{ period: 'YYYY-MM', total: float }]
     */
    public function getMonthlyRevenue(string $category, int $months = 12): array
    {
        $allowed = ['donation', 'advertisement', 'sponsored_post', 'general'];
        if (!in_array($category, $allowed, true)) $category = 'donation';

        $tbl   = match ($category) {
            'donation'      => 'donation_payments',
            'advertisement' => 'revenue_settings',
            'sponsored_post'=> 'sponsored_posts',
            default         => 'donation_payments',
        };

        if ($category === 'donation') {
            $sql = "SELECT DATE_FORMAT(created_at,'%Y-%m') AS period,
                           SUM(amount) AS total
                    FROM donation_payments
                    WHERE status='completed'
                    GROUP BY period
                    ORDER BY period DESC
                    LIMIT " . (int)$months;
            $res = $this->mysqli->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        // For ad / sponsored — read setting_value pairs (simplified)
        $sql = "SELECT setting_key AS period, setting_value AS total FROM revenue_settings
                WHERE category = '{$category}' LIMIT " . (int)$months;
        $res = $this->mysqli->query($sql);
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        return array_map(function (array $r) {
            return [
                'period' => $r['period'] ?? 'unknown',
                'total'  => (float)($r['total'] ?? 0),
            ];
        }, $rows);
    }

    // ================================================================
    //  SECTION 3 — AD PLACEMENTS
    // ================================================================

    /**
     * Get active ad by slot_key
     */
    public function getActiveAdBySlot(string $slot_key): ?array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->mysqli->prepare(
            "SELECT id, name, slot_key, placement, ad_code, publisher_name, publisher_email, status, start_date, end_date, created_by, created_at FROM ad_placements
             WHERE slot_key = ?
               AND status = 'active'
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date   IS NULL OR end_date   >= ?)
             LIMIT 1"
        );
        $stmt->bind_param('sss', $slot_key, $now, $now);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get all ad placements
     */
    public function getAllAds(array $filters = []): array
    {
        $whereArr = [];
        $params   = [];
        $types    = '';

        $sql = "SELECT ap.*, u.username AS created_by_name
                FROM ad_placements ap
                LEFT JOIN users u ON u.id = ap.created_by";

        if (!empty($filters['status'])) {
            $whereArr[] = "ap.status = ?";
            $types     .= 's';
            $params[]   = $filters['status'];
        }
        if (!empty($filters['placement'])) {
            $whereArr[] = "ap.placement = ?";
            $types     .= 's';
            $params[]   = $filters['placement'];
        }

        if ($whereArr) $sql .= " WHERE " . implode(' AND ', $whereArr);
        $sql .= " ORDER BY ap.created_at DESC";

        $stmt = $this->mysqli->prepare($sql);
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Total ad revenue earned (sum of revenue_earned column)
     */
    public function getAdRevenueTotal(): float
    {
        $res = $this->mysqli->query(
            "SELECT SUM(revenue_earned) AS total FROM ad_placements WHERE status='active'"
        );
        $row = $res->fetch_assoc();
        return (float)($row['total'] ?? 0);
    }

    /**
     * Upsert an ad placement (create or update by slot_key)
     */
    public function upsertPlacement(array $data): int|false
    {
        $sql = "INSERT INTO ad_placements
                (name, slot_key, placement, ad_code, publisher_name, publisher_email,
                 status, start_date, end_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name=VALUES(name), placement=VALUES(placement),
                    ad_code=VALUES(ad_code), publisher_name=VALUES(publisher_name),
                    publisher_email=VALUES(publisher_email), status=VALUES(status),
                    start_date=VALUES(start_date), end_date=VALUES(end_date)";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError("AdvertisementModel::upsertPlacement - Prepare failed: " . $this->mysqli->error);
            return false;
        }

        $name      = $data['name']             ?? '';
        $slotKey   = $data['slot_key']         ?? '';
        $placement = $data['placement']        ?? 'global';
        $adCode    = $data['ad_code']          ?? '';
        $pubName   = $data['publisher_name']   ?? null;
        $pubEmail  = $data['publisher_email']  ?? null;
        $status    = $data['status']           ?? 'active';
        $startDate = $data['start_date']       ?? null;
        $endDate   = $data['end_date']         ?? null;
        $createdBy = (int)($data['created_by'] ?? 0);

        $stmt->bind_param('sssssssssi',
            $name, $slotKey, $placement, $adCode,
            $pubName, $pubEmail, $status, $startDate, $endDate, $createdBy
        );
        if (!$stmt->execute()) {
            logError("AdvertisementModel::upsertPlacement - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $id = $stmt->insert_id ?: (int)($data['id'] ?? 0);
        $stmt->close();
        return $id > 0 ? $id : true;
    }

    // ================================================================
    //  SECTION 4 — SPONSORED POSTS
    // ================================================================

    /**
     * Check whether a post has a sponsored-posts row
     */
    public function getSponsoredForPost(int $postId): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, post_id, sponsor_name, sponsor_logo, label_type, label_text, label_color, background_color, sponsored_from, sponsored_until, paid_amount, payment_method, payment_status, notes, created_by, created_at FROM sponsored_posts WHERE post_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Get all sponsored posts
     */
    public function getSponsoredPosts(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT sp.id, sp.post_id, sp.sponsor_name, sp.sponsor_logo, sp.label_type, sp.label_text, sp.label_color, sp.background_color, sp.sponsored_from, sp.sponsored_until, sp.paid_amount, sp.payment_method, sp.payment_status, sp.notes, sp.created_by, sp.created_at, p.title AS post_title, p.slug AS post_slug, p.published
             FROM sponsored_posts sp
             LEFT JOIN posts p ON p.id = sp.post_id
             ORDER BY sp.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Create or update sponsored-post record
     */
    public function setSponsoredPost(array $data): int|false
    {
        $sql = "INSERT INTO sponsored_posts
                (post_id, sponsor_name, sponsor_logo, label_type, label_text,
                 label_color, background_color, sponsored_from, sponsored_until,
                 paid_amount, payment_method, payment_status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    sponsor_name=VALUES(sponsor_name),
                    sponsor_logo=VALUES(sponsor_logo),
                    label_type=VALUES(label_type),
                    label_text=VALUES(label_text),
                    label_color=VALUES(label_color),
                    background_color=VALUES(background_color),
                    sponsored_from=VALUES(sponsored_from),
                    sponsored_until=VALUES(sponsored_until),
                    paid_amount=VALUES(paid_amount),
                    payment_method=VALUES(payment_method),
                    payment_status=VALUES(payment_status),
                    notes=VALUES(notes)";

        $stmt = $this->mysqli->prepare($sql);
        if (!$stmt) {
            logError("AdvertisementModel::setSponsoredPost - Prepare failed: " . $this->mysqli->error);
            return false;
        }

        $postId        = (int)($data['post_id']           ?? 0);
        $sponsorName   = $data['sponsor_name']           ?? '';
        $sponsorLogo   = $data['sponsor_logo']           ?? null;
        $labelType     = $data['label_type']             ?? 'sponsored';
        $labelText     = $data['label_text']             ?? 'Sponsored';
        $labelColor    = $data['label_color']            ?? '#f59e0b';
        $bgColor       = $data['background_color']       ?? '#fffbeb';
        $from          = $data['sponsored_from']         ?? null;
        $until         = $data['sponsored_until']        ?? null;
        $paid          = $data['paid_amount']            ?? null;
        $pm            = $data['payment_method']         ?? null;
        $ps            = $data['payment_status']         ?? 'pending';
        $notes         = $data['notes']                  ?? null;
        $createdBy     = (int)($data['created_by']       ?? 0);

        $stmt->bind_param('issssssssssssi',
            $postId, $sponsorName, $sponsorLogo, $labelType, $labelText,
            $labelColor, $bgColor, $from, $until, $paid, $pm, $ps, $notes, $createdBy
        );

        if (!$stmt->execute()) {
            logError("AdvertisementModel::setSponsoredPost - Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }
        $id = $stmt->insert_id ?: $postId;
        $stmt->close();
        return $id;
    }

    /**
     * Remove sponsored flag from a post
     */
    public function removeSponsoredPost(int $postId): bool
    {
        $stmt = $this->mysqli->prepare("DELETE FROM sponsored_posts WHERE post_id = ?");
        $stmt->bind_param('i', $postId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // ================================================================
    //  SECTION 5 — REVENUE SETTINGS
    // ================================================================

    /**
     * Get a raw revenue setting value by key.
     */
    public function getRevenueSetting(string $key, mixed $default = null): mixed
    {
        $stmt = $this->mysqli->prepare(
            "SELECT setting_value, setting_type FROM revenue_settings WHERE setting_key = ? LIMIT 1"
        );
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) return $default;

        $val  = $row['setting_value'] ?? '';
        $type = strtolower($row['setting_type'] ?? 'text');

        return match ($type) {
            'number' => (float)$val,
            'boolean'=> filter_var($val, FILTER_VALIDATE_BOOLEAN),
            'json'   => json_decode($val, true),
            default  => $val,
        };
    }

    /**
     * Get all revenue settings grouped by category
     */
    public function getAllRevenueSettings(): array
    {$res = $this->mysqli->query("SELECT id, setting_key, setting_value, setting_type, category, updated_at FROM revenue_settings ORDER BY category, setting_key");
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $grouped = [];
        foreach ($rows as $r) {
            $cat = $r['category'] ?? 'general';
            $grouped[$cat][$r['setting_key']] = $r;
        }
        return $grouped;
    }

    /**
     * Upsert a revenue setting row
     */
    public function setRevenueSetting(string $key, mixed $value, string $type = 'text',
                                      string $category = 'general', ?int $updatedBy = null): bool
    {
        $rawVal = match ($type) {
            'boolean' => (int)(bool)$value,
            'number'  => (string)(float)$value,
            'json'    => json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => (string)$value,
        };

        $types = 'ssss';
        $params = [$key, $rawVal, $type, $category];
        if ($updatedBy !== null) {
            $types .= 'i';
            $params[] = $updatedBy;
        }

        $sql = "INSERT INTO revenue_settings (setting_key, setting_value, setting_type, category, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    setting_value=VALUES(setting_value),
                    setting_type=VALUES(setting_type),
                    category=VALUES(category),
                    updated_at=NOW()";

        $stmt = $this->mysqli->prepare($sql);
        $refs = [];
        foreach ($params as $k => &$v) $refs[$k] = &$v;
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
