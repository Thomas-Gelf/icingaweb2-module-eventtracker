ALTER TABLE issue
  ADD COLUMN downtime_rule_uuid VARBINARY(16) NULL DEFAULT NULL AFTER ticket_ref;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (30, NOW());
