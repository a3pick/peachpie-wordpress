<?php
/**
 * Plugin Name:       Webisoft Finance Suite (WS Finance)
 * Description:       مجموعه ماژول‌های آماده برای سایت‌های وکالتی و حقوقی (المنتور / Hello Elementor) با شورت‌کد، حالت روشن/تاریک، فرم دریافت درخواست و پنل تنظیمات کامل در wp-admin.
 * Version:           1.5.5
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webisoft
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webisoft-finance-suite
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WSF_VERSION', '1.5.5' );
define( 'WSF_FILE', __FILE__ );
define( 'WSF_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSF_URL', plugin_dir_url( __FILE__ ) );
define( 'WSF_OPT', 'wsf_options' );

require_once WSF_DIR . 'includes/class-wsf-icons.php';
require_once WSF_DIR . 'includes/class-wsf-schema.php';
require_once WSF_DIR . 'includes/class-wsf-options.php';
require_once WSF_DIR . 'includes/class-wsf-assets.php';
require_once WSF_DIR . 'includes/class-wsf-render.php';
require_once WSF_DIR . 'includes/class-wsf-woo.php';
require_once WSF_DIR . 'includes/class-wsf-access.php';
require_once WSF_DIR . 'includes/class-wsf-leads.php';
require_once WSF_DIR . 'includes/class-wsf-article.php';
require_once WSF_DIR . 'includes/class-wsf-extras.php';
require_once WSF_DIR . 'includes/class-wsf-admin.php';

register_activation_hook( __FILE__, array( 'WSF_Options', 'activate' ) );

add_action(
	'plugins_loaded',
	function () {
		WSF_Options::ensure();
		WSF_Assets::init();
		WSF_Render::init();
		WSF_Woo::init();
		WSF_Access::init();
		WSF_Leads::init();
		WSF_Article::init();
		WSF_Extras::init();
		if ( is_admin() ) {
			WSF_Admin::init();
		}
	}
);
