<?php
/**
 * Plugin Name:       Smart Bulk Product Importer (درون‌ریز هوشمند محصولات)
 * Description:       درون‌ریزی انبوه محصولات ووکامرس از Excel/CSV با گروه‌بندی خودکار به محصول متغیر، ویژگی‌های سراسری، برند، دسته‌بندی، توضیحات و متای سئو (Yoast / Rank Math) و Schema — با پیش‌نمایش، اجرای مرحله‌ای و بازگردانی.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Payam Nazeri
 * Text Domain:       sbpi
 * WC requires at least: 7.0
 * WC tested up to:   10.2
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

define( 'SBPI_VERSION', '1.0.0' );
define( 'SBPI_FILE', __FILE__ );
define( 'SBPI_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBPI_URL', plugin_dir_url( __FILE__ ) );

require_once SBPI_DIR . 'includes/class-sbpi-util.php';
require_once SBPI_DIR . 'includes/class-sbpi-reader.php';
require_once SBPI_DIR . 'includes/class-sbpi-parser.php';
require_once SBPI_DIR . 'includes/class-sbpi-planner.php';
require_once SBPI_DIR . 'includes/class-sbpi-importer.php';
require_once SBPI_DIR . 'includes/class-sbpi-seo.php';
require_once SBPI_DIR . 'includes/class-sbpi-admin.php';

// Products only — no order tables are touched, so HPOS is safe.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'افزونه درون‌ریز هوشمند محصولات به WooCommerce نیاز دارد.', 'sbpi' ) . '</p></div>';
				}
			);
			return;
		}
		SBPI_SEO::init();
		if ( is_admin() ) {
			SBPI_Admin::init();
		}
	}
);
