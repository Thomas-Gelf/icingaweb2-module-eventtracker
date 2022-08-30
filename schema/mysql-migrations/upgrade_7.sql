CREATE TABLE api_token (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  permissions TEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (7, NOW());
