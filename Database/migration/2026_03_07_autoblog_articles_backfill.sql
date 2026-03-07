-- ============================================================
-- Autoblog articles backfill migration (2026-03-07)
-- Brings `autoblog_articles` schema in line with AutoBlogModel
-- Adds missing legacy/original and AI fields and updates status ENUM
-- ============================================================

-- NOTE: `ADD COLUMN IF NOT EXISTS` requires MySQL 8.0+.
-- If you're on MySQL 5.7, run the fallback section at the bottom.

ALTER TABLE `autoblog_articles`
  ADD COLUMN IF NOT EXISTS `original_title` VARCHAR(500) NULL AFTER `source_id`,
  ADD COLUMN IF NOT EXISTS `original_url` TEXT NULL AFTER `original_title`,
  ADD COLUMN IF NOT EXISTS `original_content` LONGTEXT NULL AFTER `original_url`,
  ADD COLUMN IF NOT EXISTS `original_excerpt` TEXT NULL AFTER `original_content`,
  ADD COLUMN IF NOT EXISTS `original_author` VARCHAR(255) NULL AFTER `original_excerpt`,
  ADD COLUMN IF NOT EXISTS `featured_image` TEXT NULL AFTER `original_author`,
  ADD COLUMN IF NOT EXISTS `original_published_at` DATETIME NULL AFTER `featured_image`,
  ADD COLUMN IF NOT EXISTS `ai_title` TEXT NULL AFTER `original_published_at`,
  ADD COLUMN IF NOT EXISTS `ai_content` LONGTEXT NULL AFTER `ai_title`,
  ADD COLUMN IF NOT EXISTS `ai_excerpt` TEXT NULL AFTER `ai_content`,
  ADD COLUMN IF NOT EXISTS `ai_summary` TEXT NULL AFTER `ai_excerpt`,
  ADD COLUMN IF NOT EXISTS `seo_score` INT DEFAULT 0 AFTER `ai_summary`,
  ADD COLUMN IF NOT EXISTS `word_count` INT DEFAULT 0 AFTER `seo_score`,
  ADD COLUMN IF NOT EXISTS `error_message` TEXT NULL AFTER `word_count`,
  MODIFY COLUMN `status` ENUM('collected','processing','processed','approved','published','failed') DEFAULT 'collected';

CREATE INDEX IF NOT EXISTS `idx_seo_score` ON `autoblog_articles` (`seo_score`);

-- ============================================================
-- MySQL 5.7 fallback (run only if your server rejects IF NOT EXISTS)
-- ============================================================
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_title` VARCHAR(500) NULL AFTER `source_id`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_url` TEXT NULL AFTER `original_title`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_content` LONGTEXT NULL AFTER `original_url`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_excerpt` TEXT NULL AFTER `original_content`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_author` VARCHAR(255) NULL AFTER `original_excerpt`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `featured_image` TEXT NULL AFTER `original_author`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `original_published_at` DATETIME NULL AFTER `featured_image`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_title` TEXT NULL AFTER `original_published_at`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_content` LONGTEXT NULL AFTER `ai_title`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_excerpt` TEXT NULL AFTER `ai_content`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `ai_summary` TEXT NULL AFTER `ai_excerpt`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `seo_score` INT DEFAULT 0 AFTER `ai_summary`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `word_count` INT DEFAULT 0 AFTER `seo_score`;
-- ALTER TABLE `autoblog_articles` ADD COLUMN `error_message` TEXT NULL AFTER `word_count`;
-- ALTER TABLE `autoblog_articles` MODIFY COLUMN `status` ENUM('collected','processing','processed','approved','published','failed') DEFAULT 'collected';
-- CREATE INDEX `idx_seo_score` ON `autoblog_articles` (`seo_score`);
