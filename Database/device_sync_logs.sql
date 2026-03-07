-- ========================================
-- টেবিল / Table: device_sync_logs
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 2
-- ========================================

CREATE TABLE IF NOT EXISTS `device_sync_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'User who performed the action',
  `notification_id` int(11) DEFAULT NULL,
  `device_id` varchar(255) NOT NULL COMMENT 'Unique device identifier',
  `device_type` varchar(50) DEFAULT NULL COMMENT 'Web, iOS, Android, etc',
  `action` varchar(50) DEFAULT 'read' COMMENT 'read, unread, dismissed, clicked',
  `action_timestamp_utc` datetime DEFAULT NULL COMMENT 'When the action occurred (UTC)',
  `client_dedup_id` varchar(255) DEFAULT NULL COMMENT 'Client deduplication ID',
  `synced_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'When logged to server',
  `is_duplicate` tinyint(1) DEFAULT 0 COMMENT 'Whether marked as duplicate',
  `is_synced` tinyint(1) DEFAULT 0 COMMENT 'Whether synced to other devices',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_notification_id` (`notification_id`),
  KEY `idx_action` (`action`),
  KEY `idx_synced_at` (`synced_at`),
  KEY `idx_is_synced` (`is_synced`),
  KEY `idx_user_device` (`user_id`,`device_id`),
  KEY `idx_user_notification_device` (`user_id`,`notification_id`,`device_id`),
  KEY `idx_user_device_action` (`user_id`,`device_id`,`action`),
  KEY `idx_dedup_check` (`client_dedup_id`,`notification_id`),
  CONSTRAINT `device_sync_logs_ibfk_notification_id` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `device_sync_logs_ibfk_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `device_sync_logs` (`id`, `user_id`, `notification_id`, `device_id`, `device_type`, `action`, `action_timestamp_utc`, `client_dedup_id`, `synced_at`, `is_duplicate`, `is_synced`, `created_at`, `updated_at`) VALUES ('1', '1', NULL, '1770817636986-slg1ax9ac', 'web', 'sync', '2026-02-16 13:05:14', NULL, '2026-02-16 19:05:14', '0', '0', '2026-02-16 19:05:14', '2026-02-16 19:05:14');
INSERT INTO `device_sync_logs` (`id`, `user_id`, `notification_id`, `device_id`, `device_type`, `action`, `action_timestamp_utc`, `client_dedup_id`, `synced_at`, `is_duplicate`, `is_synced`, `created_at`, `updated_at`) VALUES ('2', '1', NULL, '1770817636986-slg1ax9ac', 'web', 'sync', '2026-02-16 13:05:44', NULL, '2026-02-16 19:05:44', '0', '0', '2026-02-16 19:05:44', '2026-02-16 19:05:44');
