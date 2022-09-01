CREATE TABLE action_history (
  uuid VARBINARY(16) NOT NULL,
  action_uuid VARBINARY(16) NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  ts_done BIGINT(20) NOT NULL,
  success ENUM('y', 'n') NOT NULL,
  message TEXT NOT NULL,
  PRIMARY KEY (uuid),
  INDEX timestamp (ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (9, NOW());
