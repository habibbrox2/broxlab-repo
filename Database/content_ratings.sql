-- ========================================
-- টেবিল / Table: content_ratings
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 2
-- ========================================

CREATE TABLE IF NOT EXISTS `content_ratings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content_type` enum('post','page','service') NOT NULL,
  `content_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `guest_ip` varchar(45) DEFAULT NULL,
  `rater_key` varchar(191) NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_content_rater` (`content_type`,`content_id`,`rater_key`),
  KEY `idx_content_lookup` (`content_type`,`content_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `content_ratings` (`id`, `content_type`, `content_id`, `user_id`, `guest_ip`, `rater_key`, `rating`, `created_at`, `updated_at`) VALUES ('1', 'service', '8', NULL, '103.25.250.130', 'guest:103.25.250.130', '5', '2026-03-01 23:31:51', '2026-03-01 23:31:51');
INSERT INTO `content_ratings` (`id`, `content_type`, `content_id`, `user_id`, `guest_ip`, `rater_key`, `rating`, `created_at`, `updated_at`) VALUES ('2', 'service', '8', NULL, '103.13.193.101', 'guest:103.13.193.101', '5', '2026-03-01 23:31:52', '2026-03-02 03:07:15');
