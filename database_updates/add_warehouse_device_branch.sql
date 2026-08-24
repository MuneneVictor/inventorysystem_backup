-- Add WAREHOUSE as a valid devices branch for owner inventory records.
ALTER TABLE devices
MODIFY COLUMN branch ENUM('KIMATHI','MOI','WAREHOUSE') NOT NULL;
