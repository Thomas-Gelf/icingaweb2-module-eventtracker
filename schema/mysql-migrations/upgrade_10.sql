ALTER TABLE action ADD COLUMN description MEDIUMTEXT DEFAULT NULL AFTER enabled;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (10, NOW());
