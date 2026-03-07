-- ========================================
-- টেবিল / Table: autoblog_queue
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_type` enum('collect','process','publish','retry') NOT NULL,
  `source_id` int(11) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `status` enum('pending','running','completed','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

