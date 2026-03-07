-- ========================================
-- টেবিল / Table: mobile_images
-- তারিখ / Date: 2026-03-07 01:28:42
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 7
-- ========================================

CREATE TABLE IF NOT EXISTS `mobile_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mobile_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `mobile_id` (`mobile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('1', '1', 'samsung_sm galaxy a17 5g_0.jpg');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('2', '1', 'samsung_sm galaxy a17 5g_1.jpg');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('3', '1', 'samsung_sm galaxy a17 5g_2.jpg');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('4', '10', '/uploads/mobiles/78fc237623d7c485_1769089793_5987.png');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('5', '11', '/uploads/mobiles/0eea5fde1df452c4_1769089909_3788.png');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('6', '10', '/uploads/mobiles/cc982f23ec5b00d9_1769091478_2014.jpg');
INSERT INTO `mobile_images` (`id`, `mobile_id`, `image_url`) VALUES ('7', '10', '/uploads/mobiles/f6c57c331b8336f3_1769091973_6764.jpg');
