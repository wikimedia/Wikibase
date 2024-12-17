-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: repo/sql/abstract/wb_property_info.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/wb_property_info (
  pi_property_id INT UNSIGNED NOT NULL,
  pi_type VARBINARY(32) NOT NULL,
  pi_info BLOB NOT NULL,
  INDEX pi_type (pi_type),
  PRIMARY KEY(pi_property_id)
) /*$wgDBTableOptions*/;
