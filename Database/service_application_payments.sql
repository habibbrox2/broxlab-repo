-- ========================================
-- টেবিল / Table: service_application_payments
-- তারিখ / Date: 2026-03-07 01:28:43
-- মোড / Mode: Full Export
-- মোট সারি / Total Rows: 8
-- ========================================

CREATE TABLE IF NOT EXISTS `service_application_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_id` int(10) unsigned NOT NULL,
  `mode` varchar(20) DEFAULT NULL,
  `gateway` varchar(60) DEFAULT NULL,
  `payment_method` varchar(60) DEFAULT NULL,
  `transaction_id` varchar(191) DEFAULT NULL,
  `sender_number` varchar(40) DEFAULT NULL,
  `payer_name` varchar(191) DEFAULT NULL,
  `receiver_account` varchar(191) DEFAULT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `status` varchar(60) NOT NULL DEFAULT 'pending',
  `gateway_response` longtext DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_application_payments_application` (`application_id`),
  UNIQUE KEY `uq_service_application_payments_transaction` (`transaction_id`),
  KEY `idx_service_application_payments_status` (`status`),
  KEY `idx_service_application_payments_created_at` (`created_at`),
  KEY `idx_service_application_payments_user_id` (`user_id`),
  KEY `idx_service_application_payments_service_id` (`service_id`),
  CONSTRAINT `fk_service_application_payments_application` FOREIGN KEY (`application_id`) REFERENCES `service_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ডাটা ইনসার্ট / Data Insert

INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('1', '11', '15', '8', 'gateway', 'bkash', 'bkash', NULL, NULL, NULL, NULL, '1200.00', 'USD', 'pending_gateway', NULL, '2026-03-02 02:08:36', NULL, NULL, '2026-03-02 02:08:36', '2026-03-02 02:08:36', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('2', '12', '15', '8', 'gateway', 'bkash', 'bkash', NULL, NULL, NULL, NULL, '1200.00', 'BDT', 'pending_gateway', NULL, '2026-03-02 16:13:58', NULL, NULL, '2026-03-02 16:13:58', '2026-03-02 16:13:58', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('3', '13', '15', '8', 'gateway', 'bkash', 'bkash', NULL, NULL, NULL, NULL, '1200.00', 'BDT', 'pending_gateway', NULL, '2026-03-02 18:34:14', NULL, NULL, '2026-03-02 18:34:14', '2026-03-02 18:34:14', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('4', '14', '15', '8', 'gateway', 'bkash', 'bkash', NULL, NULL, NULL, NULL, '1200.00', 'BDT', 'pending_gateway', NULL, '2026-03-02 18:48:03', NULL, NULL, '2026-03-02 18:48:04', '2026-03-02 18:48:04', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('8', '15', '15', '8', 'manual', NULL, 'bkash', '111', '111', '111', '01677291778', '1200.00', 'BDT', 'submitted', NULL, '2026-03-05 14:01:50', NULL, NULL, '2026-03-02 23:53:04', '2026-03-05 14:01:50', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('11', '18', '15', '8', 'manual', NULL, 'bkash', 'Uui', '999', '999', '01677291778', '1200.00', 'BDT', 'submitted', NULL, '2026-03-03 23:00:12', NULL, NULL, '2026-03-03 23:00:12', '2026-03-03 23:00:12', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('12', '19', '15', '8', 'manual', NULL, 'rocket', 'Hh', 'Hhh', 'Hhh', '016772917787', '1200.00', 'BDT', 'submitted', NULL, '2026-03-04 23:42:08', NULL, NULL, '2026-03-04 23:42:08', '2026-03-04 23:42:08', NULL);
INSERT INTO `service_application_payments` (`id`, `application_id`, `user_id`, `service_id`, `mode`, `gateway`, `payment_method`, `transaction_id`, `sender_number`, `payer_name`, `receiver_account`, `amount`, `currency`, `status`, `gateway_response`, `submitted_at`, `paid_at`, `completed_at`, `created_at`, `updated_at`, `deleted_at`) VALUES ('14', '21', '15', '8', 'manual', NULL, 'rocket', 'Yyy', '77', '77', '016772917787', '1200.00', 'BDT', 'submitted', NULL, '2026-03-05 22:28:38', NULL, NULL, '2026-03-05 22:28:38', '2026-03-05 22:28:38', NULL);
