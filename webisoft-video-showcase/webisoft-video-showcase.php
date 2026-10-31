<?php
/**
 * Plugin Name:       Webisoft Video Showcase
 * Plugin URI:        https://webisoft.ir
 * Description:       ویترین ویدیویی به‌سبک اینستاگرام (نه استوری) برای نمایش نمونه‌کار — گرید ویدیویی قابل‌تنظیم با پخش در لایت‌باکس، به‌صورت ویجت المنتور و شورت‌کد، با پلی‌لیست مجزا برای هر صفحه/پست، مستقل از سایر پلاگین‌های Webisoft و قابل نصب روی هر سایت المنتوری.
 * Version:           2.0.0
 * Author:            Webisoft
 * Author URI:        https://webisoft.ir
 * Text Domain:       webisoft-video-showcase
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'WVS_VERSION', '2.0.0' );
define( 'WVS_FILE', __FILE__ );
define( 'WVS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WVS_URL', plugin_dir_url( __FILE__ ) );
define( 'WVS_BASENAME', plugin_basename( __FILE__ ) );
define( 'WVS_OPTION_ITEMS', 'wvs_items' );
define( 'WVS_OPTION_SETTINGS', 'wvs_settings' );
define( 'WVS_META_ITEMS', '_wvs_items' );
define( 'WVS_CPT', 'wvs_playlist' );

require_once WVS_DIR . 'includes/functions.php';
require_once WVS_DIR . 'includes/class-admin.php';
require_once WVS_DIR . 'includes/class-playlists.php';
require_once WVS_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'Webisoft_Video_Showcase_Plugin', 'instance' ) );
