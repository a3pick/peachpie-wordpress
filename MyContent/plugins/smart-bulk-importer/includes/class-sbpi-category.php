<?php
/**
 * Model-family category pages: the hub that ranks for the broad query ("قیمت آیفون 13")
 * while each product page targets its exact version ("آیفون 13 128 گیگ استوک").
 *
 * After an import, every leaf category holding 2+ imported products gets (only if its
 * description is empty) a short intro plus [sbpi_compare] — a live comparison table of
 * all versions with current prices and stock — and a buying guide from the glossary.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category hub pages.
 */
final class SBPI_Category {

	/** Marker so we only ever touch descriptions we wrote. */
	const MARK = '[sbpi_compare]';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_shortcode( 'sbpi_compare', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Strip "|slug" parts: "موبایل|mobile" → "موبایل".
	 *
	 * @param string $segment Path segment.
	 * @return string
	 */
	public static function name( $segment ) {
		return trim( explode( '|', (string) $segment, 2 )[0] );
	}

	/**
	 * Write hub content for the categories of an imported plan.
	 *
	 * @param array $products Plan products.
	 * @return int[] Term IDs whose description was written.
	 */
	public static function describe( array $products ) {
		$groups = array();
		foreach ( $products as $p ) {
			if ( ! $p['category'] ) {
				continue;
			}
			$groups[ implode( '>', $p['category'] ) ][] = $p;
		}
		$written = array();
		foreach ( $groups as $specs ) {
			if ( count( $specs ) < 2 ) {
				continue;
			}
			$term = self::find( $specs[0]['category'] );
			if ( ! $term || '' !== trim( $term->description ) ) {
				continue; // Never overwrite an editor's text.
			}
			$name     = $term->name;
			$storages = array();
			$conds    = array();
			$guide    = array();
			foreach ( $specs as $s ) {
				foreach ( $s['attributes'] as $a ) {
					if ( preg_match( '/حافظه|storage|ظرفیت/iu', $a['name'] . ' ' . $a['slug'] ) && ! preg_match( '/رم|ram/iu', $a['name'] ) ) {
						$storages = array_merge( $storages, $a['values'] );
					}
				}
				$conds = array_merge( $conds, SBPI_SEO::condition_values( $s ) );
				foreach ( $s['glossary'] as $g ) {
					$guide[ $g[0] ] = $g;
				}
			}
			$storages = array_values( array_unique( $storages ) );
			$conds    = array_values( array_unique( $conds ) );

			$intro = sprintf( 'همه نسخه‌های %s را در این صفحه مقایسه کنید', $name );
			$bits  = array();
			if ( $storages ) {
				$bits[] = 'ظرفیت ' . SBPI_Util::join_fa( $storages );
			}
			if ( $conds ) {
				$bits[] = 'وضعیت ' . SBPI_Util::join_fa( $conds );
			}
			if ( $bits ) {
				$intro .= '؛ ' . implode( ' و ', $bits );
			}
			$intro .= '. قیمت و موجودی جدول زیر همیشه به‌روز است و با کلیک روی هر نسخه، مشخصات کامل، رنگ‌ها و گزینه‌های آن را می‌بینید.';

			wp_update_term( $term->term_id, 'product_cat', array( 'description' => $intro . "\n\n" . self::MARK ) );
			update_term_meta( $term->term_id, 'sbpi_guide', array_values( $guide ) );
			self::seo_meta( $term->term_id, $name, $storages, $conds );
			$written[] = (int) $term->term_id;
		}
		return $written;
	}

	/**
	 * Category meta description in Yoast / Rank Math (only when empty).
	 *
	 * @param int      $id       Term ID.
	 * @param string   $name     Category name.
	 * @param string[] $storages Storages.
	 * @param string[] $conds    Conditions.
	 */
	private static function seo_meta( $id, $name, array $storages, array $conds ) {
		$desc = SBPI_Util::truncate(
			sprintf( 'قیمت روز و خرید %s%s%s؛ مقایسه همه نسخه‌ها با موجودی به‌روز.', $name, $storages ? ' در ظرفیت‌های ' . SBPI_Util::join_fa( $storages ) : '', $conds ? '، ' . SBPI_Util::join_fa( $conds ) : '' ),
			158
		);
		$plugin = SBPI_SEO::seo_plugin();
		if ( 'rankmath' === $plugin && '' === (string) get_term_meta( $id, 'rank_math_description', true ) ) {
			update_term_meta( $id, 'rank_math_description', $desc );
		} elseif ( 'yoast' === $plugin ) {
			$all = get_option( 'wpseo_taxonomy_meta', array() );
			if ( empty( $all['product_cat'][ $id ]['wpseo_desc'] ) ) {
				$all['product_cat'][ $id ]['wpseo_desc'] = $desc;
				update_option( 'wpseo_taxonomy_meta', $all );
			}
		}
	}

	/**
	 * Existing category for a path (no creation).
	 *
	 * @param string[] $path Path segments.
	 * @return WP_Term|null
	 */
	private static function find( array $path ) {
		$parent = 0;
		$term   = null;
		foreach ( $path as $segment ) {
			$found = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'name'       => self::name( $segment ),
					'parent'     => $parent,
					'hide_empty' => false,
					'number'     => 1,
				)
			);
			if ( is_wp_error( $found ) || ! $found ) {
				return null;
			}
			$term   = $found[0];
			$parent = $term->term_id;
		}
		return $term;
	}

	/**
	 * Undo: clear descriptions we wrote (untouched since).
	 *
	 * @param int[] $ids Term IDs.
	 */
	public static function undo( array $ids ) {
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) && false !== strpos( $term->description, self::MARK ) ) {
				wp_update_term( $term->term_id, 'product_cat', array( 'description' => '' ) );
				delete_term_meta( $term->term_id, 'sbpi_guide' );
			}
		}
	}

	/**
	 * [sbpi_compare] — live comparison of all products in the current (or given) category.
	 *
	 * @param array $atts Attributes: category (ID), limit.
	 * @return string HTML.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'category' => 0, 'limit' => 100 ), $atts, 'sbpi_compare' );
		$id   = (int) $atts['category'];
		if ( ! $id && is_tax( 'product_cat' ) ) {
			$id = get_queried_object_id();
		}
		if ( ! $id || ! function_exists( 'wc_get_products' ) ) {
			return '';
		}
		$term = get_term( $id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}
		$products = wc_get_products(
			array(
				'status'   => 'publish',
				'category' => array( $term->slug ),
				'limit'    => min( 200, max( 1, (int) $atts['limit'] ) ),
				'orderby'  => 'title',
				'order'    => 'ASC',
			)
		);
		if ( ! $products ) {
			return '';
		}
		$html  = '<div class="sbpi-compare"><h2>' . esc_html( sprintf( 'مقایسه قیمت نسخه‌های %s', $term->name ) ) . '</h2>';
		$html .= '<table class="sbpi-compare-table"><thead><tr><th>نسخه</th><th>قیمت</th><th>موجودی</th></tr></thead><tbody>';
		foreach ( $products as $p ) {
			$price = $p->get_price_html();
			$html .= '<tr><td><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a></td>'
				. '<td>' . ( '' !== $price ? wp_kses_post( $price ) : 'تماس بگیرید' ) . '</td>'
				. '<td>' . esc_html( $p->is_in_stock() ? 'موجود' : 'ناموجود' ) . '</td></tr>';
		}
		$html .= '</tbody></table>';

		$guide = get_term_meta( $id, 'sbpi_guide', true );
		if ( is_array( $guide ) && $guide ) {
			$html .= '<h2>' . esc_html( sprintf( 'راهنمای انتخاب %s', $term->name ) ) . '</h2><ul class="sbpi-guide">';
			foreach ( $guide as $g ) {
				$html .= '<li><strong>' . esc_html( $g[0] ) . ':</strong> ' . esc_html( $g[1] ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}
}
