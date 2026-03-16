<?php

// classes/MobileModel.php



class MobileModel {

    private $mysqli;



    public function __construct($mysqli) {

        $this->mysqli = $mysqli;

    }



    // Insert a mobile

    public function insertMobile($brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official = 0) {

        try {
            $stmt = $this->mysqli->prepare(
                "INSERT INTO mobiles (brand_name, model_name, official_price, unofficial_price, status, release_date, is_official) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param("ssddssi", $brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official);

            $stmt->execute();
            $id = (int)$stmt->insert_id;
            $stmt->close();
            return $id;
        } catch (\Throwable $e) {
            error_log('[MobileModel] insertMobile failed: ' . $e->getMessage());
            return 0;
        }

    }

    public function fetchAllSpecKeys(): array {

        $stmt = $this->mysqli->query("SELECT DISTINCT spec_key FROM mobile_specs ORDER BY spec_key ASC");

        return $stmt->fetch_all(MYSQLI_ASSOC); // returns array of ['spec_key' => '...']

    }



    // Update a mobile

    public function updateMobile($id, $brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official = 0) {

        try {
            $stmt = $this->mysqli->prepare(
                "UPDATE mobiles SET brand_name = ?, model_name = ?, official_price = ?, unofficial_price = ?, status = ?, release_date = ?, is_official = ? WHERE id = ?"
            );

            $stmt->bind_param("ssddssii", $brand_name, $model_name, $official_price, $unofficial_price, $status, $release_date, $is_official, $id);

            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (\Throwable $e) {
            error_log('[MobileModel] updateMobile failed: ' . $e->getMessage());
            return false;
        }

    }



    // Insert specifications

    public function insertSpecifications($mobile_id, array $keys, array $values) {

        if (empty($keys)) return;

        $spec_values = [];

        $params = [];

        foreach ($keys as $i => $key) {

            $spec_values[] = "(?, ?, ?)";

            $params[] = $mobile_id;

            $params[] = $key;

            $params[] = $values[$i] ?? '';

        }

        $query = "INSERT INTO mobile_specs (mobile_id, spec_key, spec_value) VALUES " . implode(", ", $spec_values);

        $stmt = $this->mysqli->prepare($query);

        $stmt->bind_param(str_repeat("iss", count($keys)), ...$params);

        $stmt->execute();

        $stmt->close();

    }



    // ✅ Update specifications (delete + reinsert)

    public function updateSpecifications($mobile_id, array $keys, array $values) {

        $stmt = $this->mysqli->prepare("DELETE FROM mobile_specs WHERE mobile_id = ?");

        $stmt->bind_param("i", $mobile_id);

        $stmt->execute();

        $stmt->close();

        $this->insertSpecifications($mobile_id, $keys, $values);

    }



    // Insert images

    public function insertImages($mobile_id, array $images) {

        foreach ($images as $image) {

            $stmt = $this->mysqli->prepare("INSERT INTO mobile_images (mobile_id, image_url) VALUES (?, ?)");

            $stmt->bind_param("is", $mobile_id, $image);

            $stmt->execute();

            $stmt->close();

        }

    }



    // ✅ Update images (delete + reinsert)

    public function updateImages($mobile_id, array $images) {

        $stmt = $this->mysqli->prepare("DELETE FROM mobile_images WHERE mobile_id = ?");

        $stmt->bind_param("i", $mobile_id);

        $stmt->execute();

        $stmt->close();

        $this->insertImages($mobile_id, $images);

    }

    // Delete specific images
    public function deleteImages(array $image_ids) {
        if (empty($image_ids)) return false;
        
        $placeholders = implode(',', array_fill(0, count($image_ids), '?'));
        $stmt = $this->mysqli->prepare("DELETE FROM mobile_images WHERE id IN ($placeholders)");
        
        // Bind parameters dynamically
        $types = str_repeat('i', count($image_ids));
        $stmt->bind_param($types, ...$image_ids);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }



    // Delete mobile (with related data)

    public function deleteMobile($id) {

        $this->mysqli->prepare("DELETE FROM mobile_specs WHERE mobile_id = ?")->bind_param("i", $id)->execute();

        $this->mysqli->prepare("DELETE FROM mobile_images WHERE mobile_id = ?")->bind_param("i", $id)->execute();

        $this->mysqli->prepare("DELETE FROM mobiles WHERE id = ?")->bind_param("i", $id)->execute();

    }



    // Fetch by ID

    public function fetchMobileById($id) {

        $stmt = $this->mysqli->prepare("SELECT * FROM mobiles WHERE id = ?");

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $data = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        return $data;

    }



    // Fetch list (paginated) - অপ্টিমাইজড
    public function fetchMobiles($limit = 20, $offset = 0, $sort = 'id', $order = 'DESC') {
            // whitelist allowed sort columns
            $allowed = ['id','brand_name','model_name','release_date','created_at'];
            if (!in_array($sort, $allowed)) $sort = 'id';
            $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

            // শুধু মোবাইল ডেটা ফেচ করুন (ইমেজ জয়েন ছাড়া)
            $sql = "
                SELECT m.id, m.brand_name, m.model_name, m.official_price, 
                       m.unofficial_price, m.status, m.release_date, m.created_at, m.is_official
                FROM mobiles m
                ORDER BY " . $sort . " " . $order . "
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $mobiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // যদি মোবাইল আছে তাহলে এক কোয়েরিতে সব ইমেজ ফেচ করুন
            if (!empty($mobiles)) {
                $mobileIds = array_column($mobiles, 'id');
                $placeholders = implode(',', array_fill(0, count($mobileIds), '?'));
                
                $imageSQL = "SELECT mobile_id, image_url FROM mobile_images WHERE mobile_id IN ({$placeholders}) ORDER BY mobile_id, id";
                $imageStmt = $this->mysqli->prepare($imageSQL);
                $imageStmt->bind_param(str_repeat('i', count($mobileIds)), ...$mobileIds);
                $imageStmt->execute();
                $images = $imageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $imageStmt->close();
                
                // ইমেজ প্রথমটি অনুযায়ী গ্রুপ করুন
                $firstImageByMobile = [];
                foreach ($images as $img) {
                    if (!isset($firstImageByMobile[$img['mobile_id']])) {
                        $firstImageByMobile[$img['mobile_id']] = $img['image_url'];
                    }
                }
                
                // মোবাইলে প্রথম ইমেজ এসাইন করুন
                foreach ($mobiles as &$mobile) {
                    $mobile['image_path'] = $firstImageByMobile[$mobile['id']] ?? null;
                }
            }

            return $mobiles;
    }



    // Count total

    public function countMobiles() {

        return (int)($this->mysqli->query("SELECT COUNT(*) as total FROM mobiles")->fetch_assoc()['total'] ?? 0);

    }



    // Specs

    public function fetchSpecsByMobileId($id) {

        $stmt = $this->mysqli->prepare("SELECT spec_key, spec_value FROM mobile_specs WHERE mobile_id = ?");

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return $data;

    }



    // Images

    public function fetchImagesByMobileId($id) {

        $stmt = $this->mysqli->prepare("SELECT image_url FROM mobile_images WHERE mobile_id = ?");

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        return $rows; // আর array_column ব্যবহার করবেন না

    }

    /**
     * Get user mobiles count
     */
    public function getUserMobilesCount(int $userId): int {
        // Mobiles table doesn't support user ownership - return 0
        // In future, implement user_applications table for user submissions
        return 0;
    }

    /**
     * Get user mobiles count by status
     */
    public function getUserMobilesCountByStatus(int $userId, string $status): int {
        // Mobiles table doesn't support user ownership - return 0
        // In future, implement user_applications table for user submissions
        return 0;
    }

    /**
     * Get user's recent mobiles
     */
    public function getUserRecentMobiles(int $userId, int $limit = 10): array {
        // Mobiles table doesn't support user ownership - return empty array
        // In future, implement user_applications table for user submissions
        return [];
    }

    // ====================== PAGINATION & SEARCH ======================

    /**
     * Get paginated, searched, and sorted mobiles
     */
    public function getMobiles($page = 1, $limit = 20, $search = '', $sort = 'brand_name', $order = 'ASC', $filters = []) {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $allowedSorts = ['id', 'brand_name', 'model_name', 'official_price', 'status', 'created_at', 'is_official'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'brand_name';
        
        $sql = "SELECT id, brand_name, model_name, official_price, unofficial_price, status, release_date, is_official, created_at
                FROM mobiles
                WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (brand_name LIKE ? OR model_name LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        // Filter by status if provided
        if (!empty($filters['status']) && in_array($filters['status'], ['official', 'unofficial', 'both'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (isset($filters['is_official']) && $filters['is_official'] !== '') {
            $sql .= " AND is_official = ?";
            $params[] = $filters['is_official'];
            $types .= 'i';
        }
        
        $sql .= " ORDER BY `{$sort}` {$order} LIMIT {$limit} OFFSET {$offset}";
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get total count of mobiles with optional search and filters
     */
    public function getMobilesCount($search = '', $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM mobiles WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (brand_name LIKE ? OR model_name LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['official', 'unofficial', 'both'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
            $types .= 's';
        }

        if (isset($filters['is_official']) && $filters['is_official'] !== '') {
            $sql .= " AND is_official = ?";
            $params[] = $filters['is_official'];
            $types .= 'i';
        }
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    // Get related mobiles (অপ্টিমাইজড: N+1 সমস্যা সমাধান করা)
    public function getRelatedMobiles($mobileId, $limit = 3) {
        // মোট মোবাইল সংখ্যা পান (র‍্যান্ডম অফসেট এর জন্য)
        $countResult = $this->mysqli->query("SELECT COUNT(*) as total FROM mobiles WHERE id != {$mobileId}");
        $countData = $countResult->fetch_assoc();
        $total = $countData['total'] ?? 0;
        
        if ($total < 1) return [];
        
        // র‍্যান্ডম অফসেট তৈরি করুন (ORDER BY RAND() এর চেয়ে দ্রুত)
        $randomOffset = mt_rand(0, max(0, $total - $limit));
        
        // মোবাইল ডেটা ফেচ করুন
        $sql = "SELECT id, brand_name, model_name, official_price, unofficial_price, status, release_date 
                FROM mobiles WHERE id != ? LIMIT {$limit} OFFSET {$randomOffset}";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $mobileId);
        $stmt->execute();
        $mobiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // মোবাইল IDs সংগ্রহ করুন
        if (!empty($mobiles)) {
            $mobileIds = array_column($mobiles, 'id');
            $placeholders = implode(',', array_fill(0, count($mobileIds), '?'));
            
            // এক কোয়েরিতে সব ইমেজ ফেচ করুন (N+1 সমাধান)
            $imageSQL = "SELECT mobile_id, image_url FROM mobile_images WHERE mobile_id IN ({$placeholders}) ORDER BY mobile_id";
            $imageStmt = $this->mysqli->prepare($imageSQL);
            $imageStmt->bind_param(str_repeat('i', count($mobileIds)), ...$mobileIds);
            $imageStmt->execute();
            $images = $imageStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $imageStmt->close();
            
            // ইমেজ গ্রুপ করুন
            $imagesByMobile = [];
            foreach ($images as $img) {
                if (!isset($imagesByMobile[$img['mobile_id']])) {
                    $imagesByMobile[$img['mobile_id']] = [];
                }
                $imagesByMobile[$img['mobile_id']][] = ['url' => $img['image_url']];
            }
            
            // মোবাইলে ইমেজ এসাইন করুন
            foreach ($mobiles as &$mobile) {
                $mobile['images'] = $imagesByMobile[$mobile['id']] ?? [];
                $mobile['image'] = !empty($mobile['images']) ? $mobile['images'][0]['url'] : null;
            }
        }
        
        return $mobiles;
    }

    // Get complete mobile data for detail view (এক জায়গায় সব ডেটা - N+1 সমস্যা নেই)
    public function getMobileComplete($id) {
        if (!$id || !is_numeric($id)) return null;

        // মোবাইল বেসিক ডেটা
        $sql = "SELECT * FROM mobiles WHERE id = ? LIMIT 1";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $mobile = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$mobile) return null;

        // তিনটি আলাদা কোয়েরিতে: স্পেসিফিকেশন, ইমেজ, ট্যাগ (প্রয়োজন অনুযায়ী)
        
        // ১. স্পেসিফিকেশন
        $specSQL = "SELECT * FROM mobile_specs WHERE mobile_id = ? ORDER BY id";
        $specStmt = $this->mysqli->prepare($specSQL);
        $specStmt->bind_param("i", $id);
        $specStmt->execute();
        $mobile['specifications'] = $specStmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $specStmt->close();

        // ২. ইমেজ
        $imgSQL = "SELECT * FROM mobile_images WHERE mobile_id = ? ORDER BY id";
        $imgStmt = $this->mysqli->prepare($imgSQL);
        $imgStmt->bind_param("i", $id);
        $imgStmt->execute();
        $mobile['images'] = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $imgStmt->close();

        // ৩. ট্যাগ (যদি অস্তিত্ব থাকে)
        $tagCheck = $this->mysqli->query("SHOW TABLES LIKE 'content_tags'");
        if ($tagCheck && $tagCheck->num_rows > 0) {
            $tagSQL = "SELECT t.* FROM content_tags ct 
                      JOIN tags t ON t.id = ct.tag_id 
                      WHERE ct.content_type = 'mobile' AND ct.content_id = ? 
                      ORDER BY ct.id";
            $tagStmt = $this->mysqli->prepare($tagSQL);
            $tagStmt->bind_param("i", $id);
            $tagStmt->execute();
            $mobile['tags'] = $tagStmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $tagStmt->close();
        }

        return $mobile;
    }
}
