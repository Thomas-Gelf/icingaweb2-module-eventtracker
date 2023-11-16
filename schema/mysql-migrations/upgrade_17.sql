ALTER TABLE issue
    ADD COLUMN problem_identifier VARCHAR(64) NULL DEFAULT NULL AFTER object_name;

CREATE TABLE problem_handling (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(64) NOT NULL,
  instruction_url TEXT DEFAULT NULL,
  trigger_actions ENUM('y', 'n'),
  enabled ENUM('y', 'n'),
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (17, NOW());
