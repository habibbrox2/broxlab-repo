-- ========================================
-- টেবিল / Table: categories
-- তারিখ / Date: 2026-03-08 02:32:38
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 17
-- ========================================

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(150) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `order` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('1', 'Tech News', NULL, 'tech-news', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('2', 'Online Earnings', NULL, 'online-earnings', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('4', 'Reviews', NULL, 'reviews', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('5', 'Banking -  ব্যাংকিং', NULL, 'bank', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('6', 'News', NULL, 'news', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('7', 'টিপস্', NULL, 'tips', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('9', 'Chakri - চাকরি', NULL, 'chakribd', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('10', 'Tutorials -  টিউটোরিয়ালস', NULL, 'tutorials', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('11', 'Online Services', NULL, 'online-services', NULL, NULL, NULL, '0', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('12', 'Technology', 'Technology news and articles', 'technology', NULL, NULL, NULL, '1', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('13', 'Business', 'Business and finance articles', 'business', NULL, NULL, NULL, '2', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('14', 'Sports', 'Sports news and updates', 'sports', NULL, NULL, NULL, '3', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('15', 'Entertainment', 'Entertainment and celebrity news', 'entertainment', NULL, NULL, NULL, '4', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('16', 'Health', 'Health and wellness articles', 'health', NULL, NULL, NULL, '5', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('17', 'Education', 'Educational content and tutorials', 'education', NULL, NULL, NULL, '6', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('18', 'Lifestyle', 'Lifestyle and living articles', 'lifestyle', NULL, NULL, NULL, '7', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`, `parent_id`, `image`, `icon`, `order`, `is_featured`, `meta_title`, `meta_description`, `status`, `created_at`, `updated_at`) VALUES ('19', 'Travel', 'Travel guides and destinations', 'travel', NULL, NULL, NULL, '8', '0', NULL, NULL, 'active', '2026-03-07 16:20:38', '2026-03-07 16:20:38');
