-- ========================================
-- টেবিল / Table: service_images
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 4
-- ========================================

CREATE TABLE IF NOT EXISTS `service_images` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(10) unsigned NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `mime_type` varchar(50) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_is_featured` (`is_featured`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `service_images` (`id`, `service_id`, `image_path`, `thumbnail_path`, `alt_text`, `caption`, `file_size`, `mime_type`, `is_featured`, `display_order`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '7', '/public/uploads/services/1769774185_135eab3a_20251123_162624.jpg', '/public/uploads/services/thumbs/thumb_1769774185_135eab3a_20251123_162624.jpg', '20251123_162624.jpg', '', NULL, NULL, '0', '0', '2026-01-30 17:56:26', '2026-02-01 03:36:19', NULL);
INSERT INTO `service_images` (`id`, `service_id`, `image_path`, `thumbnail_path`, `alt_text`, `caption`, `file_size`, `mime_type`, `is_featured`, `display_order`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '7', '/public/uploads/services/1769784766_bfb33e74_20251123_162650.jpg', '/public/uploads/services/thumbs/thumb_1769784766_bfb33e74_20251123_162650.jpg', '20251123_162650.jpg', '', NULL, NULL, '0', '0', '2026-01-30 20:52:46', '2026-02-01 03:36:19', NULL);
INSERT INTO `service_images` (`id`, `service_id`, `image_path`, `thumbnail_path`, `alt_text`, `caption`, `file_size`, `mime_type`, `is_featured`, `display_order`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '8', '/uploads/services/onlain-jn-m-nibn-dhn_i1772103444_301.png', '/uploads/services/onlain-jn-m-nibn-dhn_i1772103444_301_medium.png', 'images.png', '', NULL, NULL, '0', '0', '2026-02-26 16:57:24', '2026-03-02 02:07:08', NULL);
INSERT INTO `service_images` (`id`, `service_id`, `image_path`, `thumbnail_path`, `alt_text`, `caption`, `file_size`, `mime_type`, `is_featured`, `display_order`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '8', '/uploads/services/onlain-jn-m-nibn-dhn_i1772103444_674.png', '/uploads/services/onlain-jn-m-nibn-dhn_i1772103444_674_medium.png', 'অনলাইনে-নতুন-জন্ম-নিবন্ধনের-জন্য-আবেদন.png', '', NULL, NULL, '0', '0', '2026-02-26 16:57:24', '2026-03-02 02:07:08', NULL);
