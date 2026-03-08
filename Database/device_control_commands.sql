-- ========================================
-- টেবিল / Table: device_control_commands
-- তারিখ / Date: 2026-03-08 02:32:39
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `device_control_commands` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `command` varchar(50) NOT NULL,
  `payload` longtext DEFAULT NULL,
  `status` enum('queued','delivered','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
  `requested_by` bigint(20) DEFAULT NULL,
  `response_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_dcc_device_status` (`device_id`,`status`,`created_at`),
  KEY `idx_dcc_created_at` (`created_at`),
  CONSTRAINT `fk_dcc_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

