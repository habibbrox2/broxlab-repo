-- GitHub Webhook Database Tables
-- Run this SQL to create the required tables for GitHub webhook functionality

-- Create webhook settings table
CREATE TABLE
IF NOT EXISTS `deploy_webhook_settings`
(
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR
(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `setting_type` VARCHAR
(20) DEFAULT 'string',
  `description` TEXT,
  `is_sensitive` TINYINT
(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_setting_key`
(`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default webhook settings
INSERT IGNORE
INTO `deploy_webhook_settings`
(`setting_key`, `setting_value`, `setting_type`, `description`, `is_sensitive`) VALUES
('webhook_enabled', '0', 'boolean', 'Enable or disable GitHub webhook integration', 0),
('webhook_secret', '', 'string', 'GitHub webhook secret for signature verification', 1),
('webhook_branch', 'main', 'string', 'Branch to trigger deployments on', 0),
('webhook_events', '["push"]', 'json', 'GitHub events that trigger deployment', 0),
('webhook_auto_deploy', '0', 'boolean', 'Automatically run deployment on webhook trigger', 0),
('last_webhook_delivery', '', 'string', 'Last webhook delivery timestamp', 0),
('last_webhook_status', '', 'string', 'Last webhook delivery status', 0);

-- Create webhook logs table
CREATE TABLE
IF NOT EXISTS `deploy_webhook_logs`
(
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `delivery_id` VARCHAR
(100) DEFAULT NULL,
  `event_type` VARCHAR
(50) DEFAULT NULL,
  `payload` LONGTEXT,
  `signature_verified` TINYINT
(1) DEFAULT 0,
  `deployment_triggered` TINYINT
(1) DEFAULT 0,
  `deployment_status` VARCHAR
(20) DEFAULT NULL,
  `ip_address` VARCHAR
(45) DEFAULT NULL,
  `user_agent` VARCHAR
(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_created_at`
(`created_at`),
  INDEX `idx_delivery_id`
(`delivery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
