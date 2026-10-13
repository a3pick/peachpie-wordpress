<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Data is only removed when "Remove all data on uninstall" is enabled in settings.
 * Quarantined files are always restored first so nothing stays hidden.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'MSW_DIR', plugin_dir_path( __FILE__ ) );
require_once MSW_DIR . 'includes/class-msw-util.php';
require_once MSW_DIR . 'includes/class-msw-db.php';
require_once MSW_DIR . 'includes/class-msw-quarantine.php';

$msw_settings = get_option( 'msw_settings', array() );
if ( ! empty( $msw_settings['remove_data'] ) ) {
	MSW_Quarantine::register_status();
	MSW_Quarantine::restore_all();
	MSW_Util::rrmdir( MSW_Util::abs( 'msw-quarantine' ) );
	MSW_DB::drop();
	foreach ( array( 'msw_settings', 'msw_db_version', 'msw_job', 'msw_log', 'msw_last_scan', 'msw_lock', 'msw_acf', 'msw_filelist', 'msw_stats' ) as $msw_opt ) {
		delete_option( $msw_opt );
	}
	delete_post_meta_by_key( '_msw_protected' );
}
