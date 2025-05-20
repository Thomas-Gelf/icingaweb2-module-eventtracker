UPDATE problem_handling SET trigger_actions = 'y', enabled = 'y';

ALTER TABLE problem_handling
  MODIFY COLUMN trigger_actions ENUM('y', 'n') NOT NULL,
  MODIFY COLUMN enabled ENUM('y', 'n') NOT NULL;

INSERT INTO eventtracker_schema_migration
  (schema_version, migration_time)
VALUES (29, NOW());
