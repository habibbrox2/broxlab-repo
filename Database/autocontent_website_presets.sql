-- ========================================
-- টেবিল / Table: autocontent_website_presets
-- তারিখ / Date: 2026-03-08 02:32:38
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 7
-- ========================================

CREATE TABLE IF NOT EXISTS `autocontent_website_presets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `preset_key` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `selector_list_container` text DEFAULT NULL,
  `selector_list_item` text DEFAULT NULL,
  `selector_list_title` text DEFAULT NULL,
  `selector_list_link` text DEFAULT NULL,
  `selector_list_date` text DEFAULT NULL,
  `selector_list_image` text DEFAULT NULL,
  `selector_title` text DEFAULT NULL,
  `selector_content` text DEFAULT NULL,
  `selector_image` text DEFAULT NULL,
  `selector_excerpt` text DEFAULT NULL,
  `selector_date` text DEFAULT NULL,
  `selector_author` text DEFAULT NULL,
  `selector_pagination` text DEFAULT NULL,
  `selector_read_more` text DEFAULT NULL,
  `selector_category` text DEFAULT NULL,
  `selector_tags` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `preset_key` (`preset_key`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('1', 'prothomalo', 'Prothom Alo', 'body', '.wide-story-card, .news_with_item', 'h3.headline-title a.title-link', NULL, 'time.published-at, time.published-time', NULL, 'h1.IiRps, h1[data-title-0]', '.story-element.story-element-text', 'meta[property=\"og:image\"]', 'meta[name=\"description\"]', 'time[datetime]', '.author-name, .contributor-name', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('2', 'bdnews24', 'BD News 24', '#data-wrapper', '.SubCat-wrapper, .col-md-3', 'h5 a, .SubcatList-detail h5 a', NULL, '.publish-time, span.publish-time', NULL, '.details-title h1, h1', '#contentDetails, .details-brief', '.details-img img, .details-img picture img', '.details-title h2, h2.shoulder-text', '.pub-up .pub, .pub-up span:first-child', '.author-name-wrap .author, .detail-author-name .author', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('3', 'bbc', 'BBC Bangla', '.content--list', '.bbc-uk8os5, .article, .news-item', 'h3 a, .article-title a', '', 'time, .date', '', 'h1, .article-title', '.article-body, .article__body-content', 'meta[property=\"og:image\"], .article-image img', 'meta[name=\"description\"]', 'time[datetime]', '.byline__name', '', '', '', '', '1', '2026-03-07 22:46:34', '2026-03-08 06:59:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('4', 'ittefaq', 'Ittefaq', '.news-list, .category-news', '.news-item, .category-item', 'h2 a, h3 a, .news-title a', NULL, '.date, time, .publish-date', NULL, 'h1, .article-title', '.article-content, .news-content', '.article-img img, meta[property=\"og:image\"]', 'meta[name=\"description\"]', '.publish-date, time', '.author, .writer', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('5', 'jugantor', 'Jugantor', '.news_list, .cat_news', '.news_item, .cat_item', 'h2 a, h3 a', NULL, '.date, time', NULL, 'h1, .title', '.details, .content', 'meta[property=\"og:image\"]', 'meta[name=\"description\"]', '.date, time', '.writer, .author', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('6', 'kalerkhobor', 'Kaler Khobor', '.latest-news, .news-list', '.news-item, .item', 'h3 a, h4 a', NULL, '.date, time', NULL, 'h1, .headline', '.news-details, .content', 'meta[property=\"og:image\"]', 'meta[name=\"description\"]', '.date, time', '.author, .writer', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
INSERT INTO `autocontent_website_presets` (`id`, `preset_key`, `name`, `selector_list_container`, `selector_list_item`, `selector_list_title`, `selector_list_link`, `selector_list_date`, `selector_list_image`, `selector_title`, `selector_content`, `selector_image`, `selector_excerpt`, `selector_date`, `selector_author`, `selector_pagination`, `selector_read_more`, `selector_category`, `selector_tags`, `is_active`, `created_at`, `updated_at`) VALUES ('7', 'dailystar', 'The Daily Star', '.news-feed, .latest-news', '.news-card, .news-item', 'h3 a, .headline a', NULL, '.date, time', NULL, 'h1, .article-headline', '.article-body, .content', 'meta[property=\"og:image\"]', 'meta[name=\"description\"]', '.publish-date, time', '.author-name, .byline', NULL, NULL, NULL, NULL, '1', '2026-03-07 22:46:34', '2026-03-07 22:46:34');
