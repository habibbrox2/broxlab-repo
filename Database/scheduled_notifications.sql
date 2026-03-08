-- ========================================
-- টেবিল / Table: scheduled_notifications
-- তারিখ / Date: 2026-03-08 02:32:42
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `scheduled_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL COMMENT 'Admin who scheduled this notification',
  `title` varchar(255) NOT NULL COMMENT 'Notification title',
  `body` text NOT NULL COMMENT 'Notification message body',
  `scheduled_at` datetime NOT NULL COMMENT 'Scheduled time (UTC)',
  `scheduled_at_user_tz` datetime DEFAULT NULL COMMENT 'Scheduled time in user timezone',
  `user_timezone` varchar(50) DEFAULT 'UTC' COMMENT 'User timezone for display',
  `recipient_type` varchar(50) DEFAULT 'all' COMMENT 'all, guest, user, role, permission, specific',
  `recipient_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Specific user IDs if type is specific' CHECK (json_valid(`recipient_ids`)),
  `channels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of channels: push, email, in_app' CHECK (json_valid(`channels`)),
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional JSON payload' CHECK (json_valid(`data`)),
  `status` varchar(50) DEFAULT 'scheduled' COMMENT 'scheduled, sent, failed, cancelled',
  `paused` tinyint(1) DEFAULT 0,
  `paused_at` datetime DEFAULT NULL,
  `pause_reason` varchar(255) DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `suppress_followups` tinyint(1) DEFAULT 0,
  `sent_notification_id` int(11) DEFAULT NULL COMMENT 'Corresponding notification_id when sent',
  `error_message` text DEFAULT NULL COMMENT 'Error message if failed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When actually sent',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_status` (`status`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_created_at` (`created_at`),
  KEY `scheduled_notifications_ibfk_notification_id` (`sent_notification_id`),
  CONSTRAINT `scheduled_notifications_ibfk_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `scheduled_notifications_ibfk_notification_id` FOREIGN KEY (`sent_notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

