<?php
/**
 * classes/ServiceModel.php
 * 
 * Handles all service-related database operations
 * Services are the offerings that users can apply for
 */

class ServiceModel {

    private $mysqli;
    private ?array $serviceColumns = null;

    public function __construct(mysqli $mysqli) {
        $this->mysqli = $mysqli;
    }

    /**
     * Return all columns from services table (cached per request).
     */
    private function getServiceColumns(): array {
        if ($this->serviceColumns !== null) {
            return $this->serviceColumns;
        }

        $this->serviceColumns = [];
        $result = $this->mysqli->query("SHOW COLUMNS FROM services");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $this->serviceColumns[] = $field;
                }
            }
        }

        return $this->serviceColumns;
    }

    /**
     * Check if a column exists in services table.
     */
    private function hasServiceColumn(string $column): bool {
        return in_array($column, $this->getServiceColumns(), true);
    }

    /**
     * Backward-compatible field hydration from metadata when schema column is absent.
     */
    private function applyLegacyDerivedFields(array $service): array {
        $metadata = $service['metadata'] ?? null;
        if (!is_array($metadata)) {
            $metadata = is_string($metadata) && $metadata !== '' ? (json_decode($metadata, true) ?: []) : [];
        }

        if (
            (!array_key_exists('price', $service) || $service['price'] === null || $service['price'] === '') &&
            isset($metadata['_service_price']) &&
            is_numeric($metadata['_service_price'])
        ) {
            $service['price'] = (float)$metadata['_service_price'];
        }

        if (
            (!array_key_exists('redirect_url', $service) || $service['redirect_url'] === null || $service['redirect_url'] === '') &&
            !empty($metadata['_redirect_url'])
        ) {
            $service['redirect_url'] = (string)$metadata['_redirect_url'];
        }

        unset($metadata['_service_price'], $metadata['_redirect_url']);
        $service['metadata'] = $metadata;

        return $service;
    }

    // ============================================================================
    // FINDERS
    // ============================================================================

    /**
     * Get all active services
     * @return array
     */
    public function getAllActive(): array {
        $stmt = $this->mysqli->prepare("
            SELECT s.*,
                   COALESCE(v.views, 0) AS views,
                   COALESCE(i.impressions, 0) AS impressions
            FROM services s
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS views
                FROM views
                WHERE content_type = 'service'
                GROUP BY content_id
            ) v ON v.content_id = s.id
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS impressions
                FROM impressions
                WHERE content_type = 'service'
                GROUP BY content_id
            ) i ON i.content_id = s.id
            WHERE s.status IN ('active', 'archived')
            AND s.deleted_at IS NULL
            ORDER BY s.created_at DESC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }


    /**
     * Get all services (admin view) with pagination
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllServices(int $limit = 20, int $offset = 0): array {
        $stmt = $this->mysqli->prepare("
            SELECT s.*,
                   COALESCE(v.views, 0) AS views,
                   COALESCE(i.impressions, 0) AS impressions
            FROM services s
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS views
                FROM views
                WHERE content_type = 'service'
                GROUP BY content_id
            ) v ON v.content_id = s.id
            LEFT JOIN (
                SELECT content_id, COUNT(*) AS impressions
                FROM impressions
                WHERE content_type = 'service'
                GROUP BY content_id
            ) i ON i.content_id = s.id
            WHERE s.deleted_at IS NULL
            ORDER BY s.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    /**
     * Get single service by ID
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                   (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions
            FROM services s
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Get service by slug
     * @param string $slug
     * @return array|null
     */
    public function findBySlug(string $slug): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                   (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions
            FROM services s
            WHERE slug = ? AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Check if a slug is available
     * @param string $slug
     * @param int $excludeId Optional: ID to exclude when checking (for editing)
     * @return bool True if slug is available, false if already in use
     */
    public function isSlugAvailable(string $slug, int $excludeId = 0): bool {
        $query = "SELECT id FROM services WHERE slug = ? AND deleted_at IS NULL";
        $params = [$slug];
        $types = 's';

        if ($excludeId > 0) {
            $query .= " AND id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return !$exists;
    }



    // ============================================================================
    // CREATE/UPDATE
    // ============================================================================

    /**
     * Create a new service
     * @param array $data Service data
     * @return int|null Service ID or null on failure
     */
    public function create(array $data): ?int {
        $name = $data['name'] ?? '';
        $slug = $data['slug'] ?? slugify($name);
        $description = $data['description'] ?? '';
        $icon = $data['icon'] ?? null;
        $status = $data['status'] ?? 'active';
        $is_premium = (int)($data['is_premium'] ?? 0);
        $price = isset($data['price']) && is_numeric($data['price']) ? round((float)$data['price'], 2) : 0.0;
        $redirectUrl = trim((string)($data['redirect_url'] ?? ''));
        $requires_approval = (int)($data['requires_approval'] ?? 1);
        $auto_approve = (int)($data['auto_approve'] ?? 0);
        $requires_documents = (int)($data['requires_documents'] ?? 0);
        $metadataArr = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [];
        if (!$this->hasServiceColumn('price') && $price > 0) {
            $metadataArr['_service_price'] = $price;
        }
        if (!$this->hasServiceColumn('redirect_url') && $redirectUrl !== '') {
            $metadataArr['_redirect_url'] = $redirectUrl;
        }
        $metadata = !empty($metadataArr) ? json_encode($metadataArr) : null;
        $form_fields = isset($data['form_fields']) && !empty($data['form_fields']) ? json_encode($data['form_fields']) : null;

        $columns = [
            'name', 'slug', 'description', 'icon', 'status', 'is_premium',
            'requires_approval', 'auto_approve', 'requires_documents',
            'metadata', 'form_fields'
        ];
        $params = [
            $name, $slug, $description, $icon, $status, $is_premium,
            $requires_approval, $auto_approve, $requires_documents,
            $metadata, $form_fields
        ];
        $types = 'sssssiiiiss';

        if ($this->hasServiceColumn('price')) {
            $columns[] = 'price';
            $params[] = $price;
            $types .= 'd';
        }
        if ($this->hasServiceColumn('redirect_url')) {
            $columns[] = 'redirect_url';
            $params[] = $redirectUrl !== '' ? $redirectUrl : null;
            $types .= 's';
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->mysqli->prepare("
            INSERT INTO services (" . implode(', ', $columns) . ")
            VALUES (" . $placeholders . ")
        ");

        if (!$stmt) {
            logError('Prepare error: ' . $this->mysqli->error);
            return null;
        }

        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            return (int) $this->mysqli->insert_id;
        }
        
        logError('Execute error: ' . $stmt->error);
        return null;
    }

    /**
     * Update service
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool {
        $updates = [];
        $params = [];
        $types = '';

        if (!$this->hasServiceColumn('price') || !$this->hasServiceColumn('redirect_url')) {
            $metadataArr = [];
            if (array_key_exists('metadata', $data)) {
                $metadataRaw = $data['metadata'];
                if (is_array($metadataRaw)) {
                    $metadataArr = $metadataRaw;
                } elseif (is_string($metadataRaw) && $metadataRaw !== '') {
                    $metadataArr = json_decode($metadataRaw, true) ?: [];
                }
            } else {
                $existing = $this->findById($id);
                $metadataArr = !empty($existing['metadata']) ? (json_decode((string)$existing['metadata'], true) ?: []) : [];
            }

            if (array_key_exists('price', $data) && !$this->hasServiceColumn('price')) {
                $priceVal = isset($data['price']) && is_numeric($data['price']) ? round((float)$data['price'], 2) : 0.0;
                if ($priceVal > 0) {
                    $metadataArr['_service_price'] = $priceVal;
                } else {
                    unset($metadataArr['_service_price']);
                }
                $data['metadata'] = $metadataArr;
            }

            if (array_key_exists('redirect_url', $data) && !$this->hasServiceColumn('redirect_url')) {
                $redirectUrl = trim((string)($data['redirect_url'] ?? ''));
                if ($redirectUrl !== '') {
                    $metadataArr['_redirect_url'] = $redirectUrl;
                } else {
                    unset($metadataArr['_redirect_url']);
                }
                $data['metadata'] = $metadataArr;
            }
        }

        $allowedFields = [
            'name', 'description', 'slug', 'icon', 'status', 'is_premium',
            'requires_approval', 'auto_approve', 'requires_documents',
            'metadata', 'form_fields',
            'price', 'redirect_url'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                if (($field === 'price' || $field === 'redirect_url') && !$this->hasServiceColumn($field)) {
                    continue;
                }

                $updates[] = "$field = ?";
                $value = $data[$field];
                
                if (in_array($field, ['metadata', 'form_fields']) && is_array($value)) {
                    $value = json_encode($value);
                }

                if ($field === 'price') {
                    $value = is_numeric($value) ? round((float)$value, 2) : 0.0;
                }
                if ($field === 'redirect_url') {
                    $value = trim((string)$value);
                    if ($value === '') {
                        $value = null;
                    }
                }
                
                $params[] = $value;
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $types .= 'i';

        $sql = "UPDATE services SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);

        return $stmt->execute();
    }

    // ============================================================================
    // SERVICE TEMPLATE FIELDS (Custom Form Fields)
    // ============================================================================

    /**
     * Get form fields for a service
     * @param int $serviceId
     * @return array
     */
    public function getFormFields(int $serviceId): array {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_form_templates 
            WHERE service_id = ? 
            ORDER BY field_order ASC
        ");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    /**
     * Delete all form fields for a service
     * @param int $serviceId
     * @return bool
     */
    public function deleteFormFields(int $serviceId): bool {
        $stmt = $this->mysqli->prepare("
            DELETE FROM service_form_templates 
            WHERE service_id = ?
        ");
        $stmt->bind_param('i', $serviceId);
        return $stmt->execute();
    }

    /**
     * Add form field to service
     * @param int $serviceId
     * @param array $field
     * @return int|null Field ID
     */
    public function addFormField(int $serviceId, array $field): ?int {
        $stmt = $this->mysqli->prepare("
            INSERT INTO service_form_templates (
                service_id, form_field_name, field_type, label, required,
                placeholder, validation_rules, field_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $field_name = $field['form_field_name'] ?? '';
        $field_type = $field['field_type'] ?? 'text';
        $label = $field['label'] ?? '';
        $required = $field['required'] ?? 1;
        $placeholder = $field['placeholder'] ?? '';
        $validation = isset($field['validation_rules']) ? json_encode($field['validation_rules']) : null;
        $order = $field['field_order'] ?? 0;

        $stmt->bind_param(
            'isssissi',
            $serviceId, $field_name, $field_type, $label, $required,
            $placeholder, $validation, $order
        );

        if ($stmt->execute()) {
            return (int) $this->mysqli->insert_id;
        }
        return null;
    }

    /**
     * Delete service (soft delete)
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $stmt = $this->mysqli->prepare("
            UPDATE services SET deleted_at = NOW() WHERE id = ?
        ");
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }

    /**
     * Check if service requires approval
     * @param int $serviceId
     * @return bool
     */
    public function requiresApproval(int $serviceId): bool {
        $service = $this->findById($serviceId);
        return $service ? (bool) $service['requires_approval'] : false;
    }

    /**
     * Check if service auto-approves applications
     * @param int $serviceId
     * @return bool
     */
    public function shouldAutoApprove(int $serviceId): bool {
        $service = $this->findById($serviceId);
        return $service ? (bool) $service['auto_approve'] : false;
    }

    // ============================================================================
    // SERVICE IMAGES (Multiple images per service)
    // ============================================================================

    /**
     * Get all images for a service
     * @param int $serviceId
     * @return array
     */
    public function getServiceImages(int $serviceId): array {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_images 
            WHERE service_id = ? AND deleted_at IS NULL
            ORDER BY display_order ASC, created_at ASC
        ");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        return array_map([$this, 'normalizeServiceImageRow'], $rows);
    }

    /**
     * Get featured image for a service (raw DB row)
     * @param int $serviceId
     * @return array|null
     */
    public function getFeaturedImage(int $serviceId): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_images 
            WHERE service_id = ? AND is_featured = 1 AND deleted_at IS NULL 
            LIMIT 1
        ");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        return $row ? $this->normalizeServiceImageRow($row) : null;
    }

    /**
     * Get image URLs for a service. Returns an array of string URLs.
     * Falls back to extracting images from `services.description` when no DB images exist.
     * @param int $serviceId
     * @param int $limit
     * @return array
     */
    public function getServiceImageUrls(int $serviceId, int $limit = 3): array {
        $rows = $this->getServiceImages($serviceId);
        $urls = [];

        if (!empty($rows)) {
            foreach ($rows as $r) {
                // prefer image_path first, then thumbnail_path
                $src = $r['image_path'] ?? $r['thumbnail_path'] ?? null;
                if ($src) {
                    // Resolve if numeric or structured
                    $resolved = $this->resolveMaybeMediaReference($src);
                    if (!empty($resolved)) $urls[] = $resolved;
                }

                if (count($urls) >= $limit) break;
            }
            // Only return DB-derived URLs if we actually found usable values; otherwise fall back to description extraction
            if (!empty($urls)) {
                return array_values(array_unique($urls));
            }
        }

        // Fallback: extract from description HTML
        $service = $this->findById($serviceId);
        if (!$service) return [];

        $desc = $service['description'] ?? '';
        $extracted = $this->extractMultipleImages($desc, $limit);
        return $extracted;
    }

    /**
     * Get featured image URL as string (uses featured DB image if present, otherwise first available URL)
     * @param int $serviceId
     * @return string|null
     */
    public function getFeaturedImageUrl(int $serviceId): ?string {
        $featured = $this->getFeaturedImage($serviceId);
        if (!empty($featured)) {
            // prefer image_path first, then thumbnail_path
            $src = $featured['image_path'] ?? $featured['thumbnail_path'] ?? null;
            if (!empty($src)) return $this->resolveMaybeMediaReference($src);
        }

        // Fallback to first DB/extracted URL
        $urls = $this->getServiceImageUrls($serviceId, 1);
        return $urls[0] ?? null;
    }

    /**
     * Extract multiple image URLs from HTML content (description)
     * @param string $html
     * @param int $limit
     * @return array
     */
    private function extractMultipleImages($html, $limit = 3) {
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
                    if (!empty($val)) {
                        // Resolve numeric ids or structured values to usable URLs
                        $resolved = $this->resolveMaybeMediaReference($val);

                        $images[] = $resolved;
                        break;
                    }
                }
            }
            if (count($images) >= $limit) break;
        }

        // Normalize uniqueness while preserving order
        $seen = [];
        $out = [];
        foreach ($images as $i) {
            // sanitize and skip invalid tiny values
            $i = trim((string)$i);
            if ($i === '') continue;
            if (!isset($seen[$i])) {
                $seen[$i] = true;
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * Public wrapper to extract images from arbitrary HTML.
     * Useful for callers outside the model to reuse the extraction logic.
     * @param string $html
     * @param int $limit
     * @return array
     */
    public function extractImagesFromHtml($html, $limit = 3): array {
        return $this->extractMultipleImages($html, $limit);
    }

    /**
     * Resolve a value that may be a direct URL, a numeric media ID, or JSON-encoded structure.
     * Returns a usable string URL/path or the original value when no resolution found.
     * @param mixed $value
     * @return string
     */
    private function resolveMaybeMediaReference($value): string {
        if (is_array($value)) {
            // Common keys to try
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

    /**
     * Normalize stored image paths so stale thumbnail references do not break callers.
     * If a thumbnail file no longer exists but the original image does, fall back to the original.
     * @param array $row
     * @return array
     */
    private function normalizeServiceImageRow(array $row): array {
        $imagePath = $this->resolveMaybeMediaReference($row['image_path'] ?? '');
        $thumbnailPath = $this->resolveMaybeMediaReference($row['thumbnail_path'] ?? '');

        if ($thumbnailPath !== '' && !$this->serviceImagePathExists($thumbnailPath)) {
            $thumbnailPath = $imagePath;
        }

        if ($imagePath !== '' && !$this->serviceImagePathExists($imagePath) && $thumbnailPath !== '') {
            $imagePath = $thumbnailPath;
        }

        $row['image_path'] = $imagePath !== '' ? $imagePath : null;
        $row['thumbnail_path'] = $thumbnailPath !== '' ? $thumbnailPath : null;
        return $row;
    }

    /**
     * Check whether a local upload-backed image path exists on disk.
     * External URLs are treated as valid because they cannot be verified locally.
     * @param string $path
     * @return bool
     */
    private function serviceImagePathExists(string $path): bool {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        if (preg_match('#^(?:https?:)?//#i', $path) || strpos($path, 'data:') === 0) {
            return true;
        }

        if (function_exists('brox_upload_web_path_to_fs_path')) {
            $fsPath = brox_upload_web_path_to_fs_path($path);
            if ($fsPath !== null) {
                return file_exists($fsPath);
            }
        }

        return true;
    }

    /**
     * Add image to service
     * @param int $serviceId
     * @param string $imagePath
     * @param string|null $thumbnailPath
     * @param string $altText
     * @param string $caption
     * @param bool $isFeatured
     * @param int $displayOrder
     * @return int|null Image ID
     */
    public function addImage(int $serviceId, string $imagePath, ?string $thumbnailPath = null, string $altText = '', string $caption = '', bool $isFeatured = false, int $displayOrder = 0): ?int {
        $stmt = $this->mysqli->prepare("
            INSERT INTO service_images (service_id, image_path, thumbnail_path, alt_text, caption, is_featured, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $featured = $isFeatured ? 1 : 0;
        $stmt->bind_param('issssii', $serviceId, $imagePath, $thumbnailPath, $altText, $caption, $featured, $displayOrder);
        
        if ($stmt->execute()) {
            return (int) $this->mysqli->insert_id;
        }
        return null;
    }

    /**
     * Update image
     * @param int $imageId
     * @param string|null $altText
     * @param string|null $caption
     * @param bool|null $isFeatured
     * @param int|null $displayOrder
     * @return bool
     */
    public function updateImage(int $imageId, ?string $altText = null, ?string $caption = null, ?bool $isFeatured = null, ?int $displayOrder = null): bool {
        $updates = [];
        $params = [];
        $types = '';

        if ($altText !== null) {
            $updates[] = "alt_text = ?";
            $types .= 's';
            $params[] = $altText;
        }

        if ($caption !== null) {
            $updates[] = "caption = ?";
            $types .= 's';
            $params[] = $caption;
        }

        if ($isFeatured !== null) {
            $updates[] = "is_featured = ?";
            $types .= 'i';
            $params[] = $isFeatured ? 1 : 0;
        }

        if ($displayOrder !== null) {
            $updates[] = "display_order = ?";
            $types .= 'i';
            $params[] = $displayOrder;
        }

        if (empty($updates)) {
            return true;
        }

        $updates[] = "updated_at = NOW()";
        $query = "UPDATE service_images SET " . implode(', ', $updates) . " WHERE id = ?";
        $types .= 'i';
        $params[] = $imageId;

        $stmt = $this->mysqli->prepare($query);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Delete image (soft delete)
     * @param int $imageId
     * @return bool
     */
    public function deleteImage(int $imageId): bool {
        $stmt = $this->mysqli->prepare("
            UPDATE service_images SET deleted_at = NOW() WHERE id = ?
        ");
        $stmt->bind_param('i', $imageId);
        return $stmt->execute();
    }

    /**
     * Set featured image
     * @param int $imageId
     * @return bool
     */
    public function setFeaturedImage(int $imageId): bool {
        // Get service_id first
        $stmt = $this->mysqli->prepare("SELECT service_id FROM service_images WHERE id = ? AND deleted_at IS NULL");
        $stmt->bind_param('i', $imageId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) {
            return false;
        }

        $serviceId = $result['service_id'];

        // Unfeature all images for this service
        $stmt = $this->mysqli->prepare("UPDATE service_images SET is_featured = 0 WHERE service_id = ?");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();

        // Feature this image
        return $this->updateImage($imageId, null, null, true, null);
    }

    /**
     * Get image by ID
     * @param int $imageId
     * @return array|null
     */
    public function getImageById(int $imageId): ?array {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM service_images WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $imageId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        return $row ? $this->normalizeServiceImageRow($row) : null;
    }

    /**
     * Hard delete image (permanent)
     * @param int $imageId
     * @return bool
     */
    public function hardDeleteImage(int $imageId): bool {
        $stmt = $this->mysqli->prepare("
            DELETE FROM service_images WHERE id = ?
        ");
        $stmt->bind_param('i', $imageId);
        return $stmt->execute();
    }

    /**
     * Reorder images for a service
     * @param int $serviceId
     * @param array $orderMap [imageId => displayOrder]
     * @return bool
     */
    public function reorderImages(int $serviceId, array $orderMap): bool {
        foreach ($orderMap as $imageId => $order) {
            $this->updateImage($imageId, null, null, null, $order);
        }
        return true;
    }

    /**
     * Get image count for service
     * @param int $serviceId
     * @return int
     */
    public function getImageCount(int $serviceId): int {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as count FROM service_images 
            WHERE service_id = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get service with enriched data (images, form fields, metadata parsed)
     * @param int $id
     * @return array|null
     */
    public function getEnriched(int $id): ?array {
        $service = $this->findById($id);
        
        if (!$service) {
            return null;
        }

        // Parse JSON fields
        $service['metadata'] = $service['metadata'] ? json_decode($service['metadata'], true) : [];
        $service['form_fields'] = $service['form_fields'] ? json_decode($service['form_fields'], true) : [];
        $service = $this->applyLegacyDerivedFields($service);

        // Add images (DB rows) and extra URL helpers
        $service['images'] = $this->getServiceImages($id);
        $service['image_urls'] = $this->getServiceImageUrls($id);
        $service['featured_image'] = $this->getFeaturedImage($id);
        $service['featured_image_url'] = $this->getFeaturedImageUrl($id);

        // Add form templates
        $service['form_templates'] = $this->getFormFields($id);

        return $service;
    }

    /**
     * Get all active services with enriched data
     * @return array
     */
    public function getAllActiveEnriched(): array {
        $services = $this->getAllActive();
        
        return array_map(function($service) {
            $service['metadata'] = $service['metadata'] ? json_decode($service['metadata'], true) : [];
            $service['form_fields'] = $service['form_fields'] ? json_decode($service['form_fields'], true) : [];
            $service = $this->applyLegacyDerivedFields($service);
            // keep DB rows for compatibility and also provide URL-friendly fields
            $service['images'] = $this->getServiceImages($service['id']);
            $service['image_urls'] = $this->getServiceImageUrls($service['id']);
            $service['featured_image'] = $this->getFeaturedImage($service['id']);
            $service['featured_image_url'] = $this->getFeaturedImageUrl($service['id']);
            return $service;
        }, $services);
    }

    /**
     * Count services
     * @return int
     */
    public function countAll(): int {
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as count FROM services WHERE deleted_at IS NULL
        ");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Search services
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function search(string $query, int $limit = 20): array {
        $searchTerm = "%{$query}%";
        $stmt = $this->mysqli->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM views v WHERE v.content_type = 'service' AND v.content_id = s.id) AS views,
                   (SELECT COUNT(*) FROM impressions i WHERE i.content_type = 'service' AND i.content_id = s.id) AS impressions
            FROM services s
            WHERE (name LIKE ? OR description LIKE ?) 
            AND status = 'active' AND deleted_at IS NULL
            ORDER BY name ASC 
            LIMIT ?
        ");
        $stmt->bind_param('ssi', $searchTerm, $searchTerm, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    /**
     * Track a service impression (every detail hit).
     */
    public function addServiceImpression(int $serviceId, string $ip): bool {
        $stmt = $this->mysqli->prepare("
            INSERT INTO impressions (content_type, content_id, viewer_ip, impression_at)
            VALUES ('service', ?, ?, NOW())
        ");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $serviceId, $ip);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }

    /**
     * Track a service view only if same IP has no view in the last 24h.
     */
    public function addServiceViewIfUnique24h(int $serviceId, string $ip): bool {
        $exists = $this->mysqli->prepare("
            SELECT id
            FROM views
            WHERE content_type = 'service'
              AND content_id = ?
              AND viewer_ip = ?
              AND viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            LIMIT 1
        ");
        if (!$exists) {
            return false;
        }
        $exists->bind_param('is', $serviceId, $ip);
        $exists->execute();
        $exists->store_result();
        $alreadyViewed = $exists->num_rows > 0;
        $exists->close();

        if ($alreadyViewed) {
            return false;
        }

        $insert = $this->mysqli->prepare("
            INSERT INTO views (content_type, content_id, viewer_ip, viewed_at)
            VALUES ('service', ?, ?, NOW())
        ");
        if (!$insert) {
            return false;
        }
        $insert->bind_param('is', $serviceId, $ip);
        $ok = $insert->execute();
        $insert->close();
        return (bool)$ok;
    }
}
