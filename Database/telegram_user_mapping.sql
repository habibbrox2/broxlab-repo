-- ========================================
-- টেবিল / Table: telegram_user_mapping
-- তারিখ / Date: 2026-03-08 02:32:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `telegram_user_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `telegram_user_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_user_id` (`telegram_user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `telegram_user_mapping_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

