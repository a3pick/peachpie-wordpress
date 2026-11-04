<?php
defined( 'ABSPATH' ) || exit;

/**
 * ابزارهای مقاله: زمان مطالعه، خلاصه مطلب (متاباکس)، دکمه‌های مفید بود / اشتراک‌گذاری.
 * کلیدهای متای قدیمی (_webisoft_*) برای حفظ داده‌های موجود نگه داشته شده‌اند.
 */
class WSL_Article {

	const META_SUMMARY = '_webisoft_article_summary';
	const META_LIKES   = '_webisoft_likes';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 11 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_summary' ), 10, 2 );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function register() {
		add_shortcode( 'reading_time', array( __CLASS__, 'sc_reading_time' ) );
		add_shortcode( 'article_summary', array( __CLASS__, 'sc_summary' ) );
		add_shortcode( 'article_actions', array( __CLASS__, 'sc_actions' ) );
	}

	private static function post_from_atts( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		return $atts['id'] ? get_post( (int) $atts['id'] ) : get_post();
	}

	/* ---------- زمان مطالعه ---------- */
	public static function sc_reading_time( $atts = array() ) {
		$post = self::post_from_atts( $atts );
		if ( ! $post ) {
			return '';
		}
		$o     = WSL_Options::sec( 'article' );
		$text  = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$words = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$min   = max( 1, (int) ceil( count( $words ) / max( 1, (int) $o['rt_wpm'] ) ) );
		$n     = (string) $min;
		if ( $o['rt_fa_digits'] ) {
			$n = str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), $n );
		}
		return '<span class="wsl-reading-time">' . esc_html( str_replace( '{n}', $n, $o['rt_label'] ) ) . '</span>';
	}

	/* ---------- خلاصه مطلب ---------- */
	private static function summary_types() {
		$types = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) WSL_Options::get( 'article', 'summary_types', 'post' ) ) as $t ) {
			$t = sanitize_key( $t );
			if ( $t && post_type_exists( $t ) ) {
				$types[] = $t;
			}
		}
		return $types ? $types : array( 'post' );
	}

	public static function metabox() {
		if ( function_exists( 'webisoft_summary_callback' ) ) {
			return; // اسنیپت قدیمی فعال است؛ از تکرار متاباکس جلوگیری می‌شود.
		}
		foreach ( self::summary_types() as $type ) {
			add_meta_box( 'wsl_summary', 'خلاصه مطلب (باکس ابتدای مقاله)', array( __CLASS__, 'metabox_html' ), $type, 'normal', 'high' );
		}
	}

	public static function metabox_html( $post ) {
		wp_nonce_field( 'wsl_summary', 'wsl_summary_nonce' );
		$value = get_post_meta( $post->ID, self::META_SUMMARY, true );
		echo '<p><label for="wsl_summary_field">این متن با شورت‌کد <code>[article_summary]</code> نمایش داده می‌شود.</label></p>';
		echo '<textarea id="wsl_summary_field" name="wsl_summary_field" style="width:100%" rows="4">' . esc_textarea( $value ) . '</textarea>';
	}

	public static function save_summary( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['wsl_summary_nonce'], $_POST['wsl_summary_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsl_summary_nonce'] ) ), 'wsl_summary' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$val = sanitize_textarea_field( wp_unslash( $_POST['wsl_summary_field'] ) );
		if ( '' === $val ) {
			delete_post_meta( $post_id, self::META_SUMMARY );
		} else {
			update_post_meta( $post_id, self::META_SUMMARY, $val );
		}
	}

	public static function sc_summary( $atts = array() ) {
		$post = self::post_from_atts( $atts );
		if ( ! $post ) {
			return '';
		}
		$summary = get_post_meta( $post->ID, self::META_SUMMARY, true );
		if ( '' === (string) $summary ) {
			return '';
		}
		$title = WSL_Options::get( 'article', 'summary_title', 'خلاصه مطلب' );
		return '<div class="wsl wsl-summary" dir="' . esc_attr( WSL_Render::dir() ) . '"><div class="wsl-summary__t">' . WSL_Icons::render( 'sparkles' ) . esc_html( $title ) . '</div><p class="wsl-summary__p">' . nl2br( esc_html( $summary ) ) . '</p></div>';
	}

	/* ---------- مفید بود / اشتراک‌گذاری ---------- */
	public static function sc_actions( $atts = array() ) {
		$post = self::post_from_atts( $atts );
		if ( ! $post ) {
			return '';
		}
		$o     = WSL_Options::sec( 'article' );
		$likes = (int) get_post_meta( $post->ID, self::META_LIKES, true );
		$h     = '<div class="wsl wsl-actions" dir="' . esc_attr( WSL_Render::dir() ) . '" data-wsl="actions" data-id="' . (int) $post->ID . '" data-url="' . esc_url( get_permalink( $post ) ) . '" data-title="' . esc_attr( get_the_title( $post ) ) . '" data-share="' . esc_attr( $o['share_label'] ) . '">';
		$h    .= '<button type="button" class="wsl-abtn" data-wsl-like><span class="wsl-abtn__t">' . esc_html( $o['like_label'] ) . '</span>' . WSL_Icons::render( 'thumbs-up' );
		if ( $o['show_like_count'] ) {
			$h .= '<span class="wsl-abtn__n" data-wsl-count>' . esc_html( WSL_Render::num( $likes ) ) . '</span>';
		}
		$h .= '</button><button type="button" class="wsl-abtn" data-wsl-share><span class="wsl-abtn__t">' . esc_html( $o['share_label'] ) . '</span>' . WSL_Icons::render( 'share' ) . '</button></div>';
		return $h;
	}

	public static function routes() {
		register_rest_route(
			'wsl/v1',
			'/like',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_like' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	public static function rest_like( $request ) {
		$id   = absint( $request->get_param( 'post_id' ) );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) || post_password_required( $post ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 404 );
		}
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'wsl_like_' . md5( $ip . '|' . $id );
		$likes = (int) get_post_meta( $id, self::META_LIKES, true );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, DAY_IN_SECONDS );
			$likes++;
			update_post_meta( $id, self::META_LIKES, $likes );
		}
		return new WP_REST_Response( array( 'ok' => true, 'likes' => $likes, 'display' => WSL_Render::num( $likes ) ), 200 );
	}
}
