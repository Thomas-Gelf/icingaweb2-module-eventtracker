CREATE TABLE raw_event (
  event_uuid varbinary(16) NOT NULL,
  input_uuid varbinary(16) DEFAULT NULL,
  ts_received bigint(20) NOT NULL,
  processing_result enum('received','failed','ignored','issue_created','issue_refreshed','issue_acknowledged','issue_closed') COLLATE utf8mb4_bin NOT NULL,
  error_message text COLLATE utf8mb4_bin,
  raw_input mediumtext COLLATE utf8mb4_bin NOT NULL,
  input_format enum('string','json') COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (event_uuid),
  KEY sender (input_uuid),
  KEY ts (ts_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (13, NOW());
