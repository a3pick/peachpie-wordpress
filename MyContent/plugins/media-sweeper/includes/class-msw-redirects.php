<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 301 redirects for upload URLs that no longer exist (renamed / converted).
 * Only fires on a 404, so existing files are never intercepted.
 */
class MSW_Redirects {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
	}

	public static function add( $old, $new, $att_id = 0 ) {
		global $wpdb;
		if ( $old === $new || strlen( $old ) > 185 || ! MSW_Util::safe_rel( $old ) || ! MSW_Util::safe_rel( $new ) ) {
			return;
		}
		$t = MSW_Util::t( 'redirects' );
		// Flatten chains: anything that pointed to $old now points to $new.
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET new_rel = %s WHERE new_rel = %s", $new, $old ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( "REPLACE INTO $t (old_rel,new_rel,att_id,hits,created) VALUES (%s,%s,%d,0,UTC_TIMESTAMP())", $old, $new, $att_id ) ); // phpcs:ignore
		// A path that exists again must not redirect.
		$wpdb->delete( $t, array( 'old_rel' => $new ) );
	}

	/** Target URL for a request URI, or null. */
	public static function resolve( $uri ) {
		global $wpdb;
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$base = (string) wp_parse_url( MSW_Util::baseurl(), PHP_URL_PATH );
		if ( '' === $base || 0 !== strpos( $path, $base . '/' ) ) {
			return null;
		}
		$rel = ltrim( rawurldecode( substr( $path, strlen( $base ) ) ), '/' );
		if ( '' === $rel || strlen( $rel ) > 191 || ! MSW_Util::safe_rel( $rel ) ) {
			return null;
		}
		$t   = MSW_Util::t( 'redirects' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT old_rel, new_rel FROM $t WHERE old_rel = %s", $rel ) ); // phpcs:ignore
		if ( ! $row || $row->old_rel !== $rel || ! is_file( MSW_Util::abs( $row->new_rel ) ) ) {
			return null;
		}
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET hits = hits + 1 WHERE old_rel = %s", $rel ) ); // phpcs:ignore
		return MSW_Util::url( $row->new_rel );
	}

	public static function handle() {
		if ( ! is_404() || ! MSW_Util::get( 'redirects_enable' ) || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$to = self::resolve( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security
		if ( $to ) {
			wp_redirect( $to, 301, 'Media Sweeper' ); // phpcs:ignore WordPress.Security.SafeRedirect
			exit;
		}
	}
}
