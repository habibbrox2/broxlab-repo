-- ========================================
-- টেবিল / Table: role_permissions
-- তারিখ / Date: 2026-03-07 01:28:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 117
-- ========================================

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`,`permission_id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('1', '1', '37', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('2', '1', '38', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('3', '1', '39', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('4', '1', '34', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('5', '1', '35', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('6', '1', '36', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('7', '1', '42', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('8', '1', '43', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('9', '1', '29', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('10', '1', '30', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('11', '1', '31', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('12', '1', '32', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('13', '1', '33', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('14', '1', '23', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('15', '1', '24', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('16', '1', '25', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('17', '1', '26', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('18', '1', '27', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('19', '1', '28', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('20', '1', '6', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('21', '1', '7', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('22', '1', '8', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('23', '1', '9', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('24', '1', '10', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('25', '1', '17', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('26', '1', '18', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('27', '1', '19', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('28', '1', '20', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('29', '1', '21', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('30', '1', '22', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('31', '1', '1', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('32', '1', '2', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('33', '1', '3', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('34', '1', '4', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('35', '1', '5', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('36', '1', '40', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('37', '1', '41', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('38', '1', '11', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('39', '1', '12', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('40', '1', '13', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('41', '1', '14', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('42', '1', '15', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('43', '1', '16', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('64', '2', '37', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('65', '2', '38', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('66', '2', '39', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('67', '2', '34', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('68', '2', '35', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('69', '2', '36', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('70', '2', '42', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('71', '2', '43', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('72', '2', '29', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('73', '2', '30', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('74', '2', '31', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('75', '2', '32', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('76', '2', '33', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('77', '2', '23', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('78', '2', '24', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('79', '2', '25', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('80', '2', '26', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('81', '2', '27', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('82', '2', '28', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('83', '2', '17', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('84', '2', '18', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('85', '2', '19', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('86', '2', '20', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('87', '2', '21', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('88', '2', '22', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('89', '2', '40', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('90', '2', '41', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('91', '2', '11', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('92', '2', '12', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('93', '2', '13', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('94', '2', '14', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('95', '2', '15', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('96', '2', '16', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('127', '3', '17', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('128', '3', '18', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('129', '3', '19', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('130', '3', '20', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('131', '3', '22', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('132', '3', '23', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('133', '3', '24', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('134', '3', '25', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('135', '3', '26', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('136', '3', '28', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('137', '3', '29', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('138', '3', '30', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('139', '3', '31', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('140', '3', '32', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('141', '3', '34', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('142', '3', '35', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('143', '3', '37', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('144', '3', '38', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('145', '3', '42', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('146', '3', '43', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('158', '4', '34', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('159', '4', '42', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('160', '4', '29', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('161', '4', '31', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('162', '4', '23', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('163', '4', '25', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('164', '4', '17', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('165', '4', '19', '2025-12-24 01:08:50');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('174', '2', '1', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('175', '2', '2', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('176', '2', '3', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('177', '2', '4', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('178', '2', '5', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('179', '2', '6', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('180', '2', '7', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('181', '2', '8', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('182', '2', '9', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('183', '2', '10', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('190', '4', '3', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('191', '4', '8', '2025-12-24 01:31:43');
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES ('192', '4', '13', '2025-12-24 01:31:43');
