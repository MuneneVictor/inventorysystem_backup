ALTER TABLE `monitors`
MODIFY COLUMN `branch`
ENUM('KIMATHI','MOI','WAREHOUSE')
NOT NULL;



ALTER TABLE `monitors`
ADD COLUMN `asset_id` VARCHAR(100) NULL AFTER `inventory_owner`;

ALTER TABLE `monitors`
ADD COLUMN `manufacturer` VARCHAR(100) NULL AFTER `asset_id`;

ALTER TABLE `monitors`
ADD COLUMN `form_factor` VARCHAR(100) NULL AFTER `manufacturer`;

ALTER TABLE `monitors`
ADD COLUMN `grade` VARCHAR(50) NULL AFTER `form_factor`;

ALTER TABLE `monitors`
ADD COLUMN `buying_price` DECIMAL(12,2) NULL AFTER `grade`;

ALTER TABLE `monitors`
ADD COLUMN `owner_profit` DECIMAL(12,2) NULL AFTER `buying_price`;

ALTER TABLE `monitors`
ADD COLUMN `owner_notes` TEXT NULL AFTER `owner_profit`;

ALTER TABLE `monitors`
ADD COLUMN `symetic` VARCHAR(100) NULL AFTER `owner_notes`;

ALTER TABLE `monitors`
ADD COLUMN `dollar_value` VARCHAR(100) NULL AFTER `symetic`;

ALTER TABLE `monitors`
ADD COLUMN `owner_location` VARCHAR(100) NULL AFTER `dollar_value`;