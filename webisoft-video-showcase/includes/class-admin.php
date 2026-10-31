<?php
defined( 'ABSPATH' ) || exit;

final class Webisoft_Video_Showcase_Admin {

	const MENU_SLUG = 'webisoft-video-showcase';

	public function register_menu() {
		$hook = add_menu_page(
			__( 'ویترین ویدیویی Webisoft', 'webisoft-video-showcase' ),
			__( 'ویترین ویدیویی', 'webisoft-video-showcase' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-format-video',
			58
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'maybe_save' ) );
		}
	}

	public function enqueue_assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$is_settings = false !== strpos( $screen->id, self::MENU_SLUG );
		$is_post_ui  = 'post' === $screen->base && in_array( $screen->post_type, wvs_get_enabled_post_types(), true );

		if ( ! $is_settings && ! $is_post_ui ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style( 'wvs-admin', WVS_URL . 'assets/css/admin.css', array(), WVS_VERSION );
		wp_enqueue_script( 'wvs-admin', WVS_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), WVS_VERSION, true );
	}

	public function maybe_save() {
		if ( empty( $_POST['wvs_save'] ) ) {
			return;
		}

		check_admin_referer( 'wvs_save_settings' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما دسترسی لازم برای این عملیات را ندارید.', 'webisoft-video-showcase' ) );
		}

		$raw_settings = isset( $_POST['wvs_settings'] ) ? wp_unslash( $_POST['wvs_settings'] ) : array();
		update_option( WVS_OPTION_SETTINGS, $this->sanitize_settings( $raw_settings ) );

		$raw_items = isset( $_POST['wvs_items'] ) ? wp_unslash( $_POST['wvs_items'] ) : array();
		update_option( WVS_OPTION_ITEMS, wvs_sanitize_items( $raw_items ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => self::MENU_SLUG,
					'wvs_saved'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function sanitize_settings( $raw ) {
		$raw   = (array) $raw;
		$clean = array();

		$checkboxes  = array( 'show_play_icon', 'show_duration_badge', 'show_views_badge', 'lightbox_autoplay', 'enable_schema', 'enable_preconnect' );
		$numbers     = array( 'columns_desktop', 'columns_tablet', 'columns_mobile', 'gap', 'gap_tablet', 'gap_mobile', 'radius', 'radius_tablet', 'radius_mobile', 'eager_count' );
		$hex_colors  = array( 'badge_text_color', 'play_icon_color', 'skeleton_color' );
		$free_colors = array( 'lightbox_bg_color', 'badge_bg_color' ); // rgba()/hsl() هم مجازند، نه فقط hex
		$selects     = array(
			'aspect_ratio'  => array( '1/1', '4/5', '9/16' ),
			'hover_effect'  => array( 'none', 'zoom', 'dim' ),
		);

		foreach ( wvs_default_settings() as $key => $default_value ) {
			if ( 'post_types' === $key ) {
				$types         = isset( $raw[ $key ] ) ? array_map( 'sanitize_key', (array) $raw[ $key ] ) : array();
				$clean[ $key ] = array_values( array_filter( $types, 'post_type_exists' ) );
			} elseif ( in_array( $key, $checkboxes, true ) ) {
				$clean[ $key ] = empty( $raw[ $key ] ) ? 0 : 1;
			} elseif ( in_array( $key, $numbers, true ) ) {
				$clean[ $key ] = isset( $raw[ $key ] ) ? max( 0, absint( $raw[ $key ] ) ) : $default_value;
			} elseif ( isset( $selects[ $key ] ) ) {
				$value         = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $default_value;
				$clean[ $key ] = in_array( $value, $selects[ $key ], true ) ? $value : $default_value;
			} elseif ( in_array( $key, $hex_colors, true ) ) {
				$clean[ $key ] = sanitize_hex_color( $raw[ $key ] ?? '' ) ?: $default_value;
			} elseif ( in_array( $key, $free_colors, true ) ) {
				$clean[ $key ] = sanitize_text_field( $raw[ $key ] ?? $default_value );
			} else {
				$clean[ $key ] = sanitize_text_field( $raw[ $key ] ?? $default_value );
			}
		}

		// حداقل یک ستون، جلوگیری از گرید صفر-ستونه.
		foreach ( array( 'columns_desktop', 'columns_tablet', 'columns_mobile' ) as $col_key ) {
			$clean[ $col_key ] = max( 1, (int) $clean[ $col_key ] );
		}

		return $clean;
	}

	public function render_page() {
		require WVS_DIR . 'admin/page-settings.php';
	}
}
