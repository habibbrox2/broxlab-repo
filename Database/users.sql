-- ========================================
-- টেবিল / Table: users
-- তারিখ / Date: 2026-03-07 01:28:44
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 9
-- ========================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firebase_uid` varchar(255) DEFAULT NULL,
  `auth_provider` varchar(50) DEFAULT 'email' COMMENT 'Login method: email, google, facebook, etc',
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `password_set_at` timestamp NULL DEFAULT NULL COMMENT 'When user first set/changed their password',
  `profile_pic` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `alternate_phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','banned','pending') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_token_expires_at` timestamp NULL DEFAULT NULL,
  `phone_verified` tinyint(1) DEFAULT 0,
  `phone_verification_code` varchar(10) DEFAULT NULL,
  `phone_verification_attempts` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `last_failed_login_at` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `login_device` varchar(150) DEFAULT NULL,
  `notification_topic_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'User topic prefs {"system":1,"promo":0,"alert":1}' CHECK (json_valid(`notification_topic_preferences`)),
  `notification_rate_limits` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '{"hourly":500,"daily":2000}' CHECK (json_valid(`notification_rate_limits`)),
  `facebook_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `idx_email_verification_token` (`email_verification_token`),
  UNIQUE KEY `firebase_uid` (`firebase_uid`),
  KEY `status_idx` (`status`),
  KEY `created_at_idx` (`created_at`),
  KEY `idx_auth_provider` (`auth_provider`),
  KEY `idx_account_locked` (`account_locked_until`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('1', 'kx0TiSG4ADdVUjtkNHDysWr7Dp82', 'google', 'admin', 'hrhabib.hrs@gmail.com', '$2y$10$Vkhnjd6tKnwfRS2CGhvfXesJRRAGehuu0x2N4FgTLU8J2bRmP2nBG', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocLiaPZ8XiTqmtejm1I8rjaQwSfLUbl8mXqLh7Vvbd21t9y4-QrtTw=s96-c', 'Habibur', 'Rahman', 'male', '2018-01-01', '', '', '', '', '', '', '', 'active', '1', NULL, NULL, '0', NULL, '0', '2025-08-16 21:29:24', '2026-03-07 06:12:47', NULL, NULL, '2026-02-15 20:04:58', '10', '2026-03-07 06:12:47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, '', '', '', '');
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('4', 'H74WOJ2U4ogyvYbLohYZwwxxMuh1', 'email', 'user', 'hrhabib.etc@gmail.com', '$2y$10$Vkhnjd6tKnwfRS2CGhvfXesJRRAGehuu0x2N4FgTLU8J2bRmP2nBG', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKD2jtCOO1l6w-P9YQm0FaRvx4VeLEI3R8PxId8cKcfRzfg6w=s96-c', 'HR', 'Habib', 'male', '2026-01-27', '', '', '', '', '', '', '', 'active', '1', NULL, NULL, '0', NULL, '0', '2025-08-16 21:29:24', '2026-03-03 22:42:53', NULL, NULL, '2026-01-26 16:40:28', '4', '2026-03-03 18:42:53', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', NULL, NULL, '', '', '', '');
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('10', 'XZd3DqizkrTWLsDkPNlGAN2Lmbb2', 'email', 'habibur_rahman', 'hrhabib.etcx@gmail.com', '$2y$10$9HR4erKX6mdIirq7It82/O35GC/I0zKsYE6.MHLwvPf6kCEIHCKuW', NULL, NULL, NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', NULL, NULL, '0', NULL, '0', '2026-02-04 16:16:30', '2026-02-18 14:02:22', NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('15', NULL, 'email', 'guest_applicant', 'guest.applicant@broxbhai.local', '$2y$10$dqXp3M2qF7OXc2D2g2vntu1rCKY3fg9l5jADUEM3.UUXQETSRI3Gu', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '1', NULL, NULL, '0', NULL, '0', '2026-03-02 00:56:23', '2026-03-02 00:56:23', NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('17', 'hhcl6s08FbeCwyR4BCSdr2fToE42', 'google', 'habibur_rahman_1', 'hrhabib.gpay@gmail.com', '$2y$10$MrbDhSSrk3nQ9VRcS/W03uEyV00vxDBAUrvlpMsoh9fE8NgnNxLKe', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocKZbs1vFaBVRwhSyvsjRE1XDoO0zn0PCNVlL_RTIvDiWwV-qg=s96-c', 'Habibur', 'Rahman', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', NULL, NULL, '0', NULL, '0', '2026-03-02 19:05:05', '2026-03-02 19:05:05', NULL, NULL, NULL, '0', '2026-03-02 15:05:05', '103.13.193.101', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('18', NULL, 'email', 'hrhaaabib.hrs@gmail.com', 'hrhaaabib.hrs@gmail.com', '$2y$10$3I0ZnF5nP47WKuy0TZBSWu3qfWQXu/arOjNGIfUlYOhg2/R.I.OTO', NULL, NULL, NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', NULL, NULL, '0', NULL, '0', '2026-03-02 19:05:38', '2026-03-02 19:05:38', NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('19', 'Dky0TzKSGIeslvVicWtubG0K6k82', 'google', 'nayem_islam', 'nayemislam663773@gmail.com', '$2y$10$uiCsZWQHdjG6tzzEv7jHaeGMwn0PvzFoBhxbPvjMxVnMVcgIpyZIe', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocIpkwajIIA2ZgvNAxnATnoIvy_mddb_V_s6iB_dzqw56zZz4A=s96-c', 'NAYEM', 'ISLAM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', NULL, NULL, '0', NULL, '0', '2026-03-02 20:48:28', '2026-03-02 23:09:54', NULL, NULL, NULL, '0', '2026-03-02 16:48:28', '103.73.198.227', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('20', NULL, 'email', 'nayemislam663773', 'nayemislamsnapchat@gmail.com', '$2y$10$/BRX.q5oEokAFWJmJY7EMuAMDVInWrPPQvFKxwNSSrI9I0NVRVM7S', NULL, NULL, NULL, 'NAYEM', 'ISLAM', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', '16b437d2889c82add557e06edfa302530b6f35d6ad73cc0b0085ea348bdb08d0', '2026-03-04 04:53:58', '0', NULL, '0', '2026-03-03 00:53:58', '2026-03-05 13:23:47', NULL, NULL, NULL, '0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `users` (`id`, `firebase_uid`, `auth_provider`, `username`, `email`, `password`, `password_changed_at`, `password_set_at`, `profile_pic`, `first_name`, `last_name`, `gender`, `dob`, `phone`, `alternate_phone`, `address`, `city`, `state`, `country`, `zipcode`, `status`, `email_verified`, `email_verification_token`, `email_verification_token_expires_at`, `phone_verified`, `phone_verification_code`, `phone_verification_attempts`, `created_at`, `updated_at`, `deleted_at`, `account_locked_until`, `last_failed_login_at`, `failed_login_attempts`, `last_login`, `login_ip`, `login_device`, `notification_topic_preferences`, `notification_rate_limits`, `facebook_url`, `twitter_url`, `instagram_url`, `linkedin_url`) VALUES ('21', 'XcWmE7DvoMbirDy8DaA46ZMHrjr2', 'google', 'coronavirus_official', 'anyphonelog@gmail.com', '$2y$10$b33knBGL3CBFyojyD/lgiOJDxkHc8SP5hn1ngM3KcGhIRworn1Sd.', NULL, NULL, 'https://lh3.googleusercontent.com/a/ACg8ocI3yY-LxihV_c4h-1-wnl6xCpPKJh4nm4awiiOdUO6T5XHdgw=s96-c', 'CoronaVirus', 'Official', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '0', NULL, NULL, '0', NULL, '0', '2026-03-07 03:39:18', '2026-03-07 03:39:27', NULL, NULL, NULL, '0', '2026-03-07 03:39:27', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:148.0) Gecko/20100101 Firefox/148.0', NULL, NULL, NULL, NULL, NULL, NULL);
