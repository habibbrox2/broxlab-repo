<?php
/**
 * scripts/migrate-cv-personal-info-to-cv-infos.php
 * Migration script: Legacy cv_personal_info -> cv_infos
 * Usage: php scripts/migrate-cv-personal-info-to-cv-infos.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../public_html/_db.php';
echo "=== CV Personal Info -> cv_infos Migration ===\n\n";
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) die("Connection failed\n");
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET FOREIGN_KEY_CHECKS=0");
try {
    echo "[1/5] Creating cv_infos table... ";
    $mysqli->query("CREATE TABLE IF NOT EXISTS `cv_infos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `full_name` VARCHAR(255) NOT NULL DEFAULT '',
        `job_title` VARCHAR(255) NOT NULL DEFAULT '',
        `email` VARCHAR(255) NOT NULL DEFAULT '',
        `phone` VARCHAR(50) NOT NULL DEFAULT '',
        `address` VARCHAR(500) NOT NULL DEFAULT '',
        `date_of_birth` DATE DEFAULT NULL,
        `nationality` VARCHAR(100) NOT NULL DEFAULT '',
        `gender` ENUM('male','female','other') DEFAULT NULL,
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
        `builder_data` LONGTEXT DEFAULT NULL,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "OK\n";
    echo "[2/5] Checking legacy tables... ";
    $r = $mysqli->query("SHOW TABLES LIKE 'cv_personal_info'");
    if ($r && $r->num_rows > 0) {
        echo "found. Migrating data...\n";
        $mysqli->query("INSERT IGNORE INTO `cv_infos` (
            `user_id`, `full_name`, `job_title`, `email`, `phone`, `address`,
            `date_of_birth`, `nationality`, `gender`, `driving_license`,
            `website`, `linkedin`, `github`, `twitter`, `portfolio`,
            `national_id_no`, `passport_no`, `birth_certificate_no`, `religion`,
            `title`, `template`, `professional_status`, `builder_data`, `profile_photo`,
            `is_active`, `view_count`, `download_count`, `last_viewed_at`,
            `deleted_at`, `created_at`, `updated_at`)
            SELECT COALESCE(lpi.`user_id`, 0), COALESCE(lpi.`full_name`, ''),
            COALESCE(lpi.`job_title`, ''), COALESCE(lpi.`email`, ''),
            COALESCE(lpi.`phone`, ''), COALESCE(lpi.`address`, ''),
            lpi.`date_of_birth`, COALESCE(lpi.`nationality`, ''),
            lpi.`gender`, COALESCE(lpi.`driving_license`, ''),
            COALESCE(lpi.`website`, ''), COALESCE(lpi.`linkedin`, ''),
            COALESCE(lpi.`github`, ''), COALESCE(lpi.`twitter`, ''),
            COALESCE(lpi.`portfolio`, ''), COALESCE(lpi.`national_id_no`, ''),
            COALESCE(lpi.`passport_no`, ''), COALESCE(lpi.`birth_certificate_no`, ''),
            COALESCE(lpi.`religion`, ''), COALESCE(lc.`title`, 'My CV'),
            COALESCE(lc.`template`, 'modern'), lc.`professional_status`,
            lc.`builder_data`, COALESCE(lpi.`profile_photo`, lc.`profile_photo`),
            COALESCE(lc.`is_active`, 1), COALESCE(lc.`view_count`, 0),
            COALESCE(lc.`download_count`, 0), lc.`last_viewed_at`,
            COALESCE(lpi.`deleted_at`, lc.`deleted_at`),
            COALESCE(lpi.`created_at`, lc.`created_at`, NOW()),
            COALESCE(lpi.`updated_at`, lc.`updated_at`, NOW())
            FROM `cv_personal_info` lpi LEFT JOIN `cvs` lc ON lpi.`cv_id` = lc.`id`");
        echo "       Migrated " . $mysqli->affected_rows . " records\n";
    } else {
        echo "not found, skipping.\n";
    }
    echo "[3/5] Verifying cv_infos... ";
    $r = $mysqli->query("SELECT COUNT(*) as c FROM cv_infos WHERE deleted_at IS NULL");
    echo $r->fetch_assoc()['c'] . " active records.\n";
    echo "[4/5] Dropping legacy tables...\n";
    foreach (['cv_template_purchases','cv_items','cv_sections','cvs','cv_personal_info'] as $t) {
        $r = $mysqli->query("SHOW TABLES LIKE '$t'");
        if ($r && $r->num_rows > 0) { $mysqli->query("DROP TABLE IF EXISTS `$t`"); echo "       Dropped `$t`\n"; }
        else { echo "       `$t` not found, skipped.\n"; }
    }
    echo "[5/5] Verification complete.\n";
    echo "\n=== Migration complete! ===\n";
} catch (Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1"); $mysqli->close(); exit(1);
}
$mysqli->query("SET FOREIGN_KEY_CHECKS=1"); $mysqli->close();
