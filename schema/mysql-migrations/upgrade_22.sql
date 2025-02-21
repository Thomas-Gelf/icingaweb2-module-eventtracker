CREATE TABLE issue_downtime_history (
  ts_modification BIGINT UNSIGNED NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  rule_uuid VARBINARY(16) NULL DEFAULT NULL,
  rule_config_uuid VARBINARY(16) NULL DEFAULT NULL,
  action ENUM('activated', 'deactivated') NOT NULL,
  PRIMARY KEY (ts_modification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
  VALUES (22, NOW());
