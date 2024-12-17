-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: repo/sql/abstract/wb_changes.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/wb_changes (
  change_id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  change_type VARCHAR(25) NOT NULL,
  change_time BINARY(14) NOT NULL,
  change_object_id VARBINARY(14) NOT NULL,
  change_revision_id INT UNSIGNED NOT NULL,
  change_user_id INT UNSIGNED NOT NULL,
  change_info MEDIUMBLOB NOT NULL,
  INDEX wb_changes_change_time (change_time),
  INDEX wb_changes_change_revision_id (change_revision_id),
  INDEX change_object_id (change_object_id),
  PRIMARY KEY(change_id)
) /*$wgDBTableOptions*/;
