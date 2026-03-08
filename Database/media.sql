-- ========================================
-- টেবিল / Table: media
-- তারিখ / Date: 2026-03-08 02:32:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 12
-- ========================================

CREATE TABLE IF NOT EXISTS `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` longtext DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `media_type` enum('image','video','audio','document') NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_media_type` (`media_type`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('1', '1', 'Screenshot 2026-01-17 001954', '', '2026/01/Screenshot-2026-01-17-001954-696d2e8dd619b.png', '2026/01/Screenshot-2026-01-17-001954-696d2e8dd619b_thumb.png', 'Screenshot-2026-01-17-001954.png', 'image/png', 'image', '54693', '790', '659', NULL, '2026-01-19 01:03:41', '2026-01-19 01:03:41');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('2', '1', 'IMG-20260118-WA0013', '', '2026/01/IMG-20260118-WA0013-696d586b761fe.jpg', '2026/01/IMG-20260118-WA0013-696d586b761fe_thumb.jpg', 'IMG-20260118-WA0013.jpg', 'image/jpeg', 'image', '51843', '1078', '623', NULL, '2026-01-19 00:02:19', '2026-01-19 00:02:19');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('3', '1', 'bd-feature-performance-that-powers-your-day-549757775', '', '2026/01/bd-feature-performance-that-powers-your-day-549757775-69718238dc86c.webp', '2026/01/bd-feature-performance-that-powers-your-day-549757775-69718238dc86c_thumb.webp', 'bd-feature-performance-that-powers-your-day-549757775.webp', 'image/webp', 'image', '74856', '720', '392', NULL, '2026-01-22 03:49:44', '2026-01-22 03:49:44');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('4', '1', 'bd-feature--549757751', '', '2026/01/bd-feature--549757751-6971824d2affc.jpeg', '2026/01/bd-feature--549757751-6971824d2affc_thumb.jpeg', 'bd-feature--549757751.jpeg', 'image/jpeg', 'image', '55653', '720', '458', NULL, '2026-01-22 03:50:05', '2026-01-22 03:50:05');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('5', '1', 'bd-feature-triple-cameras-to-capture-more-details-549757779', '', '2026/01/bd-feature-triple-cameras-to-capture-more-details-549757779-69718262901dd.webp', '2026/01/bd-feature-triple-cameras-to-capture-more-details-549757779-69718262901dd_thumb.webp', 'bd-feature-triple-cameras-to-capture-more-details-549757779.webp', 'image/webp', 'image', '16674', '720', '283', NULL, '2026-01-22 03:50:26', '2026-01-22 03:50:26');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('6', '0', '78fc237623d7c485_1769089793_5987.png', NULL, '/uploads/mobiles/78fc237623d7c485_1769089793_5987.png', NULL, 'Screenshot 2025-12-06 213505.png', 'image/png', 'image', '18215', NULL, NULL, NULL, '2026-01-22 19:49:53', '2026-01-22 19:49:53');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('7', '0', '0eea5fde1df452c4_1769089909_3788.png', NULL, '/uploads/mobiles/0eea5fde1df452c4_1769089909_3788.png', NULL, 'Screenshot 2025-12-06 213505.png', 'image/png', 'image', '18215', NULL, NULL, NULL, '2026-01-22 19:51:49', '2026-01-22 19:51:49');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('8', '0', 'cc982f23ec5b00d9_1769091478_2014.jpg', NULL, '/uploads/mobiles/cc982f23ec5b00d9_1769091478_2014.jpg', NULL, 'Enam Signature 2023.jpg', 'image/jpeg', 'image', '11094', NULL, NULL, NULL, '2026-01-22 20:17:58', '2026-01-22 20:17:58');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('9', '0', 'f6c57c331b8336f3_1769091973_6764.jpg', NULL, '/uploads/mobiles/f6c57c331b8336f3_1769091973_6764.jpg', NULL, 'anamul 300px.JPG', 'image/jpeg', 'image', '66514', NULL, NULL, NULL, '2026-01-22 20:26:13', '2026-01-22 20:26:13');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('10', '1', 'whatsapp_image_2025_10_30_at_2_15_39_pm_1769852736_4190.jpeg', NULL, '/uploads/content/whatsapp_image_2025_10_30_at_2_15_39_pm_1769852736_4190.jpeg', NULL, 'WhatsApp Image 2025-10-30 at 2.15.39 PM.jpeg', 'image/jpeg', 'image', '65249', NULL, NULL, NULL, '2026-01-31 11:45:36', '2026-01-31 11:45:36');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('11', '1', 'whatsapp_image_2025_10_30_at_2_15_39_pm_1769852935_6984.jpeg', NULL, '/uploads/content/whatsapp_image_2025_10_30_at_2_15_39_pm_1769852935_6984.jpeg', NULL, 'WhatsApp Image 2025-10-30 at 2.15.39 PM.jpeg', 'image/jpeg', 'image', '65249', NULL, NULL, NULL, '2026-01-31 11:48:55', '2026-01-31 11:48:55');
INSERT INTO `media` (`id`, `user_id`, `title`, `description`, `file_path`, `thumbnail_path`, `original_name`, `mime_type`, `media_type`, `file_size`, `width`, `height`, `deleted_at`, `created_at`, `updated_at`) VALUES ('12', '1', 'whatsapp_image_2025_10_30_at_2_15_39_pm_1769853548_4484.jpeg', NULL, '/uploads/content/whatsapp_image_2025_10_30_at_2_15_39_pm_1769853548_4484.jpeg', NULL, 'WhatsApp Image 2025-10-30 at 2.15.39 PM.jpeg', 'image/jpeg', 'image', '65249', NULL, NULL, NULL, '2026-01-31 11:59:08', '2026-01-31 11:59:08');
