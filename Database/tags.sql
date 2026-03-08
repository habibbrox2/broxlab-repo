-- ========================================
-- টেবিল / Table: tags
-- তারিখ / Date: 2026-03-08 02:32:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 10
-- ========================================

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('1', 'Microsoft', NULL, 'microsoft');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('2', 'Copilot studio', NULL, 'copilot-studio');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('3', 'Visual studio code', NULL, 'visual-studio-code');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('4', 'Github copilot Devops', NULL, 'github-copilot-devops');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('5', 'tech news', NULL, 'tech-news');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('6', 'Online Earings', NULL, 'online-earings');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('7', 'Online Earnings', NULL, 'online-earnings');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('8', 'Banking', NULL, 'banking');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('9', 'ট্রিকস্', NULL, 'n-a');
INSERT INTO `tags` (`id`, `name`, `description`, `slug`) VALUES ('11', 'Online Services', NULL, 'online-services');
