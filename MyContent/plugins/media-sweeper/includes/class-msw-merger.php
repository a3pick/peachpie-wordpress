<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merge byte-identical duplicates: references to a duplicate's FILES are
 * rewritten to the keeper's files (301 redirects too) and the duplicate is
 * then moved to quarantine (reversible).
 *
 * A duplicate that is referenced by attachment ID somewhere (featured image,
 * gallery, wp-image-N class...) is NOT merged - IDs cannot be remapped safely
 * in arbitrary data, so such copies are reported and left alone.
 */
class MSW_Merger {

	private static function files( $id ) {
		$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
		$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
		$dir  = ( false === strpos( $rel, '/' ) ) ? '' : dirname( $rel ) . '/';
		$out  = array( 'main' => $rel, 'orig' => '', 'sizes' => array(), 'dir' => $dir );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['original_image'] ) ) {
				$out['orig'] = $dir . $meta['original_image'];
			}
			foreach ( (array) ( isset( $meta['sizes'] ) ? $meta['sizes'] : array() ) as $sz ) {
				if ( ! empty( $sz['file'] ) && ! empty( $sz['width'] ) ) {
					$out['sizes'][] = array( $dir . $sz['file'], (int) $sz['width'] . 'x' . (int) $sz['height'] );
				}
			}
		}
		return $out;
	}

	/**
	 * Validate a merge and compute old => new file paths (no changes made).
	 *
	 * @return array|string [ 'pairs' => [...] ] or an error message.
	 */
	public static function plan( $dup, $keep ) {
		global $wpdb;
		if ( $dup === $keep || ! $dup || ! $keep ) {
			return 'جفت نامعتبر';
		}
		foreach ( array( $dup, $keep ) as $id ) {
			$p = get_post( $id );
			if ( ! $p || 'attachment' !== $p->post_type || 'inherit' !== $p->post_status ) {
				return "پیوست #$id در دسترس نیست";
			}
		}
		if ( get_post_meta( $dup, '_msw_protected', true ) ) {
			return 'کپی محافظت‌شده است';
		}
		if ( MSW_Util::is_offloaded( $dup ) || MSW_Util::is_offloaded( $keep ) ) {
			return 'فایل روی ذخیره‌ساز خارجی است';
		}
		if ( MSW_Util::shares_file( $dup ) ) {
			return 'پیوست دیگری از همین فایل استفاده می‌کند';
		}
		$d = self::files( $dup );
		$k = self::files( $keep );
		if ( '' === $d['main'] || '' === $k['main'] || ! is_file( MSW_Util::abs( $d['main'] ) ) || ! is_file( MSW_Util::abs( $k['main'] ) ) ) {
			return 'فایل روی دیسک نیست';
		}
		if ( md5_file( MSW_Util::abs( $d['main'] ) ) !== md5_file( MSW_Util::abs( $k['main'] ) ) ) {
			return 'فایل‌ها یکسان نیستند';
		}
		$ref = $wpdb->get_var( $wpdb->prepare( 'SELECT src FROM ' . MSW_Util::t( 'refs' ) . ' WHERE ref = %s', 'i:' . $dup ) ); // phpcs:ignore
		if ( $ref ) {
			$desc = MSW_Query::describe_src( $ref );
			return 'با شناسهٔ پیوست در «' . $desc['label'] . '» استفاده شده؛ ادغام خودکار ایمن نیست';
		}
		$pairs = array( $d['main'] => $k['main'] );
		if ( $d['orig'] ) {
			$pairs[ $d['orig'] ] = $k['orig'] ? $k['orig'] : $k['main'];
		}
		$by_dim = array();
		foreach ( $k['sizes'] as $s ) {
			if ( ! isset( $by_dim[ $s[1] ] ) ) {
				$by_dim[ $s[1] ] = $s[0];
			}
		}
		foreach ( $d['sizes'] as $s ) {
			if ( ! isset( $pairs[ $s[0] ] ) ) {
				$pairs[ $s[0] ] = isset( $by_dim[ $s[1] ] ) ? $by_dim[ $s[1] ] : $k['main'];
			}
		}
		return array( 'pairs' => $pairs );
	}

	/** @return array|string pair info or an error message. */
	public static function merge_one( $dup, $keep ) {
		global $wpdb;
		$pl = self::plan( $dup, $keep );
		if ( is_string( $pl ) ) {
			return $pl;
		}
		$pairs = $pl['pairs'];
		$mt    = MSW_Util::t( 'map' );
		foreach ( $pairs as $old => $new ) {
			if ( $old === $new || strlen( $old ) > 185 ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( "REPLACE INTO $mt (old_rel,new_rel,att_id) VALUES (%s,%s,%d)", $old, $new, $dup ) ); // phpcs:ignore
			MSW_Redirects::add( $old, $new, $keep );
		}
		return array( 'dup' => $dup, 'keep' => $keep );
	}
}
