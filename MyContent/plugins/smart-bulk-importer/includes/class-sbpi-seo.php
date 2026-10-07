<?php
/**
 * SEO: meta templates, generated content, SEO-plugin integration and Product schema.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO helper.
 */
final class SBPI_SEO {

	/** Meta keys used for our own fallback meta (when no SEO plugin is active). */
	const META_TITLE = '_sbpi_seo_title';
	const META_DESC  = '_sbpi_seo_desc';

	/**
	 * Runtime hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_structured_data_product', array( __CLASS__, 'schema' ), 20, 2 );
		if ( ! self::seo_plugin() ) {
			add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 20 );
			add_action( 'wp_head', array( __CLASS__, 'meta_description' ), 1 );
		}
	}

	/**
	 * Active SEO plugin.
	 *
	 * @return string "yoast" | "rankmath" | "aioseo" | "".
	 */
	public static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'aioseo';
		}
		return '';
	}

	/**
	 * Tokens available to templates.
	 *
	 * @param array $spec   Product spec.
	 * @param array $global Global settings.
	 * @return array
	 */
	public static function tokens( array $spec, array $global ) {
		$tokens = array(
			'title'      => $spec['title'],
			'model'      => $spec['model'],
			'brand'      => $spec['brand'] ? $spec['brand'][0] : '',
			'brand_en'   => $spec['brand'] ? $spec['brand'][2] : '',
			'category'   => $spec['category'] ? end( $spec['category'] ) : '',
			'site'       => $global['store_name'],
			'options'    => self::options_summary( $spec, 3 ),
			'conditions' => '',
		);
		foreach ( $spec['attributes'] as $attr ) {
			$tokens[ 'attr:' . $attr['name'] ] = SBPI_Util::join_fa( $attr['values'] );
			if ( $attr['slug'] ) {
				$tokens[ 'attr:' . $attr['slug'] ] = $tokens[ 'attr:' . $attr['name'] ];
			}
			if ( 'condition' === $attr['slug'] ) {
				$tokens['conditions'] = SBPI_Util::join_fa( $attr['values'] );
			}
		}
		return $tokens;
	}

	/**
	 * "حافظه داخلی (128 GB، 256 GB و 512 GB)، رنگ (۶ رنگ)".
	 *
	 * @param array $spec Spec.
	 * @param int   $max  Values listed before summarising as a count.
	 * @return string
	 */
	public static function options_summary( array $spec, $max ) {
		$parts = array();
		foreach ( $spec['attributes'] as $attr ) {
			if ( ! $attr['variation'] ) {
				continue;
			}
			$n = count( $attr['values'] );
			if ( $n <= $max ) {
				$parts[] = $attr['name'] . ' ' . SBPI_Util::join_fa( $attr['values'] );
			} else {
				$parts[] = sprintf( '%d گزینه %s', $n, $attr['name'] );
			}
		}
		return SBPI_Util::join_fa( $parts );
	}

	/**
	 * SEO title / description / focus keyword.
	 *
	 * @param array $spec   Spec.
	 * @param array $global Global settings.
	 * @return array{title:string, desc:string, focus:string}
	 */
	public static function build_meta( array $spec, array $global ) {
		$tokens = self::tokens( $spec, $global );

		$title = SBPI_Util::render( $global['seo_title_tpl'], $tokens );
		if ( mb_strlen( $title, 'UTF-8' ) > 65 ) {
			// Drop the site name first, then truncate.
			$title = SBPI_Util::render( $global['seo_title_tpl'], array( 'site' => '' ) + $tokens );
		}
		$title = SBPI_Util::truncate( $title, 65 );

		$desc_tpl = trim( $global['seo_desc_tpl'] );
		if ( '' === $desc_tpl ) {
			$desc_tpl = $tokens['options']
				? 'خرید {title}{brand_part} با {options}. مشخصات، وضعیت و قیمت روز {title} را در {site} ببینید.'
				: 'خرید {title}{brand_part}. مشخصات کامل، وضعیت و قیمت روز {title} را در {site} ببینید.';
		}
		$tokens['brand_part'] = $tokens['brand'] && false === mb_stripos( $spec['title'], $tokens['brand_en'] ) ? ' از برند ' . $tokens['brand'] : '';
		$desc = SBPI_Util::truncate( SBPI_Util::render( $desc_tpl, $tokens ), 158 );

		$focus = SBPI_Util::render( $global['focus_tpl'], $tokens );

		return array(
			'title' => $title,
			'desc'  => $desc,
			'focus' => $focus,
		);
	}

	/**
	 * Long description: intro, specs table, per-version details, buying notes.
	 * Written to be useful to a shopper; meant as a solid first draft to enrich by hand.
	 *
	 * @param array $spec   Spec.
	 * @param array $global Global settings.
	 * @return string HTML.
	 */
	public static function build_description( array $spec, array $global ) {
		if ( empty( $global['gen_description'] ) ) {
			return '';
		}
		$t     = esc_html( $spec['title'] );
		$brand = $spec['brand'] ? esc_html( $spec['brand'][0] ) : '';
		$html  = '';

		$intro = $brand
			? sprintf( '%1$s یکی از محصولات برند %2$s است', $t, $brand )
			: sprintf( '%s در این صفحه معرفی شده است', $t );
		$opts = self::options_summary( $spec, 4 );
		if ( $opts ) {
			$intro .= sprintf( ' و با %s قابل انتخاب است', esc_html( $opts ) );
		}
		$intro .= 'variable' === $spec['type']
			? '. پیش از خرید، مشخصات و تفاوت نسخه‌ها را در جدول‌های زیر مقایسه کنید.'
			: '. مشخصات این محصول را در جدول زیر ببینید.';
		$html  .= '<p>' . $intro . '</p>';

		if ( $spec['attributes'] ) {
			$html .= sprintf( '<h2>مشخصات و گزینه‌های %s</h2>', $t );
			$html .= '<table class="sbpi-specs"><tbody>';
			if ( $brand ) {
				$html .= '<tr><th scope="row">برند</th><td>' . $brand . '</td></tr>';
			}
			foreach ( $spec['attributes'] as $attr ) {
				$html .= '<tr><th scope="row">' . esc_html( $attr['name'] ) . '</th><td>' . esc_html( implode( '، ', $attr['values'] ) ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}

		// Per-version differences (e.g. firmware / package per account type & grade).
		$rows = array();
		foreach ( $spec['variations'] as $v ) {
			if ( '' === $v['desc'] ) {
				continue;
			}
			$label = array();
			foreach ( $v['attrs'] as $aid => $value ) {
				$a = $spec['attributes'][ $aid ];
				// Many-valued axes (colour, region) add noise to this table.
				if ( '' !== $value && count( $a['values'] ) <= 4 ) {
					$label[] = $value;
				}
			}
			$k = implode( ' / ', $label );
			if ( '' !== $k && ! isset( $rows[ $k ] ) ) {
				$rows[ $k ] = $v['desc'];
			}
		}
		if ( count( $rows ) > 1 ) {
			$html .= sprintf( '<h2>تفاوت نسخه‌های %s</h2>', $t );
			$html .= '<table class="sbpi-versions"><thead><tr><th>نسخه</th><th>جزئیات</th></tr></thead><tbody>';
			foreach ( $rows as $k => $d ) {
				$html .= '<tr><td>' . esc_html( $k ) . '</td><td>' . esc_html( $d ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}

		if ( $spec['glossary'] ) {
			$html .= sprintf( '<h2>نکات مهم پیش از خرید %s</h2><ul>', $t );
			foreach ( $spec['glossary'] as $g ) {
				$html .= '<li><strong>' . esc_html( $g[0] ) . ':</strong> ' . esc_html( $g[1] ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Short description: up to five key specs as a list.
	 *
	 * @param array $spec Spec.
	 * @return string HTML.
	 */
	public static function build_short( array $spec ) {
		$items = array();
		if ( $spec['brand'] ) {
			$items[] = '<li>برند: ' . esc_html( $spec['brand'][0] ) . '</li>';
		}
		foreach ( $spec['attributes'] as $attr ) {
			$values  = $attr['values'];
			$text    = count( $values ) > 4
				? implode( '، ', array_slice( $values, 0, 4 ) ) . ' و …'
				: implode( '، ', $values );
			$items[] = '<li>' . esc_html( $attr['name'] ) . ': ' . esc_html( $text ) . '</li>';
			if ( count( $items ) >= 5 ) {
				break;
			}
		}
		return $items ? '<ul class="sbpi-short">' . implode( '', $items ) . '</ul>' : '';
	}

	/**
	 * Write SEO meta to the active SEO plugin (and our fallback keys).
	 * Existing values are only replaced when they still equal what we wrote last time,
	 * so manual edits by an editor survive re-imports.
	 *
	 * @param int   $post_id Product ID.
	 * @param array $seo     title/desc/focus.
	 */
	public static function apply( $post_id, array $seo ) {
		$map = array(
			self::META_TITLE => $seo['title'],
			self::META_DESC  => $seo['desc'],
		);
		switch ( self::seo_plugin() ) {
			case 'yoast':
				$map['_yoast_wpseo_title']    = $seo['title'];
				$map['_yoast_wpseo_metadesc'] = $seo['desc'];
				$map['_yoast_wpseo_focuskw']  = $seo['focus'];
				break;
			case 'rankmath':
				$map['rank_math_title']         = $seo['title'];
				$map['rank_math_description']   = $seo['desc'];
				$map['rank_math_focus_keyword'] = $seo['focus'];
				break;
		}
		$written = get_post_meta( $post_id, '_sbpi_seo_written', true );
		$written = is_array( $written ) ? $written : array();
		foreach ( $map as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$current = get_post_meta( $post_id, $key, true );
			$ours    = isset( $written[ $key ] ) ? $written[ $key ] : null;
			if ( '' === $current || $current === $ours ) {
				update_post_meta( $post_id, $key, $value );
				$written[ $key ] = $value;
			}
		}
		update_post_meta( $post_id, '_sbpi_seo_written', $written );
	}

	/**
	 * Enrich WooCommerce Product schema with brand, itemCondition and mpn-free identifiers.
	 *
	 * @param array      $markup  Markup.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function schema( $markup, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->get_meta( '_sbpi_key' ) ) {
			return $markup;
		}
		$brand = $product->get_meta( '_sbpi_brand' );
		if ( $brand && empty( $markup['brand'] ) ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}
		$conditions = $product->get_meta( '_sbpi_conditions' );
		// Only a single, unambiguous condition is stated; mixed grades are left out.
		if ( is_array( $conditions ) && 1 === count( $conditions ) && ! empty( $markup['offers'] ) ) {
			foreach ( $markup['offers'] as $i => $offer ) {
				$markup['offers'][ $i ]['itemCondition'] = 'https://schema.org/' . $conditions[0];
			}
		}
		return $markup;
	}

	/**
	 * Fallback document title when no SEO plugin is active.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function document_title( $title ) {
		if ( is_singular( 'product' ) ) {
			$custom = get_post_meta( get_queried_object_id(), self::META_TITLE, true );
			if ( $custom ) {
				return $custom;
			}
		}
		return $title;
	}

	/**
	 * Fallback meta description when no SEO plugin is active.
	 */
	public static function meta_description() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$desc = get_post_meta( get_queried_object_id(), self::META_DESC, true );
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
		}
	}
}
