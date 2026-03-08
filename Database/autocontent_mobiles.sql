-- ========================================
-- টেবিল / Table: autocontent_mobiles
-- তারিখ / Date: 2026-03-08 02:32:37
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `autocontent_mobiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) DEFAULT NULL,
  `source_url` varchar(2048) NOT NULL,
  `title` varchar(500) DEFAULT '',
  `price` varchar(255) DEFAULT '',
  `brand` varchar(100) DEFAULT '',
  `model` varchar(255) DEFAULT '',
  `image_url` varchar(2048) DEFAULT '',
  `specifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specifications`)),
  `release_date` varchar(100) DEFAULT '',
  `status` enum('collected','processing','processed','published','failed') DEFAULT 'collected',
  `scraped_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_source_url` (`source_url`(500)),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_brand` (`brand`),
  KEY `idx_status` (`status`),
  KEY `idx_scraped_at` (`scraped_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

