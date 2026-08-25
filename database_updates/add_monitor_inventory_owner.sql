-- Add optional Iman ownership to monitors.
-- NULL means the monitor belongs to normal inventory / no owner assignment.

ALTER TABLE `monitors`
    ADD COLUMN `inventory_owner`
        ENUM('iman_inventory','imans_hustle')
        NULL DEFAULT NULL
        AFTER `monitor_condition`;
