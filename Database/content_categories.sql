-- ========================================
-- টেবিল / Table: content_categories
-- তারিখ / Date: 2026-03-07 01:28:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 19
-- ========================================

CREATE TABLE IF NOT EXISTS `content_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` varchar(55) DEFAULT NULL,
  `content_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `content_type` (`content_type`,`content_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('4', '', '10', '2');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('5', '', '10', '2');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('6', '', '10', '2');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('7', 'post', '5', '5');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('8', 'page', '2', '6');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('9', 'page', '3', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('10', 'page', '4', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('11', 'post', '6', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('14', 'page', '6', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('17', 'page', '7', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('24', 'post', '7', '11');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('25', 'page', '5', '9');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('26', 'page', '5', '7');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('27', 'post', '4', '2');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('29', 'post', '3', '1');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('44', 'post', '2', '9');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('45', 'post', '2', '4');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('48', 'service', '7', '11');
INSERT INTO `content_categories` (`id`, `content_type`, `content_id`, `category_id`) VALUES ('49', 'post', '10', '6');
