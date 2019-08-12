ALTER TABLE countries DROP COLUMN junk;
ALTER TABLE countries CHANGE code dafif_code VARCHAR(2);
ALTER TABLE countries CHANGE oa_code iso_code VARCHAR(2);
