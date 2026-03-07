-- =====================================================
-- AI AutoBlog Database Migration Script
-- Run this SQL to set up all required tables and columns
-- =====================================================

-- Create autoblog_sources table (if not exists)
CREATE TABLE IF NOT EXISTS `autoblog_sources` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `url` TEXT NOT NULL,
  `type` ENUM('rss', 'api', 'scrape') DEFAULT 'rss',
  `category_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `fetch_interval` INT DEFAULT 600,
  `last_fetched` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_last_fetched` (`last_fetched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add selector columns to autoblog_sources (if not exists)
ALTER TABLE `autoblog_sources` 
  ADD COLUMN IF NOT EXISTS `selector_title` VARCHAR(500) DEFAULT '' AFTER `fetch_interval`,
  ADD COLUMN IF NOT EXISTS `selector_content` VARCHAR(500) DEFAULT '' AFTER `selector_title`,
  ADD COLUMN IF NOT EXISTS `selector_image` VARCHAR(500) DEFAULT '' AFTER `selector_content`,
  ADD COLUMN IF NOT EXISTS `selector_excerpt` VARCHAR(500) DEFAULT '' AFTER `selector_image`,
  ADD COLUMN IF NOT EXISTS `selector_date` VARCHAR(500) DEFAULT '' AFTER `selector_excerpt`,
  ADD COLUMN IF NOT EXISTS `selector_author` VARCHAR(500) DEFAULT '' AFTER `selector_date`;

-- Create autoblog_articles table (if not exists)
CREATE TABLE IF NOT EXISTS `autoblog_articles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `source_id` INT NOT NULL,
  `original_url` TEXT,
  `original_title` TEXT,
  `original_content` TEXT,
  `original_author` VARCHAR(255) DEFAULT NULL,
  `original_published_at` DATETIME DEFAULT NULL,
  `ai_title` TEXT,
  `ai_content` TEXT,
  `ai_excerpt` TEXT,
  `ai_summary` TEXT,
  `content` TEXT,
  `status` ENUM('collected', 'processing', 'processed', 'approved', 'published', 'rejected', 'failed') DEFAULT 'collected',
  `seo_score` INT DEFAULT 0,
  `word_count` INT DEFAULT 0,
  `error_message` TEXT,
  `published_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_seo_score` (`seo_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add seo_score column if not exists (MySQL 8.0+ syntax)
-- ALTER TABLE `autoblog_articles` ADD COLUMN IF NOT EXISTS `seo_score` INT DEFAULT 0;

-- For MySQL 5.7 compatibility, use this instead:
-- Check if column exists, if not add it
-- (This is handled by the application code in ensureSelectorColumns)

-- Create autoblog_settings table (if not exists)
CREATE TABLE IF NOT EXISTS `autoblog_settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings if not exists
INSERT IGNORE INTO `autoblog_settings` (`setting_key`, `setting_value`) VALUES
  ('ai_endpoint', ''),
  ('ai_model', 'gpt-4o-mini'),
  ('ai_key', ''),
  ('auto_approve_threshold', '75'),
  ('auto_publish', '0'),
  ('default_category', ''),
  ('content_min_words', '300'),
  ('content_max_words', '5000'),
  ('image_download', '1'),
  ('duplicate_check', '1');

-- Create autoblog_queue table for scheduled tasks (if not exists)
CREATE TABLE IF NOT EXISTS `autoblog_queue` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `task_type` ENUM('collect', 'process', 'publish', 'retry') NOT NULL,
  `source_id` INT DEFAULT NULL,
  `article_id` INT DEFAULT NULL,
  `status` ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `max_attempts` INT DEFAULT 3,
  `error_message` TEXT,
  `scheduled_at` DATETIME DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MySQL 5.7 Alternative (if ADD COLUMN IF NOT EXISTS fails)
-- =====================================================
-- Run these manually if you're on MySQL 5.7:

-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_title` VARCHAR(500) DEFAULT '';
-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_content` VARCHAR(500) DEFAULT '';
-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_image` VARCHAR(500) DEFAULT '';
-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_excerpt` VARCHAR(500) DEFAULT '';
-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_date` VARCHAR(500) DEFAULT '';
-- ALTER TABLE `autoblog_sources` ADD COLUMN `selector_author` VARCHAR(500) DEFAULT '';

-- For autoblog_articles table:
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_excerpt` TEXT AFTER `original_content`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_excerpt` TEXT AFTER `ai_content`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_summary` TEXT AFTER `ai_excerpt`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `content` TEXT AFTER `ai_summary`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `seo_score` INT DEFAULT 0;
-- ALTER TABLE `autoblog_articles` ADD INDEX `idx_seo_score` (`seo_score`);
