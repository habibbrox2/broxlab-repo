-- ========================================
-- টেবিল / Table: app_settings
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 1
-- ========================================

CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(255) NOT NULL,
  `site_logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL,
  `default_language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `notifications_maintenance_message` varchar(1024) DEFAULT NULL,
  `notification_topics_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_topics_json`)),
  `public_nav_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_address` text DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `social_facebook` varchar(255) DEFAULT NULL,
  `social_twitter` varchar(255) DEFAULT NULL,
  `social_instagram` varchar(255) DEFAULT NULL,
  `social_youtube` varchar(255) DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(5) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT NULL,
  `mail_from_address` varchar(255) DEFAULT NULL,
  `mail_from_name` varchar(255) DEFAULT NULL,
  `allow_user_registration` tinyint(1) DEFAULT 1,
  `require_email_verification` tinyint(1) DEFAULT 1,
  `enable_2fa` tinyint(1) DEFAULT 0,
  `max_login_attempts` int(3) DEFAULT 5,
  `currency_code` varchar(10) DEFAULT 'USD',
  `currency_symbol` varchar(10) DEFAULT '$',
  `payment_gateway` varchar(50) DEFAULT NULL,
  `payment_mode` varchar(10) DEFAULT 'sandbox',
  `google_analytics_id` varchar(50) DEFAULT NULL,
  `recaptcha_site_key` varchar(100) DEFAULT NULL,
  `recaptcha_secret_key` varchar(100) DEFAULT NULL,
  `telegram_bot_token` varchar(255) DEFAULT NULL,
  `telegram_webhook_secret` varchar(255) DEFAULT NULL,
  `enable_cache` tinyint(1) DEFAULT 1,
  `cache_driver` varchar(50) DEFAULT 'file',
  `cache_lifetime` int(11) DEFAULT 3600,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `storage_retention_seconds` int(11) DEFAULT 604800 COMMENT 'max age (seconds) for temp/log files',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `app_settings` (`id`, `site_name`, `site_logo`, `favicon`, `default_language`, `timezone`, `maintenance_mode`, `notifications_enabled`, `notifications_maintenance_message`, `notification_topics_json`, `public_nav_json`, `contact_email`, `contact_phone`, `contact_address`, `meta_title`, `meta_description`, `meta_keywords`, `social_facebook`, `social_twitter`, `social_instagram`, `social_youtube`, `smtp_host`, `smtp_port`, `smtp_username`, `smtp_password`, `smtp_encryption`, `mail_from_address`, `mail_from_name`, `allow_user_registration`, `require_email_verification`, `enable_2fa`, `max_login_attempts`, `currency_code`, `currency_symbol`, `payment_gateway`, `payment_mode`, `google_analytics_id`, `recaptcha_site_key`, `recaptcha_secret_key`, `telegram_bot_token`, `telegram_webhook_secret`, `enable_cache`, `cache_driver`, `cache_lifetime`, `created_at`, `updated_at`, `storage_retention_seconds`) VALUES ('1', 'BroxLab', '', '/uploads/logo/favicon-1772727914-1317.ico', 'en', 'Asia/Bishkek', '0', '1', NULL, '[{\"slug\": \"system\", \"name\": \"System\", \"default_enabled\": 1}, {\"slug\": \"promo\", \"name\": \"Promo\", \"default_enabled\": 0}, {\"slug\": \"alert\", \"name\": \"Alert\", \"default_enabled\": 1}]', '[{\"label\":\"Home\",\"url\":\"/\",\"icon\":\"bi-house-door-fill\",\"match\":\"/\",\"enabled\":true,\"order\":10},{\"label\":\"Mobiles\",\"url\":\"/mobiles\",\"icon\":\"bi-phone-fill\",\"match\":\"/mobiles\",\"enabled\":true,\"order\":20},{\"label\":\"Articles\",\"url\":\"/posts\",\"icon\":\"bi-newspaper\",\"match\":\"/posts\",\"enabled\":true,\"order\":30},{\"label\":\"Services\",\"url\":\"/services\",\"icon\":\"bi-award-fill\",\"match\":\"/services\",\"enabled\":true,\"order\":40}]', 'hrhabib.admin@broxlab.online', '+8801941159555', 'Khararchar, Rowail, Dhamrai, Dhaka - 1822', 'BroxLab | টেক রিভিউ, অনলাইন ইনকাম ও ক্যারিয়ার টিপস', 'BroxLab-এ পান লেটেস্ট স্মার্টফোন স্পেসিফিকেশন, গ্যাজেট রিভিউ এবং সঠিক অনলাইন ক্যারিয়ার গাইডলাইন। আমরা প্রযুক্তি, ব্যাংকিং সেবা এবং চাকরির খবরের সঠিক তথ্য পৌঁছে দিই আপনার দোরগোড়ায়। আজই ভিজিট করুন!', 'BroxLab, Tech News Bangladesh, Smartphone Reviews, Online Income Guideline, Career Tips, Gadget News, Mobile Specification, ব্যাংকিং সেবা, চাকরির খবর', '', '', '', '', 'smtp.gmail.com', '587', 'hrhabib.etc@gmail.com', 'ggaa szwc zlbq islg', 'tls', 'admin@broxbhai.dgtts.org', 'Broxbhai', '1', '1', '0', '5', 'BDT', '৳', '', 'sandbox', '', '', NULL, '8623489626:AAGzJHoot-CFH5tRldC7_OC6Zup5GBnbGeQ', 'oK6aeW2XcHRq38kMxQYQSXuyM95OqGHvLj4YjWe-1sZNnPht', '0', 'file', '3600', '2025-08-19 22:37:46', '2026-03-05 23:08:47', '604800');
