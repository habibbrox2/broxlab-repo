-- ========================================
-- টেবিল / Table: user_linked_accounts
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 5
-- ========================================

CREATE TABLE IF NOT EXISTS `user_linked_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL COMMENT 'google, facebook, github, etc',
  `provider_user_id` varchar(255) NOT NULL,
  `provider_email` varchar(150) DEFAULT NULL,
  `provider_data` longtext DEFAULT NULL COMMENT 'JSON encoded provider response',
  `is_primary` tinyint(1) DEFAULT 0 COMMENT 'Primary login method',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `linked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_provider_unique` (`user_id`,`provider`,`provider_user_id`),
  UNIQUE KEY `uc_user_provider_unique` (`user_id`,`provider`),
  KEY `fk_user_linked_accounts_user_id` (`user_id`),
  KEY `idx_provider_user` (`provider`,`provider_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Linked OAuth accounts for account binding';

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `user_linked_accounts` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `provider_data`, `is_primary`, `created_at`, `linked_at`, `last_used_at`, `updated_at`, `deleted_at`) VALUES ('1', '4', 'google', 'H74WOJ2U4ogyvYbLohYZwwxxMuh1', 'hrhabib.etc@gmail.com', '{\"displayName\":\"HR Habib\",\"photoUrl\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocKD2jtCOO1l6w-P9YQm0FaRvx4VeLEI3R8PxId8cKcfRzfg6w=s96-c\",\"email\":\"hrhabib.etc@gmail.com\",\"firstName\":\"HR\",\"lastName\":\"Habib\"}', '1', '2026-02-06 03:49:41', '2026-02-06 03:49:41', '2026-03-03 22:42:53', '2026-03-03 22:42:53', NULL);
INSERT INTO `user_linked_accounts` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `provider_data`, `is_primary`, `created_at`, `linked_at`, `last_used_at`, `updated_at`, `deleted_at`) VALUES ('3', '1', 'google', 'kx0TiSG4ADdVUjtkNHDysWr7Dp82', 'hrhabib.hrs@gmail.com', '{\"displayName\":\"Habibur Rahman\",\"photoUrl\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocLiaPZ8XiTqmtejm1I8rjaQwSfLUbl8mXqLh7Vvbd21t9y4-QrtTw=s96-c\",\"email\":\"hrhabib.hrs@gmail.com\",\"firstName\":\"Habibur\",\"lastName\":\"Rahman\"}', '1', '2026-02-18 14:07:36', '2026-02-18 14:07:36', '2026-03-07 03:59:33', '2026-03-07 03:59:33', NULL);
INSERT INTO `user_linked_accounts` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `provider_data`, `is_primary`, `created_at`, `linked_at`, `last_used_at`, `updated_at`, `deleted_at`) VALUES ('9', '17', 'google', 'hhcl6s08FbeCwyR4BCSdr2fToE42', 'hrhabib.gpay@gmail.com', '{\"displayName\":\"Habibur Rahman\",\"photoUrl\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocKZbs1vFaBVRwhSyvsjRE1XDoO0zn0PCNVlL_RTIvDiWwV-qg=s96-c\",\"email\":\"hrhabib.gpay@gmail.com\",\"firstName\":\"Habibur\",\"lastName\":\"Rahman\"}', '1', '2026-03-02 19:05:05', '2026-03-02 19:05:05', '2026-03-02 19:05:05', '2026-03-02 19:05:05', NULL);
INSERT INTO `user_linked_accounts` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `provider_data`, `is_primary`, `created_at`, `linked_at`, `last_used_at`, `updated_at`, `deleted_at`) VALUES ('11', '19', 'google', 'Dky0TzKSGIeslvVicWtubG0K6k82', 'nayemislam663773@gmail.com', '{\"displayName\":\"NAYEM ISLAM\",\"photoUrl\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocIpkwajIIA2ZgvNAxnATnoIvy_mddb_V_s6iB_dzqw56zZz4A=s96-c\",\"email\":\"nayemislam663773@gmail.com\",\"firstName\":\"NAYEM\",\"lastName\":\"ISLAM\"}', '1', '2026-03-02 20:48:28', '2026-03-02 20:48:28', '2026-03-02 20:48:28', '2026-03-02 20:48:28', NULL);
INSERT INTO `user_linked_accounts` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_email`, `provider_data`, `is_primary`, `created_at`, `linked_at`, `last_used_at`, `updated_at`, `deleted_at`) VALUES ('24', '21', 'google', 'XcWmE7DvoMbirDy8DaA46ZMHrjr2', 'anyphonelog@gmail.com', '{\"displayName\":\"CoronaVirus Official\",\"photoUrl\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocI3yY-LxihV_c4h-1-wnl6xCpPKJh4nm4awiiOdUO6T5XHdgw=s96-c\",\"email\":\"anyphonelog@gmail.com\",\"firstName\":\"CoronaVirus\",\"lastName\":\"Official\"}', '1', '2026-03-07 03:39:18', '2026-03-07 03:39:18', '2026-03-07 03:39:27', '2026-03-07 03:39:27', NULL);
