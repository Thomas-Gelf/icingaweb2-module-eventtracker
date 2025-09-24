UPDATE issue SET
  status = 'open',
  downtime_config_uuid = NULL,
  ts_downtime_triggered = NULL,
  ts_downtime_expired = NULL
  WHERE status = 'in_downtime'
    AND downtime_rule_uuid IS NULL;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (32, NOW());
