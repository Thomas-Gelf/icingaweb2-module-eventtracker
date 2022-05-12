ALTER TABLE issue_file ADD COLUMN filename_checksum binary(20) NOT NULL AFTER filename;
UPDATE issue_file SET filename_checksum = UNHEX(SHA1(filename));
ALTER TABLE issue_file DROP PRIMARY KEY, ADD PRIMARY KEY (issue_uuid, file_checksum, filename_checksum);

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (6, NOW());
