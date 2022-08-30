CREATE TABLE action (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  filter TEXT DEFAULT NULL,
  enabled ENUM('y', 'n') NOT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (8, NOW());
