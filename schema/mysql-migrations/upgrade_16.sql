-- activity log? Author would be nice
CREATE TABLE downtime_rule (
  uuid VARBINARY(16) NOT NULL,
  time_definition TEXT NULL DEFAULT NULL COMMENT 'cron-style when recurring, at-style when not',
  filter_definition TEXT NOT NULL,
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
  duration INT(10) UNSIGNED NULL DEFAULT NULL,
  max_single_problem_duration INT(10) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (uuid),
  UNIQUE INDEX idx_sort(label),
  UNIQUE INDEX config(config_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

CREATE TABLE downtime_calculated (
  uuid VARBINARY(16) NOT NULL, -- uuid5(rule_config_uuid, ts_expected_start)
  rule_uuid VARBINARY(16) NOT NULL, -- important for cleanup?
  rule_config_uuid VARBINARY(16) NULL DEFAULT NULL,
  ts_expected_start BIGINT(20) UNSIGNED NOT NULL,
  ts_expected_end BIGINT(20) UNSIGNED NOT NULL,
  is_active ENUM('y', 'n') NOT NULL,
  ts_started BIGINT(20) UNSIGNED DEFAULT NULL,
  ts_triggered BIGINT(20) UNSIGNED DEFAULT NULL, -- isn't this the same as ts_started????
  PRIMARY KEY (uuid),
  UNIQUE INDEX (rule_config_uuid, ts_expected_start),
  CONSTRAINT downtime_calculated_rule_config
    FOREIGN KEY rule_config (rule_config_uuid)
      REFERENCES downtime_rule (config_uuid)
      ON DELETE CASCADE
      ON UPDATE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

ALTER TABLE downtime_rule
  ADD CONSTRAINT downtime_rule_next_calculated
    FOREIGN KEY calculated (next_calculated_uuid)
    REFERENCES downtime_calculated (uuid)
    ON DELETE SET NULL
       ON UPDATE RESTRICT;

CREATE TABLE downtime_affected_issue (
  calculation_uuid VARBINARY(16) NOT NULL,
  issue_uuid VARBINARY(16) NOT NULL,
  ts_triggered BIGINT(20) UNSIGNED NOT NULL,
  ts_scheduled_end BIGINT(20) UNSIGNED NOT NULL, -- min(dr.ts_not_after, ts_triggered + dr.duration)
  assignment ENUM('manual', 'rule') NOT NULL,
  assigned_by VARCHAR(255) NULL DEFAULT NULL,
  author VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (calculation_uuid, issue_uuid),
  CONSTRAINT calculated_downtime_rule
    FOREIGN KEY downtime_calculated_uuid (calculation_uuid)
      REFERENCES downtime_calculated (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  CONSTRAINT affected_issue_issue
    FOREIGN KEY issue (issue_uuid)
      REFERENCES downtime_calculated (uuid)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_bin;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (16, NOW());
