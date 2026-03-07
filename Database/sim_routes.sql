-- ========================================
-- টেবিল / Table: sim_routes
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 1
-- ========================================

CREATE TABLE IF NOT EXISTS `sim_routes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(100) NOT NULL,
  `match_type` enum('sender','keyword','any') NOT NULL DEFAULT 'any',
  `match_value` varchar(200) DEFAULT NULL COMMENT 'Sender number or keyword to match; NULL = any',
  `action` enum('forward_telegram','reply_sms','both') NOT NULL DEFAULT 'forward_telegram',
  `device_id` int(11) DEFAULT NULL COMMENT 'Device to use for reply_sms action',
  `sim_slot` tinyint(4) DEFAULT 1,
  `reply_message` text DEFAULT NULL COMMENT 'Auto-reply message text',
  `enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  CONSTRAINT `sim_routes_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `sim_routes` (`id`, `label`, `match_type`, `match_value`, `action`, `device_id`, `sim_slot`, `reply_message`, `enabled`, `created_at`, `updated_at`) VALUES ('1', 'Forward All to Telegram', 'any', NULL, 'forward_telegram', NULL, '1', NULL, '0', '2026-03-05 22:32:08', '2026-03-05 22:32:08');
