-- Additional fields required by the existing Iman Hustle and Iman Inventory spreadsheets.
-- All columns are nullable so normal inventory devices are unaffected.
ALTER TABLE devices
  ADD COLUMN asset_id VARCHAR(100) NULL AFTER inventory_owner,
  ADD COLUMN manufacturer VARCHAR(100) NULL AFTER asset_id,
  ADD COLUMN form_factor VARCHAR(100) NULL AFTER manufacturer,
  ADD COLUMN grade VARCHAR(50) NULL AFTER form_factor,
  ADD COLUMN buying_price DECIMAL(12,2) NULL AFTER grade,
  ADD COLUMN owner_profit DECIMAL(12,2) NULL AFTER buying_price,
  ADD COLUMN owner_notes TEXT NULL AFTER owner_profit,
  ADD COLUMN symetic VARCHAR(100) NULL AFTER owner_notes,
  ADD COLUMN dollar_value VARCHAR(50) NULL AFTER symetic,
  ADD COLUMN webcam VARCHAR(50) NULL AFTER dollar_value,
  ADD COLUMN owner_location VARCHAR(100) NULL AFTER webcam;
