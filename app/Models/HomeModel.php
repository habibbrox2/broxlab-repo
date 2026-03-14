<?php
class HomeModel
{
    private $db;
    private static $hasContentRatingsTable = null;

    public function __construct($mysqli)
    {
        $this->db = $mysqli;
    }

    private function extractMultipleImages($html, $limit = 3)
    {
        if (empty($html)) return [];

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $images = [];
        foreach ($dom->getElementsByTagName('img') as $img) {
            foreach (['src', 'data-src', 'data-original'] as $attr) {
                if ($img->hasAttribute($attr)) {
                    $images[] = $img->getAttribute($attr);
                    break;
                }
            }
            if (count($images) >= $limit) break;
        }

        return array_unique($images);
    }
    // Get tags for any content type
    private function getTagsForContent($contentType, $contentId)
    {
        $sql = "SELECT t.id, t.name, t.slug
                FROM content_tags ct
                JOIN tags t ON ct.tag_id = t.id
                WHERE ct.content_type = ? AND ct.content_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $contentType, $contentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Get categories for any content type
    private function getCategoriesForContent($contentType, $contentId)
    {
        $sql = "SELECT c.id, c.name, c.slug
                FROM content_categories cc
                JOIN categories c ON cc.category_id = c.id
                WHERE cc.content_type = ? AND cc.content_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('si', $contentType, $contentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function hasContentRatingsTable()
    {
        if (self::$hasContentRatingsTable !== null) {
            return self::$hasContentRatingsTable;
        }

        $tableCheck = $this->db->query("SHOW TABLES LIKE 'content_ratings'");
        self::$hasContentRatingsTable = ($tableCheck && $tableCheck->num_rows > 0);
        if ($tableCheck instanceof mysqli_result) {
            $tableCheck->free();
        }

        return self::$hasContentRatingsTable;
    }

    // --------------------- EXISTING FUNCTION ---------------------
    public function getUnifiedContent($page = 1, $limit = 15, $sort = 'latest')
    {
        $offset = ($page - 1) * $limit;

        switch ($sort) {
            case 'views':
                $orderBy = 'views DESC';
                break;
            case 'impressions':
                $orderBy = 'impressions DESC';
                break;
            default:
                $orderBy = 'created_at DESC';
        }

        // Test query: Get posts only first
        $testSql = "SELECT id, title, published FROM posts LIMIT 5";
        $testResult = $this->db->query($testSql);
        $testRows = $testResult->fetch_all(MYSQLI_ASSOC);
<<<<<<< HEAD
        error_log("TEST POSTS: " . json_encode($testRows));
=======
>>>>>>> temp_branch

        $sql = "
        SELECT * FROM (
            SELECT
                m.id,
                m.brand_name AS title,
                m.model_name AS subtitle,
                img.image_url AS image,
                m.created_at,
                0 AS views,
                0 AS impressions,
                'mobile' AS type,
                NULL AS url
            FROM mobiles m
            LEFT JOIN mobile_images img
                ON m.id = img.mobile_id
                AND img.id = (
                    SELECT MIN(id)
                    FROM mobile_images
                    WHERE mobile_id = m.id
                )

            UNION ALL

            SELECT
                p.id,
                p.title,
                p.content AS subtitle,
                NULL AS image,
                p.created_at,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = p.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = p.id) AS impressions,
                'page' AS type,
                p.slug AS url
            FROM pages p
            WHERE p.published IN ('1', 1, '0', 0)

            UNION ALL

            SELECT
                po.id,
                po.title,
                po.content AS subtitle,
                NULL AS image,
                po.created_at,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = po.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = po.id) AS impressions,
                'post' AS type,
                po.slug AS url
            FROM posts po
            WHERE po.published IN ('1', 1, '0', 0)
        ) AS unified
        ORDER BY $orderBy
        LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("SQL Error in getUnifiedContent: " . $this->db->error);
            return ['contents' => [], 'total_pages' => 0];
        }
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        // Debug: log what types we got
        $types = array_count_values(array_column($rows, 'type'));
        error_log("getUnifiedContent result types: " . json_encode($types));

        foreach ($rows as &$row) {
            $images = [];
            if ($row['type'] === 'mobile' && !empty($row['image'])) {
                $images[] = $row['image'];
            } elseif ($row['type'] === 'page' || $row['type'] === 'post') {
                $images = $this->extractMultipleImages($row['subtitle'], 3);
            }
            $row['images'] = !empty($images) ? $images : [];

            // Attach tags
            $row['tags'] = $this->getTagsForContent($row['type'], $row['id']);
            // Attach categories
            $row['categories'] = $this->getCategoriesForContent($row['type'], $row['id']);
        }

        // Total pages
        $countSql = "
            SELECT SUM(cnt) as total FROM (
                SELECT COUNT(*) AS cnt FROM mobiles
                UNION ALL
                SELECT COUNT(*) AS cnt FROM pages WHERE published IN ('1', 1, '0', 0)
                UNION ALL
                SELECT COUNT(*) AS cnt FROM posts WHERE published IN ('1', 1, '0', 0)
            ) AS t";
        $totalResult = $this->db->query($countSql);
        $totalRow = $totalResult->fetch_assoc();
        $totalPages = ceil($totalRow['total'] / $limit);

        return [
            'contents' => $rows,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Get top posts for homepage slider (ranked by views/impressions).
     */
    public function getTopPosts($limit = 8)
    {
        $limit = max(1, (int)$limit);
        $ratingSelect = $this->hasContentRatingsTable()
            ? "(SELECT ROUND(AVG(cr.rating), 1) FROM content_ratings cr WHERE cr.content_type = 'post' AND cr.content_id = p.id) AS rating_average,
               (SELECT COUNT(*) FROM content_ratings cr WHERE cr.content_type = 'post' AND cr.content_id = p.id) AS rating_total"
            : "0.0 AS rating_average, 0 AS rating_total";
        $sql = "
            SELECT
                p.id,
                p.title,
                p.slug,
                p.content,
                p.created_at,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions,
                {$ratingSelect}
            FROM posts p
            WHERE p.published = 1
            ORDER BY views DESC, impressions DESC, p.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();

        foreach ($rows as &$row) {
            $images = $this->extractMultipleImages($row['content'] ?? '', 1);
            $row['image'] = $images[0] ?? '';
            $row['type'] = 'post';
            $row['views'] = (int)($row['views'] ?? 0);
            $row['impressions'] = (int)($row['impressions'] ?? 0);
            $row['rating_average'] = (float)($row['rating_average'] ?? 0);
            $row['rating_total'] = (int)($row['rating_total'] ?? 0);
        }

        return $rows;
    }

    /**
     * Get top active services for homepage slider (ranked by views/impressions).
     */
    public function getTopServices($limit = 8)
    {
        $limit = max(1, (int)$limit);
        $ratingSelect = $this->hasContentRatingsTable()
            ? "(SELECT ROUND(AVG(cr.rating), 1) FROM content_ratings cr WHERE cr.content_type = 'service' AND cr.content_id = s.id) AS rating_average,
               (SELECT COUNT(*) FROM content_ratings cr WHERE cr.content_type = 'service' AND cr.content_id = s.id) AS rating_total"
            : "0.0 AS rating_average, 0 AS rating_total";
        $sql = "
            SELECT
                s.id,
                s.name,
                s.slug,
                s.description,
                s.created_at,
                s.status,
                COALESCE(
                    (
                        SELECT si.thumbnail_path
                        FROM service_images si
                        WHERE si.service_id = s.id AND si.deleted_at IS NULL
                        ORDER BY si.is_featured DESC, si.display_order ASC, si.id ASC
                        LIMIT 1
                    ),
                    (
                        SELECT si.image_path
                        FROM service_images si
                        WHERE si.service_id = s.id AND si.deleted_at IS NULL
                        ORDER BY si.is_featured DESC, si.display_order ASC, si.id ASC
                        LIMIT 1
                    )
                ) AS image,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions,
                {$ratingSelect}
            FROM services s
            WHERE s.status = 'active' AND s.deleted_at IS NULL
            ORDER BY views DESC, impressions DESC, s.created_at DESC
            LIMIT ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();

        foreach ($rows as &$row) {
            $row['title'] = $row['name'] ?? 'Service';
            $img = trim((string)($row['image'] ?? ''));
            if ($img !== '' && strpos($img, '/public_html/') === 0) {
                $img = substr($img, strlen('/public_html'));
            } elseif ($img !== '' && strpos($img, '/public/') === 0) {
                $img = substr($img, strlen('/public'));
            }
            $row['image'] = $img;
            $row['type'] = 'service';
            $row['views'] = (int)($row['views'] ?? 0);
            $row['impressions'] = (int)($row['impressions'] ?? 0);
            $row['rating_average'] = (float)($row['rating_average'] ?? 0);
            $row['rating_total'] = (int)($row['rating_total'] ?? 0);
        }

        return $rows;
    }

    // --------------------- NEW FUNCTIONS ---------------------

    // Get contents by tag
    public function getContentsByTag($tagId, $page = 1, $limit = 15, $sort = 'latest')
    {
        return $this->getContentsByRelation('tag', $tagId, $page, $limit, $sort);
    }

    // Get contents by category
    public function getContentsByCategory($categoryId, $page = 1, $limit = 15, $sort = 'latest')
    {
        return $this->getContentsByRelation('category', $categoryId, $page, $limit, $sort);
    }

    // Internal function (used by tag & category filter)
    private function getContentsByRelation($type, $relationId, $page, $limit, $sort)
    {
        $offset = ($page - 1) * $limit;

        switch ($sort) {
            case 'views':
                $orderBy = 'views DESC';
                break;
            case 'impressions':
                $orderBy = 'impressions DESC';
                break;
            default:
                $orderBy = 'created_at DESC';
        }

        $relationTable = $type === 'tag' ? 'content_tags' : 'content_categories';
        $relationField = $type === 'tag' ? 'tag_id' : 'category_id';

        $sql = "
    SELECT * FROM (
        SELECT 
            m.id,
            m.brand_name AS title,
            m.model_name AS subtitle,
            img.image_url AS image,
            m.created_at,
            0 AS views,
            0 AS impressions,
            'mobile' AS type,
            NULL AS url
        FROM mobiles m
        JOIN {$relationTable} rel 
            ON rel.content_id = m.id 
            AND rel.content_type = 'mobile'
        LEFT JOIN mobile_images img 
            ON m.id = img.mobile_id
            AND img.id = (SELECT MIN(id) FROM mobile_images WHERE mobile_id = m.id)
        WHERE rel.{$relationField} = ?

        UNION ALL

        SELECT 
            p.id,
            p.title,
            p.content AS subtitle,
            NULL AS image,
            p.created_at,
            (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = p.id),
            (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = p.id),
            'page' AS type,
            p.slug AS url
        FROM pages p
        JOIN {$relationTable} rel 
            ON rel.content_id = p.id 
            AND rel.content_type = 'page'
        WHERE p.published=1 AND rel.{$relationField} = ?

        UNION ALL

        SELECT 
            po.id,
            po.title,
            po.content AS subtitle,
            NULL AS image,
            po.created_at,
            (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = po.id),
            (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = po.id),
            'post' AS type,
            po.slug AS url
        FROM posts po
        JOIN {$relationTable} rel 
            ON rel.content_id = po.id 
            AND rel.content_type = 'post'
        WHERE po.published=1 AND rel.{$relationField} = ?
    ) AS unified
    ORDER BY $orderBy
    LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiiii', $relationId, $relationId, $relationId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as &$row) {
            $images = [];
            if ($row['type'] === 'mobile' && !empty($row['image'])) {
                $images[] = $row['image'];
            } elseif ($row['type'] === 'page' || $row['type'] === 'post') {
                $images = $this->extractMultipleImages($row['subtitle'], 3);
            }
            $row['images'] = $images ?: [];

            $row['tags'] = $this->getTagsForContent($row['type'], $row['id']);
            $row['categories'] = $this->getCategoriesForContent($row['type'], $row['id']);
        }

        // ✅ Correct total count
        $countSql = "
        SELECT SUM(cnt) as total FROM (
            SELECT COUNT(*) AS cnt 
            FROM mobiles m 
            JOIN {$relationTable} rel ON rel.content_id = m.id AND rel.content_type = 'mobile'
            WHERE rel.{$relationField} = ?
            UNION ALL
            SELECT COUNT(*) AS cnt 
            FROM pages p 
            JOIN {$relationTable} rel ON rel.content_id = p.id AND rel.content_type = 'page'
            WHERE p.published=1 AND rel.{$relationField} = ?
            UNION ALL
            SELECT COUNT(*) AS cnt 
            FROM posts po 
            JOIN {$relationTable} rel ON rel.content_id = po.id AND rel.content_type = 'post'
            WHERE po.published=1 AND rel.{$relationField} = ?
        ) AS t";

        $countStmt = $this->db->prepare($countSql);
        $countStmt->bind_param('iii', $relationId, $relationId, $relationId);
        $countStmt->execute();
        $totalResult = $countStmt->get_result()->fetch_assoc();
        $totalPages = ceil($totalResult['total'] / $limit);

        return [
            'contents' => $rows,
            'total_pages' => $totalPages
        ];
    }

    /**
     * Get real-time homepage statistics
     */
    public function getHomepageStats()
    {
        $stats = [];

        // Active Users - Count users with activity in last 30 days
        $sql = "SELECT COUNT(DISTINCT u.id) as count 
                FROM users u
                LEFT JOIN activity_logs al ON u.id = al.user_id
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        $stats['active_users'] = intval($row['count'] ?? 0);

        // Device Specs (Mobiles)
        $sql = "SELECT COUNT(*) as count FROM mobiles";
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        $stats['device_specs'] = intval($row['count'] ?? 0);

        // Articles (Posts)
        $sql = "SELECT COUNT(*) as count FROM posts WHERE published = 1";
        $result = $this->db->query($sql);
        $row = $result->fetch_assoc();
        $stats['articles'] = intval($row['count'] ?? 0);

        // Job Posts - Check if table exists first
        $stats['job_posts'] = 0;
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'job_posts'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $sql = "SELECT COUNT(*) as count FROM job_posts WHERE status = 'published' OR status = 'active'";
            $result = $this->db->query($sql);
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['job_posts'] = intval($row['count'] ?? 0);
            }
        }

        return $stats;
    }
}
