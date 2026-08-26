-- Independent Iman Inventory / Iman's Hustle v2
-- Run this after the earlier create_owner_inventory_tables.sql if those tables already exist.
-- It makes owner inventory fields nullable, adds independent maintenance history,
-- and stores the allowed owner-inventory email list in login_access_settings.

ALTER TABLE `login_access_settings`
    ADD COLUMN `owner_inventory_allowed_emails` TEXT NULL AFTER `outside_hours_message`;

UPDATE `login_access_settings`
SET `owner_inventory_allowed_emails` = 'stephanie@mombasacomputers.co.ke'
WHERE `id` = 1
  AND (`owner_inventory_allowed_emails` IS NULL OR TRIM(`owner_inventory_allowed_emails`) = '');

ALTER TABLE `iman_hustle_items`
    MODIFY COLUMN `item_type` ENUM('Device','Monitor') NULL DEFAULT 'Device',
    MODIFY COLUMN `model_name` VARCHAR(190) NULL,
    MODIFY COLUMN `serial_number` VARCHAR(190) NULL,
    MODIFY COLUMN `location` ENUM('KIMATHI','MOI','WAREHOUSE') NULL,
    MODIFY COLUMN `status` ENUM('In Stock','Sold') NOT NULL DEFAULT 'In Stock';

ALTER TABLE `iman_inventory_items`
    MODIFY COLUMN `item_type` ENUM('Device','Monitor') NULL DEFAULT 'Device',
    MODIFY COLUMN `model_name` VARCHAR(190) NULL,
    MODIFY COLUMN `serial_number` VARCHAR(190) NULL,
    MODIFY COLUMN `location` ENUM('KIMATHI','MOI','WAREHOUSE') NULL,
    MODIFY COLUMN `status` ENUM('In Stock','Sold') NOT NULL DEFAULT 'In Stock';

CREATE TABLE IF NOT EXISTS `owner_inventory_maintenance` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_key` ENUM('imans_hustle','iman_inventory') NOT NULL,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `old_processor` VARCHAR(190) NULL,
    `new_processor` VARCHAR(190) NULL,
    `old_ram` VARCHAR(50) NULL,
    `new_ram` VARCHAR(50) NULL,
    `old_storage` VARCHAR(100) NULL,
    `new_storage` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `performed_by` INT NULL,
    `date_performed` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_owner_item` (`owner_key`,`item_id`),
    KEY `idx_owner_maintenance_date` (`date_performed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
