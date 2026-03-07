-- ========================================
-- টেবিল / Table: autoblog_sources
-- তারিখ / Date: 2026-03-07 01:28:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 8
-- ========================================

CREATE TABLE IF NOT EXISTS `autoblog_sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `type` enum('rss','html','api','scrape') DEFAULT 'rss',
  `category_id` int(11) DEFAULT NULL,
  `selectors` text DEFAULT NULL,
  `fetch_interval` int(11) DEFAULT 3600,
  `selector_title` varchar(500) DEFAULT '',
  `selector_content` varchar(500) DEFAULT '',
  `selector_image` varchar(500) DEFAULT '',
  `selector_excerpt` varchar(500) DEFAULT '',
  `selector_date` varchar(500) DEFAULT '',
  `selector_author` varchar(500) DEFAULT '',
  `is_active` tinyint(1) DEFAULT 1,
  `last_fetched_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `selector_list_container` varchar(500) DEFAULT '',
  `selector_list_item` varchar(500) DEFAULT '',
  `selector_list_title` varchar(500) DEFAULT '',
  `selector_list_date` varchar(500) DEFAULT '',
  `selector_list_url` varchar(255) DEFAULT '',
  `pagination_type` varchar(50) DEFAULT 'none',
  `pagination_selector` varchar(255) DEFAULT '',
  `pagination_pattern` varchar(255) DEFAULT '',
  `max_pages` int(11) DEFAULT 10,
  `proxy_enabled` tinyint(1) DEFAULT 0,
  `proxy_provider` varchar(50) DEFAULT '',
  `proxy_config` text DEFAULT NULL,
  `min_delay` int(11) DEFAULT 1000,
  `max_delay` int(11) DEFAULT 3000,
  `last_fetch` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_last_fetched` (`last_fetched_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('1', 'Pothom Alo Latest', 'https://www.prothomalo.com/collection/latest', 'scrape', '0', NULL, '600', 'h1.IiRps, h1[data-title-0]', '.story-element.story-element-text', 'meta[property=&quot;og:image&quot;]', 'meta[name=&quot;description&quot;]', 'time[datetime]', '.author-name, .contributor-name', '1', '2026-03-07 05:45:18', '2026-03-07 02:30:34', 'body', '.wide-story-card, .news_with_item', 'h3.headline-title a.title-link', 'time.published-at, time.published-time', '', 'none', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('2', 'NY Times World', 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml', 'rss', '0', NULL, '600', '', '', '', '', '', '', '1', '2026-03-07 05:45:20', '2026-03-07 03:57:21', '', '', '', '', '', 'none', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('3', 'BdNews24', 'https://bangla.bdnews24.com/archive', 'scrape', '6', NULL, '600', '//head/title', '//div[contains(@class, &amp;quot;content&amp;quot;)]', '', '', '', '', '1', '2026-03-07 05:45:22', '2026-03-07 04:59:43', '//body, .container', '//h5', '//a', '', '', 'none', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('4', 'Prothom Alo Latest', 'https://www.prothomalo.com/', 'html', '1', NULL, '1800', '.story-title h1', '.story-element-text', '.Td4Ec img', '.lead-paragraph', 'time[datetime]', '.author-name', '1', NULL, '2026-03-07 06:28:25', '.wide-story-card, .news_with_item', '.news-item', 'h3 a', 'time', '', 'link', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('5', 'BD News 24 Bengali', 'https://bangla.bdnews24.com/', 'html', '1', NULL, '1800', '.details-title h1', '#contentDetails', '.details-img img', '.details-title h2', '.pub-up', '.author', '1', NULL, '2026-03-07 06:28:25', '.col-md-3', '.SubCat-wrapper', 'h5 a', '.publish-time', '', 'link', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('6', 'BBC Bangla', 'https://www.bbc.com/bengali', 'html', '1', NULL, '1800', 'h1', '.article-body', '.article-hero-image img', '.article-intro', 'time[datetime]', '.byline', '1', NULL, '2026-03-07 06:28:25', '.bbc-1kr7d6c', '.bbc-1kr7d6c', 'h3 a', 'time', '', 'link', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('7', 'Daily Star Bangla', 'https://www.thedailystar.net/bangla', 'html', '1', NULL, '1800', 'h1', '.field-name-body', '.field-name-field-image img', '.field-name-field-lead', '.date', '.username', '1', NULL, '2026-03-07 06:28:25', '.view-content', '.views-row', 'h2 a', '.date', '', 'link', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
INSERT INTO `autoblog_sources` (`id`, `name`, `url`, `type`, `category_id`, `selectors`, `fetch_interval`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `is_active`, `last_fetched_at`, `created_at`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_date`, `selector_list_url`, `pagination_type`, `pagination_selector`, `pagination_pattern`, `max_pages`, `proxy_enabled`, `proxy_provider`, `proxy_config`, `min_delay`, `max_delay`, `last_fetch`) VALUES ('8', 'NTV Bangla', 'https://www.ntvbd.com/bangla', 'html', '1', NULL, '1800', 'h1', '.news-details', '.news-image img', '.news-summery', '.date', '.author', '1', NULL, '2026-03-07 06:28:25', '.news-list', '.news-item', 'h3 a', '.date', '', 'link', '', '', '10', '0', '', NULL, '1000', '3000', NULL);
