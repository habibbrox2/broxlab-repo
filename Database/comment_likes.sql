-- ========================================
-- টেবিল / Table: comment_likes
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 7
-- ========================================

CREATE TABLE IF NOT EXISTS `comment_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL COMMENT 'Comment being liked',
  `user_id` int(11) DEFAULT NULL COMMENT 'User who liked (if logged in)',
  `guest_ip` varchar(45) DEFAULT NULL COMMENT 'Guest IP address (if not logged in)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When like was created',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`comment_id`,`user_id`,`guest_ip`),
  CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_like_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks likes on comments - users and guests can like each comment once';

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('1', '1', '2', NULL, '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('2', '1', '3', NULL, '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('3', '1', NULL, '192.168.1.1', '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('4', '2', '1', NULL, '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('5', '3', '2', NULL, '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('6', '4', NULL, '192.168.1.2', '2026-01-19 02:29:10');
INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `guest_ip`, `created_at`) VALUES ('7', '15', '1', '127.0.0.1', '2026-01-19 02:55:34');
