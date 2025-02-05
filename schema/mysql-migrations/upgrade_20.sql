ALTER TABLE downtime_rule
  MODIFY COLUMN filter_definition TEXT NULL DEFAULT NULL;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (20, NOW());
