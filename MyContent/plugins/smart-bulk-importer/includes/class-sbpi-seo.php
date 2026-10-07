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
		add_filter( 'the_content', array( __CLASS__, 'sibling_links' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'faq_schema' ), 20 );
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
			'category'   => $spec['category'] ? SBPI_Category::name( end( $spec['category'] ) ) : '',
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

		$html .= self::condition_text( $spec, $global );

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

		$faq = self::faq_pairs( $spec, $global );
		if ( $faq ) {
			// Visible Q&A (required for FAQPage markup to be valid).
			$html .= sprintf( '<h2>سؤالات متداول درباره %s</h2>', $t );
			foreach ( $faq as $qa ) {
				$html .= '<h3>' . esc_html( $qa[0] ) . '</h3><p>' . esc_html( $qa[1] ) . '</p>';
			}
		} elseif ( $spec['glossary'] ) {
			$html .= sprintf( '<h2>نکات مهم پیش از خرید %s</h2><ul>', $t );
			foreach ( $spec['glossary'] as $g ) {
				$html .= '<li><strong>' . esc_html( $g[0] ) . ':</strong> ' . esc_html( $g[1] ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Condition values of a spec ("نو", "استوک", …).
	 *
	 * @param array $spec Spec.
	 * @return string[]
	 */
	public static function condition_values( array $spec ) {
		foreach ( $spec['attributes'] as $a ) {
			if ( 'condition' === $a['slug'] || preg_match( '/وضعیت|grade|condition|گرید/iu', $a['name'] ) ) {
				return $a['values'];
			}
		}
		return array();
	}

	/**
	 * Default per-condition texts (drafts: replace the [bracketed] parts with your real policy).
	 *
	 * @return array condition => html
	 */
	public static function default_cond_texts() {
		return array(
			'نو'        => '<p>{title} با وضعیت «نو» کارنکرده است و در جعبه اصلی عرضه می‌شود. [نوع و مدت گارانتی، و شرایط بازگشت کالا را اینجا بنویسید.]</p>',
			'آکبند'     => '<p>{title} آکبند است؛ یعنی پلمب جعبه باز نشده است. [نوع و مدت گارانتی را اینجا بنویسید.]</p>',
			'نات اکتیو' => '<p>{title} «نات‌اکتیو» است؛ یعنی دستگاه تاکنون فعال‌سازی نشده است. [توضیح دهید جعبه باز شده یا نه، و گارانتی چیست.]</p>',
			'اکتیو'     => '<p>{title} «اکتیو» است؛ یعنی یک بار فعال‌سازی شده ولی [میزان استفاده، وضعیت ظاهری، سلامت باتری و گارانتی را دقیق بنویسید].</p>',
			'استوک'     => '<p>{title} «استوک» (کارکرده) است. ظاهر و سلامت باتری هر دستگاه ممکن است متفاوت باشد. [روش درجه‌بندی ظاهری، حداقل سلامت باتری، تست‌هایی که انجام می‌دهید و مدت ضمانت را بنویسید.]</p>',
		);
	}

	/**
	 * Text for the product's condition, when it has exactly one.
	 *
	 * @param array $spec   Spec.
	 * @param array $global Settings (cond_texts).
	 * @return string HTML.
	 */
	public static function condition_text( array $spec, array $global ) {
		$values = self::condition_values( $spec );
		$texts  = isset( $global['cond_texts'] ) && is_array( $global['cond_texts'] ) ? $global['cond_texts'] : array();
		if ( 1 !== count( $values ) || ! $texts ) {
			return '';
		}
		$norm = static function ( $t ) {
			return str_replace( array( "\xE2\x80\x8C", ' ', '-' ), '', SBPI_Util::key( $t ) );
		};
		$map = array();
		foreach ( $texts as $k => $v ) {
			// Unfinished drafts ("[write your warranty here]") are never published.
			if ( '' !== trim( wp_strip_all_tags( $v ) ) && ! preg_match( '/\[[^\]]{3,}\]/u', $v ) ) {
				$map[ $norm( $k ) ] = $v;
			}
		}
		$candidates = array_merge( array( $values[0] ), explode( '/', $values[0] ) );
		foreach ( $candidates as $c ) {
			$k = $norm( $c );
			if ( isset( $map[ $k ] ) ) {
				$body = str_replace( array( '{title}', '{model}' ), array( esc_html( $spec['title'] ), esc_html( $spec['model'] ) ), $map[ $k ] );
				return sprintf( '<h2>شرایط و وضعیت %s</h2>', esc_html( $spec['title'] ) ) . wp_kses_post( $body );
			}
		}
		return '';
	}

	/**
	 * FAQ pairs from matched glossary entries.
	 *
	 * @param array $spec   Spec.
	 * @param array $global Settings.
	 * @return array [[question, answer]]
	 */
	public static function faq_pairs( array $spec, array $global ) {
		if ( empty( $global['faq'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $spec['glossary'] as $g ) {
			$term = 'نکته' === $g[0] ? '' : SBPI_Util::header_label( $g[0] );
			$q    = '' === $term
				? sprintf( 'هنگام خرید %s به چه نکته‌ای توجه کنم؟', $spec['title'] )
				: sprintf( 'منظور از «%s» چیست؟', $term );
			$out[] = array( $q, $g[1] );
		}
		return $out;
	}

	/**
	 * FAQPage JSON-LD for products imported with FAQ enabled.
	 * Note: Google shows FAQ rich results only for well-known government/health sites
	 * (since Aug 2023); the markup still describes the visible Q&A for other consumers.
	 */
	public static function faq_schema() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$faq = get_post_meta( get_queried_object_id(), '_sbpi_faq', true );
		if ( ! is_array( $faq ) || ! $faq ) {
			return;
		}
		$items = array();
		foreach ( $faq as $qa ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $qa[0],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $qa[1],
				),
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $items,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
		) . '</script>' . "\n";
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
	 * Internal links between the separate products of one model
	 * ("iPhone 13 128 GB نو" ↔ "iPhone 13 256 GB استوک"): helps shoppers compare
	 * and gives each sibling page a crawlable, descriptive internal link.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function sibling_links( $content ) {
		if ( ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$id     = get_the_ID();
		$family = get_post_meta( $id, '_sbpi_family', true );
		if ( ! $family ) {
			return $content;
		}
		$cache = 'sbpi_fam_' . md5( $family );
		$ids   = wp_cache_get( $cache, 'sbpi' );
		if ( false === $ids ) {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'meta_key'       => '_sbpi_family', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $family, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'         => 'ids',
					'posts_per_page' => 40,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			wp_cache_set( $cache, $ids, 'sbpi', HOUR_IN_SECONDS );
		}
		$ids = array_diff( $ids, array( $id ) );
		if ( ! $ids ) {
			return $content;
		}
		$model = get_post_meta( $id, '_sbpi_model', true );
		$html  = '<h2>' . esc_html( sprintf( 'سایر نسخه‌های %s', $model ) ) . '</h2><ul class="sbpi-siblings">';
		foreach ( $ids as $sid ) {
			$html .= '<li><a href="' . esc_url( get_permalink( $sid ) ) . '">' . esc_html( get_the_title( $sid ) ) . '</a></li>';
		}
		return $content . $html . '</ul>';
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
