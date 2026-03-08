-- ========================================
-- টেবিল / Table: password_resets
-- তারিখ / Date: 2026-03-08 02:32:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 18
-- ========================================

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Hashed reset token',
  `token_type` enum('password_reset','email_verification') DEFAULT 'password_reset',
  `used` tinyint(1) DEFAULT 0 COMMENT 'Token has been used',
  `used_at` timestamp NULL DEFAULT NULL,
  `used_ip` varchar(45) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT 'Token expiration time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `fk_password_resets_user_id` (`user_id`),
  KEY `idx_token` (`token`),
  KEY `idx_user_token_unused` (`user_id`,`used`,`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Secure password reset tokens with expiration';

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('1', '1', '0360841994353fb3bf8a5f6636a630e2f099865eda3fc6d58fdf7e6bb9fbc19a', 'password_reset', '0', NULL, NULL, '2026-01-16 15:22:32', '2026-01-16 14:22:32', '2026-01-16 14:22:32');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('2', '1', '5ddaf0f61139cf5aac52b34dadbfac9378a490d7fcdd2176cea3f6f3869fca17', 'password_reset', '0', NULL, NULL, '2026-01-16 16:17:11', '2026-01-16 15:17:11', '2026-01-16 15:17:11');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('3', '1', '5ff038e9de1c9540030c0cd44c54c57ddfb0e42cb0a46a4cdc0e4a11520c53ae', 'password_reset', '0', NULL, NULL, '2026-01-16 16:29:42', '2026-01-16 15:29:42', '2026-01-16 15:29:42');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('4', '1', 'b6dd15be80f4e738cc49ed1ebc61f2e634e10039cd2d8bb25de9992d2652db68', 'password_reset', '0', NULL, NULL, '2026-01-16 16:30:50', '2026-01-16 15:30:50', '2026-01-16 15:30:50');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('5', '1', 'f4d8880a1620e9d2e30e4840fb5445caee154ff790c791a9f915e15721487f19', '', '0', NULL, NULL, '2026-01-16 16:06:09', '2026-01-16 15:51:09', '2026-01-16 15:51:09');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('6', '1', '5203380085eec0653cdb37c4d70f5604d284c9b5dea7f82efd9dc6d446a2b13a', '', '0', NULL, NULL, '2026-01-16 16:06:16', '2026-01-16 15:51:16', '2026-01-16 15:51:16');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('7', '1', '020c9eebe9e75e108679e391b3143e1fae1d2730b564b7bd193af51e76cbe379', 'password_reset', '0', NULL, NULL, '2026-01-16 16:53:04', '2026-01-16 15:53:04', '2026-01-16 15:53:04');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('8', '1', 'fef10f3932a7fec68f4ab69c6754f629ad005febd77a7e796e66836f84aa9c07', 'password_reset', '0', NULL, NULL, '2026-01-16 16:53:24', '2026-01-16 15:53:24', '2026-01-16 15:53:24');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('9', '1', '4c6d2b5962fc9b7e7b260d0beaf2212521171692f6a3204de2bde05857ce5162', 'password_reset', '0', NULL, NULL, '2026-01-16 16:53:28', '2026-01-16 15:53:28', '2026-01-16 15:53:28');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('10', '1', '36e3e0004f324271c2a6a91891df8b9cdce875d27a7e378d2050fc88b577ef2b', 'password_reset', '0', NULL, NULL, '2026-01-16 16:54:09', '2026-01-16 15:54:09', '2026-01-16 15:54:09');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('11', '1', '6ee38857c5676bb2971f227b72f8e6ee272e323be91d85baf876af6168b3a2dd', '', '0', NULL, NULL, '2026-01-16 16:10:26', '2026-01-16 15:55:26', '2026-01-16 15:55:26');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('12', '1', 'b6b97814ed886b1eda594fc0de2c553a13662c477f459e2a2fcf0213fe291775', '', '0', NULL, NULL, '2026-01-16 16:10:35', '2026-01-16 15:55:35', '2026-01-16 15:55:35');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('13', '1', '9bcbb86dd18b3559a42d9bd5e08e1c3d90c3f8826c3ec4f51f8817bb7480b1ab', '', '0', NULL, NULL, '2026-01-16 17:37:56', '2026-01-16 17:22:56', '2026-01-16 17:22:56');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('14', '1', '6cd50fe8995c1e4b4a6d21aba301c6f37cc20d888889faaaea2eebb061a0ed36', '', '0', NULL, NULL, '2026-01-16 17:42:59', '2026-01-16 17:27:59', '2026-01-16 17:27:59');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('15', '1', '963d73db9486ba1fa1fb97da9749d2afc3d9b68b3f1bd257caf33e5cea901972', '', '0', NULL, NULL, '2026-01-16 17:52:20', '2026-01-16 17:37:20', '2026-01-16 17:37:20');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('16', '4', '48079cd23eaa9be6a71db6e225a87d1f7ad9424e087c5c980be848ec5dc59abe', 'password_reset', '0', NULL, NULL, '2026-01-16 19:04:31', '2026-01-16 18:04:31', '2026-01-16 18:04:31');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('17', '19', '8519fcb6f45ad3112441923a45d866b123a40abbf50670f3931e8ff991e4ecfa', 'password_reset', '0', NULL, NULL, '2026-03-03 05:50:41', '2026-03-03 00:50:41', '2026-03-03 00:50:41');
INSERT INTO `password_resets` (`id`, `user_id`, `token`, `token_type`, `used`, `used_at`, `used_ip`, `expires_at`, `created_at`, `updated_at`) VALUES ('18', '19', 'd2ef1ca7d649cf959dace059030e254878f1ab3372a8906384b9d0b808035871', 'password_reset', '0', NULL, NULL, '2026-03-03 05:51:38', '2026-03-03 00:51:38', '2026-03-03 00:51:38');
