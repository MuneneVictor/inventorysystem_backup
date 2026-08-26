-- Adds Settings-managed email permissions for Iman Inventory / Iman's Hustle.
-- Run once.

ALTER TABLE `login_access_settings`
    ADD COLUMN `owner_inventory_allowed_emails` TEXT NULL AFTER `outside_hours_message`;

UPDATE `login_access_settings`
SET `owner_inventory_allowed_emails` = 'stephanie@mombasacomputers.co.ke'
WHERE `id` = 1
  AND (`owner_inventory_allowed_emails` IS NULL OR TRIM(`owner_inventory_allowed_emails`) = '');
