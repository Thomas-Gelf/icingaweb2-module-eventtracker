ALTER TABLE issue_history ADD INDEX sort_ts_last_modified (ts_last_modified);

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (14, NOW());
