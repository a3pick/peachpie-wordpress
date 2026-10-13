<?php
/**
 * Plugin Name: Media Sweeper Pro
 * Plugin URI:  https://example.com/media-sweeper
 * Description: شناسایی رسانه‌های استفاده‌نشده (عکس، ویدئو، صدا)، پیش‌نمایش دسته‌ای، قرنطینه امن، حذف فایل‌های یتیم، تشخیص تکراری‌ها و تبدیل امن عکس‌ها به WebP همراه با اصلاح خودکار تمام لینک‌ها.
 * Version:     1.0.0
 * Requires at least: 5.3
 * Requires PHP: 7.2
 * Author:      Media Sweeper
 * License:     GPL-2.0-or-later
 * Text Domain: media-sweeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MSW_VERSION', '1.0.0' );
define( 'MSW_DB_VERSION', '6' );
define( 'MSW_FILE', __FILE__ );
define( 'MSW_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSW_URL', plugin_dir_url( __FILE__ ) );

require_once MSW_DIR . 'includes/class-msw-util.php';
require_once MSW_DIR . 'includes/class-msw-i18n.php';
require_once MSW_DIR . 'includes/class-msw-db.php';
require_once MSW_DIR . 'includes/class-msw-extractor.php';
require_once MSW_DIR . 'includes/class-msw-sources.php';
require_once MSW_DIR . 'includes/class-msw-scanner.php';
require_once MSW_DIR . 'includes/class-msw-quarantine.php';
require_once MSW_DIR . 'includes/class-msw-converter.php';
require_once MSW_DIR . 'includes/class-msw-rewriter.php';
require_once MSW_DIR . 'includes/class-msw-optimizer.php';
require_once MSW_DIR . 'includes/class-msw-redirects.php';
require_once MSW_DIR . 'includes/class-msw-renamer.php';
require_once MSW_DIR . 'includes/class-msw-merger.php';
require_once MSW_DIR . 'includes/class-msw-job.php';
require_once MSW_DIR . 'includes/class-msw-query.php';
require_once MSW_DIR . 'includes/class-msw-cron.php';

register_activation_hook( __FILE__, array( 'MSW_DB', 'install' ) );
register_activation_hook( __FILE__, array( 'MSW_Cron', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MSW_Cron', 'deactivate' ) );

add_action( 'init', array( 'MSW_Quarantine', 'register_status' ) );
add_action( 'plugins_loaded', array( 'MSW_DB', 'maybe_upgrade' ) );

MSW_Cron::init();
MSW_Converter::init();
MSW_Redirects::init();

if ( is_admin() ) {
	require_once MSW_DIR . 'includes/class-msw-admin.php';
	MSW_Admin::init();
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MSW_DIR . 'includes/class-msw-cli.php';
	WP_CLI::add_command( 'msw', 'MSW_CLI' );
}
