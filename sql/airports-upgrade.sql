ALTER TABLE airports ADD COLUMN type TEXT;
UPDATE airports SET type='unknown';
UPDATE airports SET type='station' WHERE name LIKE '%train %' OR name LIKE '% station' OR name LIKE '% rail%' OR name LIKE '%BF' OR name LIKE '%bahnhof%';
UPDATE airports SET type='port' WHERE name LIKE '%ferry %' OR name LIKE '% port%';
UPDATE airports SET type='airport' WHERE name LIKE '% air %' OR name LIKE '%airport%' OR name LIKE '%aerodrome%' OR name LIKE '%heliport%' OR name LIKE '%seaplane%';

ALTER TABLE airports ADD COLUMN source TEXT;
UPDATE airports SET source='User' WHERE uid IS NOT NULL;
UPDATE airports SET source='Legacy' WHERE uid IS NULL;
