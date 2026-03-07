-- ========================================
-- টেবিল / Table: autoblog_scrape_queue
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_scrape_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `url` varchar(2048) NOT NULL,
  `priority` int(11) DEFAULT 5 COMMENT '1=high, 5=normal, 10=low',
  `status` varchar(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `last_attempt` datetime DEFAULT NULL,
  `next_attempt` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `result_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_data`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_next_attempt` (`next_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

