ALTER TABLE issue_history ADD COLUMN input_uuid VARBINARY(16) DEFAULT NULL AFTER closed_by;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (4, NOW());
