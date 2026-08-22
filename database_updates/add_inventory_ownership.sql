-- Inventory ownership feature
ALTER TABLE devices
  ADD COLUMN inventory_owner ENUM('iman_inventory','imans_hustle') NOT NULL DEFAULT 'iman_inventory' AFTER place,
  ADD INDEX idx_devices_inventory_owner (inventory_owner),
  ADD INDEX idx_devices_owner_status (inventory_owner, status);

-- All existing devices remain assigned to Iman Inventory by default.
-- Reassign specific old devices where needed, for example:
-- UPDATE devices SET inventory_owner='imans_hustle' WHERE serial_number IN ('SERIAL1','SERIAL2');
