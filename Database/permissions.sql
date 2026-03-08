-- ========================================
-- টেবিল / Table: permissions
-- তারিখ / Date: 2026-03-08 02:32:41
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 43
-- ========================================

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', 'role.list', 'role', 'View all roles', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', 'role.create', 'role', 'Create new role', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', 'role.view', 'role', 'View role details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', 'role.edit', 'role', 'Edit role', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('5', 'role.delete', 'role', 'Delete role', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('6', 'permission.list', 'permission', 'View all permissions', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('7', 'permission.create', 'permission', 'Create new permission', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('8', 'permission.view', 'permission', 'View permission details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('9', 'permission.edit', 'permission', 'Edit permission', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('10', 'permission.delete', 'permission', 'Delete permission', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('11', 'user.list', 'user', 'View all users', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('12', 'user.create', 'user', 'Create new user', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('13', 'user.view', 'user', 'View user details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('14', 'user.edit', 'user', 'Edit user', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('15', 'user.delete', 'user', 'Delete user', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('16', 'user.assign_role', 'user', 'Assign roles to users', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('17', 'post.list', 'post', 'View all posts', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('18', 'post.create', 'post', 'Create new post', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('19', 'post.view', 'post', 'View post details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('20', 'post.edit', 'post', 'Edit post', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('21', 'post.delete', 'post', 'Delete post', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('22', 'post.publish', 'post', 'Publish post', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('23', 'page.list', 'page', 'View all pages', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('24', 'page.create', 'page', 'Create new page', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('25', 'page.view', 'page', 'View page details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('26', 'page.edit', 'page', 'Edit page', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('27', 'page.delete', 'page', 'Delete page', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('28', 'page.publish', 'page', 'Publish page', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('29', 'mobile.list', 'mobile', 'View all mobiles', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('30', 'mobile.create', 'mobile', 'Create new mobile', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('31', 'mobile.view', 'mobile', 'View mobile details', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('32', 'mobile.edit', 'mobile', 'Edit mobile', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('33', 'mobile.delete', 'mobile', 'Delete mobile', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('34', 'comment.list', 'comment', 'View all comments', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('35', 'comment.approve', 'comment', 'Approve comments', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('36', 'comment.delete', 'comment', 'Delete comments', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('37', 'activity.list', 'activity', 'View activity logs', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('38', 'activity.export', 'activity', 'Export activity logs', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('39', 'activity.delete', 'activity', 'Delete activity logs', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('40', 'setting.list', 'setting', 'View settings', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('41', 'setting.edit', 'setting', 'Edit settings', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('42', 'dashboard.view', 'dashboard', 'View dashboard', '2025-12-24 01:08:35', NULL, NULL);
INSERT INTO `permissions` (`id`, `name`, `module`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES ('43', 'dashboard.admin', 'dashboard', 'Access admin dashboard', '2025-12-24 01:08:35', NULL, NULL);
