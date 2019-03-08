SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,ANSI_QUOTES,ERROR_FOR_DIVISION_BY_ZERO';

CREATE TABLE object_class (
  class_name VARCHAR(32) NOT NULL, -- mc_object_class
  PRIMARY KEY (class_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE sender (
  id INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
  sender_name VARCHAR(32) NOT NULL,    -- mc_tool = ICINGA, OEMS1P.EXAMPLE.COM
  implementation VARCHAR(32) NOT NULL, -- mc_tool_class = MSEND
  PRIMARY KEY (id),
  UNIQUE INDEX(sender_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE incident (
  incident_uuid VARBINARY(16) NOT NULL,
  status ENUM (
    'open',
    'acknowledged',
    'in_downtime',
    'closed'
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
  host_name VARCHAR(64) COLLATE utf8mb4_general_ci NOT NULL, -- mc_host
  object_class VARCHAR(64) NOT NULL, --
  object_name VARCHAR(128) COLLATE utf8mb4_general_ci NOT NULL,
  ts_expiration BIGINT(20) NULL DEFAULT NULL,
  ts_first_event BIGINT(20) NOT NULL, -- milliseconds since epoch
  ts_last_modified BIGINT(20) NOT NULL, -- milliseconds since epoch
  cnt_events INT(10) NOT NULL,
  owner VARCHAR(64) COLLATE utf8mb4_general_ci NULL DEFAULT NULL,
  message TEXT COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (incident_uuid),
  UNIQUE INDEX sender_event (sender_event_checksum),
  INDEX host_name (host_name),
  INDEX sort_first_event (ts_first_event),
  CONSTRAINT incident_objectclass
    FOREIGN KEY incident_objectclass_class (object_class)
    REFERENCES object_class (class_name)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT,
  CONSTRAINT incident_sender
    FOREIGN KEY incident_sender_id (sender_id)
    REFERENCES sender (id)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE incident_change (
  incident_uuid VARBINARY(16) NOT NULL,
  change_type ENUM(
    'state_change',
    'acknowledged',
    'unacknowledged',
    'downtime_start',
    'downtime_end',
    'comment'
  ),
  author_name VARCHAR(64) NOT NULL,
  comment TEXT,
  CONSTRAINT change_incident_uuid
    FOREIGN KEY change_incident_uuid (incident_uuid)
    REFERENCES incident (incident_uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;


CREATE TABLE incident_property (
  incident_uuid VARBINARY(16) NOT NULL,
  property_name VARCHAR(64) NOT NULL,
  property_value TEXT,
  CONSTRAINT incident_uuid
    FOREIGN KEY property_incident_uuid (incident_uuid)
    REFERENCES incident (incident_uuid)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

-- mc_parameter: Tablespace Name,AUD_INDX,Table Space Used(%);
-- mc_parameter_value: 99
