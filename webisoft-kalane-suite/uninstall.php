<?php
/**
 * پاک‌سازی هنگام حذف افزونه (فقط اگر در تنظیمات «سیستم» اجازه داده شده باشد).
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$opts = get_option( 'wsk_options', array() );
if ( empty( $opts['system']['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
delete_option( 'wsk_options' );
delete_option( 'wsk_db_version' );
delete_option( 'wsk_cache_gen' );
wp_clear_scheduled_hook( 'wsk_prune_stats' );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wsk_stats" ); // phpcs:ignore WordPress.DB
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wsk_views' ) ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wsk\_%' OR option_name LIKE '\_transient\_timeout\_wsk\_%'" ); // phpcs:ignore WordPress.DB
