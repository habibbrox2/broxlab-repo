<?php

declare(strict_types=1);

/**
 * MobilePhoneModel.php
 * Model for managing MobileDokan mobile phone data
 * Handles CRUD operations with prepared statements
 */

class MobilePhoneModel
{
    private mysqli $mysqli;
    private string $table = 'mobile_phones';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a phone to the database
     *
     * @param array $phoneData Phone data with keys: slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery
     * @return array{success: bool, id: int|null, error: string|null}
     */
    public function savePhone(array $phoneData): array
    {
        // Validate required fields
        $required = ['slug', 'name', 'brand', 'url'];
        foreach ($required as $field) {
            if (empty($phoneData[$field])) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Missing required field: {$field}"
                ];
            }
        }

        // Check for duplicate by slug
        if ($this->existsBySlug($phoneData['slug'])) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Phone already exists'
            ];
        }

        $stmt = $this->mysqli->prepare(
            "INSERT INTO {$this->table} 
            (slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $slug = $phoneData['slug'];
        $name = $phoneData['name'];
        $brand = $phoneData['brand'];
        $price = $phoneData['price'] ?? null;
        $priceValue = $phoneData['price_value'] ?? null;
        $url = $phoneData['url'];
        $imageUrl = $phoneData['image_url'] ?? null;
        $specs = $phoneData['specs'] ? json_encode($phoneData['specs']) : null;
        $processor = $phoneData['processor'] ?? null;
        $ram = $phoneData['ram'] ?? null;
        $storage = $phoneData['storage'] ?? null;
        $display = $phoneData['display'] ?? null;
        $battery = $phoneData['battery'] ?? null;

        $stmt->bind_param('sssissssssss', $slug, $name, $brand, $price, $priceValue, $url, $imageUrl, $specs, $processor, $ram, $storage, $display, $battery);
        $success = $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'id' => $insertId,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'id' => null,
                'error' => 'Failed to save phone: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Update an existing phone
     *
     * @param int $id Phone ID
     * @param array $phoneData Updated phone data
     * @return array{success: bool, error: string|null}
     */
    public function updatePhone(int $id, array $phoneData): array
    {
        $fields = [];
        $types = '';
        $values = [];

        $fieldMap = [
            'slug' => 's',
            'name' => 's',
            'brand' => 's',
            'price' => 's',
            'price_value' => 'i',
            'url' => 's',
            'image_url' => 's',
            'specs' => 's',
            'processor' => 's',
            'ram' => 's',
            'storage' => 's',
            'display' => 's',
            'battery' => 's',
        ];

        foreach ($phoneData as $key => $value) {
            if (isset($fieldMap[$key])) {
                $fields[] = "{$key} = ?";
                $types .= $fieldMap[$key];
                
                if ($key === 'specs' && is_array($value)) {
                    $values[] = json_encode($value);
                } else {
                    $values[] = $value;
                }
            }
        }

        if (empty($fields)) {
            return [
                'success' => false,
                'error' => 'No fields to update'
            ];
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $id;
        $types .= 'i';

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to update phone: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get phone by database ID
     *
     * @param int $id Phone ID
     * @return array|null Phone data or null if not found
     */
    public function getPhoneById(int $id): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE id = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $phone = $result->fetch_assoc();
        $stmt->close();

        if ($phone && isset($phone['specs'])) {
            $phone['specs'] = json_decode($phone['specs'], true);
        }

        return $phone ?: null;
    }

    /**
     * Get phone by slug
     *
     * @param string $slug Phone slug
     * @return array|null Phone data or null if not found
     */
    public function getPhoneBySlug(string $slug): ?array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE slug = ? 
            LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $phone = $result->fetch_assoc();
        $stmt->close();

        if ($phone && isset($phone['specs'])) {
            $phone['specs'] = json_decode($phone['specs'], true);
        }

        return $phone ?: null;
    }

    /**
     * Check if phone exists by slug
     *
     * @param string $slug Phone slug
     * @return bool True if exists, false otherwise
     */
    public function existsBySlug(string $slug): bool
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id FROM {$this->table} WHERE slug = ? LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    /**
     * Get recent phones with pagination
     *
     * @param int $limit Number of phones to return
     * @param int $offset Offset for pagination
     * @return array List of phones
     */
    public function getRecentPhones(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $phones = [];

        while ($row = $result->fetch_assoc()) {
            if (isset($row['specs'])) {
                $row['specs'] = json_decode($row['specs'], true);
            }
            $phones[] = $row;
        }

        $stmt->close();
        return $phones;
    }

    /**
     * Search phones by name or brand
     *
     * @param string $query Search query
     * @param int $limit Number of phones to return
     * @param int $offset Offset for pagination
     * @return array List of matching phones
     */
    public function searchPhones(string $query, int $limit = 20, int $offset = 0): array
    {
        $searchTerm = '%' . $query . '%';

        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE name LIKE ? OR brand LIKE ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ssii', $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $phones = [];

        while ($row = $result->fetch_assoc()) {
            if (isset($row['specs'])) {
                $row['specs'] = json_decode($row['specs'], true);
            }
            $phones[] = $row;
        }

        $stmt->close();
        return $phones;
    }

    /**
     * Get phones by brand
     *
     * @param string $brand Brand name
     * @param int $limit Number of phones to return
     * @param int $offset Offset for pagination
     * @return array List of phones
     */
    public function getPhonesByBrand(string $brand, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE brand = ? 
            ORDER BY scraped_at DESC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('sii', $brand, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $phones = [];

        while ($row = $result->fetch_assoc()) {
            if (isset($row['specs'])) {
                $row['specs'] = json_decode($row['specs'], true);
            }
            $phones[] = $row;
        }

        $stmt->close();
        return $phones;
    }

    /**
     * Get total count of phones
     *
     * @return int Total number of phones
     */
    public function getTotalCount(): int
    {
        $result = $this->mysqli->query("SELECT COUNT(*) as count FROM {$this->table}");
        
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    }

    /**
     * Get count of phones by brand
     *
     * @param string $brand Brand name
     * @return int Number of phones
     */
    public function getCountByBrand(string $brand): int
    {
        $stmt = $this->mysqli->prepare(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE brand = ?"
        );

        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('s', $brand);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['count'] ?? 0);
    }

    /**
     * Delete a phone by ID
     *
     * @param int $id Phone ID
     * @return array{success: bool, error: string|null}
     */
    public function deletePhone(int $id): array
    {
        $stmt = $this->mysqli->prepare("DELETE FROM {$this->table} WHERE id = ?");

        if (!$stmt) {
            return [
                'success' => false,
                'error' => 'Database error: ' . $this->mysqli->error
            ];
        }

        $stmt->bind_param('i', $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            return [
                'success' => true,
                'error' => null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to delete phone: ' . $this->mysqli->error
            ];
        }
    }

    /**
     * Get all unique brands
     *
     * @return array List of brand names
     */
    public function getBrands(): array
    {
        $result = $this->mysqli->query(
            "SELECT DISTINCT brand FROM {$this->table} ORDER BY brand ASC"
        );

        if (!$result) {
            return [];
        }

        $brands = [];
        while ($row = $result->fetch_assoc()) {
            $brands[] = $row['brand'];
        }

        return $brands;
    }

    /**
     * Get phones scraped after a specific date
     *
     * @param string $date Date in Y-m-d H:i:s format
     * @return array List of phones
     */
    public function getPhonesAfterDate(string $date): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE scraped_at > ? 
            ORDER BY scraped_at DESC"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $phones = [];

        while ($row = $result->fetch_assoc()) {
            if (isset($row['specs'])) {
                $row['specs'] = json_decode($row['specs'], true);
            }
            $phones[] = $row;
        }

        $stmt->close();
        return $phones;
    }

    /**
     * Get phones by price range
     *
     * @param int $minPrice Minimum price
     * @param int $maxPrice Maximum price
     * @param int $limit Number of phones to return
     * @param int $offset Offset for pagination
     * @return array List of phones
     */
    public function getPhonesByPriceRange(int $minPrice, int $maxPrice, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->mysqli->prepare(
            "SELECT id, slug, name, brand, price, price_value, url, image_url, specs, processor, ram, storage, display, battery, scraped_at, updated_at 
            FROM {$this->table} 
            WHERE price_value >= ? AND price_value <= ? 
            ORDER BY price_value ASC 
            LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iiii', $minPrice, $maxPrice, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $phones = [];

        while ($row = $result->fetch_assoc()) {
            if (isset($row['specs'])) {
                $row['specs'] = json_decode($row['specs'], true);
            }
            $phones[] = $row;
        }

        $stmt->close();
        return $phones;
    }
}
