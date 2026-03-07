-- ========================================
-- টেবিল / Table: user_roles
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 14
-- ========================================

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_role` (`user_id`,`role_id`),
  KEY `user_id` (`user_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('1', '1', '1', '2025-12-24 01:31:43');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('2', '4', '4', '2025-12-24 01:32:19');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('3', '7', '4', '2026-01-16 21:05:41');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('4', '6', '4', '2026-01-17 02:48:54');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('5', '8', '4', '2026-01-26 03:03:01');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('7', '9', '4', '2026-01-27 14:30:54');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('8', '10', '4', '2026-02-18 14:02:22');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('13', '15', '4', '2026-03-02 00:56:23');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('15', '17', '4', '2026-03-02 19:05:05');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('17', '18', '4', '2026-03-02 19:05:38');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('19', '19', '2', '2026-03-02 23:09:54');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('22', '20', '2', '2026-03-05 13:23:47');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('23', '20', '4', '2026-03-05 13:23:47');
INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`) VALUES ('24', '21', '4', '2026-03-07 03:39:18');
