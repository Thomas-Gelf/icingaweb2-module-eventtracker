ALTER TABLE downtime_rule
  ADD COLUMN ts_triggered BIGINT(20) UNSIGNED DEFAULT NULL AFTER ts_not_after,
  ADD COLUMN on_iteration_end_issue_status ENUM('open', 'closed') NULL DEFAULT NULL AFTER max_single_problem_duration;

UPDATE downtime_rule SET on_iteration_end_issue_status = 'open';

ALTER TABLE downtime_rule
  MODIFY COLUMN on_iteration_end_issue_status ENUM('open', 'closed') NOT NULL;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (24, NOW());
