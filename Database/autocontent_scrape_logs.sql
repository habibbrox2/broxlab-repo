-- ========================================
-- টেবিল / Table: autocontent_scrape_logs
-- তারিখ / Date: 2026-03-08 02:32:37
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `autocontent_scrape_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) DEFAULT NULL,
  `url` varchar(2048) NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `http_status` int(11) DEFAULT NULL,
  `response_time` decimal(10,3) DEFAULT 0.000,
  `error_message` text DEFAULT NULL,
  `content_length` int(11) DEFAULT 0,
  `retry_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `source_id` (`source_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  KEY `url` (`url`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

