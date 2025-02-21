DROP TABLE IF EXISTS downtime_affected_issue;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (21, NOW());
