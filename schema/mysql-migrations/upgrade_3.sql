ALTER TABLE issue ADD COLUMN input_uuid VARBINARY(16) DEFAULT NULL AFTER priority;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (3, NOW());
