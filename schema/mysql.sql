SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

CREATE TABLE object_class (
  class_name VARCHAR(64) NOT NULL, -- mc_object_class
  PRIMARY KEY (class_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE sender (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  sender_name VARCHAR(32) NOT NULL,    -- mc_tool = ICINGA, OEMS1P.EXAMPLE.COM
  implementation VARCHAR(32) NOT NULL, -- mc_tool_class = MSEND
  PRIMARY KEY (id),
  UNIQUE INDEX(sender_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE input (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE bucket (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  description MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE channel (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  rules MEDIUMTEXT NOT NULL,
  input_implementation TEXT DEFAULT NULL,
  input_uuids TEXT DEFAULT NULL,
  bucket_uuid VARBINARY(16) DEFAULT NULL,
  bucket_name VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label),
  CONSTRAINT channel_bucket
    FOREIGN KEY channel_bucket_uuid (bucket_uuid)
      REFERENCES bucket (uuid)
      ON DELETE RESTRICT
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE api_token (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  permissions TEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE action (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT DEFAULT NULL,
  filter TEXT DEFAULT NULL,
  enabled ENUM('y', 'n') NOT NULL,
  description MEDIUMTEXT DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE problem_handling (
  uuid VARBINARY(16) NOT NULL,
  label VARCHAR(64) NOT NULL,
  instruction_url TEXT DEFAULT NULL,
  trigger_actions ENUM('y', 'n'),
  enabled ENUM('y', 'n'),
  PRIMARY KEY (uuid),
  UNIQUE INDEX(label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE icinga_ci (
  object_id BIGINT(20) UNSIGNED NOT NULL,
  host_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
  object_type ENUM('host', 'service') NOT NULL,
  checksum VARBINARY(20) NOT NULL,
  host_name VARCHAR(128) NOT NULL,
  service_name VARCHAR(128) NULL DEFAULT NULL,
  display_name VARCHAR(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (object_id),
  INDEX idx_search (display_name(172)),
  CONSTRAINT icinga_ci_host
    FOREIGN KEY icinga_ci_host_id (host_id)
      REFERENCES icinga_ci (object_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE icinga_ci_status (
  object_id BIGINT(20) UNSIGNED NOT NULL,
  severity TINYINT UNSIGNED NOT NULL,
  -- inverted_severity TINYINT UNSIGNED NOT NULL,
  -- hard_severity TINYINT UNSIGNED NOT NULL,
  status ENUM(
    'critical', -- and down
    'unknown',
    'warning',
    'pending',
    'ok' -- and up
  ) NOT NULL,
  is_problem ENUM('y', 'n') NOT NULL,
  is_pending ENUM('y', 'n') NOT NULL,
  is_in_downtime ENUM('y', 'n') NOT NULL,
  is_acknowledged ENUM('y', 'n') NOT NULL,
  is_reachable ENUM('y', 'n') NOT NULL,
  -- TODO: is_soft_state ENUM('y', 'n') NOT NULL,
  -- last_state_change
  PRIMARY KEY (object_id),
  INDEX sort_severity (severity)
  -- INDEX sort_severity_rev (severity DESC), -- Not yet, requires MySQL 8
  -- INDEX sort_inverted_severity (inverted_severity),
  -- INDEX sort_hard_severity (hard_severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE icinga_ci_var (
  object_id BIGINT(20) UNSIGNED NOT NULL,
  varname VARCHAR(128) NOT NULL,
  varvalue TEXT NOT NULL,
  varformat ENUM ('string', 'json') NOT NULL,
  PRIMARY KEY (object_id, varname),
  INDEX idx_varname (varname),
  CONSTRAINT icinga_ci_var_ci
    FOREIGN KEY icinga_ci_var_ci_object_id (object_id)
      REFERENCES icinga_ci (object_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

-- clear early
CREATE TABLE raw_event (
  event_uuid VARBINARY(16) NOT NULL,
  input_uuid VARBINARY(16) DEFAULT NULL, -- TODO: NOT NULL
  -- issue_uuid VARBINARY(16) NOT NULL,
  ts_received BIGINT(20) NOT NULL,
  processing_result ENUM (
    'received',
    'failed',
    'ignored',
    'issue_created',
    'issue_refreshed',
    'issue_acknowledged',
    'issue_closed'
  ) NOT NULL,
  error_message TEXT DEFAULT NULL,
  raw_input MEDIUMTEXT NOT NULL,
  input_format ENUM(
    'string',
    'json'
  ) NOT NULL,
  PRIMARY KEY (event_uuid),
  -- INDEX issue (issue_uuid),
  INDEX sender (input_uuid),
  INDEX ts (ts_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE issue (
  issue_uuid VARBINARY(16) NOT NULL,
  status ENUM (
    'closed',
    'in_downtime',
    'acknowledged',
    'open'
  ) NOT NULL,
  severity ENUM (
    'debug',
    'informational',
    'notice',
    'warning',
    'error',
    'critical',
    'alert',
    'emergency'
  ) NOT NULL,
  priority ENUM (
    'lowest',
    'low',
    'normal',
    'high',
    'highest'
  ) NOT NULL,
  input_uuid VARBINARY(16) DEFAULT NULL,
  sender_id INT(10) UNSIGNED NOT NULL,
  sender_event_id VARBINARY(64) NOT NULL, -- mc_tool_key
  -- sha1(json([host_name, object_class, object_name, sender_id, sender_event_id])):
  sender_event_checksum VARBINARY(20) NOT NULL,
  host_name VARCHAR(128) COLLATE utf8mb4_general_ci DEFAULT NULL, -- mc_host
  object_class VARCHAR(128) NOT NULL, --
  object_name VARCHAR(128) COLLATE utf8mb4_general_ci NOT NULL,
  problem_identifier VARCHAR(64) NULL DEFAULT NULL,
  ts_expiration BIGINT(20) NULL DEFAULT NULL,
  ts_first_event BIGINT(20) NOT NULL, -- milliseconds since epoch
  ts_last_modified BIGINT(20) NOT NULL, -- milliseconds since epoch
  cnt_events INT(10) NOT NULL,
  owner VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ticket_ref VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  downtime_config_uuid VARBINARY(16) NULL DEFAULT NULL,
  ts_downtime_triggered BIGINT(20) NULL DEFAULT NULL,
  ts_downtime_expired BIGINT(20) NULL DEFAULT NULL,
  message TEXT COLLATE utf8mb4_general_ci NOT NULL,
  attributes TEXT COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (issue_uuid),
  UNIQUE INDEX sender_event (sender_event_checksum),
  INDEX host_name (host_name),
  INDEX sort_first_event (ts_first_event),
  CONSTRAINT issue_objectclass
    FOREIGN KEY issue_objectclass_class (object_class)
      REFERENCES object_class (class_name)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT,
  CONSTRAINT issue_sender
    FOREIGN KEY issue_sender_id (sender_id)
      REFERENCES sender (id)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE issue_activity (
  activity_uuid VARBINARY(16) NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  ts_modified BIGINT(20) NOT NULL,
  modifications TEXT NOT NULL,
  PRIMARY KEY (activity_uuid),
  INDEX (issue_uuid, ts_modified),
  INDEX (ts_modified),
  CONSTRAINT issue_activity_uuid
  FOREIGN KEY property_issue_uuid (issue_uuid)
    REFERENCES issue (issue_uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO sender (id, sender_name, implementation) VALUES (99999, 'compat', 'Compat');

CREATE TABLE file (
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
  filename_checksum binary(20) NOT NULL,
  ctime bigint(20) NOT NULL,
  PRIMARY KEY (issue_uuid, file_checksum, filename_checksum),
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

CREATE TABLE issue_history (
  issue_uuid VARBINARY(16) NOT NULL,
  severity ENUM (
    'debug',
    'informational',
    'notice',
    'warning',
    'error',
    'critical',
    'alert',
    'emergency'
  ) NOT NULL,
  priority ENUM (
    'lowest',
    'low',
    'normal',
    'high',
    'highest'
  ) NOT NULL,
  close_reason ENUM (
    'recovery',
    'manual',
    'expiration'
  ) NOT NULL,
  closed_by VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  input_uuid VARBINARY(16) DEFAULT NULL,
  sender_id INT(10) UNSIGNED NOT NULL,
  sender_event_id VARBINARY(64) NOT NULL, -- mc_tool_key
  -- sha1(json([host_name, object_class, object_name, sender_id, sender_event_id])):
  sender_event_checksum VARBINARY(20) NOT NULL,
  host_name VARCHAR(128) COLLATE utf8mb4_general_ci DEFAULT NULL, -- mc_host
  object_class VARCHAR(128) NOT NULL, --
  object_name VARCHAR(128) COLLATE utf8mb4_general_ci NOT NULL,
  problem_identifier VARCHAR(64) NULL DEFAULT NULL,
  ts_expiration BIGINT(20) NULL DEFAULT NULL,
  ts_first_event BIGINT(20) NOT NULL, -- milliseconds since epoch
  ts_last_modified BIGINT(20) NOT NULL, -- milliseconds since epoch
  cnt_events INT(10) NOT NULL,
  owner VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ticket_ref VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  downtime_config_uuid VARBINARY(16) NULL DEFAULT NULL,
  ts_downtime_triggered BIGINT(20) NULL DEFAULT NULL,
  ts_downtime_expired BIGINT(20) NULL DEFAULT NULL,
  message TEXT COLLATE utf8mb4_general_ci NOT NULL,
  attributes TEXT COLLATE utf8mb4_general_ci NOT NULL,
  activities MEDIUMTEXT NOT NULL, -- json([{ts:123,modifications:{}]) ggf: username:"",ip:""?
  PRIMARY KEY (issue_uuid),
  INDEX host_name (host_name),
  INDEX sort_first_event (ts_first_event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

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

-- activity log? Author would be nice
CREATE TABLE downtime_rule (
  uuid VARBINARY(16) NOT NULL,
  time_definition TEXT NULL DEFAULT NULL COMMENT 'cron-style when recurring, at-style when not',
  filter_definition TEXT NULL DEFAULT NULL,
  label VARCHAR(128) NOT NULL,
  message TEXT NOT NULL,
  timezone VARCHAR(64) NOT NULL,
  config_uuid VARBINARY(16) NOT NULL, -- uuid5(uuid, json(downtime_rule))
  host_list_uuid VARBINARY(16) DEFAULT NULL,
  next_calculated_uuid VARBINARY(16) NULL DEFAULT NULL,
  is_enabled ENUM('y', 'n') NOT NULL,
  is_recurring ENUM('y', 'n') NOT NULL,
  ts_not_before BIGINT(20) UNSIGNED NULL DEFAULT NULL,
  ts_not_after BIGINT(20) UNSIGNED NULL DEFAULT NULL,
  ts_triggered BIGINT(20) UNSIGNED DEFAULT NULL,
  duration INT(10) UNSIGNED NULL DEFAULT NULL,
  max_single_problem_duration INT(10) UNSIGNED NULL DEFAULT NULL,
  on_iteration_end_issue_status ENUM('open', 'closed') NOT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX idx_sort(label),
  UNIQUE INDEX config(config_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE issue_downtime_history (
  ts_modification BIGINT UNSIGNED NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  rule_uuid VARBINARY(16) NULL DEFAULT NULL,
  rule_config_uuid VARBINARY(16) NULL DEFAULT NULL,
  action ENUM('activated', 'deactivated') NOT NULL,
  PRIMARY KEY (ts_modification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

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

CREATE TABLE action_history (
  uuid VARBINARY(16) NOT NULL,
  action_uuid VARBINARY(16) NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  ts_done BIGINT(20) NOT NULL,
  success ENUM('y', 'n') NOT NULL,
  message TEXT NOT NULL,
  PRIMARY KEY (uuid),
  INDEX timestamp (ts_done)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE daemon_info (
  instance_uuid_hex VARCHAR(32) NOT NULL, -- random by daemon
  schema_version SMALLINT UNSIGNED NOT NULL,
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INT UNSIGNED NOT NULL,
  binary_path VARCHAR(128) NOT NULL,
  binary_realpath VARCHAR(128) NOT NULL,
  php_binary_path VARCHAR(128) NOT NULL,
  php_binary_realpath VARCHAR(128) NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  php_integer_size SMALLINT NOT NULL,
  running_with_systemd ENUM('y', 'n') NOT NULL,
  ts_started BIGINT(20) NOT NULL,
  ts_stopped BIGINT(20) DEFAULT NULL,
  ts_last_modification BIGINT(20) DEFAULT NULL,
  ts_last_update BIGINT(20) DEFAULT NULL,
  process_info MEDIUMTEXT NOT NULL,
  PRIMARY KEY (instance_uuid_hex)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE eventtracker_schema_migration (
  schema_version SMALLINT UNSIGNED NOT NULL,
  migration_time DATETIME NOT NULL,
  PRIMARY KEY(schema_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (26, NOW());
