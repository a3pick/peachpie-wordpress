<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin tables.
 *
 *  refs      - every reference (id / path / basename) discovered while scanning.
 *  media     - one row per attachment with the computed usage status.
 *  orphans   - files in uploads that are not part of the media library.
 *  converted - WebP conversion ledger.
 *  map       - old -> new relative path map used by the link rewriter.
 */
class MSW_DB {

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		$sql   = array();
		$sql[] = 'CREATE TABLE ' . MSW_Util::t( 'refs' ) . " (
  ref varchar(191) NOT NULL,
  src varchar(120) NOT NULL DEFAULT '',
  PRIMARY KEY  (ref)
) $c;";

		$sql[] = 'CREATE TABLE ' . MSW_Util::t( 'media' ) . " (
  attachment_id bigint(20) unsigned NOT NULL,
  type varchar(12) NOT NULL DEFAULT '',
  mime varchar(100) NOT NULL DEFAULT '',
  rel_path varchar(600) NOT NULL DEFAULT '',
  path_key varchar(191) NOT NULL DEFAULT '',
  base_key varchar(191) NOT NULL DEFAULT '',
  bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  width int(10) unsigned NOT NULL DEFAULT 0,
  height int(10) unsigned NOT NULL DEFAULT 0,
  file_hash char(32) DEFAULT NULL,
  status varchar(10) NOT NULL DEFAULT 'unused',
  locked varchar(10) NOT NULL DEFAULT '',
  used_in varchar(125) NOT NULL DEFAULT '',
  parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  poster_id bigint(20) unsigned NOT NULL DEFAULT 0,
  missing tinyint(1) NOT NULL DEFAULT 0,
  theme_ref tinyint(1) NOT NULL DEFAULT 0,
  uploaded datetime DEFAULT NULL,
  PRIMARY KEY  (attachment_id),
  KEY status_idx (status,locked,type),
  KEY path_key (path_key),
  KEY base_key (base_key),
  KEY file_hash (file_hash),
  KEY bytes (bytes)
) $c;";

		$sql[] = 'CREATE TABLE ' . MSW_Util::t( 'orphans' ) . " (
  path_hash char(32) NOT NULL,
  rel_path varchar(600) NOT NULL DEFAULT '',
  bytes bigint(20) unsigned NOT NULL DEFAULT 0,
  mtime int(10) unsigned NOT NULL DEFAULT 0,
  status varchar(12) NOT NULL DEFAULT 'orphan',
  PRIMARY KEY  (path_hash),
  KEY status (status)
) $c;";

		$sql[] = 'CREATE TABLE ' . MSW_Util::t( 'converted' ) . " (
  attachment_id bigint(20) unsigned NOT NULL,
  status varchar(12) NOT NULL DEFAULT 'pending',
  reason varchar(190) NOT NULL DEFAULT '',
  old_files longtext,
  bytes_before bigint(20) unsigned NOT NULL DEFAULT 0,
  bytes_after bigint(20) unsigned NOT NULL DEFAULT 0,
  updated datetime DEFAULT NULL,
  delete_after datetime DEFAULT NULL,
  PRIMARY KEY  (attachment_id),
  KEY status (status)
) $c;";

		$sql[] = 'CREATE TABLE ' . MSW_Util::t( 'map' ) . " (
  old_rel varchar(191) NOT NULL,
  new_rel varchar(600) NOT NULL DEFAULT '',
  att_id bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (old_rel),
  KEY att_id (att_id)
) $c;";

		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
		update_option( 'msw_db_version', MSW_DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'msw_db_version' ) !== MSW_DB_VERSION ) {
			self::install();
		}
	}

	public static function drop() {
		global $wpdb;
		foreach ( array( 'refs', 'media', 'orphans', 'converted', 'map' ) as $t ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . MSW_Util::t( $t ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
	}

	public static function truncate( $name ) {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . MSW_Util::t( $name ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
