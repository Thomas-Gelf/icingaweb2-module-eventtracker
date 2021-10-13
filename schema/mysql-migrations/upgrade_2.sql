
CREATE TABLE input (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE channel (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  rules MEDIUMTEXT NOT NULL,
  input_implementation TEXT DEFAULT NULL,
  input_uuids TEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO sender (id, sender_name, implementation) VALUES (99999, 'Compat', 'compat');


INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (2, NOW());
