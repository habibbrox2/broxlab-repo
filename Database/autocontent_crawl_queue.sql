-- ========================================
-- টেবিল / Table: autocontent_crawl_queue
-- তারিখ / Date: 2026-03-08 02:32:37
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `autocontent_crawl_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `url_hash` varchar(64) DEFAULT '',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `depth` int(11) DEFAULT 0,
  `retry_count` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_status` (`status`),
  KEY `idx_url_hash` (`url_hash`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

