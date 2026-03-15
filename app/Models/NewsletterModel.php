<?php

// classes/NewsletterModel.php

class NewsletterModel {

    private $mysqli;
    private $table = 'newsletter_subscribers';

    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Subscribe email to newsletter
     */
    public function subscribe($email, $name = '', $preferences = []): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => 'Invalid email address'];
        }

        // Check if already subscribed
        $stmt = $this->mysqli->prepare("SELECT id FROM {$this->table} WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result->num_rows > 0) {
            return ['error' => 'Email already subscribed'];
        }

        $status = 'active';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $preferences_json = json_encode($preferences ?? []);
        $subscribed_at = date('Y-m-d H:i:s');

        $stmt = $this->mysqli->prepare(
            "INSERT INTO {$this->table} (email, name, status, preferences, ip_address, subscribed_at) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            return ['error' => 'Database error: ' . $this->mysqli->error];
        }

        $stmt->bind_param("ssssss", $email, $name, $status, $preferences_json, $ip_address, $subscribed_at);
        $success = $stmt->execute();
        $subscriberId = $stmt->insert_id;
        $stmt->close();

        if ($success) {
            // Send push and email notification for newsletter subscription
            // Note: Newsletter subscribers are not users, so we'll send email notification only
            try {
                require_once __DIR__ . '/../Helpers/EmailHelper.php';
                
                $htmlBody = "<h2>স্বাগতম আমাদের নিউজলেটারে!</h2>";
                $htmlBody .= "<p>হ্যালো " . htmlspecialchars($name ?: 'Subscriber') . ",</p>";
                $htmlBody .= "<p>আপনি সফলভাবে আমাদের নিউজলেটার সাবস্ক্রাইব করেছেন।</p>";
                $htmlBody .= "<p>এখন আপনি নিয়মিত আপডেট, অফার এবং বিশেষ ঘোষণা পাবেন।</p>";
                $htmlBody .= "<p>ধন্যবাদ!</p>";
                
                sendEmail($email, 'নিউজলেটার স্বাগত', $htmlBody, $name ?: 'Subscriber');
                
            } catch (Exception $e) {
                logError("Newsletter subscription email failed: " . $e->getMessage());
            }
            
            return ['success' => 'Successfully subscribed to our newsletter'];
        } else {
            return ['error' => 'Failed to subscribe. Please try again.'];
        }
    }

    /**
     * Get all admin user IDs
     */
    public function getAdminUserIds(): array {
        $stmt = $this->mysqli->prepare("
            SELECT DISTINCT u.id FROM users u
            INNER JOIN user_roles ur ON u.id = ur.user_id
            INNER JOIN roles r ON ur.role_id = r.id
            WHERE (r.name = 'admin' OR r.name = 'super_admin') AND u.status = 'active'
        ");
        
        if (!$stmt) {
            logError("NewsletterModel::getAdminUserIds prepare error: " . $this->mysqli->error);
            return [];
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $adminIds = [];
        
        while ($row = $result->fetch_assoc()) {
            $adminIds[] = $row['id'];
        }
        
        $stmt->close();
        return $adminIds;
    }

    /**
     * Unsubscribe email from newsletter
     */
    public function unsubscribe($email): bool {
        $status = 'unsubscribed';
        $stmt = $this->mysqli->prepare("UPDATE {$this->table} SET status = ? WHERE email = ?");
        $stmt->bind_param("ss", $status, $email);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get subscriber count
     */
    public function getSubscriberCount(): int {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM {$this->table} WHERE status = 'active'");
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get all subscribers
     */
    public function getAllSubscribers($status = 'active', $limit = 100, $offset = 0): array {
        $stmt = $this->mysqli->prepare(
            "SELECT id, email, name, status, subscribed_at FROM {$this->table} 
             WHERE status = ? ORDER BY subscribed_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->bind_param("sii", $status, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $subscribers = [];
        while ($row = $result->fetch_assoc()) {
            $subscribers[] = $row;
        }
        $stmt->close();
        return $subscribers;
    }
}
