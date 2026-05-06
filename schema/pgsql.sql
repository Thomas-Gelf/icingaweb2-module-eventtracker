CREATE TYPE yes_no_enum AS ENUM ('y','n');
CREATE TYPE object_type_enum AS ENUM ('host','service');
CREATE TYPE status_enum AS ENUM ('critical','unknown','warning','pending','ok');
CREATE TYPE processing_result_enum AS ENUM (
    'received','failed','ignored','issue_created','issue_refreshed','issue_acknowledged','issue_closed'
);
CREATE TYPE input_format_enum AS ENUM ('string','json');
CREATE TYPE issue_status_enum AS ENUM ('closed','in_downtime','acknowledged','open');
CREATE TYPE issue_severity_enum AS ENUM (
    'debug','informational','notice','warning','error','critical','alert','emergency'
);
CREATE TYPE issue_priority_enum AS ENUM ('lowest','low','normal','high','highest');
CREATE TYPE close_reason_enum AS ENUM ('recovery','manual','expiration');
CREATE TYPE downtime_action_enum AS ENUM ('activated','deactivated');
CREATE TYPE config_action_enum AS ENUM ('create','modify','delete');
CREATE TYPE on_iteration_end_enum AS ENUM ('open','closed');

CREATE TABLE object_class (
  class_name VARCHAR(64),
  PRIMARY KEY (class_name)
);

CREATE TABLE sender (
  id SERIAL,
  sender_name VARCHAR(32) NOT NULL,
  implementation VARCHAR(32) NOT NULL,
  PRIMARY KEY (id)
);
CREATE UNIQUE INDEX idx_sender_sender_name ON sender(sender_name);

CREATE TABLE input (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_input_label on input(label);

CREATE TABLE bucket (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT,
  description TEXT,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX  idx_bucket_label on bucket(label);

CREATE TABLE channel (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  rules TEXT NOT NULL,
  input_implementation TEXT,
  input_uuids TEXT,
  bucket_uuid BYTEA,
  bucket_name VARCHAR(255),
  PRIMARY KEY (uuid),
  CONSTRAINT channel_bucket
    FOREIGN KEY (bucket_uuid)
      REFERENCES bucket (uuid)
      ON DELETE RESTRICT
      ON UPDATE CASCADE
);

CREATE UNIQUE INDEX idx_channel_label on channel(label);
CREATE UNIQUE INDEX idx_chanel_bucket_uuid on channel(bucket_uuid);

CREATE TABLE api_token (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  permissions TEXT,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_api_token_label on api_token(label);

CREATE TABLE action (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  implementation VARCHAR(64) NOT NULL,
  settings TEXT,
  filter TEXT,
  enabled yes_no_enum NOT NULL,
  description TEXT,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_action_label on action(label);

CREATE TABLE map (
  uuid BYTEA,
  label VARCHAR(32) NOT NULL,
  mappings TEXT NOT NULL,
  settings TEXT,
  description TEXT,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_map_label on map(label);

CREATE TABLE problem_handling (
  uuid BYTEA,
  label VARCHAR(64) NOT NULL,
  instruction_url TEXT,
  trigger_actions yes_no_enum NOT NULL,
  enabled yes_no_enum NOT NULL,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_problem_handling_label ON problem_handling(label);

CREATE TABLE icinga_ci (
  object_id BIGSERIAL,
  host_id BIGINT,
  object_type object_type_enum NOT NULL,
  checksum BYTEA NOT NULL,
  host_name VARCHAR(128) NOT NULL,
  service_name VARCHAR(128),
  display_name VARCHAR(255) NOT NULL,
  PRIMARY KEY (object_id),
  CONSTRAINT icinga_ci_host
    FOREIGN KEY (host_id)
      REFERENCES icinga_ci (object_id)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);
CREATE INDEX idx_icinga_ci_display_name on icinga_ci(display_name);
CREATE INDEX idx_icinga_ci_host_id on icinga_ci(host_id);

CREATE TABLE icinga_ci_status (
  object_id BIGINT NOT NULL,
  severity SMALLINT NOT NULL CHECK (severity >= 0),
  status status_enum NOT NULL,
  is_problem yes_no_enum NOT NULL,
  is_pending yes_no_enum NOT NULL,
  is_in_downtime yes_no_enum NOT NULL,
  is_acknowledged yes_no_enum NOT NULL,
  is_reachable yes_no_enum NOT NULL,
  PRIMARY KEY (object_id)
);
CREATE INDEX sort_severity ON icinga_ci_status(severity);

CREATE TABLE icinga_ci_var (
  object_id BIGINT NOT NULL CHECK (object_id >= 0),
  varname VARCHAR(128) NOT NULL,
  varvalue TEXT NOT NULL,
  varformat input_format_enum NOT NULL,
  PRIMARY KEY (object_id, varname),
  CONSTRAINT icinga_ci_var_ci
    FOREIGN KEY (object_id)
       REFERENCES icinga_ci(object_id)
       ON DELETE CASCADE
       ON UPDATE CASCADE
);

CREATE INDEX idx_varname ON icinga_ci_var(varname);
CREATE INDEX idx_icinga_ci_var_ci_object_id ON icinga_ci_var(object_id);

--clear early
CREATE TABLE raw_event (
  event_uuid BYTEA,
  input_uuid BYTEA DEFAULT NULL,
  ts_received BIGINT NOT NULL,
  processing_result processing_result_enum NOT NULL,
  error_message TEXT DEFAULT NULL,
  raw_input TEXT NOT NULL,
  input_format input_format_enum NOT NULL,
  PRIMARY KEY (event_uuid)
);

CREATE INDEX idx_raw_event_sender ON raw_event(input_uuid);
CREATE INDEX ts ON raw_event(ts_received);

CREATE TABLE issue (
  issue_uuid BYTEA,
  status issue_status_enum NOT NULL,
  severity issue_severity_enum NOT NULL,
  priority issue_priority_enum NOT NULL,
  input_uuid BYTEA DEFAULT NULL,
  sender_id INTEGER NOT NULL CHECK (sender_id >= 0),
  sender_event_id BYTEA NOT NULL,
  sender_event_checksum BYTEA NOT NULL UNIQUE,
  host_name VARCHAR(128) DEFAULT NULL,
  object_class VARCHAR(128) NOT NULL,
  object_name VARCHAR(128) NOT NULL,
  problem_identifier VARCHAR(64) DEFAULT NULL,
  ts_expiration BIGINT DEFAULT NULL,
  ts_first_event BIGINT NOT NULL,
  ts_last_modified BIGINT NOT NULL,
  cnt_events INTEGER NOT NULL,
  owner VARCHAR(64) DEFAULT NULL,
  ticket_ref VARCHAR(64) DEFAULT NULL,
  downtime_rule_uuid BYTEA DEFAULT NULL,
  downtime_config_uuid BYTEA DEFAULT NULL,
  ts_downtime_triggered BIGINT DEFAULT NULL,
  ts_downtime_expired BIGINT DEFAULT NULL,
  message TEXT NOT NULL,
  attributes TEXT NOT NULL,
  PRIMARY KEY (issue_uuid),
  CONSTRAINT issue_objectclass
    FOREIGN KEY (object_class)
      REFERENCES object_class (class_name)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT,
  CONSTRAINT issue_sender
    FOREIGN KEY (sender_id)
      REFERENCES sender (id)
      ON DELETE RESTRICT
      ON UPDATE RESTRICT
);
CREATE UNIQUE INDEX sender_event ON issue(sender_event_checksum);
CREATE INDEX idx_issue_host_name ON issue(host_name);
CREATE INDEX idx_issue_sort_first_event ON issue(ts_first_event);
CREATE INDEX idx_issue_issue_objectclass_class ON issue(object_class);
CREATE INDEX idx_issue_issue_sender_id ON issue(sender_id);

CREATE TABLE issue_activity (
  activity_uuid BYTEA,
  issue_uuid BYTEA NOT NULL,
  ts_modified BIGINT NOT NULL,
  modifications TEXT NOT NULL,
  PRIMARY KEY (activity_uuid),
  CONSTRAINT issue_activity
    FOREIGN KEY (issue_uuid)
      REFERENCES issue(issue_uuid)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
CREATE INDEX idx_issue_activity_issue_ts ON issue_activity(issue_uuid, ts_modified);
CREATE INDEX idx_issue_activity_ts ON issue_activity(ts_modified);
CREATE INDEX idx_issue_activity_uuid ON issue_activity(issue_uuid);

INSERT INTO sender(id, sender_name, implementation) VALUES (99999, 'compat', 'Compat');

CREATE TABLE file (
  checksum BYTEA,
  data BYTEA NOT NULL,
  size INTEGER NOT NULL CHECK (size >= 0),
  mime_type VARCHAR(255) NOT NULL,
  ctime BIGINT NOT NULL,
  PRIMARY KEY (checksum)
);

CREATE TABLE issue_file (
  issue_uuid BYTEA NOT NULL,
  file_checksum BYTEA NOT NULL,
  filename VARCHAR(255) NOT NULL,
  filename_checksum BYTEA NOT NULL,
  ctime BIGINT NOT NULL,
  PRIMARY KEY (issue_uuid, file_checksum, filename_checksum),
  CONSTRAINT fk_issue_file_issue
    FOREIGN KEY (issue_uuid)
      REFERENCES issue(issue_uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT fk_issue_file_file
    FOREIGN KEY (file_checksum)
      REFERENCES file(checksum)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE INDEX issue_file_issue_uuid ON issue_file(issue_uuid);

CREATE TABLE issue_history (
  issue_uuid BYTEA,
  severity issue_severity_enum NOT NULL,
  priority issue_priority_enum NOT NULL,
  close_reason close_reason_enum NOT NULL,
  closed_by VARCHAR(64) DEFAULT NULL,
  input_uuid BYTEA DEFAULT NULL,
  sender_id INTEGER NOT NULL CHECK (sender_id >= 0),
  sender_event_id BYTEA NOT NULL,
  sender_event_checksum BYTEA NOT NULL,
  host_name VARCHAR(128) DEFAULT NULL,
  object_class VARCHAR(128) NOT NULL,
  object_name VARCHAR(128) NOT NULL,
  problem_identifier VARCHAR(64) DEFAULT NULL,
  ts_expiration BIGINT DEFAULT NULL,
  ts_first_event BIGINT NOT NULL,
  ts_last_modified BIGINT NOT NULL,
  cnt_events INTEGER NOT NULL,
  owner VARCHAR(64) DEFAULT NULL,
  ticket_ref VARCHAR(64) DEFAULT NULL,
  downtime_rule_uuid BYTEA DEFAULT NULL,
  downtime_config_uuid BYTEA DEFAULT NULL,
  ts_downtime_triggered BIGINT DEFAULT NULL,
  ts_downtime_expired BIGINT DEFAULT NULL,
  message TEXT NOT NULL,
  attributes TEXT NOT NULL,
  activities TEXT NOT NULL,
  PRIMARY KEY (issue_uuid)
);
CREATE INDEX idx_issue_history_host_name ON issue_history(host_name);
CREATE INDEX sort_first_event ON issue_history(ts_first_event);

CREATE TABLE host_list (
  uuid BYTEA,
  label VARCHAR(128) NOT NULL UNIQUE,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_label ON host_list(label);

CREATE TABLE host_list_member (
  list_uuid BYTEA NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  PRIMARY KEY (list_uuid, hostname),
  CONSTRAINT host_list_member
    FOREIGN KEY (list_uuid)
      REFERENCES host_list(uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
);

CREATE INDEX host_list_member_list_uuid ON host_list_member(list_uuid);

CREATE TABLE downtime_rule (
  uuid BYTEA,
  time_definition TEXT DEFAULT NULL,
  filter_definition TEXT DEFAULT NULL,
  label VARCHAR(128) NOT NULL UNIQUE,
  message TEXT NOT NULL,
  timezone VARCHAR(64) NOT NULL,
  config_uuid BYTEA NOT NULL UNIQUE,
  host_list_uuid BYTEA DEFAULT NULL,
  is_enabled yes_no_enum NOT NULL,
  is_recurring yes_no_enum NOT NULL,
  ts_not_before BIGINT DEFAULT NULL CHECK (ts_not_before >= 0),
  ts_not_after BIGINT DEFAULT NULL CHECK (ts_not_after >= 0),
  ts_triggered BIGINT DEFAULT NULL CHECK (ts_triggered >= 0),
  duration INTEGER DEFAULT NULL CHECK (duration >= 0),
  max_single_problem_duration INTEGER DEFAULT NULL CHECK (max_single_problem_duration >= 0),
  on_iteration_end_issue_status on_iteration_end_enum NOT NULL,
  PRIMARY KEY (uuid)
);

CREATE UNIQUE INDEX idx_sort ON downtime_rule(label);
CREATE UNIQUE INDEX config ON downtime_rule(config_uuid);

CREATE TABLE issue_downtime_history (
  ts_modification BIGINT CHECK (ts_modification >= 0),
  issue_uuid BYTEA NOT NULL,
  rule_uuid BYTEA DEFAULT NULL,
  rule_config_uuid BYTEA DEFAULT NULL,
  action downtime_action_enum NOT NULL,
  PRIMARY KEY (ts_modification)
);

CREATE TABLE config_history (
  ts_modification BIGINT CHECK (ts_modification >= 0),
  action config_action_enum NOT NULL,
  object_uuid BYTEA NOT NULL,
  config_uuid BYTEA NOT NULL,
  object_type VARCHAR(32) NOT NULL,
  label VARCHAR(255) NOT NULL,
  properties_old TEXT DEFAULT NULL,
  properties_new TEXT DEFAULT NULL,
  author VARCHAR(255) NOT NULL,
  PRIMARY KEY (ts_modification)
);
CREATE INDEX idx_rule_history ON config_history(object_uuid, ts_modification DESC);

CREATE TABLE action_history (
  uuid BYTEA,
  action_uuid BYTEA NOT NULL,
  issue_uuid BYTEA NOT NULL,
  ts_done BIGINT NOT NULL,
  success yes_no_enum NOT NULL,
  message TEXT NOT NULL,
  PRIMARY KEY (uuid)
);
CREATE INDEX timestamp ON action_history(ts_done);

CREATE TABLE daemon_info (
  instance_uuid_hex VARCHAR(32),
  schema_version SMALLINT NOT NULL CHECK (schema_version >= 0),
  fqdn VARCHAR(255) NOT NULL,
  username VARCHAR(64) NOT NULL,
  pid INTEGER NOT NULL CHECK (pid >= 0),
  binary_path VARCHAR(128) NOT NULL,
  binary_realpath VARCHAR(128) NOT NULL,
  php_binary_path VARCHAR(128) NOT NULL,
  php_binary_realpath VARCHAR(128) NOT NULL,
  php_version VARCHAR(64) NOT NULL,
  php_integer_size SMALLINT NOT NULL,
  running_with_systemd yes_no_enum NOT NULL,
  ts_started BIGINT NOT NULL,
  ts_stopped BIGINT DEFAULT NULL,
  ts_last_modification BIGINT DEFAULT NULL,
  ts_last_update BIGINT DEFAULT NULL,
  process_info TEXT NOT NULL,
  PRIMARY KEY (instance_uuid_hex)
);

CREATE TABLE eventtracker_schema_migration (
  schema_version SMALLINT NOT NULL CHECK (schema_version >= 0),
  migration_time TIMESTAMP NOT NULL,
  PRIMARY KEY (schema_version)
);

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (31, NOW());
