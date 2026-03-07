-- ========================================
-- টেবিল / Table: visitors
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 8
-- ========================================

CREATE TABLE IF NOT EXISTS `visitors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `first_visit` datetime DEFAULT NULL,
  `last_visit` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('1', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:147.0) Gecko/20100101 Firefox/147.0', '2026-01-28 02:45:36', '2026-01-28 15:19:57');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.108.2 Chrome/142.0.7444.235 Electron/39.2.7 Safari/537.36', '2026-01-28 04:03:34', '2026-01-28 04:11:42');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('3', '103.13.193.100', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Mobile Safari/537.36', '2026-01-28 04:21:15', '2026-01-28 05:25:57');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('4', '205.210.31.87', 'Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity', '2026-01-28 05:47:23', '2026-01-28 05:47:23');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('5', '205.210.31.90', 'Hello from Palo Alto Networks, find out more about our scans in https://docs-cortex.paloaltonetworks.com/r/1/Cortex-Xpanse/Scanning-activity', '2026-01-28 06:50:38', '2026-01-28 06:50:38');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('6', '74.7.228.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36; compatible; OAI-SearchBot/1.3; robots.txt; +https://openai.com/searchbot', '2026-01-28 09:57:11', '2026-01-28 09:57:11');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('7', '44.196.29.182', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/138.0.0.0 Safari/537.36', '2026-01-28 13:49:01', '2026-01-28 13:49:01');
INSERT INTO `visitors` (`id`, `ip_address`, `user_agent`, `first_visit`, `last_visit`) VALUES ('8', '54.162.31.194', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36', '2026-01-28 15:01:19', '2026-01-28 15:01:19');
