-- ========================================
-- টেবিল / Table: content_tags
-- তারিখ / Date: 2026-03-08 02:32:39
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 22
-- ========================================

CREATE TABLE IF NOT EXISTS `content_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_type` varchar(55) DEFAULT NULL,
  `content_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag_id` (`tag_id`),
  KEY `content_type` (`content_type`,`content_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('9', 'post', '5', '7');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('10', 'post', '5', '8');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('11', 'page', '2', '5');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('12', 'page', '3', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('13', 'page', '4', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('14', 'post', '6', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('17', 'page', '6', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('20', 'page', '7', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('27', 'mobile', '2', '8');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('30', 'post', '7', '11');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('31', 'page', '5', '9');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('32', 'post', '4', '7');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('33', 'post', '4', '6');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('35', 'post', '3', '5');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('65', 'post', '2', '6');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('66', 'post', '2', '4');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('67', 'post', '2', '3');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('68', 'post', '2', '2');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('69', 'post', '2', '1');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('72', 'service', '7', '7');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('73', 'post', '10', '8');
INSERT INTO `content_tags` (`id`, `content_type`, `content_id`, `tag_id`) VALUES ('74', 'post', '10', '6');
