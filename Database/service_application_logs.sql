-- ========================================
-- টেবিল / Table: service_application_logs
-- তারিখ / Date: 2026-03-08 02:32:42
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 11
-- ========================================

CREATE TABLE IF NOT EXISTS `service_application_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `action_type` enum('created','status_changed','approved','rejected','processing','activated','note_added','edited') DEFAULT 'status_changed',
  `old_status` enum('pending','processing','approved','rejected') DEFAULT NULL,
  `new_status` enum('pending','processing','approved','rejected') DEFAULT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `reason` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('5', '11', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-03-02 02:08:36');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('6', '12', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.25.250.130', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-02 16:13:58');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('7', '13', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.25.250.130', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-02 18:34:14');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('8', '14', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.25.250.130', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-02 18:48:04');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('9', '15', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 23:53:04');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('10', '16', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 23:53:24');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('11', '17', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-03-03 00:10:20');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('12', '18', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.25.250.128', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-03 23:00:12');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('13', '19', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.25.250.129', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-04 23:42:08');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('14', '20', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 14:01:50');
INSERT INTO `service_application_logs` (`id`, `application_id`, `user_id`, `action`, `action_type`, `old_status`, `new_status`, `changes`, `reason`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES ('15', '21', '15', 'created', 'created', NULL, NULL, NULL, NULL, 'Application submitted', '103.13.193.101', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-05 22:28:38');
