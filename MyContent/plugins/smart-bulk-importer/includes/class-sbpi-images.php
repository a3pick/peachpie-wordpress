<?php
/**
 * Automatic images from the Media Library by file name.
 *
 * Naming convention (case-insensitive, any image extension):
 *   iphone-13-128-gb-used.jpg  → exactly this product (e.g. real photos of a used item)
 *   iphone-13-blue.jpg         → model + colour: product image and the Blue variations
 *   iphone-13.jpg              → model fallback
 *   iphone-13-blue_2.jpg …     → extra gallery images (_2 … _9)
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Image matcher.
 */
final class SBPI_Images {

	/** @var array|null name => [index => attachment ID] */
	private static $index = null;

	/**
	 * Media Library index by normalised file name (one query per request).
	 *
	 * @return array
	 */
	private static function index() {
		if ( null !== self::$index ) {
			return self::$index;
		}
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT p.ID, m.meta_value AS file FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
			 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
			 ORDER BY p.ID ASC"
		);
		self::$index = array();
		foreach ( $rows as $row ) {
			$name = strtolower( pathinfo( $row->file, PATHINFO_FILENAME ) );
			$name = preg_replace( '/-scaled$|-rotated$|-\d+x\d+$/', '', $name );
			$name = preg_replace( '/[\s_]+(?=\d+$)/', '_', str_replace( ' ', '-', $name ) );
			$pos  = 1;
			if ( preg_match( '/^(.+)_(\d)$/', $name, $m ) ) {
				$name = $m[1];
				$pos  = (int) $m[2];
			}
			if ( ! isset( self::$index[ $name ][ $pos ] ) ) {
				self::$index[ $name ][ $pos ] = (int) $row->ID;
			}
		}
		foreach ( self::$index as &$list ) {
			ksort( $list );
		}
		return self::$index;
	}

	/**
	 * The colour attribute of a spec (by slug or name).
	 *
	 * @param array $spec Spec.
	 * @return string|null Attribute id.
	 */
	public static function color_attr( array $spec ) {
		foreach ( $spec['attributes'] as $aid => $a ) {
			if ( in_array( $a['slug'], array( 'color', 'colour', 'rang', 'pa_color' ), true ) || preg_match( '/رنگ|colou?r/iu', $a['name'] ) ) {
				return $aid;
			}
		}
		return null;
	}

	/**
	 * Find images for a product spec.
	 *
	 * @param array $spec Spec.
	 * @return array{main:int, gallery:int[], colors:array<string,int>}
	 */
	public static function match( array $spec ) {
		$index  = self::index();
		$family = strtolower( $spec['family'] );
		$out    = array(
			'main'    => 0,
			'gallery' => array(),
			'colors'  => array(),
		);
		if ( ! $index || '' === $family || '%' === substr( $family, 0, 1 ) ) {
			return $out;
		}

		$caid = self::color_attr( $spec );
		if ( null !== $caid ) {
			foreach ( $spec['attributes'][ $caid ]['values'] as $color ) {
				$key = $family . '-' . sanitize_title( $color );
				if ( isset( $index[ $key ] ) ) {
					$out['colors'][ $color ] = reset( $index[ $key ] );
				}
			}
		}

		$own = strtolower( $spec['slug'] );
		if ( isset( $index[ $own ] ) ) {
			$out['main']    = reset( $index[ $own ] );
			$out['gallery'] = array_slice( array_values( $index[ $own ] ), 1 );
		} elseif ( $out['colors'] ) {
			$first          = sanitize_title( (string) array_key_first( $out['colors'] ) );
			$out['main']    = reset( $out['colors'] );
			$out['gallery'] = array_slice( array_values( $index[ $family . '-' . $first ] ), 1 );
		} elseif ( isset( $index[ $family ] ) ) {
			$out['main']    = reset( $index[ $family ] );
			$out['gallery'] = array_slice( array_values( $index[ $family ] ), 1 );
		}
		// Other colours join the gallery so shoppers can see every finish.
		foreach ( $out['colors'] as $id ) {
			if ( $id !== $out['main'] && ! in_array( $id, $out['gallery'], true ) ) {
				$out['gallery'][] = $id;
			}
		}
		return $out;
	}

	/**
	 * Set alt text when the attachment has none.
	 *
	 * @param int    $id  Attachment.
	 * @param string $alt Alt text.
	 */
	public static function ensure_alt( $id, $alt ) {
		if ( $id && '' === (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		}
	}
}
