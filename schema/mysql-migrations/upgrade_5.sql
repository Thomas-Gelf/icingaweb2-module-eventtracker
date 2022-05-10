CREATE TABLE file(
  checksum binary(20) NOT NULL COMMENT 'sha1(data)',
  data mediumblob NOT NULL COMMENT 'maximum length of 16777215 (2^24 - 1) bytes, or 16MB in storage',
  size mediumint unsigned NOT NULL COMMENT 'max value 16777215',
  mime_type varchar(255) NOT NULL,
  ctime bigint(20) NOT NULL,
  PRIMARY KEY (checksum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE issue_file (
  issue_uuid VARBINARY(16) NOT NULL,
  file_checksum binary(20) NOT NULL,
  filename varchar(255) NOT NULL,
  ctime bigint(20) NOT NULL,
  PRIMARY KEY (issue_uuid, file_checksum),
  CONSTRAINT fk_issue_file_issue
    FOREIGN KEY (issue_uuid)
      REFERENCES issue (issue_uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT fk_issue_file_file
    FOREIGN KEY (file_checksum)
      REFERENCES file (checksum)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (5, NOW());
