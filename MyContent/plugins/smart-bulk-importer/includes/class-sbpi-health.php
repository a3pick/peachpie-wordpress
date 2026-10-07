<?php
/**
 * SEO health report for products (read-only).
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Health report.
 */
final class SBPI_Health {

	/** Issue labels. */
	const ISSUES = array(
		'no_image'     => 'بدون تصویر شاخص',
		'no_price'     => 'بدون قیمت (قابل خرید نیست)',
		'thin'         => 'توضیحات کوتاه (کمتر از ~۱۵۰ کلمه)',
		'no_desc_meta' => 'بدون توضیحات متا',
		'dup_title'    => 'عنوان سئوی تکراری',
		'long_title'   => 'عنوان سئو بلندتر از ۶۵ کاراکتر',
		'draft'        => 'منتشر نشده',
	);

	/** Max products analysed per report. */
	const LIMIT = 5000;

	/**
	 * Analyse products.
	 *
	 * @param bool $only_ours Only products created by this plugin.
	 * @return array{rows: array, counts: array, total: int}
	 */
	public static function run( $only_ours ) {
		global $wpdb;
		$plugin = SBPI_SEO::seo_plugin();
		$tkey   = 'yoast' === $plugin ? '_yoast_wpseo_title' : ( 'rankmath' === $plugin ? 'rank_math_title' : SBPI_SEO::META_TITLE );
		$dkey   = 'yoast' === $plugin ? '_yoast_wpseo_metadesc' : ( 'rankmath' === $plugin ? 'rank_math_description' : SBPI_SEO::META_DESC );
		$join   = $only_ours ? "INNER JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = '_sbpi_key'" : '';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- static SQL; values via prepare.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, p.post_status, p.post_content,
					MAX(CASE WHEN m.meta_key = '_thumbnail_id' THEN m.meta_value END) AS thumb,
					MIN(CASE WHEN m.meta_key = '_price' AND m.meta_value <> '' THEN m.meta_value END) AS price,
					MAX(CASE WHEN m.meta_key = %s THEN m.meta_value END) AS seo_title,
					MAX(CASE WHEN m.meta_key = %s THEN m.meta_value END) AS seo_desc
				 FROM {$wpdb->posts} p
				 {$join}
				 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ('_thumbnail_id', '_price', %s, %s)
				 WHERE p.post_type = 'product' AND p.post_status IN ('publish', 'draft', 'pending', 'private')
				 GROUP BY p.ID
				 ORDER BY p.post_title ASC
				 LIMIT %d",
				$tkey,
				$dkey,
				$tkey,
				$dkey,
				self::LIMIT
			)
		);
		// phpcs:enable

		$titles = array();
		foreach ( $rows as $r ) {
			$t = SBPI_Util::key( '' !== (string) $r->seo_title ? $r->seo_title : $r->post_title );
			$titles[ $t ] = isset( $titles[ $t ] ) ? $titles[ $t ] + 1 : 1;
		}

		$out    = array();
		$counts = array_fill_keys( array_keys( self::ISSUES ), 0 );
		foreach ( $rows as $r ) {
			$issues = array();
			if ( ! $r->thumb ) {
				$issues[] = 'no_image';
			}
			if ( null === $r->price || '' === $r->price ) {
				$issues[] = 'no_price';
			}
			$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $r->post_content ) ), -1, PREG_SPLIT_NO_EMPTY ) );
			if ( $words < 150 ) {
				$issues[] = 'thin';
			}
			if ( '' === trim( (string) $r->seo_desc ) ) {
				$issues[] = 'no_desc_meta';
			}
			$title = '' !== (string) $r->seo_title ? $r->seo_title : $r->post_title;
			if ( $titles[ SBPI_Util::key( $title ) ] > 1 ) {
				$issues[] = 'dup_title';
			}
			// Template variables (%%title%%, %title%) cannot be measured reliably.
			if ( '' !== (string) $r->seo_title && false === strpos( $r->seo_title, '%' ) && mb_strlen( $r->seo_title, 'UTF-8' ) > 65 ) {
				$issues[] = 'long_title';
			}
			if ( 'publish' !== $r->post_status ) {
				$issues[] = 'draft';
			}
			foreach ( $issues as $i ) {
				$counts[ $i ]++;
			}
			if ( $issues ) {
				$out[] = array(
					'id'     => (int) $r->ID,
					'title'  => $r->post_title,
					'seo'    => $title,
					'words'  => $words,
					'issues' => $issues,
				);
			}
		}
		return array(
			'rows'   => $out,
			'counts' => $counts,
			'total'  => count( $rows ),
		);
	}
}
