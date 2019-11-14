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
  sender_id INT(10) UNSIGNED NOT NULL,
  sender_event_id VARBINARY(64) NOT NULL, -- mc_tool_key
  -- sha1(json([host_name, object_class, object_name, sender_id, sender_event_id])):
  sender_event_checksum VARBINARY(20) NOT NULL,
  host_name VARCHAR(128) COLLATE utf8mb4_general_ci DEFAULT NULL, -- mc_host
  object_class VARCHAR(128) NOT NULL, --
  object_name VARCHAR(128) COLLATE utf8mb4_general_ci NOT NULL,
  ts_expiration BIGINT(20) NULL DEFAULT NULL,
  ts_first_event BIGINT(20) NOT NULL, -- milliseconds since epoch
  ts_last_modified BIGINT(20) NOT NULL, -- milliseconds since epoch
  cnt_events INT(10) NOT NULL,
  owner VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ticket_ref VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
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
  sender_id INT(10) UNSIGNED NOT NULL,
  sender_event_id VARBINARY(64) NOT NULL, -- mc_tool_key
  -- sha1(json([host_name, object_class, object_name, sender_id, sender_event_id])):
  sender_event_checksum VARBINARY(20) NOT NULL,
  host_name VARCHAR(128) COLLATE utf8mb4_general_ci DEFAULT NULL, -- mc_host
  object_class VARCHAR(128) NOT NULL, --
  object_name VARCHAR(128) COLLATE utf8mb4_general_ci NOT NULL,
  ts_expiration BIGINT(20) NULL DEFAULT NULL,
  ts_first_event BIGINT(20) NOT NULL, -- milliseconds since epoch
  ts_last_modified BIGINT(20) NOT NULL, -- milliseconds since epoch
  cnt_events INT(10) NOT NULL,
  owner VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  ticket_ref VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  message TEXT COLLATE utf8mb4_general_ci NOT NULL,
  attributes TEXT COLLATE utf8mb4_general_ci NOT NULL,
  activities MEDIUMTEXT NOT NULL, -- json([{ts:123,modifications:{}]) ggf: username:"",ip:""?
  PRIMARY KEY (issue_uuid),
  INDEX host_name (host_name),
  INDEX sort_first_event (ts_first_event)
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
