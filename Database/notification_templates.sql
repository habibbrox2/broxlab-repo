-- ========================================
-- টেবিল / Table: notification_templates
-- তারিখ / Date: 2026-03-08 02:32:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 8
-- ========================================

CREATE TABLE IF NOT EXISTS `notification_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Display name',
  `slug` varchar(100) NOT NULL COMMENT 'Unique identifier (e.g., welcome_notification)',
  `title` varchar(255) NOT NULL COMMENT 'Notification title template',
  `body` longtext NOT NULL COMMENT 'Notification message template (supports {{VARIABLES}})',
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Available template variables as JSON',
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Supported channels: ["in_app", "fcm", "email", "sms"]',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1 COMMENT '0 = Disabled, 1 = Active',
  `icon` varchar(50) DEFAULT NULL COMMENT 'Icon class for UI (e.g., mdi-bell, mdi-check)',
  `color` varchar(20) DEFAULT NULL COMMENT 'Color code for UI',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'Welcome Notification', 'welcome_notification', 'স্বাগতম {{USER_NAME}}!', 'আপনি সফলভাবে {{APP_NAME}} এ যোগদান করেছেন। প্রোফাইল সম্পূর্ণ করতে শুরু করুন।', '{\"APP_NAME\":\"\",\"USER_NAME\":\"\"}', '[\"in_app\",\"fcm\",\"email\"]', 'নতুন ব্যবহারকারী স্বাগত বিজ্ঞপ্তি', '1', 'mdi-hand-wave', '#0d6efd', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'Service Application Received', 'service_app_received', '✅ আবেদন গৃহীত হয়েছে', '{{SERVICE_NAME}} সেবার জন্য আপনার আবেদন আমরা পেয়েছি। আমরা শীঘ্রই আপডেট দেব।', '{\"SERVICE_NAME\":\"\"}', '[\"in_app\",\"fcm\",\"email\"]', 'সেবা আবেদন রসিদ', '1', 'mdi-check-circle', '#198754', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', 'Service Approved', 'service_approved', '🎉 আবেদন অনুমোদিত হয়েছে', '{{SERVICE_NAME}} সেবার জন্য আপনার আবেদন অনুমোদিত হয়েছে। এখন কাজ শুরু করতে পারেন।', '{\"SERVICE_NAME\":\"\",\"ACTION_URL\":\"/services\"}', '[\"in_app\",\"fcm\",\"email\"]', 'সেবা অনুমোদন বিজ্ঞপ্তি', '1', 'mdi-star-circle', '#ffc107', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', 'Service Rejected', 'service_rejected', '❌ আবেদন বাতিল হয়েছে', 'দুঃখিত, {{SERVICE_NAME}} সেবার জন্য আপনার আবেদন অনুমোদিত হয়নি। {{REASON}}', '{\"SERVICE_NAME\":\"\",\"REASON\":\"\"}', '[\"in_app\",\"fcm\",\"email\"]', 'সেবা বাতিল বিজ্ঞপ্তি', '1', 'mdi-alert-circle', '#dc3545', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', 'Payment Received', 'payment_received', '💳 পেমেন্ট সফল', '{{AMOUNT}} টাকা আপনার পেমেন্ট সফলভাবে প্রসেস হয়েছে। রসিদ: {{RECEIPT_ID}}', '{\"AMOUNT\":\"\",\"RECEIPT_ID\":\"\"}', '[\"in_app\",\"fcm\",\"email\"]', 'পেমেন্ট সফলতা বিজ্ঞপ্তি', '1', 'mdi-cash-check', '#0d6efd', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', 'Comment Reply', 'comment_reply', '💬 নতুন উত্তর', '{{REPLIER_NAME}} আপনার মন্তব্যে উত্তর দিয়েছেন: \"{{REPLY_TEXT}}\"', '{\"REPLIER_NAME\":\"\",\"REPLY_TEXT\":\"\",\"ACTION_URL\":\"\"}', '[\"in_app\",\"fcm\"]', 'মন্তব্য উত্তর বিজ্ঞপ্তি', '1', 'mdi-comment-multiple', '#6f42c1', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', 'Post Liked', 'post_liked', '❤️ আপনার পোস্ট পছন্দ হয়েছে', '{{LIKER_NAME}} আপনার পোস্ট পছন্দ করেছেন।', '{\"LIKER_NAME\":\"\",\"POST_TITLE\":\"\"}', '[\"in_app\",\"fcm\"]', 'পোস্ট লাইক বিজ্ঞপ্তি', '1', 'mdi-heart', '#dc3545', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
INSERT INTO `notification_templates` (`id`, `name`, `slug`, `title`, `body`, `variables`, `channels`, `description`, `is_active`, `icon`, `color`, `created_by`, `updated_by`, `created_at`, `updated_at`, `deleted_at`) VALUES ('8', 'Promotion Announcement', 'promotion_announcement', '🎁 বিশেষ অফার', 'এখনই {{DISCOUNT}}% পর্যন্ত ছাড় পান। {{OFFER_DETAILS}}', '{\"DISCOUNT\":\"\",\"OFFER_DETAILS\":\"\",\"PROMO_CODE\":\"\"}', '[\"in_app\",\"fcm\",\"email\"]', 'প্রচার ঘোষণা বিজ্ঞপ্তি', '1', 'mdi-gift', '#0dcaf0', NULL, NULL, '2026-03-03 02:36:36', '2026-03-03 02:36:36', NULL);
