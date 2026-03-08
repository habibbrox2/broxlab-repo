-- =============================================================================
-- AI Auto Blog - Complete Database Migration
-- =============================================================================
-- This file contains all the necessary SQL statements to create and set up
-- the database tables for the AI Auto Blog system including scraper features
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Table structure for table `admins`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'admin',
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `categories`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `posts`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_sticky` tinyint(1) DEFAULT 0,
  `meta_title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','published','archived','scheduled') DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `author_id` (`author_id`),
  KEY `status` (`status`),
  KEY `published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `post_tags`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `post_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `tags`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '#007bff',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `users`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `provider` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'email',
  `provider_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `autoblog_sources`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoblog_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `type` varchar(20) DEFAULT 'html',
  `category_id` int(11) DEFAULT NULL,
  `selector_list_container` text,
  `selector_list_item` varchar(255) DEFAULT NULL,
  `selector_list_title` varchar(255) DEFAULT NULL,
  `selector_list_date` varchar(255) DEFAULT NULL,
  `selector_list_url` varchar(255) DEFAULT NULL,
  `selector_title` varchar(255) DEFAULT NULL,
  `selector_content` text,
  `selector_image` varchar(255) DEFAULT NULL,
  `selector_excerpt` text,
  `selector_date` varchar(255) DEFAULT NULL,
  `selector_author` varchar(255) DEFAULT NULL,
  `pagination_type` varchar(50) DEFAULT 'none',
  `pagination_selector` varchar(255) DEFAULT NULL,
  `pagination_pattern` varchar(255) DEFAULT NULL,
  `max_pages` int(11) DEFAULT 10,
  `proxy_enabled` tinyint(1) DEFAULT 0,
  `proxy_provider` varchar(50) DEFAULT NULL,
  `proxy_config` text,
  `fetch_interval` int(11) DEFAULT 3600,
  `is_active` tinyint(1) DEFAULT 1,
  `last_fetch` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `is_active` (`is_active`),
  KEY `last_fetch` (`last_fetch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `autoblog_articles`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoblog_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `original_title` varchar(255) DEFAULT NULL,
  `original_content` longtext,
  `original_url` varchar(2048) DEFAULT NULL,
  `original_author` varchar(100) DEFAULT NULL,
  `original_image` varchar(255) DEFAULT NULL,
  `ai_title` varchar(255) DEFAULT NULL,
  `ai_content` longtext,
  `ai_summary` text,
  `ai_tags` text,
  `ai_slug` varchar(255) DEFAULT NULL,
  `ai_category` varchar(100) DEFAULT NULL,
  `ai_meta_description` text,
  `featured_image` varchar(255) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `url` varchar(2048) DEFAULT NULL,
  `word_count` int(11) DEFAULT 0,
  `seo_score` int(11) DEFAULT 0,
  `status` varchar(20) DEFAULT 'collected',
  `published_date` datetime DEFAULT NULL,
  `simhash` varchar(64) DEFAULT NULL,
  `content_hash` varchar(32) DEFAULT NULL,
  `scrape_source` varchar(50) DEFAULT NULL,
  `scrape_method` varchar(20) DEFAULT 'html',
  `scrape_metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `category_id` (`category_id`),
  KEY `status` (`status`),
  KEY `simhash` (`simhash`(64)),
  KEY `content_hash` (`content_hash`),
  KEY `url` (`url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `autoblog_scrape_logs`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoblog_scrape_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) DEFAULT NULL,
  `url` varchar(2048) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `http_status` int(11) DEFAULT NULL,
  `response_time` decimal(10,3) DEFAULT 0,
  `error_message` text,
  `content_length` int(11) DEFAULT 0,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `url` (`url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `autoblog_scrape_queue`
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `autoblog_scrape_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `priority` int(11) DEFAULT 5,
  `status` varchar(20) DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `last_attempt` datetime DEFAULT NULL,
  `next_attempt` datetime DEFAULT NULL,
  `error_message` text,
  `result_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `next_attempt` (`next_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Insert default data
-- -----------------------------------------------------------------------------

-- Insert default admin
INSERT INTO `admins` (`username`, `email`, `password`, `name`, `role`) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert default categories
INSERT INTO `categories` (`name`, `slug`, `description`, `order`, `status`) VALUES
('Technology', 'technology', 'Technology news and articles', 1, 'active'),
('Business', 'business', 'Business and finance articles', 2, 'active'),
('Sports', 'sports', 'Sports news and updates', 3, 'active'),
('Entertainment', 'entertainment', 'Entertainment and celebrity news', 4, 'active'),
('Health', 'health', 'Health and wellness articles', 5, 'active'),
('Education', 'education', 'Educational content and tutorials', 6, 'active'),
('Lifestyle', 'lifestyle', 'Lifestyle and living articles', 7, 'active'),
('Travel', 'travel', 'Travel guides and destinations', 8, 'active');

-- Insert sample autoblog sources (Bangladeshi news sites)
INSERT INTO `autoblog_sources` (`name`, `url`, `type`, `category_id`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_title`, `selector_content`, `selector_image`, `pagination_type`, `fetch_interval`, `is_active`) VALUES
('Prothom Alo Latest', 'https://www.prothomalo.com/', 'html', 1, '.wide-story-card, .news_with_item', '.news-item', 'h3 a', '.story-title h1', '.story-element-text', '.Td4Ec img', 'link', 1800, 1),
('BD News 24 Bengali', 'https://bangla.bdnews24.com/', 'html', 1, '.col-md-3', '.SubCat-wrapper', 'h5 a', '.details-title h1', '#contentDetails', '.details-img img', 'link', 1800, 1),
('BBC Bangla', 'https://www.bbc.com/bengali', 'html', 1, '.bbc-1kr7d6c', '.bbc-1kr7d6c', 'h3 a', 'h1', '.article-body', '.article-hero-image img', 'link', 1800, 1),
('Daily Star Bangla', 'https://www.thedailystar.net/bangla', 'html', 1, '.view-content', '.views-row', 'h2 a', 'h1', '.field-name-body', '.field-name-field-image img', 'link', 1800, 1),
('NTV Bangla', 'https://www.ntvbd.com/bangla', 'html', 1, '.news-list', '.news-item', 'h3 a', 'h1', '.news-details', '.news-image img', 'link', 1800, 1),
('Jugantor', 'https://www.jugantor.com/', 'html', 1, '.lead-news', '.news-item', 'h2 a', 'h1', '.content-details', '.news-image img', 'link', 1800, 1),
('Ittefaq', 'https://www.ittefaq.com.bd/', 'html', 1, '.news-list', '.news-item', 'h3 a', 'h1', '.news-details', '.image-content img', 'link', 1800, 1),
('Samakal', 'https://samakal.com/', 'html', 1, '.news-list', '.news-item', 'h2 a', 'h1', '.content-details', '.news-image img', 'link', 1800, 1);

-- Insert sample posts
INSERT INTO `posts` (`title`, `slug`, `content`, `excerpt`, `category_id`, `status`, `published_at`, `created_at`) VALUES
('Welcome to AI Auto Blog', 'welcome-to-ai-auto-blog', '<p>Welcome to your AI-powered auto blogging system. This system can automatically scrape content from various sources, enhance it with AI, and publish to your blog.</p><h2>Features</h2><ul><li>Automatic content scraping from multiple sources</li><li>AI-powered content enhancement</li><li>SEO optimization</li><li>Automatic duplicate detection</li><li>Multi-language support</li></ul>', 'Welcome to your AI-powered auto blogging system.', 6, 'published', NOW(), NOW()),
('Getting Started Guide', 'getting-started-guide', '<p>This guide will help you get started with the AI Auto Blog system.</p><h2>Setup</h2><p>Configure your scraping sources from the admin panel and start collecting content.</p>', 'Learn how to set up and use the AI Auto Blog system.', 6, 'published', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Migration Complete
-- =============================================================================
-- Run this file using: mysql -u username -p database_name < migrations.sql
-- =============================================================================
