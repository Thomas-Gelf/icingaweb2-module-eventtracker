ALTER TABLE issue_history
  ADD COLUMN downtime_config_uuid VARBINARY(16) NULL DEFAULT NULL AFTER ticket_ref,
  ADD COLUMN ts_downtime_triggered BIGINT(20) NULL DEFAULT NULL AFTER downtime_config_uuid,
  ADD COLUMN ts_downtime_expired BIGINT(20) NULL DEFAULT NULL AFTER ts_downtime_triggered;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (26, NOW());
