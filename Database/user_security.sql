-- ========================================
-- টেবিল / Table: user_security
-- তারিখ / Date: 2026-03-08 02:32:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 1
-- ========================================

CREATE TABLE IF NOT EXISTS `user_security` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_failed_login_at` timestamp NULL DEFAULT NULL,
  `last_failed_login_ip` varchar(45) DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `lock_reason` varchar(255) DEFAULT NULL COMMENT 'Reason for account lock',
  `twofa_enabled` tinyint(1) DEFAULT 0,
  `twofa_method` enum('totp','email','sms') DEFAULT NULL,
  `twofa_secret` varchar(255) DEFAULT NULL COMMENT 'TOTP secret (encrypted)',
  `twofa_backup_codes` longtext DEFAULT NULL COMMENT 'Encrypted backup codes (JSON)',
  `twofa_verified_at` timestamp NULL DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `suspicious_activity_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backup_codes`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `fk_user_security_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-user security settings and 2FA configuration';

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `user_security` (`id`, `user_id`, `failed_login_attempts`, `last_failed_login_at`, `last_failed_login_ip`, `account_locked_until`, `lock_reason`, `twofa_enabled`, `twofa_method`, `twofa_secret`, `twofa_backup_codes`, `twofa_verified_at`, `last_verified_at`, `suspicious_activity_count`, `created_at`, `updated_at`, `backup_codes`) VALUES ('1', '4', '0', NULL, NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, '0', '2026-01-16 18:21:20', '2026-01-16 19:05:36', NULL);
