-- ========================================
-- টেবিল / Table: contact_messages
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 2
-- ========================================

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPv4 / IPv6',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser info',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `deleted_at`, `ip_address`, `user_agent`, `created_at`) VALUES ('1', 'Zara Baylebridge', 'domains@search-broxlab.online', 'broxlab.online', 'Hello,\r\n\r\nList your broxlab.online website in Google Search Index and have it appear in Web Search Results!\r\n\r\nSubmit broxlab.online at https://searchregister.info', '1', NULL, '204.114.78.139', NULL, '2026-02-02 20:59:19');
INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `deleted_at`, `ip_address`, `user_agent`, `created_at`) VALUES ('2', 'Candida Saucier', 'domains@search-broxlab.online', 'broxlab.online', 'Hello,\r\n\r\nList your broxlab.online website in Google Search Index and have it appear in Web Search Results!\r\n\r\nSubmit broxlab.online at https://searchregister.info', '1', NULL, '161.115.239.142', NULL, '2026-02-02 23:34:51');
