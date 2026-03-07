-- ========================================
-- টেবিল / Table: comments
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 15
-- ========================================

CREATE TABLE IF NOT EXISTS `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT 'User ID if logged in',
  `guest_name` varchar(100) DEFAULT NULL COMMENT 'Guest name if not logged in',
  `content` longtext NOT NULL COMMENT 'Comment/reply text (HTML-safe)',
  `status` enum('pending','approved','rejected','hidden') DEFAULT 'pending' COMMENT 'Moderation status: pending, approved, rejected, hidden',
  `is_admin_reply` tinyint(1) DEFAULT 0 COMMENT 'Flag: 1 if this is an admin reply, 0 otherwise',
  `reviewed_by` int(11) DEFAULT NULL COMMENT 'Admin who reviewed the comment',
  `reviewed_at` datetime DEFAULT NULL COMMENT 'When comment was reviewed',
  `rejection_reason` text DEFAULT NULL COMMENT 'Reason for rejection (if rejected)',
  `parent_id` int(11) DEFAULT NULL COMMENT 'Parent comment ID for nested replies',
  `reply_count` int(11) DEFAULT 0 COMMENT 'Total replies to this comment',
  `content_type` varchar(50) NOT NULL DEFAULT 'post' COMMENT 'Type: post, page, mobile, product, etc',
  `content_id` int(11) NOT NULL COMMENT 'ID of the content being commented on',
  `likes` int(11) DEFAULT 0 COMMENT 'Total likes count',
  `edited_at` datetime DEFAULT NULL COMMENT 'When comment was last edited',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When comment was created',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last update timestamp',
  `deleted_at` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_content` (`content_type`,`content_id`) COMMENT 'Find comments for specific content',
  KEY `idx_parent` (`parent_id`) COMMENT 'Find replies for a comment',
  KEY `idx_user` (`user_id`) COMMENT 'Find comments by user',
  KEY `idx_status` (`status`) COMMENT 'Find comments by status',
  KEY `idx_hidden` (`status`,`content_type`,`content_id`) COMMENT 'Find non-hidden comments for display',
  KEY `idx_created` (`created_at`) COMMENT 'Find recent comments',
  KEY `idx_reply_count` (`content_type`,`content_id`,`parent_id`) COMMENT 'Find top comments by replies',
  KEY `fk_reviewed_by` (`reviewed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comments with nested reply support - for posts, pages, products, etc';

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '1', NULL, 'Great article! Very informative.', 'approved', '0', '2', '2026-01-19 10:00:00', NULL, NULL, '2', 'post', '1', '5', NULL, '2026-01-18 15:00:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', NULL, 'John Doe', 'I enjoyed reading this post.', 'pending', '0', NULL, NULL, NULL, NULL, '1', 'post', '1', '2', NULL, '2026-01-18 16:30:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '2', NULL, 'Thanks for sharing!', 'approved', '0', '2', '2026-01-19 10:15:00', NULL, NULL, '0', 'post', '1', '1', NULL, '2026-01-18 17:00:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '3', NULL, 'I completely agree with you!', 'approved', '0', '2', '2026-01-19 10:30:00', NULL, '1', '0', 'post', '1', '1', NULL, '2026-01-18 18:00:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', NULL, 'Jane Smith', 'Great response to the comment.', 'pending', '0', NULL, NULL, NULL, '1', '0', 'post', '1', '0', NULL, '2026-01-18 19:00:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', '1', NULL, 'You make a good point!', 'approved', '0', '2', '2026-01-19 11:00:00', NULL, '2', '0', 'post', '1', '2', NULL, '2026-01-18 20:00:00', '2026-01-19 02:29:09', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('15', '1', NULL, 'bb', 'pending', '0', NULL, NULL, NULL, NULL, '0', 'post', '3', '1', NULL, '2026-01-19 02:43:28', '2026-01-19 02:55:34', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('16', '1', NULL, 'mm', 'pending', '0', NULL, NULL, NULL, NULL, '0', 'post', '3', '0', NULL, '2026-01-19 02:43:53', '2026-01-19 02:43:53', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('17', '1', NULL, 'mm', 'approved', '0', '1', '2026-01-19 02:47:52', NULL, NULL, '0', 'post', '3', '0', NULL, '2026-01-19 02:44:09', '2026-01-19 02:47:52', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('18', '1', NULL, 'নন', 'pending', '0', NULL, NULL, NULL, NULL, '0', 'post', '3', '0', NULL, '2026-01-19 02:48:38', '2026-01-19 02:48:38', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('19', '1', NULL, 'mm', 'pending', '0', NULL, NULL, NULL, NULL, '0', 'post', '3', '0', NULL, '2026-01-19 02:53:28', '2026-01-19 02:53:28', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('20', '1', NULL, 'nn', 'pending', '0', NULL, NULL, NULL, '15', '0', 'post', '3', '0', NULL, '2026-01-19 02:56:04', '2026-01-19 02:56:04', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('21', '1', NULL, 'nn', 'pending', '0', NULL, NULL, NULL, '15', '0', 'post', '3', '0', NULL, '2026-01-19 02:56:05', '2026-01-19 02:56:05', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('22', '1', NULL, 'aavvv', 'hidden', '0', '1', '2026-01-19 00:19:03', 'ক', '21', '0', 'post', '3', '0', NULL, '2026-01-19 02:56:28', '2026-01-19 04:19:03', NULL);
INSERT INTO `comments` (`id`, `user_id`, `guest_name`, `content`, `status`, `is_admin_reply`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `parent_id`, `reply_count`, `content_type`, `content_id`, `likes`, `edited_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('23', '1', NULL, 'aavvv', 'pending', '0', NULL, NULL, NULL, '21', '0', 'post', '3', '0', NULL, '2026-01-19 02:56:28', '2026-01-19 02:56:28', NULL);
