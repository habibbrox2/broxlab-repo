-- ========================================
-- টেবিল / Table: feature_flags
-- তারিখ / Date: 2026-03-08 02:32:39
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 7
-- ========================================

CREATE TABLE IF NOT EXISTS `feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature_key` varchar(50) NOT NULL,
  `enabled` tinyint(1) DEFAULT 0,
  `super_admin_only` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_key` (`feature_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('1', 'sms_gateway', '0', '0', '2026-03-05 12:25:48', '2026-03-05 12:25:48');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('2', 'incoming_sms', '1', '0', '2026-03-05 12:25:48', '2026-03-05 23:14:52');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('3', 'sim_routing', '0', '0', '2026-03-05 12:25:48', '2026-03-05 12:25:48');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('4', 'remote_device', '1', '1', '2026-03-05 12:25:48', '2026-03-05 23:14:58');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('5', 'data_scraper', '0', '0', '2026-03-05 12:25:48', '2026-03-05 23:37:21');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('6', 'pdf_tools', '0', '0', '2026-03-05 12:25:48', '2026-03-05 12:25:48');
INSERT INTO `feature_flags` (`id`, `feature_key`, `enabled`, `super_admin_only`, `created_at`, `updated_at`) VALUES ('7', 'telegram_panel', '0', '0', '2026-03-05 12:25:48', '2026-03-05 12:25:48');
