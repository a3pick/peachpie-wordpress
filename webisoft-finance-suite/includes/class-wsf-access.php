<?php
defined( 'ABSPATH' ) || exit;

/**
 * کنترل دسترسی به محتوای قفل‌دار و دانلود محافظت‌شده.
 * افزونه اشتراک شما باید فیلتر زیر را پیاده کند:
 *
 *   add_filter( 'wsf_user_has_access', function ( $has, $level, $user_id ) { ... return true/false; }, 10, 3 );
 *
 * پیش‌فرض (بدون فیلتر): فقط مدیران دسترسی دارند.
 */
class WSF_Access {

	public static function init() {
		add_action( 'admin_post_wsf_dl', array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nopriv_wsf_dl', array( __CLASS__, 'download' ) );
		add_shortcode( 'fin_lock', array( __CLASS__, 'sc_lock' ) );
	}

	public static function can( $level = 'member' ) {
		if ( 'free' === $level ) {
			return true;
		}
		$default = current_user_can( 'manage_options' );
		return (bool) apply_filters( 'wsf_user_has_access', $default, $level, get_current_user_id() );
	}

	/** [fin_lock level="member" plans="URL"]محتوای ویژه[/fin_lock] */
	public static function sc_lock( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'level' => 'member', 'plans' => '', 'message' => 'این بخش ویژه اعضاست.', 'button' => 'دریافت اشتراک' ), $atts, 'fin_lock' );
		if ( self::can( sanitize_key( $a['level'] ) ) ) {
			return do_shortcode( $content );
		}
		$btn = $a['plans'] ? ' <a class="wsf-btn wsf-btn--primary" href="' . esc_url( $a['plans'] ) . '">' . esc_html( $a['button'] ) . '</a>' : '';
		return '<div class="wsf wsf-lockbox" dir="' . esc_attr( WSF_Render::dir() ) . '"><span class="wsf-iconbox">' . WSF_Icons::render( 'lock' ) . '</span><p>' . esc_html( $a['message'] ) . '</p>' . $btn . '</div>';
	}

	/** آدرس دانلود محافظت‌شده برای مقدار فایل (ID یا URL). */
	public static function download_url( $file ) {
		return add_query_arg( array( 'action' => 'wsf_dl', 'k' => md5( (string) $file ) ), admin_url( 'admin-post.php' ) );
	}

	public static function download() {
		$k     = isset( $_GET['k'] ) ? sanitize_key( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$items = WSF_Options::get( 'downloads', 'items', array() );
		$found = null;
		foreach ( (array) $items as $it ) {
			if ( ! empty( $it['file'] ) && md5( (string) $it['file'] ) === $k ) {
				$found = $it;
				break;
			}
		}
		if ( ! $found ) {
			wp_die( 'فایل پیدا نشد.', '', array( 'response' => 404 ) );
		}
		if ( 'member' === $found['lock'] && ! self::can( 'member' ) ) {
			$plans = WSF_Options::get( 'downloads', 'plans_url', '' );
			if ( $plans ) {
				wp_safe_redirect( $plans );
				exit;
			}
			wp_die( 'برای دانلود این فایل اشتراک لازم است.', '', array( 'response' => 403 ) );
		}
		if ( is_numeric( $found['file'] ) ) {
			$path = get_attached_file( (int) $found['file'] );
			if ( ! $path || ! is_readable( $path ) ) {
				wp_die( 'فایل در دسترس نیست.', '', array( 'response' => 404 ) );
			}
			nocache_headers();
			$mime = get_post_mime_type( (int) $found['file'] );
			header( 'Content-Type: ' . ( $mime ? $mime : 'application/octet-stream' ) );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
			header( 'X-Content-Type-Options: nosniff' );
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			exit;
		}
		wp_redirect( esc_url_raw( $found['file'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- آدرس را مدیر وارد کرده است.
		exit;
	}
}
