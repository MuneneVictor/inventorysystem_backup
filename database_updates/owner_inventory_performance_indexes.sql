-- Owner inventory performance indexes
-- Safe to run more than once: each index is added only when it does not already exist.

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_hustle_items` ADD INDEX `idx_ih_status_date_id` (`status`,`date_added`,`id`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_hustle_items' AND index_name='idx_ih_status_date_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_hustle_items` ADD INDEX `idx_ih_sold_date_id` (`sold_at`,`id`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_hustle_items' AND index_name='idx_ih_sold_date_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_hustle_items` ADD INDEX `idx_ih_model` (`model_name`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_hustle_items' AND index_name='idx_ih_model'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_hustle_items` ADD INDEX `idx_ih_location` (`location`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_hustle_items' AND index_name='idx_ih_location'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_inventory_items` ADD INDEX `idx_ii_status_date_id` (`status`,`date_added`,`id`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_inventory_items' AND index_name='idx_ii_status_date_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_inventory_items` ADD INDEX `idx_ii_sold_date_id` (`sold_at`,`id`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_inventory_items' AND index_name='idx_ii_sold_date_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_inventory_items` ADD INDEX `idx_ii_model` (`model_name`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_inventory_items' AND index_name='idx_ii_model'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `iman_inventory_items` ADD INDEX `idx_ii_location` (`location`)',
        'SELECT 1'
    )
    FROM information_schema.statistics
    WHERE table_schema=@db AND table_name='iman_inventory_items' AND index_name='idx_ii_location'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
