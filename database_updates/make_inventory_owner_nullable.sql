-- Make device inventory ownership optional.
ALTER TABLE devices
  MODIFY inventory_owner ENUM('iman_inventory','imans_hustle') NULL DEFAULT NULL;
