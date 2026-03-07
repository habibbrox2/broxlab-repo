-- ========================================
-- টেবিল / Table: autoblog_articles
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 5
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_id` int(11) NOT NULL,
  `original_title` varchar(500) DEFAULT NULL,
  `original_url` text DEFAULT NULL,
  `original_content` longtext DEFAULT NULL,
  `original_excerpt` text DEFAULT NULL,
  `original_author` varchar(255) DEFAULT NULL,
  `featured_image` text DEFAULT NULL,
  `original_published_at` datetime DEFAULT NULL,
  `ai_title` text DEFAULT NULL,
  `ai_content` longtext DEFAULT NULL,
  `ai_excerpt` text DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `url` text NOT NULL,
  `content` longtext DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `status` enum('collected','processing','processed','approved','published','failed') DEFAULT 'collected',
  `ai_summary` text DEFAULT NULL,
  `seo_score` int(11) DEFAULT 0,
  `word_count` int(11) DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `simhash` varchar(64) DEFAULT '',
  `content_hash` varchar(32) DEFAULT '',
  `scrape_source` varchar(50) DEFAULT '',
  `scrape_method` varchar(20) DEFAULT 'html',
  `scrape_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scrape_metadata`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_source_url` (`source_id`,`url`(255)),
  KEY `idx_source` (`source_id`),
  KEY `idx_status` (`status`),
  KEY `idx_published` (`published_at`),
  KEY `idx_seo_score` (`seo_score`),
  KEY `idx_simhash` (`simhash`),
  KEY `idx_content_hash` (`content_hash`),
  KEY `idx_url` (`url`(255))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `autoblog_articles` (`id`, `source_id`, `original_title`, `original_url`, `original_content`, `original_excerpt`, `original_author`, `featured_image`, `original_published_at`, `ai_title`, `ai_content`, `ai_excerpt`, `title`, `url`, `content`, `excerpt`, `image_url`, `author`, `published_at`, `status`, `ai_summary`, `seo_score`, `word_count`, `error_message`, `created_at`, `updated_at`, `simhash`, `content_hash`, `scrape_source`, `scrape_method`, `scrape_metadata`) VALUES ('1', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'NYT > World News', 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml', '(No description found)', '(No description found)', '', '', '2026-03-07 04:00:26', 'collected', NULL, '0', '0', NULL, '2026-03-07 04:00:26', NULL, '', '', '', 'html', NULL);
INSERT INTO `autoblog_articles` (`id`, `source_id`, `original_title`, `original_url`, `original_content`, `original_excerpt`, `original_author`, `featured_image`, `original_published_at`, `ai_title`, `ai_content`, `ai_excerpt`, `title`, `url`, `content`, `excerpt`, `image_url`, `author`, `published_at`, `status`, `ai_summary`, `seo_score`, `word_count`, `error_message`, `created_at`, `updated_at`, `simhash`, `content_hash`, `scrape_source`, `scrape_method`, `scrape_metadata`) VALUES ('2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'আজকের খবর | আজকের ব্রেকিং নিউজ বাংলাদেশ', 'https://www.prothomalo.com/collection/latest', 'আজকের সর্বশেষ খবর - বাংলাদেশসহ সারা বিশ্বে ঘটে যাওয়া আজকের সর্বশেষ সকল গুরুত্বপূর্ণ নতুন খবর, ব্রেকিং নিউজ, ছবি, ভিডিও প্রতিবেদন ও প্রধান প্রধান সংবাদ শিরোনাম পেতে ভিজিট করুন প্রথম আলো', 'আজকের সর্বশেষ খবর - বাংলাদেশসহ সারা বিশ্বে ঘটে যাওয়া আজকের সর্বশেষ সকল গুরু?', 'https://media.prothomalo.com/prothomalo-bangla/2021-04/99bb8379-f7b0-49f2-9ef7-368619bdeaa6/2021_04_01.png', '', '2026-03-07 04:17:07', 'collected', NULL, '0', '0', NULL, '2026-03-07 04:17:07', NULL, '', '', '', 'html', NULL);
INSERT INTO `autoblog_articles` (`id`, `source_id`, `original_title`, `original_url`, `original_content`, `original_excerpt`, `original_author`, `featured_image`, `original_published_at`, `ai_title`, `ai_content`, `ai_excerpt`, `title`, `url`, `content`, `excerpt`, `image_url`, `author`, `published_at`, `status`, `ai_summary`, `seo_score`, `word_count`, `error_message`, `created_at`, `updated_at`, `simhash`, `content_hash`, `scrape_source`, `scrape_method`, `scrape_metadata`) VALUES ('3', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'আজকের খবর | আজকের ব্রেকিং নিউজ বাংলাদেশ', 'https://www.prothomalo.com/collection/latest/', 'আজকের সর্বশেষ খবর - বাংলাদেশসহ সারা বিশ্বে ঘটে যাওয়া আজকের সর্বশেষ সকল গুরুত্বপূর্ণ নতুন খবর, ব্রেকিং নিউজ, ছবি, ভিডিও প্রতিবেদন ও প্রধান প্রধান সংবাদ শিরোনাম পেতে ভিজিট করুন প্রথম আলো', 'আজকের সর্বশেষ খবর - বাংলাদেশসহ সারা বিশ্বে ঘটে যাওয়া আজকের সর্বশেষ সকল গুরু?', 'https://media.prothomalo.com/prothomalo-bangla/2021-04/99bb8379-f7b0-49f2-9ef7-368619bdeaa6/2021_04_01.png', '', '2026-03-07 04:17:10', 'collected', NULL, '0', '0', NULL, '2026-03-07 04:17:10', NULL, '', '', '', 'html', NULL);
INSERT INTO `autoblog_articles` (`id`, `source_id`, `original_title`, `original_url`, `original_content`, `original_excerpt`, `original_author`, `featured_image`, `original_published_at`, `ai_title`, `ai_content`, `ai_excerpt`, `title`, `url`, `content`, `excerpt`, `image_url`, `author`, `published_at`, `status`, `ai_summary`, `seo_score`, `word_count`, `error_message`, `created_at`, `updated_at`, `simhash`, `content_hash`, `scrape_source`, `scrape_method`, `scrape_metadata`) VALUES ('4', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'বাংলাদেশের খবর | Bangladesh News Update | Prothom Alo', 'https://www.prothomalo.com/bangladesh', 'বাংলাদেশের আজকের খবর সর্বশেষ সংবাদ শিরোনাম, প্রতিবেদন, বিশ্লেষণ, রাজনীতি, খেলাধুলা, বিনোদন, ব্যবসা-বাণিজ্য, সড়ক, ছবি, ভিডিও খবর দেখতে ভিজিট করুন প্রথম আলো', 'বাংলাদেশের আজকের খবর সর্বশেষ সংবাদ শিরোনাম, প্রতিবেদন, বিশ্লেষণ, রাজনীতি, খ?', 'https://media.prothomalo.com/prothomalo/import/default/2016/03/15/4d3620a7127d4a031a05a962fcc4b253-palo-logo.jpg', '', '2026-03-07 04:17:15', 'collected', NULL, '0', '0', NULL, '2026-03-07 04:17:15', NULL, '', '', '', 'html', NULL);
INSERT INTO `autoblog_articles` (`id`, `source_id`, `original_title`, `original_url`, `original_content`, `original_excerpt`, `original_author`, `featured_image`, `original_published_at`, `ai_title`, `ai_content`, `ai_excerpt`, `title`, `url`, `content`, `excerpt`, `image_url`, `author`, `published_at`, `status`, `ai_summary`, `seo_score`, `word_count`, `error_message`, `created_at`, `updated_at`, `simhash`, `content_hash`, `scrape_source`, `scrape_method`, `scrape_metadata`) VALUES ('5', '3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Just a moment...', 'https://bangla.bdnews24.com/archive', '(No description found)', '(No description found)', '', '', '2026-03-07 05:00:12', 'collected', NULL, '0', '0', NULL, '2026-03-07 05:00:12', NULL, '', '', '', 'html', NULL);
