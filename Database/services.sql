-- ========================================
-- টেবিল / Table: services
-- তারিখ / Date: 2026-03-07 01:28:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 5
-- ========================================

CREATE TABLE IF NOT EXISTS `services` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` longtext DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `is_premium` tinyint(1) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `redirect_url` varchar(500) DEFAULT NULL,
  `requires_approval` tinyint(1) DEFAULT 1,
  `auto_approve` tinyint(1) DEFAULT 0,
  `requires_documents` tinyint(1) DEFAULT 0,
  `max_applications_per_user` int(11) DEFAULT 1,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `form_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`form_fields`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_premium` (`is_premium`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `services` (`id`, `name`, `description`, `slug`, `category`, `icon`, `status`, `is_premium`, `price`, `redirect_url`, `requires_approval`, `auto_approve`, `requires_documents`, `max_applications_per_user`, `metadata`, `form_fields`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'Birth Certificate জন্ম নিবন্ধন', 'রোরির', 'birth-certificate', 'Online Sheba', NULL, 'active', '0', '0.00', NULL, '1', '0', '0', '1', NULL, NULL, '2026-01-29 22:08:42', '2026-01-29 22:08:42', NULL);
INSERT INTO `services` (`id`, `name`, `description`, `slug`, `category`, `icon`, `status`, `is_premium`, `price`, `redirect_url`, `requires_approval`, `auto_approve`, `requires_documents`, `max_applications_per_user`, `metadata`, `form_fields`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'bfgb আমি কেন রা', 'ননান', 'bfgb-ami-ken-ra', '', '', 'active', '0', '0.00', NULL, '0', '0', '0', '1', '[]', '[]', '2026-01-30 02:07:41', '2026-01-30 02:07:41', NULL);
INSERT INTO `services` (`id`, `name`, `description`, `slug`, `category`, `icon`, `status`, `is_premium`, `price`, `redirect_url`, `requires_approval`, `auto_approve`, `requires_documents`, `max_applications_per_user`, `metadata`, `form_fields`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', 'bfgb আমি কেন রা', 'hb  h', 'bfgb-ami-ken-ra-1', '', '', 'archived', '0', '0.00', NULL, '0', '0', '0', '1', '[]', NULL, '2026-01-30 02:19:20', '2026-01-30 20:20:43', NULL);
INSERT INTO `services` (`id`, `name`, `description`, `slug`, `category`, `icon`, `status`, `is_premium`, `price`, `redirect_url`, `requires_approval`, `auto_approve`, `requires_documents`, `max_applications_per_user`, `metadata`, `form_fields`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', 'আমি', '&lt;p&gt;রারিার&lt;/p&gt;', 'ami', '', '', 'active', '1', '0.00', NULL, '0', '0', '0', '1', '{\"\\u0995\":\"\\u0996\\u09bf\"}', NULL, '2026-01-30 17:56:25', '2026-01-30 17:56:25', NULL);
INSERT INTO `services` (`id`, `name`, `description`, `slug`, `category`, `icon`, `status`, `is_premium`, `price`, `redirect_url`, `requires_approval`, `auto_approve`, `requires_documents`, `max_applications_per_user`, `metadata`, `form_fields`, `created_at`, `updated_at`, `deleted_at`) VALUES ('8', 'অনলাইন জন্ম নিবন্ধন', 'Hhh', 'onlain-jn-m-nibn-dhn', NULL, '', 'active', '1', '1200.00', NULL, '1', '0', '1', '1', '{\"KI\":\"A\"}', NULL, '2026-02-26 16:56:50', '2026-03-02 02:07:08', NULL);
