-- ========================================
-- টেবিল / Table: user_recovery_emails
-- তারিখ / Date: 2026-03-08 02:32:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `user_recovery_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_email` (`user_id`,`email`),
  UNIQUE KEY `verification_token` (`verification_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_verified` (`verified`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_user_recovery_emails_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

