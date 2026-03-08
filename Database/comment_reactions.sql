-- ========================================
-- টেবিল / Table: comment_reactions
-- তারিখ / Date: 2026-03-08 02:32:38
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 4
-- ========================================

CREATE TABLE IF NOT EXISTS `comment_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_ip` varchar(45) NOT NULL,
  `reaction_emoji` varchar(10) NOT NULL DEFAULT '?',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`comment_id`,`user_id`,`guest_ip`),
  KEY `idx_comment_id` (`comment_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_reaction` (`reaction_emoji`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `comment_reactions` (`id`, `comment_id`, `user_id`, `guest_ip`, `reaction_emoji`, `created_at`, `updated_at`) VALUES ('1', '1', '1', '127.0.0.1', '😮', '2026-01-19 03:40:39', '2026-01-19 03:40:39');
INSERT INTO `comment_reactions` (`id`, `comment_id`, `user_id`, `guest_ip`, `reaction_emoji`, `created_at`, `updated_at`) VALUES ('3', '15', '1', '127.0.0.1', '😂', '2026-01-19 03:43:28', '2026-01-19 03:43:28');
INSERT INTO `comment_reactions` (`id`, `comment_id`, `user_id`, `guest_ip`, `reaction_emoji`, `created_at`, `updated_at`) VALUES ('4', '20', '1', '127.0.0.1', '😮', '2026-01-19 03:43:53', '2026-01-19 03:43:53');
INSERT INTO `comment_reactions` (`id`, `comment_id`, `user_id`, `guest_ip`, `reaction_emoji`, `created_at`, `updated_at`) VALUES ('5', '15', NULL, '103.13.193.101', '😂', '2026-01-19 04:13:50', '2026-01-19 04:13:50');
