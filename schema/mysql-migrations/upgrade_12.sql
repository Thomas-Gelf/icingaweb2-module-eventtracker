CREATE TABLE bucket (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  description MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

ALTER TABLE channel
  ADD COLUMN bucket_uuid VARBINARY(16) DEFAULT NULL,
  ADD COLUMN bucket_name VARCHAR(255) DEFAULT NULL,
  ADD CONSTRAINT channel_bucket
    FOREIGN KEY channel_bucket_uuid (bucket_uuid)
      REFERENCES bucket (uuid)
      ON DELETE RESTRICT
      ON UPDATE CASCADE;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (12, NOW());
