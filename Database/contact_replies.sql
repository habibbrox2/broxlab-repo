-- ========================================
-- টেবিল / Table: contact_replies
-- তারিখ / Date: 2026-03-08 02:32:38
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 0
-- ========================================

CREATE TABLE IF NOT EXISTS `contact_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `reply_message` text NOT NULL,
  `replied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_id` (`contact_id`),
  KEY `idx_admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

