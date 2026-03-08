-- ========================================
-- টেবিল / Table: mobiles
-- তারিখ / Date: 2026-03-08 02:32:40
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 11
-- ========================================

CREATE TABLE IF NOT EXISTS `mobiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(255) NOT NULL,
  `model_name` varchar(255) NOT NULL,
  `is_official` tinyint(1) NOT NULL DEFAULT 1,
  `official_price` decimal(10,2) DEFAULT NULL,
  `unofficial_price` decimal(10,2) DEFAULT NULL,
  `status` enum('official','unofficial','both') NOT NULL,
  `release_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('1', 'Samsung', 'SM Galaxy A17 5G', '1', '30000.00', '25000.00', 'official', '2025-12-08', '2025-08-19 23:44:59');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('2', 'Sam', 'SM Galaxy A17 5G', '1', '11.00', '22.00', 'official', '2026-01-22', '2026-01-22 18:49:46');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('3', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 18:58:15');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('4', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:02:35');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('5', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:04:13');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('6', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:05:41');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('7', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:12:34');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('8', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:12:39');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('9', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '0000-00-00', '2026-01-22 19:35:34');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('10', 'Sam', 'SM_T', '1', '111.00', '11.00', 'official', '2026-01-22', '2026-01-22 19:38:38');
INSERT INTO `mobiles` (`id`, `brand_name`, `model_name`, `is_official`, `official_price`, `unofficial_price`, `status`, `release_date`, `created_at`) VALUES ('11', 'Sam', 'SM Galaxy A17 5G', '1', '555.00', '52.00', 'official', '0000-00-00', '2026-01-22 19:51:49');
