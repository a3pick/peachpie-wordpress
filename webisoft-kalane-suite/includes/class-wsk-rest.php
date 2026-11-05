<?php
defined( 'ABSPATH' ) || exit;

/**
 * نقطه‌ی عمومی REST برای ثبت آمار (کلیک بنر/دسته، نمایش استوری، بازدید محصول).
 *
 * چون روی صفحه‌های کش‌شده هم کار می‌کند نمی‌شود از nonce استفاده کرد؛ به‌جای آن:
 *  - نوع از فهرست سفید؛ کلید باید با یک آیتم واقعاً تنظیم‌شده یا محصول منتشرشده بخواند،
 *  - هر IP+کلید حداکثر هر چند ثانیه یک‌بار شمرده می‌شود (Transient).
 */
class WSK_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'wsk/v1',
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'hit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'type' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $v ) {
							return in_array( $v, WSK_DB::TYPES, true );
						},
					),
					'key'  => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	private static function throttled( $type, $key ) {
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$id   = 'wsk_h_' . md5( $ip . '|' . $type . '|' . $key );
		$ttl  = 'product' === $type ? 30 * MINUTE_IN_SECONDS : 8;
		if ( get_transient( $id ) ) {
			return true;
		}
		set_transient( $id, 1, $ttl );
		return false;
	}

	/** برچسب خوانا برای آیتم؛ اگر آیتم وجود نداشت null. */
	private static function resolve( $type, $key ) {
		if ( 'product' === $type ) {
			$id = absint( $key );
			if ( ! $id || 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				return null;
			}
			return array( (string) $id, get_the_title( $id ) );
		}

		$map = array( 'banner' => array( 'banners', 'alt' ), 'category' => array( 'categories', 'title' ), 'story' => array( 'stories', 'alt' ) );
		if ( ! isset( $map[ $type ] ) || ! preg_match( '/^(\d{1,3})$/', $key, $m ) ) {
			return null;
		}
		$idx   = (int) $m[1];
		$items = (array) WSK_Options::get( $map[ $type ][0 ], 'items', array() );
		if ( 'category' === $type && 'woo' === WSK_Options::get( 'categories', 'source', 'manual' ) ) {
			return array( (string) $idx, 'دسته #' . ( $idx + 1 ) );
		}
		if ( ! isset( $items[ $idx ] ) ) {
			return null;
		}
		$label = ! empty( $items[ $idx ][ $map[ $type ][1] ] ) ? $items[ $idx ][ $map[ $type ][1] ] : ( $type . ' #' . ( $idx + 1 ) );
		return array( (string) $idx, $label );
	}

	public static function hit( WP_REST_Request $req ) {
		if ( ! WSK_Options::get( 'system', 'track_stats', 1 ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 202 );
		}
		$type = (string) $req->get_param( 'type' );
		$key  = (string) $req->get_param( 'key' );

		$res = self::resolve( $type, $key );
		if ( ! $res ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		if ( self::throttled( $type, $res[0] ) ) {
			return new WP_REST_Response( array( 'ok' => true, 'counted' => false ), 200 );
		}
		WSK_DB::hit( $type, $res[0], $res[1] );
		if ( 'product' === $type ) {
			WSK_DB::bump_views( (int) $res[0] );
		}
		return new WP_REST_Response( array( 'ok' => true, 'counted' => true ), 200 );
	}
}
