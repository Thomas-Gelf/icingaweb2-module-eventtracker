CREATE TABLE host_list (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(128) NOT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX idx_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE host_list_member (
  list_uuid VARBINARY(16) NOT NULL,
  hostname VARCHAR(255) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  PRIMARY KEY (list_uuid, hostname),
  CONSTRAINT host_list_member_list
    FOREIGN KEY list (list_uuid)
      REFERENCES host_list (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (15, NOW());
