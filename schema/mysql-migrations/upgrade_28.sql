ALTER TABLE downtime_rule DROP COLUMN next_calculated_uuid;
DROP TABLE downtime_calculated;
DROP TABLE downtime_affected_issue;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (28, NOW());
