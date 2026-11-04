<?php
/**
 * Plugin Name:       Webisoft Legal Suite (WS Legal)
 * Description:       مجموعه ماژول‌های آماده برای سایت‌های وکالتی و حقوقی (المنتور / Hello Elementor) با شورت‌کد، حالت روشن/تاریک، فرم دریافت درخواست و پنل تنظیمات کامل در wp-admin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Webisoft
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webisoft-legal-suite
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WSL_VERSION', '1.0.0' );
define( 'WSL_FILE', __FILE__ );
define( 'WSL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSL_URL', plugin_dir_url( __FILE__ ) );
define( 'WSL_OPT', 'wsl_options' );

require_once WSL_DIR . 'includes/class-wsl-icons.php';
require_once WSL_DIR . 'includes/class-wsl-schema.php';
require_once WSL_DIR . 'includes/class-wsl-options.php';
require_once WSL_DIR . 'includes/class-wsl-assets.php';
require_once WSL_DIR . 'includes/class-wsl-render.php';
require_once WSL_DIR . 'includes/class-wsl-leads.php';
require_once WSL_DIR . 'includes/class-wsl-article.php';
require_once WSL_DIR . 'includes/class-wsl-extras.php';
require_once WSL_DIR . 'includes/class-wsl-admin.php';

register_activation_hook( __FILE__, array( 'WSL_Options', 'activate' ) );

add_action(
	'plugins_loaded',
	function () {
		WSL_Options::ensure();
		WSL_Assets::init();
		WSL_Render::init();
		WSL_Leads::init();
		WSL_Article::init();
		WSL_Extras::init();
		if ( is_admin() ) {
			WSL_Admin::init();
		}
	}
);
