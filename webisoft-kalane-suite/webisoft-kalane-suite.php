<?php
/**
 * Plugin Name:       Webisoft Kalane Suite (WS Kalane)
 * Description:       مجموعه ماژول‌های فروشگاهی کالانه (ووکامرس / المنتور / وودمارت): استوری، بنر، دسته‌بندی، اسلایدر محصول و مقاله، مرتب‌سازی دیجی‌کالایی، آمار کلیک و بازدید و پنل تنظیمات کامل در wp-admin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webisoft
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webisoft-kalane-suite
 * Domain Path:       /languages
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WSK_VERSION', '1.0.0' );
define( 'WSK_DB_VERSION', '1' );
define( 'WSK_FILE', __FILE__ );
define( 'WSK_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSK_URL', plugin_dir_url( __FILE__ ) );
define( 'WSK_OPT', 'wsk_options' );

require_once WSK_DIR . 'includes/class-wsk-schema.php';
require_once WSK_DIR . 'includes/class-wsk-options.php';
require_once WSK_DIR . 'includes/class-wsk-db.php';
require_once WSK_DIR . 'includes/class-wsk-assets.php';
require_once WSK_DIR . 'includes/class-wsk-render.php';
require_once WSK_DIR . 'includes/class-wsk-shop.php';
require_once WSK_DIR . 'includes/class-wsk-rest.php';
require_once WSK_DIR . 'includes/class-wsk-admin.php';

register_activation_hook( __FILE__, array( 'WSK_DB', 'activate' ) );

// سازگاری با ووکامرس HPOS (این افزونه به سفارش‌ها دست نمی‌زند).
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WSK_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		WSK_Options::ensure();
		WSK_DB::init();
		WSK_Assets::init();
		WSK_Render::init();
		WSK_Shop::init();
		WSK_Rest::init();
		if ( is_admin() ) {
			WSK_Admin::init();
		}
	}
);
