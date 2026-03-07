-- ========================================
-- টেবিল / Table: categories
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 9
-- ========================================

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('1', 'Tech News', NULL, 'tech-news');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('2', 'Online Earnings', NULL, 'online-earnings');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('4', 'Reviews', NULL, 'reviews');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('5', 'Banking -  ব্যাংকিং', NULL, 'bank');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('6', 'News', NULL, 'news');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('7', 'টিপস্', NULL, 'tips');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('9', 'Chakri - চাকরি', NULL, 'chakribd');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('10', 'Tutorials -  টিউটোরিয়ালস', NULL, 'tutorials');
INSERT INTO `categories` (`id`, `name`, `description`, `slug`) VALUES ('11', 'Online Services', NULL, 'online-services');
