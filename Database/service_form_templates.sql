-- ========================================
-- টেবিল / Table: service_form_templates
-- তারিখ / Date: 2026-03-07 01:28:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 3
-- ========================================

CREATE TABLE IF NOT EXISTS `service_form_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int(10) unsigned NOT NULL,
  `form_field_name` varchar(100) NOT NULL,
  `field_type` enum('text','email','phone','textarea','select','checkbox','file','date') DEFAULT 'text',
  `label` varchar(255) NOT NULL,
  `required` tinyint(1) DEFAULT 1,
  `placeholder` varchar(255) DEFAULT NULL,
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `field_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_field_order` (`field_order`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `service_form_templates` (`id`, `service_id`, `form_field_name`, `field_type`, `label`, `required`, `placeholder`, `validation_rules`, `field_order`, `created_at`) VALUES ('3', '7', 'ন-ম', 'text', 'নাম', '0', '', NULL, '0', '2026-02-01 03:36:19');
INSERT INTO `service_form_templates` (`id`, `service_id`, `form_field_name`, `field_type`, `label`, `required`, `placeholder`, `validation_rules`, `field_order`, `created_at`) VALUES ('14', '8', 'জন-মন-বন-ধন-ব-য-ক-ত-র-ন-ম-ব-ল-য়', 'text', 'জন্মনিবন্ধন ব্যাক্তির নাম বাংলায়', '1', 'জন্মনিবন্ধন ব্যাক্তির নাম বাংলায়', NULL, '0', '2026-03-02 02:07:08');
INSERT INTO `service_form_templates` (`id`, `service_id`, `form_field_name`, `field_type`, `label`, `required`, `placeholder`, `validation_rules`, `field_order`, `created_at`) VALUES ('15', '8', 'জন-মন-বন-ধন-ব-য-ক-ত-র-ন-ম-ই-র-জ-ত', 'text', 'জন্মনিবন্ধন ব্যাক্তির নাম ইংরেজিতে', '1', 'জন্মনিবন্ধন ব্যাক্তির নাম ইংরেজিতে', NULL, '1', '2026-03-02 02:07:08');
