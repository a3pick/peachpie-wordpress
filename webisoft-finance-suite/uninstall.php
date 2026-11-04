<?php
/**
 * پاک‌سازی هنگام حذف افزونه (فقط اگر کاربر در تنظیمات «سیستم» اجازه داده باشد).
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$opts = get_option( 'wsf_options', array() );
if ( empty( $opts['system']['delete_on_uninstall'] ) ) {
	return;
}

delete_option( 'wsf_options' );

$leads = get_posts(
	array(
		'post_type'      => 'wsf_lead',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $leads as $lead_id ) {
	wp_delete_post( $lead_id, true );
}
delete_post_meta_by_key( '_wsf_likes' );
