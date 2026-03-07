-- ========================================
-- টেবিল / Table: autoblog_settings
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 15
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('ai_endpoint', '', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('ai_key', '', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('ai_model', 'gpt-4o-mini', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('autoblog_enabled', '1', '2026-03-07 02:58:43');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('auto_approve_threshold', '75', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('auto_collect', '0', '2026-03-07 02:58:44');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('auto_process', '0', '2026-03-07 02:58:44');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('auto_publish', '0', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('content_max_words', '5000', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('content_min_words', '300', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('default_category', '', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('duplicate_check', '1', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('image_download', '1', '2026-03-07 01:54:29');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('max_articles_per_source', '10', '2026-03-07 02:58:45');
INSERT INTO `autoblog_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES ('publish_status', 'published', '2026-03-07 02:58:45');
