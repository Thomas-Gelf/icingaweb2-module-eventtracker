CREATE TABLE config_history (
  ts_modification BIGINT UNSIGNED NOT NULL,
  action ENUM('create', 'modify', 'delete') NOT NULL,
  object_uuid VARBINARY(16) NOT NULL,
  config_uuid VARBINARY(16) NOT NULL,
  object_type VARCHAR(32) NOT NULL,
  label VARCHAR(255) NOT NULL,
  properties_old TEXT NULL DEFAULT NULL,
  properties_new TEXT NULL DEFAULT NULL,
  author VARCHAR(255) NOT NULL,
  PRIMARY KEY (ts_modification),
  INDEX idx_rule_history (object_uuid, ts_modification DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (19, NOW());
