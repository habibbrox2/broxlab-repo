-- ========================================
-- টেবিল / Table: ai_messages
-- তারিখ / Date: 2026-03-12 18:21:39
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 4
-- ========================================

CREATE TABLE IF NOT EXISTS `ai_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `role` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `ai_messages` (`id`, `conversation_id`, `role`, `content`, `created_at`) VALUES ('1', '2', 'user', 'এক লাইনে উত্তর দিন।', '2026-03-11 16:57:21');
INSERT INTO `ai_messages` (`id`, `conversation_id`, `role`, `content`, `created_at`) VALUES ('2', '2', 'assistant', 'আপনি কি বলতে চেয়েছেন?', '2026-03-11 16:57:23');
INSERT INTO `ai_messages` (`id`, `conversation_id`, `role`, `content`, `created_at`) VALUES ('3', '3', 'user', 'আমার প্রশ্ন সংক্ষেপ করুন।', '2026-03-11 21:44:18');
INSERT INTO `ai_messages` (`id`, `conversation_id`, `role`, `content`, `created_at`) VALUES ('4', '3', 'assistant', 'আপনার প্রশ্নটি সংক্ষেপে বলতে চাইলে, অনুগ্রহ করে মূল প্রশ্নটি লিখুন, তাহলে আমি আপনাকে সাহায্য করতে পারব।', '2026-03-11 21:44:25');
