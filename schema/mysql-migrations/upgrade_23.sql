ALTER TABLE downtime_rule DROP FOREIGN KEY downtime_rule_next_calculated;

DROP TABLE downtime_calculated;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (23, NOW());
