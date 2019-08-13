ALTER TABLE countries DROP COLUMN junk;
ALTER TABLE countries CHANGE code dafif_code VARCHAR(2);
ALTER TABLE countries CHANGE oa_code iso_code VARCHAR(2);
ALTER TABLE countries MODIFY dafif_code VARCHAR(2) AFTER iso_code;

ALTER TABLE airlines ADD COLUMN country_code VARCHAR(2) AFTER country;
ALTER TABLE airports ADD COLUMN country_code VARCHAR(2) AFTER country;

ALTER TABLE airlines ADD COLUMN source TEXT;
UPDATE airlines SET source='User' WHERE source IS NULL AND uid IS NOT NULL;
UPDATE airlines SET source='Legacy' WHERE source IS NULL AND uid IS NULL;
