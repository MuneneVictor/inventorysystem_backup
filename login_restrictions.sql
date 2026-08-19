-- Login Access Restrictions Migration
-- Run this once on the existing inventory_system database.

CREATE TABLE IF NOT EXISTS `login_access_settings` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `restrictions_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `blocked_days` varchar(100) NOT NULL DEFAULT 'sunday',
  `enforce_working_hours` tinyint(1) NOT NULL DEFAULT 1,
  `work_start_time` time NOT NULL DEFAULT '08:00:00',
  `work_end_time` time NOT NULL DEFAULT '18:00:00',
  `timezone` varchar(64) NOT NULL DEFAULT 'Africa/Nairobi',
  `blocked_day_message` varchar(255) DEFAULT 'The system is closed today. Please log in on the next working day.',
  `outside_hours_message` varchar(255) DEFAULT 'The system is currently outside working hours. Please try again during the allowed login period.',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
INSERT INTO `login_access_settings`
(`id`, `restrictions_enabled`, `blocked_days`, `enforce_working_hours`,
 `work_start_time`, `work_end_time`, `timezone`,
 `blocked_day_message`, `outside_hours_message`, `updated_by`)
VALUES
(1, 1, 'sunday', 1, '08:00:00', '18:00:00', 'Africa/Nairobi',
 'The system is closed today. Please log in on the next working day.',
 'The system is currently outside working hours. Please try again during the allowed login period.',
 NULL)
ON DUPLICATE KEY UPDATE `id` = `id`;
