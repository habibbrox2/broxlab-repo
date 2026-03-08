-- ========================================
-- টেবিল / Table: newsletter_subscribers
-- তারিখ / Date: 2026-03-08 02:32:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 2
-- ========================================

CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `status` enum('active','unsubscribed','bounced') DEFAULT 'active',
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `subscribed_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `newsletter_subscribers` (`id`, `email`, `name`, `status`, `preferences`, `ip_address`, `subscribed_at`, `updated_at`) VALUES ('1', 'hrhabib.hrs@gmail.com', '', 'active', '[]', '103.13.193.101', '2026-01-29 16:33:56', '2026-01-29 12:33:56');
INSERT INTO `newsletter_subscribers` (`id`, `email`, `name`, `status`, `preferences`, `ip_address`, `subscribed_at`, `updated_at`) VALUES ('2', 'hrhabib.etc@gmail.com', '', 'active', '[]', '103.13.193.101', '2026-01-29 17:21:39', '2026-01-29 13:21:39');
