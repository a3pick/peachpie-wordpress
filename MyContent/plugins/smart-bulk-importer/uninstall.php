<?php
/**
 * Uninstall: remove this plugin's settings and temporary data.
 *
 * Products, attributes, categories and images created by imports are site content
 * and are intentionally kept. Product meta used for re-import matching (_sbpi_*) is
 * kept too, so reinstalling the plugin can still update those products.
 *
 * @package SBPI
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'sbpi_global', 'sbpi_presets', 'sbpi_batches', 'sbpi_cond_texts' ) as $option ) {
	delete_option( $option );
}

// Per-job / per-batch data: sbpi_job_*, sbpi_plan_*, sbpi_state_*, sbpi_pchanges_*, sbpi_pundo_*, sbpi_log_*.
$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name REGEXP '^sbpi_(job|plan|state|pchanges|pundo|log)_'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $names as $option ) {
	delete_option( $option );
}

delete_transient( 'sbpi_lock' );

// Undo snapshots are useless without the plugin.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_sbpi\\_snap\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
