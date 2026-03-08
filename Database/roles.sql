-- ========================================
-- টেবিল / Table: roles
-- তারিখ / Date: 2026-03-08 02:32:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 4
-- ========================================

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT NULL,
  `ranking` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_super_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `is_super_admin` (`is_super_admin`),
  KEY `idx_ranking` (`ranking`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `roles` (`id`, `name`, `ranking`, `description`, `is_super_admin`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'super_admin', '100', 'Super Administrator - Full System Access', '1', '2025-12-24 01:08:35', '2026-01-15 20:20:27', NULL);
INSERT INTO `roles` (`id`, `name`, `ranking`, `description`, `is_super_admin`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'admin', '90', 'Administrator - Manage Content & Users', '0', '2025-12-24 01:08:35', '2026-01-15 20:20:27', NULL);
INSERT INTO `roles` (`id`, `name`, `ranking`, `description`, `is_super_admin`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', 'moderator', '70', 'Moderator - Manage Content', '0', '2025-12-24 01:08:35', '2026-01-15 20:20:27', NULL);
INSERT INTO `roles` (`id`, `name`, `ranking`, `description`, `is_super_admin`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', 'user', '50', 'Regular User - Standard Access', '0', '2025-12-24 01:08:35', '2026-01-15 20:20:27', NULL);
