ALTER TABLE issue_history
  ADD COLUMN problem_identifier VARCHAR(64) NULL DEFAULT NULL AFTER object_name;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (18, NOW());
