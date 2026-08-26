-- OPTIONAL ONE-TIME COPY OF EXISTING OWNER DATA.
-- Run create_owner_inventory_tables.sql first.
-- Existing rows in devices/monitors are NOT deleted.
-- INSERT IGNORE makes this safe against duplicate serial numbers already copied.

INSERT IGNORE INTO iman_hustle_items
(item_type,asset_id,manufacturer,model_name,form_factor,processor,ram,storage,serial_number,grade,buying_price,planned_selling_price,notes,location,status,sales_person,selling_price,sold_at,added_by,date_added)
SELECT
'Device',d.asset_id,d.manufacturer,d.model_name,d.form_factor,d.processor,
CASE WHEN d.ram IS NULL OR d.ram=0 THEN NULL ELSE CONCAT(d.ram,'GB') END,
CASE WHEN d.storage_capacity IS NULL OR d.storage_capacity=0 THEN NULL ELSE CONCAT(d.storage_capacity,'GB ',COALESCE(d.storage_type,'')) END,
d.serial_number,d.grade,d.buying_price,d.price,d.owner_notes,d.branch,d.status,u.full_name,d.selling_price,d.sold_at,d.added_by,d.date_added
FROM devices d LEFT JOIN users u ON u.id=d.sold_by
WHERE d.inventory_owner='imans_hustle';

INSERT IGNORE INTO iman_hustle_items
(item_type,asset_id,manufacturer,model_name,form_factor,processor,ram,storage,serial_number,grade,buying_price,planned_selling_price,notes,location,status,sales_person,selling_price,sold_at,added_by,date_added)
SELECT
'Monitor',m.asset_id,m.manufacturer,m.model_name,COALESCE(m.form_factor,'Monitor'),NULL,NULL,NULL,
m.serial_number,m.grade,m.buying_price,m.price,m.owner_notes,m.branch,m.status,u.full_name,m.selling_price,m.sold_at,m.added_by,m.date_added
FROM monitors m LEFT JOIN users u ON u.id=m.sold_by
WHERE m.inventory_owner='imans_hustle';

INSERT IGNORE INTO iman_inventory_items
(item_type,asset_id,buying_usd,selling_usd,buying_price,planned_selling_price,manufacturer,model_name,processor,ram,storage,serial_number,grade,touch_screen,webcam,notes,location,status,sales_person,selling_price,sold_at,added_by,date_added)
SELECT
'Device',d.asset_id,
CASE WHEN d.symetic REGEXP '^[0-9.,]+$' THEN CAST(REPLACE(d.symetic,',','') AS DECIMAL(12,2)) ELSE NULL END,
CASE WHEN d.dollar_value REGEXP '^[0-9.,]+$' THEN CAST(REPLACE(d.dollar_value,',','') AS DECIMAL(12,2)) ELSE NULL END,
d.buying_price,d.price,d.manufacturer,d.model_name,d.processor,
CASE WHEN d.ram IS NULL OR d.ram=0 THEN NULL ELSE CONCAT(d.ram,'GB') END,
CASE WHEN d.storage_capacity IS NULL OR d.storage_capacity=0 THEN NULL ELSE CONCAT(d.storage_capacity,'GB ',COALESCE(d.storage_type,'')) END,
d.serial_number,d.grade,d.touch,d.webcam,d.owner_notes,COALESCE(d.owner_location,d.branch),d.status,u.full_name,d.selling_price,d.sold_at,d.added_by,d.date_added
FROM devices d LEFT JOIN users u ON u.id=d.sold_by
WHERE d.inventory_owner='iman_inventory';

INSERT IGNORE INTO iman_inventory_items
(item_type,asset_id,buying_usd,selling_usd,buying_price,planned_selling_price,manufacturer,model_name,processor,ram,storage,serial_number,grade,touch_screen,webcam,notes,location,status,sales_person,selling_price,sold_at,added_by,date_added)
SELECT
'Monitor',m.asset_id,
CASE WHEN m.symetic REGEXP '^[0-9.,]+$' THEN CAST(REPLACE(m.symetic,',','') AS DECIMAL(12,2)) ELSE NULL END,
CASE WHEN m.dollar_value REGEXP '^[0-9.,]+$' THEN CAST(REPLACE(m.dollar_value,',','') AS DECIMAL(12,2)) ELSE NULL END,
m.buying_price,m.price,m.manufacturer,m.model_name,NULL,NULL,NULL,m.serial_number,m.grade,NULL,NULL,m.owner_notes,COALESCE(m.owner_location,m.branch),m.status,u.full_name,m.selling_price,m.sold_at,m.added_by,m.date_added
FROM monitors m LEFT JOIN users u ON u.id=m.sold_by
WHERE m.inventory_owner='iman_inventory';
