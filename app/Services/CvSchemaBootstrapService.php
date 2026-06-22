<?php

declare(strict_types=1);

/**
 * CvSchemaBootstrapService — Ensures the cv_infos table exists with all needed columns.
 * Legacy tables (cvs, cv_sections, cv_items, cv_template_purchases) removed.
 */

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
            $this->ensureCvPersonalInfoTable();
            self::$bootstrapped = true;
            return true;
        } catch (Throwable $e) {
            if (function_exists('logError')) {
                logError('CV schema bootstrap failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    private function ensureCvPersonalInfoTable(): void
    {
        $this->runSql(<<<SQL
CREATE TABLE IF NOT EXISTS `cv_infos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
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
    `title` VARCHAR(255) NOT NULL DEFAULT 'My CV',
    `template` VARCHAR(50) NOT NULL DEFAULT 'modern',
    `professional_status` VARCHAR(100) DEFAULT NULL,
    `experience_json` JSON DEFAULT NULL COMMENT 'Work experience entries',
    `education_json` JSON DEFAULT NULL COMMENT 'Education entries',
    `skills_json` JSON DEFAULT NULL COMMENT 'Skills entries',
    `languages_json` JSON DEFAULT NULL COMMENT 'Languages entries',
    `social_links_json` JSON DEFAULT NULL COMMENT 'Social links entries',
    `custom_sections_json` JSON DEFAULT NULL COMMENT 'Custom section entries',
    `references_json` JSON DEFAULT NULL COMMENT 'Reference entries',
    `profile_photo` VARCHAR(500) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `view_count` INT(11) NOT NULL DEFAULT 0,
    `download_count` INT(11) NOT NULL DEFAULT 0,
    `last_viewed_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_id` (`user_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_full_name` (`full_name`),
    INDEX `idx_template` (`template`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_cv_infos_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Single CV table — personal info + CV metadata + JSON section columns'
SQL);

        $this->ensureColumnExists('cv_infos', 'experience_json', "JSON DEFAULT NULL COMMENT 'Work experience entries'");
        $this->ensureColumnExists('cv_infos', 'education_json', "JSON DEFAULT NULL COMMENT 'Education entries'");
        $this->ensureColumnExists('cv_infos', 'skills_json', "JSON DEFAULT NULL COMMENT 'Skills entries'");
        $this->ensureColumnExists('cv_infos', 'languages_json', "JSON DEFAULT NULL COMMENT 'Languages entries'");
        $this->ensureColumnExists('cv_infos', 'social_links_json', "JSON DEFAULT NULL COMMENT 'Social links entries'");
        $this->ensureColumnExists('cv_infos', 'custom_sections_json', "JSON DEFAULT NULL COMMENT 'Custom section entries'");
        $this->ensureColumnExists('cv_infos', 'references_json', "JSON DEFAULT NULL COMMENT 'Reference entries'");
    }

    private function runSql(string $sql): void
    {
        if (!$this->mysqli->query($sql)) {
            throw new RuntimeException($this->mysqli->error ?: 'Unknown CV schema error');
        }
    }

    private function ensureColumnExists(string $table, string $column, string $definition): void
    {
        $tableSql = $this->mysqli->real_escape_string($table);
        $columnSql = $this->mysqli->real_escape_string($column);

        $sql = "SELECT COUNT(*) AS cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = '{$tableSql}'
                  AND COLUMN_NAME = '{$columnSql}'";
        $result = $this->mysqli->query($sql);
        if (!$result) {
            throw new RuntimeException($this->mysqli->error ?: 'Failed to inspect CV schema');
        }

        $row = $result->fetch_assoc();
        if ((int)($row['cnt'] ?? 0) > 0) {
            return;
        }

        $this->runSql("ALTER TABLE `{$tableSql}` ADD COLUMN `{$columnSql}` {$definition}");
    }
}
