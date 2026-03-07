-- ========================================
-- টেবিল / Table: devices
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_name` varchar(100) NOT NULL,
  `device_model` varchar(100) DEFAULT NULL,
  `api_token` varchar(64) NOT NULL,
  `battery_level` tinyint(4) DEFAULT NULL,
  `is_charging` tinyint(1) DEFAULT 0,
  `last_sync` timestamp NULL DEFAULT NULL,
  `status` enum('online','offline','disabled') DEFAULT 'offline',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

