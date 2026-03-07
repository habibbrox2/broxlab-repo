-- ========================================
-- টেবিল / Table: autoblog_proxy_pool
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 1
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_proxy_pool` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proxy_host` varchar(255) NOT NULL,
  `proxy_port` int(11) NOT NULL DEFAULT 8080,
  `proxy_username` varchar(255) DEFAULT '',
  `proxy_password` varchar(255) DEFAULT '',
  `proxy_type` varchar(20) DEFAULT 'http' COMMENT 'http, https, socks5, residential',
  `provider` varchar(50) DEFAULT 'custom' COMMENT 'bright_data, scraper_api, smart_proxy, custom',
  `is_active` tinyint(1) DEFAULT 1,
  `last_used` datetime DEFAULT NULL,
  `success_count` int(11) DEFAULT 0,
  `failure_count` int(11) DEFAULT 0,
  `avg_response_time` decimal(10,3) DEFAULT 0.000,
  `country` varchar(50) DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_provider` (`provider`),
  KEY `idx_last_used` (`last_used`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `autoblog_proxy_pool` (`id`, `proxy_host`, `proxy_port`, `proxy_username`, `proxy_password`, `proxy_type`, `provider`, `is_active`, `last_used`, `success_count`, `failure_count`, `avg_response_time`, `country`, `created_at`, `updated_at`) VALUES ('1', 'proxy.example.com', '8080', '', '', 'http', 'custom', '0', NULL, '0', '0', '0.000', 'bd', '2026-03-07 06:28:25', '2026-03-07 06:28:25');
