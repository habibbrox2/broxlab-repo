<?php

declare(strict_types=1);

class CvSchemaBootstrapService
{
    private mysqli $mysqli;
    private static bool $bootstrapped = false;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function ensureAll(): bool
    {
        if (self::$bootstrapped) {
            return true;
        }

        try {
            $this->ensureLegacyTables();
            $this->ensureLegacyColumns();
            $this->ensureV3Tables();
            self::$bootstrapped = true;
            return true;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('CV schema bootstrap failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    private function ensureLegacyTables(): void
    {
        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cvs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'My CV',
  `template` varchar(50) NOT NULL DEFAULT 'modern',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `professional_status` varchar(100) DEFAULT NULL,
  `builder_data` longtext DEFAULT NULL,
  `profile_photo` varchar(500) DEFAULT NULL,
  `download_count` int(11) NOT NULL DEFAULT 0,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `last_viewed_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `cvs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_id` int(11) NOT NULL,
  `section_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cv_id` (`cv_id`),
  KEY `idx_sort_order` (`cv_id`,`sort_order`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `cv_sections_ibfk_1` FOREIGN KEY (`cv_id`) REFERENCES `cvs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_id` int(11) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `content_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content_json`)),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_section_id` (`section_id`),
  KEY `idx_sort_order` (`section_id`,`sort_order`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `cv_items_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `cv_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`data_json`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cv_version` (`cv_id`, `version_number`),
  KEY `idx_cv_id` (`cv_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `cv_versions_ibfk_1` FOREIGN KEY (`cv_id`) REFERENCES `cvs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cv_versions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_shares` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cv_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_cv_id` (`cv_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `cv_shares_ibfk_1` FOREIGN KEY (`cv_id`) REFERENCES `cvs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_personal_info` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cv_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `full_name` VARCHAR(255) NOT NULL DEFAULT '',
    `job_title` VARCHAR(255) NOT NULL DEFAULT '',
    `email` VARCHAR(255) NOT NULL DEFAULT '',
    `phone` VARCHAR(50) NOT NULL DEFAULT '',
    `address` VARCHAR(500) NOT NULL DEFAULT '',
    `date_of_birth` DATE DEFAULT NULL,
    `nationality` VARCHAR(100) NOT NULL DEFAULT '',
    `gender` ENUM('male', 'female', 'other') DEFAULT NULL,
    `driving_license` VARCHAR(50) NOT NULL DEFAULT '',
    `website` VARCHAR(500) NOT NULL DEFAULT '',
    `linkedin` VARCHAR(500) NOT NULL DEFAULT '',
    `github` VARCHAR(500) NOT NULL DEFAULT '',
    `twitter` VARCHAR(500) NOT NULL DEFAULT '',
    `portfolio` VARCHAR(500) NOT NULL DEFAULT '',
    `national_id_no` VARCHAR(100) NOT NULL DEFAULT '',
    `passport_no` VARCHAR(50) NOT NULL DEFAULT '',
    `birth_certificate_no` VARCHAR(100) NOT NULL DEFAULT '',
    `religion` VARCHAR(100) NOT NULL DEFAULT '',
    `deleted_at` timestamp NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_cv_id` (`cv_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_full_name` (`full_name`),
    INDEX `idx_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_personal_info_cv` FOREIGN KEY (`cv_id`) REFERENCES `cvs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_personal_info_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    private function ensureLegacyColumns(): void
    {
        // Migration: rename `order` to `sort_order` to avoid MariaDB reserved word conflicts
        $this->migrateOrderColumn();

        $this->ensureColumn('cvs', 'professional_status', "VARCHAR(100) DEFAULT NULL AFTER is_active");
        $this->ensureColumn('cvs', 'builder_data', "LONGTEXT DEFAULT NULL AFTER professional_status");
        $this->ensureColumn('cvs', 'profile_photo', "VARCHAR(500) DEFAULT NULL AFTER builder_data");
        $this->ensureColumn('cvs', 'download_count', "INT(11) NOT NULL DEFAULT 0 AFTER profile_photo");
        $this->ensureColumn('cvs', 'view_count', "INT(11) NOT NULL DEFAULT 0 AFTER download_count");
        $this->ensureColumn('cvs', 'last_viewed_at', "TIMESTAMP NULL DEFAULT NULL AFTER view_count");
        $this->ensureColumn('cvs', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER last_viewed_at");

        $this->ensureColumn('cv_sections', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER is_visible");
        $this->ensureColumn('cv_items', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER `sort_order`");
        $this->ensureColumn('cv_shares', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER expires_at");
        $this->ensureColumn('cv_personal_info', 'deleted_at', "TIMESTAMP NULL DEFAULT NULL AFTER birth_certificate_no");
    }

    /**
     * Migrate existing `order` columns to `sort_order` to avoid MariaDB reserved word conflicts.
     */
    private function migrateOrderColumn(): void
    {
        $tables = ['cv_sections', 'cv_items'];

        foreach ($tables as $table) {
            $safeTable = str_replace('`', '``', $table);

            // Check if the old `order` column still exists
            $result = $this->mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE 'order'");
            if (!$result || $result->num_rows === 0) {
                continue;
            }

            // Check if sort_order already exists (e.g., partial migration)
            $checkResult = $this->mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE 'sort_order'");
            if ($checkResult && $checkResult->num_rows > 0) {
                continue;
            }

            $indexSuffix = $table === 'cv_sections' ? 'cv_id' : 'section_id';

            // Check if the old index exists before attempting to drop it
            $indexCheck = $this->mysqli->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = 'idx_order'");
            $hasOldIndex = $indexCheck && $indexCheck->num_rows > 0;

            // Rename the column — MariaDB auto-updates indexes to reference the new name
            $this->runSql("ALTER TABLE `{$safeTable}` CHANGE COLUMN `order` `sort_order` INT(11) NOT NULL DEFAULT 0");

            if ($hasOldIndex) {
                // Drop old index and recreate with new name to keep naming consistent
                $this->runSql("ALTER TABLE `{$safeTable}` DROP INDEX `idx_order`");
                $this->runSql("ALTER TABLE `{$safeTable}` ADD INDEX `idx_sort_order` (`{$indexSuffix}`, `sort_order`)");
            }
        }
    }

    private function ensureV3Tables(): void
    {
        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'My CV',
  `slug` varchar(255) NOT NULL,
  `cv_id` int(11) DEFAULT NULL,
  `professional_summary` longtext DEFAULT NULL,
  `active_template_id` int(11) DEFAULT NULL,
  `completion_score` tinyint(3) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active_template` (`active_template_id`),
  KEY `idx_user_active` (`user_id`, `is_active`),
  CONSTRAINT `fk_cv_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT 'modern',
  `tags` json DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `preview_images` json DEFAULT NULL,
  `status` enum('active','disabled','draft') NOT NULL DEFAULT 'active',
  `is_free` tinyint(1) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `version` varchar(20) NOT NULL DEFAULT '1.0.0',
  `supported_sections` json NOT NULL,
  `features` json DEFAULT NULL,
  `best_for` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT 'System',
  `installed_via` enum('built-in','zip','admin') NOT NULL DEFAULT 'built-in',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`),
  KEY `idx_is_free` (`is_free`),
  KEY `idx_is_premium` (`is_premium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);

        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `user_cv_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_profile_template` (`user_id`, `profile_id`, `template_id`),
  KEY `idx_profile_id` (`profile_id`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_favorite` (`user_id`, `is_favorite`),
  CONSTRAINT `fk_uct_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uct_profile` FOREIGN KEY (`profile_id`) REFERENCES `cv_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uct_template` FOREIGN KEY (`template_id`) REFERENCES `cv_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
SQL);
    }

    private function ensureColumn(string $table, string $column, string $definition): void
    {
        $safeTable = str_replace('`', '``', $table);
        $safeColumn = $this->mysqli->real_escape_string($column);

        $result = $this->mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if ($result && $result->num_rows > 0) {
            return;
        }

        $safeColumnName = str_replace('`', '``', $column);
        $this->runSql("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumnName}` {$definition}");
    }

    private function runSql(string $sql): void
    {
        if (!$this->mysqli->query($sql)) {
            throw new RuntimeException($this->mysqli->error ?: 'Unknown CV schema error');
        }
    }
}
