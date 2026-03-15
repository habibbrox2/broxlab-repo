<?php
// classes/ContentModel.php
class ContentModel {
    private $mysqli;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    // -----------------Image Extractor -----------------
 private function extractFirstImage($html, $resolve = true) {
    if (empty($html)) return null;

    // Decode HTML entities
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    foreach ($dom->getElementsByTagName('img') as $img) {
        foreach (['src', 'data-src', 'data-original'] as $attr) {
            if ($img->hasAttribute($attr)) {
                $val = trim($img->getAttribute($attr));
                if ($val === '') continue;
                if ($resolve) {
                    // Resolve numeric IDs or JSON-encoded structures to usable URLs
                    $resolved = $this->resolveMaybeMediaReference($val);
                    if (!empty($resolved)) return $resolved;
                } else {
                    // Return raw candidate value (no numeric/JSON resolution)
                    return $val;
                }
            }
        }
    }

    // Check for meta tags (og:image / twitter:image)
    foreach ($dom->getElementsByTagName('meta') as $meta) {
        if ($meta->hasAttribute('property') && in_array(strtolower($meta->getAttribute('property')), ['og:image', 'twitter:image'])) {
            $val = trim($meta->getAttribute('content'));
            if (!empty($val)) {
                if ($resolve) {
                    $resolved = $this->resolveMaybeMediaReference($val);
                    if (!empty($resolved)) return $resolved;
                } else {
                    return $val;
                }
            }
        }
        if ($meta->hasAttribute('name') && in_array(strtolower($meta->getAttribute('name')), ['og:image', 'twitter:image'])) {
            $val = trim($meta->getAttribute('content'));
            if (!empty($val)) {
                if ($resolve) {
                    $resolved = $this->resolveMaybeMediaReference($val);
                    if (!empty($resolved)) return $resolved;
                } else {
                    return $val;
                }
            }
        }
    }

    // Fallback: try to extract image URLs by regex (handles encoded/escaped HTML, markdown, raw URLs)
    $patterns = [
        '/src=[\"\']([^\"\']+?\.(?:jpe?g|png|gif|webp|svg|avif))(?:[\"\'])/i',
        '/!\[[^\]]*\]\(([^)]+)\)/i',
        '/(https?:\/\/[^\s\"\']+?\.(?:jpe?g|png|gif|webp|svg|avif))/i',
        '/data:image\/[^;]+;base64,[A-Za-z0-9+\/=]+/i'
    ];
    foreach ($patterns as $pat) {
        if (preg_match($pat, $html, $m)) {
            $candidate = $m[1] ?? $m[0];
            if ($resolve) {
                $resolved = $this->resolveMaybeMediaReference($candidate);
                if (!empty($resolved)) return $resolved;
            } else {
                return $candidate;
            }
        }
    }

    return null;
}  

    private function extractAllImages($html, $resolve = true) {
    if (empty($html)) return [];

    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

    $images = [];

    foreach ($dom->getElementsByTagName('img') as $img) {
        foreach (['src', 'data-src', 'data-original'] as $attr) {
            if ($img->hasAttribute($attr)) {
                $val = trim($img->getAttribute($attr));
                if ($val === '') continue;
                if ($resolve) {
                    $resolved = $this->resolveMaybeMediaReference($val);
                    if (!empty($resolved)) $images[] = $resolved;
                } else {
                    $images[] = $val;
                }
                break;
            }
        }
    }

    // Fallback: extract urls from HTML / markdown / raw text if DOM found nothing
    if (empty($images)) {
        $regexes = [
            '/src=[\"\']([^\"\']+?\.(?:jpe?g|png|gif|webp|svg|avif))(?:[\"\'])/i',
            '/!\[[^\]]*\]\(([^)]+)\)/i',
            '/(https?:\/\/[^\s\"\']+?\.(?:jpe?g|png|gif|webp|svg|avif))/i',
            '/data:image\/[^;]+;base64,[A-Za-z0-9+\/=]+/i'
        ];
        foreach ($regexes as $rx) {
            if (preg_match_all($rx, $html, $matches)) {
                foreach ($matches[1] ?? $matches[0] as $m) {
                    if ($resolve) {
                        $resolved = $this->resolveMaybeMediaReference($m);
                        if (!empty($resolved)) $images[] = $resolved;
                    } else {
                        $images[] = $m;
                    }
                }
            }
        }
    }

    // Normalize uniqueness while preserving order
    $seen = [];
    $out = [];
    foreach ($images as $i) {
        $i = trim((string)$i);
        if ($i === '') continue;
        if (!isset($seen[$i])) {
            $seen[$i] = true;
            $out[] = $i;
        }
    }

    return $out;
}  

    // Public wrappers for controllers to access image extractors
    public function extractFirstImageFromHtml($html, $resolve = true) {
        return $this->extractFirstImage($html, $resolve);
    }

    public function extractAllImagesFromHtml($html, $resolve = true) {
        return $this->extractAllImages($html, $resolve);
    }

    /**
     * Public wrapper to resolve media references (numeric IDs, JSON, arrays) into usable URLs
     */
    public function resolveMediaReference($value) {
        return $this->resolveMaybeMediaReference($value);
    }

    private function resolveMaybeMediaReference($value) {
        if (is_array($value)) {
            return (string)($value['url'] ?? $value['path'] ?? $value['thumbnail_path'] ?? reset($value));
        }

        $val = trim((string)$value);
        if ($val === '') return '';

        // If looks like JSON, try decode
        if (($val[0] ?? '') === '{' || ($val[0] ?? '') === '[') {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return (string)($decoded['url'] ?? $decoded['path'] ?? $decoded['thumbnail_path'] ?? reset($decoded));
                }
            }
        }

        // If purely numeric, treat as media id
        if (preg_match('/^[0-9]+$/', $val)) {
            try {
                if (class_exists('MediaModel')) {
                    $mediaModel = new MediaModel($this->mysqli);
                    $media = $mediaModel->getById((int)$val);
                    if ($media) {
                        return (string)($media['thumbnail_path'] ?? $media['file_path'] ?? $media['url'] ?? '');
                    }
                }
            } catch (Throwable $e) {
                // ignore and fall through
            }
        }

        // Otherwise return as-is
        return $val;
    }

    private function slugify($text) {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    } 

    /**
     * Generate unique SEO-friendly permalink
     * If title is empty, generates a unique ID-based permalink
     */
    public function generateUniquePermalink($title, $excludePostId = null) {
        // If title is empty or null, generate unique ID-based permalink
        if (empty($title)) {
            $baseSlug = 'post-' . uniqid() . '-' . mt_rand(1000, 9999);
        } else {
            $baseSlug = $this->slugify($title);
        }

        // Check if permalink already exists
        $sql = "SELECT COUNT(*) as cnt FROM posts WHERE slug = ?";
        if ($excludePostId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->mysqli->prepare($sql);
        if ($excludePostId) {
            $stmt->bind_param("si", $baseSlug, $excludePostId);
        } else {
            $stmt->bind_param("s", $baseSlug);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        // If permalink doesn't exist, return it
        if ($row['cnt'] == 0) {
            return $baseSlug;
        }

        // If it exists, append a counter
        $counter = 1;
        while ($counter < 1000) {
            $newPermalink = $baseSlug . '-' . $counter;
            $stmt = $this->mysqli->prepare("SELECT COUNT(*) as cnt FROM posts WHERE slug = ?");
            $stmt->bind_param("s", $newPermalink);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            if ($row['cnt'] == 0) {
                return $newPermalink;
            }
            $counter++;
        }

        // Fallback: use uniqid
        return 'post-' . uniqid();
    }

    // -------------------- PAGES --------------------
    public function getAllPages() {
        $sql = "SELECT pg.*,
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = pg.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = pg.id) AS impressions
                FROM pages pg
                ORDER BY pg.created_at DESC";
        $result = $this->mysqli->query($sql);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            // Resolve media references to usable URLs when extracting from content
            $row['image'] = $this->extractFirstImage($row['content'], true);
        }
        return $rows;
    }

    public function getPageById($id) {
        $sql = "SELECT pg.*,
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = pg.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = pg.id) AS impressions
                FROM pages pg
                WHERE pg.id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) $row['image'] = $this->extractFirstImage($row['content'], true);
        return $row;
    }

    public function getPageBySlug($slug) {
        $sql = "SELECT p.*, 
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = p.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = p.id) AS impressions
                FROM pages p WHERE p.slug = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) $row['image'] = $this->extractFirstImage($row['content'], true);
        return $row;
    }

    // -------------------- POSTS --------------------
    public function getAllPosts() {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
                FROM posts p
                ORDER BY p.created_at DESC";
        $result = $this->mysqli->query($sql);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            // Resolve media references to usable URLs when extracting from content
            $row['image'] = $this->extractFirstImage($row['content'], true);
        }
        return $rows;
    }

    public function getPostById($id) {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
                FROM posts p
                WHERE p.id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) $row['image'] = $this->extractFirstImage($row['content'], true);
        return $row;
    }

    public function getPostBySlug($slug) {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                       (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
                FROM posts p
                WHERE p.slug = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row) $row['image'] = $this->extractFirstImage($row['content'], true);
        return $row;
    }

    // -------------------- CRUD METHODS --------------------
    public function createPage($title, $content, $published = 0, $slug = null) {
        $stmt = $this->mysqli->prepare("INSERT INTO pages (title, content, published, slug) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $title, $content, $published, $slug);
        $stmt->execute();
        return $this->mysqli->insert_id;
    }

    public function updatePage($id, $title, $content, $published, $slug) {
        $stmt = $this->mysqli->prepare("UPDATE pages SET title = ?, content = ?, published = ?, slug = ? WHERE id = ?");
        $stmt->bind_param("ssisi", $title, $content, $published, $slug, $id);
        return $stmt->execute();
    }

    public function deletePage($id) {
        $stmt = $this->mysqli->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function createPost($title, $content, $author, $slug, $published = 0, $reader_indexing = null) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO posts (title, content, author, slug, published, reader_indexing) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssii", $title, $content, $author, $slug, $published, $reader_indexing);
        $stmt->execute();
        return $this->mysqli->insert_id;
    }

    public function updatePost($id, $title, $content, $slug, $published, $reader_indexing) {
        $stmt = $this->mysqli->prepare("
            UPDATE posts 
            SET title = ?, content = ?, slug = ?, published = ?, reader_indexing = ? 
            WHERE id = ?
        ");
        $stmt->bind_param("sssiii", $title, $content, $slug, $published, $reader_indexing, $id);
        return $stmt->execute();
    }

    public function deletePost($id) {
        $stmt = $this->mysqli->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ----------------- Unified tracking -----------------
    public function addImpression(string $type, int $contentId, string $ip): bool {
        if (!in_array($type, ['post', 'page', 'service'], true)) {
            return false;
        }
        $stmt = $this->mysqli->prepare("
            INSERT INTO impressions (content_type, content_id, viewer_ip, impression_at)
            VALUES (?, ?, ?, NOW())
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param("sis", $type, $contentId, $ip);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    public function addViewIfUnique24h(string $type, int $contentId, string $ip): bool {
        if (!in_array($type, ['post', 'page', 'service'], true)) {
            return false;
        }

        $exists = $this->mysqli->prepare("
            SELECT id
            FROM views
            WHERE content_type = ? AND content_id = ? AND viewer_ip = ?
              AND viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        if (!$exists) {
            return false;
        }

        $exists->bind_param("sis", $type, $contentId, $ip);
        $exists->execute();
        $exists->store_result();
        $alreadyViewed = $exists->num_rows > 0;
        $exists->close();

        if ($alreadyViewed) {
            return false;
        }

        $insert = $this->mysqli->prepare("
            INSERT INTO views (content_type, content_id, viewer_ip, viewed_at)
            VALUES (?, ?, ?, NOW())
        ");
        if (!$insert) {
            return false;
        }

        $insert->bind_param("sis", $type, $contentId, $ip);
        $ok = $insert->execute();
        $insert->close();
        return (bool)$ok;
    }

    public function addPostImpression($postId, $ip) {
        return $this->addImpression('post', (int)$postId, (string)$ip);
    }

    public function addPageImpression($pageId, $ip) {
        return $this->addImpression('page', (int)$pageId, (string)$ip);
    }

    public function addPostView($postId, $ip) {
        return $this->addViewIfUnique24h('post', (int)$postId, (string)$ip);
    }

    public function addPageView($pageId, $ip) {
        return $this->addViewIfUnique24h('page', (int)$pageId, (string)$ip);
    }

    // Note: Draft status now managed via 'published' column only (0=draft, 1=published)
    // Legacy draft status methods removed

    // ----------------- TAGS FOR POSTS & PAGES -----------------
    public function attachTagsToContent($type, $contentId, array $tagIds) {
        // Remove existing tags
        $stmt = $this->mysqli->prepare("DELETE FROM content_tags WHERE content_type = ? AND content_id = ?");
        $stmt->bind_param("si", $type, $contentId);
        $stmt->execute();
        $stmt->close();

        if (empty($tagIds)) return;

        // Insert new tags
        $stmt = $this->mysqli->prepare("INSERT INTO content_tags (content_type, content_id, tag_id) VALUES (?, ?, ?)");
        foreach ($tagIds as $tagId) {
            $stmt->bind_param("sii", $type, $contentId, $tagId);
            $stmt->execute();
        }
        $stmt->close();
    }

    public function getTagsForContent($type, $contentId) {
        $tags = [];
        $stmt = $this->mysqli->prepare("
            SELECT t.id, t.name, t.slug
            FROM content_tags ct
            INNER JOIN tags t ON ct.tag_id = t.id
            WHERE ct.content_type = ? AND ct.content_id = ?
        ");
        $stmt->bind_param("si", $type, $contentId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tags[] = $row;
        }
        $stmt->close();
        return $tags;
    }

    // ----------------- UTILITY: Categories & Tags -----------------



    public function createCategory($name, $slug = null) {
        $slug = $slug ?: $this->slugify($name);
        $stmt = $this->mysqli->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $slug);
        $stmt->execute();
        return $this->mysqli->insert_id;
    }

    public function createTag($name, $slug = null) {
        $slug = $slug ?: $this->slugify($name);
        $stmt = $this->mysqli->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $slug);
        $stmt->execute();
        return $this->mysqli->insert_id;
    }

    public function getTagsByPostId($postId) {
        return $this->getTagsForContent('post', $postId);
    }
    public function getAllCategories() {
        $result = $this->mysqli->query("SELECT * FROM categories ORDER BY id DESC");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getCategoryById($id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function getCategoryBySlug($slug) {
        $stmt = $this->mysqli->prepare("SELECT * FROM categories WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function updateCategory($id, $name, $slug = null) {
        $slug = $slug ?: $this->slugify($name);
        $stmt = $this->mysqli->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $slug, $id);
        return $stmt->execute();
    }

    public function deleteCategory($id) {
        $stmt = $this->mysqli->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    // ----------------- Tags -----------------
    public function getAllTags() {
        $result = $this->mysqli->query("SELECT * FROM tags ORDER BY id DESC");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getTagById($id) {
        $stmt = $this->mysqli->prepare("SELECT * FROM tags WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function getTagBySlug($slug) {
        $stmt = $this->mysqli->prepare("SELECT * FROM tags WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res->fetch_assoc();
    }

    public function updateTag($id, $name, $slug = null) {
        $slug = $slug ?: $this->slugify($name);
        $stmt = $this->mysqli->prepare("UPDATE tags SET name = ?, slug = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $slug, $id);
        return $stmt->execute();
    }

    public function deleteTag($id) {
        $stmt = $this->mysqli->prepare("DELETE FROM tags WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }


    // -------------------- POSTS: Pagination, Search, Sort --------------------
    public function countPosts($search = '') {
        $sql = "SELECT COUNT(*) as cnt FROM posts WHERE 1";
        if ($search) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $stmt = $this->mysqli->prepare($sql);
            $like = "%$search%";
            $stmt->bind_param("ss", $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        } else {
            $result = $this->mysqli->query($sql);
            $row = $result->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }
    }

    // Old getPosts method removed - use new paginated getPosts() method instead

    // -------------------- PAGES: Pagination, Search, Sort --------------------
    public function countPages($search = '') {
        $sql = "SELECT COUNT(*) as cnt FROM pages WHERE 1";
        if ($search) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $stmt = $this->mysqli->prepare($sql);
            $like = "%$search%";
            $stmt->bind_param("ss", $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        } else {
            $result = $this->mysqli->query($sql);
            $row = $result->fetch_assoc();
            return (int)($row['cnt'] ?? 0);
        }
    }

    // Old getPages method removed - use new paginated getPages() method instead


    // -------------------- Update Content Tags --------------------
    public function updateContentTags($type, $contentId, array $tagIds) {
        $this->attachTagsToContent($type, $contentId, $tagIds);
    }

    // -------------------- Related, Previous, Next --------------------
    public function getPreviousPost($id) {
        $stmt = $this->mysqli->prepare(
            "SELECT p.*, p.slug AS url 
            FROM posts p 
            WHERE p.id < ? 
            ORDER BY p.id DESC 
            LIMIT 1"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getNextPost($id) {
        $stmt = $this->mysqli->prepare(
            "SELECT p.*, p.slug AS url 
            FROM posts p 
            WHERE p.id > ? 
            ORDER BY p.id ASC 
            LIMIT 1"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }





    public function getPreviousPage($id) {
        $stmt = $this->mysqli->prepare("SELECT p.*, p.slug AS url FROM pages p WHERE p.id < ? ORDER BY p.id DESC LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }

    public function getNextPage($id) {
        $stmt = $this->mysqli->prepare("SELECT p.*, p.slug AS url FROM pages p WHERE p.id > ? ORDER BY p.id ASC LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }




    // Attach categories to any content type
    public function attachCategoriesToContent($contentType, $contentId, array $categoryIds) {
        // 1. Delete existing categories
        $stmt = $this->mysqli->prepare("DELETE FROM content_categories WHERE content_type = ? AND content_id = ?");
        $stmt->bind_param("si", $contentType, $contentId);
        $stmt->execute();
        $stmt->close();

        // 2. Insert new categories
        $stmt = $this->mysqli->prepare("INSERT INTO content_categories (content_type, content_id, category_id) VALUES (?, ?, ?)");
        foreach ($categoryIds as $categoryId) {
            $stmt->bind_param("sii", $contentType, $contentId, $categoryId);
            $stmt->execute();
        }
        $stmt->close();
    }

    // Get categories assigned to a content
    public function getCategoriesForContent($contentType, $contentId) {
        $sql = "
            SELECT c.id, c.name, c.slug
            FROM content_categories cc
            JOIN categories c ON cc.category_id = c.id
            WHERE cc.content_type = ? AND cc.content_id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("si", $contentType, $contentId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get new posts created today
     */
    public function getNewPostsToday(): int {
        $today = date('Y-m-d');
        $sql = "SELECT COUNT(*) as count FROM posts 
                WHERE published = 1 AND DATE(created_at) = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get draft count
     */
    public function getDraftCount(): int {
        $sql = "SELECT COUNT(*) as count FROM posts WHERE published = 0";
        $result = $this->mysqli->query($sql);
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get recent posts
     */
    public function getRecentPosts(int $limit = 5): array {
        $sql = "SELECT p.id, p.title, 
                       CASE WHEN p.published = 0 THEN 'draft' 
                            WHEN p.published = 1 THEN 'published' 
                            ELSE 'unpublished' END as status,
                       p.created_at as published_at,
                       p.author as author_name
                FROM posts p
                WHERE p.published = 1
                ORDER BY p.created_at DESC
                LIMIT ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get posts on specific date
     */
    public function getPostsOnDate(string $date): int {
        $sql = "SELECT COUNT(*) as count FROM posts 
                WHERE published = 1 AND DATE(created_at) = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    // -------------------- TAG-BASED CONTENT VIEW --------------------
    
    /**
     * Get all content by tag slug (posts, pages)
     */
    public function getContentByTagSlug($slug, $limit = 20, $offset = 0)
    {
        $sql = "
            SELECT 'post' AS type, p.id, p.title, p.slug AS url,
                p.content, p.created_at, p.author, 0 AS is_premium, NULL AS status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
            FROM posts p
            INNER JOIN content_tags ct ON ct.content_id = p.id AND ct.content_type = 'post'
            INNER JOIN tags t ON t.id = ct.tag_id
            WHERE t.slug = ? AND p.published = 1

            UNION ALL

            SELECT 'page' AS type, pg.id, pg.title, pg.slug AS url,
                pg.content, pg.created_at, NULL AS author, 0 AS is_premium, NULL AS status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = pg.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = pg.id) AS impressions
            FROM pages pg
            INNER JOIN content_tags ct2 ON ct2.content_id = pg.id AND ct2.content_type = 'page'
            INNER JOIN tags t2 ON t2.id = ct2.tag_id
            WHERE t2.slug = ? AND pg.published = 1

            UNION ALL

            SELECT 'mobile' AS type, m.id,
                CONCAT(m.brand_name, ' ', m.model_name) AS title,
                CONCAT('/mobiles/view/', m.id) AS url,
                (
                    SELECT GROUP_CONCAT(CONCAT(ms.spec_key, ': ', ms.spec_value) SEPARATOR '\n')
                    FROM mobile_specs ms
                    WHERE ms.mobile_id = m.id
                ) AS content,
                m.created_at, NULL AS author, 0 AS is_premium, NULL AS status,
                0 AS views,
                0 AS impressions
            FROM mobiles m
            INNER JOIN content_tags ct3 ON ct3.content_id = m.id AND ct3.content_type = 'mobile'
            INNER JOIN tags t3 ON t3.id = ct3.tag_id
            WHERE t3.slug = ?

            UNION ALL

            SELECT 'service' AS type, s.id, s.name AS title,
                CONCAT('/services/', s.slug) AS url,
                COALESCE(s.description, '') AS content,
                s.created_at, NULL AS author, s.is_premium, s.status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions
            FROM services s
            INNER JOIN content_tags ct4 ON ct4.content_id = s.id AND ct4.content_type = 'service'
            INNER JOIN tags t4 ON t4.id = ct4.tag_id
            WHERE t4.slug = ? AND s.status = 'active' AND s.deleted_at IS NULL

            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssssii", $slug, $slug, $slug, $slug, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $contents = [];
        while ($row = $result->fetch_assoc()) {
            // Extract first image (if any HTML content) -- do NOT resolve numeric/JSON media refs for posts/pages
            $row['image'] = $this->extractFirstImage($row['content'], false);
            // Extract all images (if any)
            $row['images'] = $this->extractAllImages($row['content'], false);
            $contents[] = $row;
        }

        // For services, fetch normalized image URLs using ServiceModel helpers
        $serviceModel = new ServiceModel($this->mysqli);
        foreach ($contents as &$c) {
            if (isset($c['type']) && $c['type'] === 'service') {
                $imageUrls = $serviceModel->getServiceImageUrls((int)$c['id']);
                $featuredUrl = $serviceModel->getFeaturedImageUrl((int)$c['id']);

                $c['images'] = !empty($imageUrls) ? $imageUrls : ($c['images'] ?? []);
                $c['image'] = $featuredUrl ?? ($c['images'][0] ?? ($c['image'] ?? null));
                // Ensure slug is available for front-end links
                if (!isset($c['slug'])) $c['slug'] = $this->slugify($c['title']);
            }
        }

        $stmt->close();
        return $contents;
    }


    /**
     * Count content by tag slug
     */
    public function countContentByTagSlug($slug) {
        $sql = "
            SELECT COUNT(*) as count FROM (
                SELECT p.id FROM posts p
                INNER JOIN content_tags ct ON ct.content_id = p.id AND ct.content_type = 'post'
                INNER JOIN tags t ON t.id = ct.tag_id
                WHERE t.slug = ? AND p.published = 1

                UNION

                SELECT pg.id FROM pages pg
                INNER JOIN content_tags ct2 ON ct2.content_id = pg.id AND ct2.content_type = 'page'
                INNER JOIN tags t2 ON t2.id = ct2.tag_id
                WHERE t2.slug = ? AND pg.published = 1

                UNION

                SELECT m.id FROM mobiles m
                INNER JOIN content_tags ct3 ON ct3.content_id = m.id AND ct3.content_type = 'mobile'
                INNER JOIN tags t3 ON t3.id = ct3.tag_id
                WHERE t3.slug = ?

                UNION

                SELECT s.id FROM services s
                INNER JOIN content_tags ct4 ON ct4.content_id = s.id AND ct4.content_type = 'service'
                INNER JOIN tags t4 ON t4.id = ct4.tag_id
                WHERE t4.slug = ? AND s.status = 'active' AND s.deleted_at IS NULL
            ) as combined
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssss", $slug, $slug, $slug, $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)($row['count'] ?? 0);
    }

    // -------------------- CATEGORY-BASED CONTENT VIEW --------------------
    
    /**
     * Get all content by category slug (posts, pages)
     */
    public function getContentByCategorySlug($slug, $limit = 20, $offset = 0) {
        $sql = "
            SELECT 'post' as type, p.id, p.title, p.slug as url, p.content, p.created_at, p.author, 0 as is_premium, NULL as status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
            FROM posts p
            INNER JOIN content_categories cc ON cc.content_id = p.id AND cc.content_type = 'post'
            INNER JOIN categories c ON c.id = cc.category_id
            WHERE c.slug = ? AND p.published = 1

            UNION

            SELECT 'page' as type, pg.id, pg.title, pg.slug as url, pg.content, pg.created_at, NULL as author, 0 as is_premium, NULL as status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = pg.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = pg.id) AS impressions
            FROM pages pg
            INNER JOIN content_categories cc2 ON cc2.content_id = pg.id AND cc2.content_type = 'page'
            INNER JOIN categories c2 ON c2.id = cc2.category_id
            WHERE c2.slug = ? AND pg.published = 1

            UNION

            SELECT 'mobile' AS type,
                m.id,
                CONCAT(m.brand_name, ' ', m.model_name) AS title,
                CONCAT('/mobiles/view/', m.id) AS url,
                (
                    SELECT GROUP_CONCAT(CONCAT(ms.spec_key, ': ', ms.spec_value) SEPARATOR '\n')
                    FROM mobile_specs ms
                    WHERE ms.mobile_id = m.id
                ) AS content,
                m.created_at,
                NULL AS author,
                0 as is_premium, NULL as status,
                0 AS views,
                0 AS impressions
            FROM mobiles m
            INNER JOIN content_categories cc3 ON cc3.content_id = m.id AND cc3.content_type = 'mobile'
            INNER JOIN categories c3 ON c3.id = cc3.category_id
            WHERE c3.slug = ?

            UNION

            SELECT 'service' as type, s.id, s.name as title, CONCAT('/services/', s.slug) as url, COALESCE(s.description, '') as content, s.created_at, NULL as author, s.is_premium, s.status,
                (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions
            FROM services s
            INNER JOIN content_categories cc4 ON cc4.content_id = s.id AND cc4.content_type = 'service'
            INNER JOIN categories c4 ON c4.id = cc4.category_id
            WHERE c4.slug = ? AND s.status = 'active' AND s.deleted_at IS NULL

            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssssii", $slug, $slug, $slug, $slug, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $contents = [];
        while ($row = $result->fetch_assoc()) {
            $row['image'] = $this->extractFirstImage($row['content'], false);
            $row['images'] = $this->extractAllImages($row['content'], false);
            $contents[] = $row;
        }
        
        // For services, fetch images from the service_images table so listings (category/tag) show thumbnails
        $serviceModel = new ServiceModel($this->mysqli);
        foreach ($contents as &$c) {
            if (isset($c['type']) && $c['type'] === 'service') {
                $imagesRaw = $serviceModel->getServiceImages((int)$c['id']);
                $images = array_values(array_filter(array_map(function($img){ return $img['image_path'] ?? null; }, $imagesRaw ?: [])));
                $featured = $serviceModel->getFeaturedImage((int)$c['id']);
                // Prefer featured image, then first service image, then existing extracted image
                $c['images'] = !empty($images) ? $images : ($c['images'] ?? []);
                $c['image'] = $featured['image_path'] ?? ($images[0] ?? ($c['image'] ?? null));
            }
        }

        $stmt->close();
        return $contents;
    }

    /**
     * Count content by category slug
     */
    public function countContentByCategorySlug($slug) {
        $sql = "
            SELECT COUNT(*) as count FROM (
                SELECT p.id FROM posts p
                INNER JOIN content_categories cc ON cc.content_id = p.id AND cc.content_type = 'post'
                INNER JOIN categories c ON c.id = cc.category_id
                WHERE c.slug = ? AND p.published = 1

                UNION

                SELECT pg.id FROM pages pg
                INNER JOIN content_categories cc2 ON cc2.content_id = pg.id AND cc2.content_type = 'page'
                INNER JOIN categories c2 ON c2.id = cc2.category_id
                WHERE c2.slug = ? AND pg.published = 1

                UNION

                SELECT m.id FROM mobiles m
                INNER JOIN content_categories cc3 ON cc3.content_id = m.id AND cc3.content_type = 'mobile'
                INNER JOIN categories c3 ON c3.id = cc3.category_id
                WHERE c3.slug = ?

                UNION

                SELECT s.id FROM services s
                INNER JOIN content_categories cc4 ON cc4.content_id = s.id AND cc4.content_type = 'service'
                INNER JOIN categories c4 ON c4.id = cc4.category_id
                WHERE c4.slug = ? AND s.status = 'active' AND s.deleted_at IS NULL
            ) as combined
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssss", $slug, $slug, $slug, $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)($row['count'] ?? 0);
    }

    // ==================== SITEMAP METHODS ====================
    
    /**
     * Get all published posts for sitemap (XML or HTML)
     * Returns: id, slug (slug), updated_at, title (for HTML)
     */
    public function getSitemapPosts($limit = 500) {
        try {
            $sql = "SELECT id, slug, updated_at, title 
                    FROM posts 
                    WHERE published = 1 
                    ORDER BY updated_at DESC 
                    LIMIT ?";
            
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            $posts = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return $posts ?: [];
        } catch (Exception $e) {
            logError("ContentModel::getSitemapPosts - " . $e->getMessage());
            return [];
        }
    }


    /**
     * Get all categories for sitemap
     * Returns: id, slug, name
     */
    public function getSitemapCategories() {
        try {
            $sql = "SELECT id, slug, name 
                    FROM categories 
                    ORDER BY name ASC";
            
            $result = $this->mysqli->query($sql);
            $categories = $result->fetch_all(MYSQLI_ASSOC);
            
            return $categories ?: [];
        } catch (Exception $e) {
            logError("ContentModel::getSitemapCategories - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all tags for sitemap
     * Returns: id, slug, name
     */
    public function getSitemapTags() {
        try {
            $sql = "SELECT id, slug, name 
                    FROM tags 
                    ORDER BY name ASC";
            
            $result = $this->mysqli->query($sql);
            $tags = $result->fetch_all(MYSQLI_ASSOC);
            
            return $tags ?: [];
        } catch (Exception $e) {
            logError("ContentModel::getSitemapTags - " . $e->getMessage());
            return [];
        }
    }

    // ====================== PAGINATION & SEARCH ======================

    /**
     * Get paginated, searched, and sorted categories
     */
    public function getCategories($page = 1, $limit = 20, $search = '', $sort = 'name', $order = 'ASC') {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'name';
        
        $sql = "SELECT * FROM categories WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR slug LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
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
     * Get total count of categories with optional search filter
     */
    public function getCategoriesCount($search = '') {
        $sql = "SELECT COUNT(*) as total FROM categories WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR slug LIKE ?)";
            $stmt = $this->mysqli->prepare($sql);
            $searchTerm = '%' . $search . '%';
            $stmt->bind_param('ss', $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get paginated, searched, and sorted tags
     */
    public function getTags($page = 1, $limit = 20, $search = '', $sort = 'name', $order = 'ASC') {
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'name';
        
        $sql = "SELECT * FROM tags WHERE 1=1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR slug LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
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
     * Get total count of tags with optional search filter
     */
    public function getTagsCount($search = '') {
        $sql = "SELECT COUNT(*) as total FROM tags WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (name LIKE ? OR slug LIKE ?)";
            $stmt = $this->mysqli->prepare($sql);
            $searchTerm = '%' . $search . '%';
            $stmt->bind_param('ss', $searchTerm, $searchTerm);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Get paginated, searched, and sorted posts
     */
    public function getPosts($page = 1, $limit = 20, $search = '', $sort = 'created_at', $order = 'DESC', $filters = []) {
        $page  = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['id', 'title', 'created_at', 'updated_at', 'views', 'impressions'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
        
        $sql = "SELECT p.*,
               (SELECT COUNT(*) FROM views v WHERE v.content_type = 'post' AND v.content_id = p.id) AS views,
               (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'post' AND i.content_id = p.id) AS impressions
        FROM posts p WHERE p.published = 1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (p.title LIKE ? OR p.content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['published', 'draft'])) {
            $publishedValue = $filters['status'] === 'published' ? 1 : 0;
            $sql .= " AND p.published = ?";
            $params[] = $publishedValue;
            $types .= 'i';
        }
        
        $orderBy = in_array($sort, ['views', 'impressions'], true) ? $sort : "p.`{$sort}`";
        $sql .= " ORDER BY " . $orderBy . " " . $order . " LIMIT " . $limit . " OFFSET " . $offset;
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['image'] = $this->extractFirstImage($row['content'], false);
            $row['images'] = $this->extractAllImages($row['content'], false);
        }
        return $rows;
    }

    /**
     * Get total count of posts with optional search/filter
     */
    public function getPostsCount($search = '', $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM posts WHERE published = 1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['published', 'draft'])) {
            $publishedValue = $filters['status'] === 'published' ? 1 : 0;
            $sql .= " AND published = ?";
            $params[] = $publishedValue;
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

    /**
     * Get paginated, searched, and sorted pages
     */
    public function getPages($page = 1, $limit = 20, $search = '', $sort = 'created_at', $order = 'DESC', $filters = []) {
        $page  = max(1, (int)$page);
        $limit = max(1, (int)$limit);
        $offset = ($page - 1) * $limit;
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        $allowedSorts = ['id', 'title', 'created_at', 'updated_at', 'views', 'impressions'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
        
        $sql = "SELECT pg.*,
               (SELECT COUNT(*) FROM views v WHERE v.content_type = 'page' AND v.content_id = pg.id) AS views,
               (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'page' AND i.content_id = pg.id) AS impressions
        FROM pages pg WHERE pg.published = 1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (pg.title LIKE ? OR pg.content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['published', 'draft'])) {
            $publishedValue = $filters['status'] === 'published' ? 1 : 0;
            $sql .= " AND pg.published = ?";
            $params[] = $publishedValue;
            $types .= 'i';
        }
        
        $orderBy = in_array($sort, ['views', 'impressions'], true) ? $sort : "pg.`{$sort}`";
        $sql .= " ORDER BY " . $orderBy . " " . $order . " LIMIT " . $limit . " OFFSET " . $offset;
        
        if (!empty($params)) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->mysqli->query($sql);
        }
        
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['image'] = $this->extractFirstImage($row['content'], false);
            $row['images'] = $this->extractAllImages($row['content'], false);
        }
        return $rows;
    }

    /**
     * Get total count of pages with optional search/filter
     */
    public function getPagesCount($search = '', $filters = []) {
        $sql = "SELECT COUNT(*) as total FROM pages WHERE published = 1";
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params = [$searchTerm, $searchTerm];
            $types = 'ss';
        }
        
        if (!empty($filters['status']) && in_array($filters['status'], ['published', 'draft'])) {
            $publishedValue = $filters['status'] === 'published' ? 1 : 0;
            $sql .= " AND published = ?";
            $params[] = $publishedValue;
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

    // ==================== PUBLIC ARCHIVE ROUTES ====================

    /**
     * Get content by tag ID
     */
    public function getContentByTag($tagId, $page = 1, $limit = 12, $search = '', $sort = 'latest') {
        // Delegate to slug-based mixed-content method
        $tag = $this->getTagById($tagId);
        if (!$tag) return [];
        $offset = ($page - 1) * $limit;
        return $this->getContentByTagSlug($tag['slug'], $limit, $offset);
    }

    /**
     * Count content by tag
     */
    public function getContentByTagCount($tagId, $search = '') {
        $tag = $this->getTagById($tagId);
        if (!$tag) return 0;
        return $this->countContentByTagSlug($tag['slug']);
    }

    /**
     * Get content by category ID
     */
    public function getContentByCategory($categoryId, $page = 1, $limit = 12, $search = '', $sort = 'latest') {
        // Delegate to slug-based mixed-content method
        $category = $this->getCategoryById($categoryId);
        if (!$category) return [];
        $offset = ($page - 1) * $limit;
        return $this->getContentByCategorySlug($category['slug'], $limit, $offset);
    }

    /**
     * Count content by category
     */
    public function getContentByCategoryCount($categoryId, $search = '') {
        $category = $this->getCategoryById($categoryId);
        if (!$category) return 0;
        return $this->countContentByCategorySlug($category['slug']);
    }

    // -------------------- Pages --------------------
    public function getRelatedPages(int $pageId, int $limit = 5): array
    {
        $sql = "
            SELECT 
                p.*,
                COALESCE(v.views, 0) AS views,
                COALESCE(i.impressions, 0) AS impressions
            FROM pages p
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS views
                FROM views
                WHERE content_type = 'page'
                GROUP BY content_id
            ) v ON v.content_id = p.id
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS impressions
                FROM impressions
                WHERE content_type = 'page'
                GROUP BY content_id
            ) i ON i.content_id = p.id
            WHERE p.published = 1
              AND p.id != ?
              AND p.id >= (
                  SELECT FLOOR(RAND() * (SELECT MAX(id) FROM pages))
              )
            ORDER BY p.id
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $pageId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Fallback if fewer than requested
        if (count($rows) < $limit) {
            $rows = array_merge(
                $rows,
                $this->getRelatedPagesFallback($pageId, $limit - count($rows))
            );
        }

        foreach ($rows as &$row) {
            $this->prepareRelatedItem($row, 'page');
        }
        unset($row);

        return $rows;
    }

    private function getRelatedPagesFallback(int $pageId, int $limit): array
    {
        $sql = "
            SELECT p.*, 0 AS views, 0 AS impressions
            FROM pages p
            WHERE p.published = 1
              AND p.id != ?
            ORDER BY p.id ASC
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $pageId, $limit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // -------------------- Posts --------------------
    public function getRelatedPosts(int $postId, int $limit = 5): array
    {
        $sql = "
            SELECT 
                p.*,
                COALESCE(v.views, 0) AS views,
                COALESCE(i.impressions, 0) AS impressions
            FROM posts p
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS views
                FROM views
                WHERE content_type = 'post'
                GROUP BY content_id
            ) v ON v.content_id = p.id
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS impressions
                FROM impressions
                WHERE content_type = 'post'
                GROUP BY content_id
            ) i ON i.content_id = p.id
            WHERE p.published = 1
              AND p.id != ?
              AND p.id >= (
                  SELECT FLOOR(RAND() * (SELECT MAX(id) FROM posts))
              )
            ORDER BY p.id
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $postId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($rows) < $limit) {
            $rows = array_merge(
                $rows,
                $this->getRelatedPostsFallback($postId, $limit - count($rows))
            );
        }

        foreach ($rows as &$row) {
            $this->prepareRelatedItem($row, 'post');
        }
        unset($row);

        return $rows;
    }

    private function getRelatedPostsFallback(int $postId, int $limit): array
    {
        $sql = "
            SELECT p.*, 0 AS views, 0 AS impressions
            FROM posts p
            WHERE p.published = 1
              AND p.id != ?
            ORDER BY p.id ASC
            LIMIT ?
        ";

        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ii", $postId, $limit);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // -------------------- Prepare Related Item --------------------
    private function prepareRelatedItem(array &$row, string $type): void
    {
        $row['views'] = (int)($row['views'] ?? 0);
        $row['impressions'] = (int)($row['impressions'] ?? 0);

        $content = $row['content'] ?? '';

        // Extract all images (up to 5)
        $images = $this->extractAllImages($content, true);

        // Fallback: extract first image if all_images empty
        if (empty($images)) {
            $first = $this->extractFirstImage($content, true);
            if ($first) $images[] = $first;
        }

        $row['images'] = array_slice($images, 0, 5);
        $row['image']  = $row['images'][0] ?? null;

        // Ensure slug is set
        $row['slug'] = $row['slug']
            ?? $row['url']
            ?? ($type . '-' . ($row['id'] ?? 'n-a'));

        $row['type'] = $type;
    }


}
