<?php

/**
 * GSMArena Device Model
 *
 * Handles database operations for GSMArena mobile devices
 *
 * @package BroxBhai
 * @since 2026-03-26
 */
class GSMArenaDeviceModel
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Save a new device to database
     */
    public function saveDevice(array $deviceData): int
    {
        $sql = "INSERT INTO gsmarena_devices (slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->mysqli->prepare($sql);

        $specsJson = json_encode($deviceData['specs'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmt->bind_param(
            'ssssssssssssssssss',
            $deviceData['slug'],
            $deviceData['name'],
            $deviceData['brand'],
            $deviceData['url'],
            $deviceData['image_url'],
            $specsJson,
            $deviceData['released'],
            $deviceData['body'],
            $deviceData['sim'],
            $deviceData['os'],
            $deviceData['display_size'],
            $deviceData['display_resolution'],
            $deviceData['display_type'],
            $deviceData['cpu'],
            $deviceData['ram'],
            $deviceData['storage'],
            $deviceData['main_camera'],
            $deviceData['battery_capacity']
        );

        $result = $stmt->execute();
        $stmt->close();

        if ($result) {
            return (int)$this->mysqli->insert_id;
        }

        throw new \Exception("Failed to save device: " . $this->mysqli->error);
    }

    /**
     * Update an existing device
     */
    public function updateDevice(int $id, array $deviceData): bool
    {
        $sql = "UPDATE gsmarena_devices SET slug = ?, name = ?, brand = ?, url = ?, image_url = ?, specs = ?, released = ?, body = ?, sim = ?, os = ?, display_size = ?, display_resolution = ?, display_type = ?, cpu = ?, ram = ?, storage = ?, main_camera = ?, battery_capacity = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);

        $specsJson = json_encode($deviceData['specs'] ?? [], JSON_UNESCAPED_UNICODE);

        $stmt->bind_param(
            'ssssssssssssssssssi',
            $deviceData['slug'],
            $deviceData['name'],
            $deviceData['brand'],
            $deviceData['url'],
            $deviceData['image_url'],
            $specsJson,
            $deviceData['released'],
            $deviceData['body'],
            $deviceData['sim'],
            $deviceData['os'],
            $deviceData['display_size'],
            $deviceData['display_resolution'],
            $deviceData['display_type'],
            $deviceData['cpu'],
            $deviceData['ram'],
            $deviceData['storage'],
            $deviceData['main_camera'],
            $deviceData['battery_capacity'],
            $id
        );

        $result = $stmt->execute();
        $stmt->close();

        if (!$result) {
            throw new \Exception("Failed to update device: " . $this->mysqli->error);
        }

        return $result;
    }

    /**
     * Get device by database ID
     */
    public function getDeviceById(int $id): ?array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            return $row;
        }

        return null;
    }

    /**
     * Get device by slug
     */
    public function getDeviceBySlug(string $slug): ?array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices WHERE slug = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            return $row;
        }

        return null;
    }

    /**
     * Check if device exists by slug
     */
    public function existsBySlug(string $slug): bool
    {
        $sql = "SELECT COUNT(*) as count FROM gsmarena_devices WHERE slug = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'] > 0;
        }

        return false;
    }

    /**
     * Get recent devices with pagination
     */
    public function getRecentDevices(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices ORDER BY released DESC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            $devices[] = $row;
        }

        return $devices;
    }

    /**
     * Search devices by name or brand
     */
    public function searchDevices(string $query, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices WHERE name LIKE ? OR brand LIKE ? ORDER BY released DESC LIMIT ? OFFSET ?";
        $searchTerm = "%{$query}%";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('sssii', $searchTerm, $searchTerm, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            $devices[] = $row;
        }

        return $devices;
    }

    /**
     * Get devices by brand
     */
    public function getDevicesByBrand(string $brand, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices WHERE brand = ? ORDER BY released DESC LIMIT ? OFFSET ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('sii', $brand, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            $devices[] = $row;
        }

        return $devices;
    }

    /**
     * Get total count of devices
     */
    public function getTotalCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM gsmarena_devices";
        $result = $this->mysqli->query($sql);

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'];
        }

        return 0;
    }

    /**
     * Get count by brand
     */
    public function getCountByBrand(string $brand): int
    {
        $sql = "SELECT COUNT(*) as count FROM gsmarena_devices WHERE brand = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $brand);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['count'];
        }

        return 0;
    }

    /**
     * Get all unique brands
     */
    public function getBrands(): array
    {
        $sql = "SELECT DISTINCT brand FROM gsmarena_devices ORDER BY brand ASC";
        $result = $this->mysqli->query($sql);

        $brands = [];
        while ($row = $result->fetch_assoc()) {
            $brands[] = $row['brand'];
        }

        return $brands;
    }

    /**
     * Get devices after a specific date
     */
    public function getDevicesAfterDate(string $date): array
    {
        $sql = "SELECT id, slug, name, brand, url, image_url, specs, released, body, sim, os, display_size, display_resolution, display_type, cpu, ram, storage, main_camera, battery_capacity, scraped_at, updated_at FROM gsmarena_devices WHERE scraped_at > ? ORDER BY released DESC";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();

        $devices = [];
        while ($row = $result->fetch_assoc()) {
            $row['specs'] = json_decode($row['specs'], true);
            $devices[] = $row;
        }

        return $devices;
    }

    /**
     * Delete a device
     */
    public function deleteDevice(int $id): bool
    {
        $sql = "DELETE FROM gsmarena_devices WHERE id = ?";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param('i', $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }
}
