<?php
defined( 'ABSPATH' ) || exit;

/**
 * رندر شورت‌کدها. تمام متن‌ها، رنگ‌ها و داده‌ها از تنظیمات (دیتابیس) خوانده می‌شود؛
 * هیچ <style> یا <script> درون‌خطی‌ای در خروجی نیست.
 */
class WSK_Render {

	private static $first_banner = true;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 11 );
		add_action( 'wp_footer', array( __CLASS__, 'product_beacon' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'floating_toggle' ), 20 );
		add_action( 'save_post_product', array( __CLASS__, 'flush_cache' ) );
		add_action( 'save_post_post', array( __CLASS__, 'flush_cache' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'flush_cache' ) );
	}

	public static function tags() {
		return array(
			'kalane_stories'        => 'stories',
			'kalane_banner'         => 'banners',
			'kalane_banners'        => 'banners',
			'kalane_categories'     => 'categories',
			'kalane_divider'        => 'divider',
			'kalane_product_slider' => 'product_slider',
			'kalane_blog_slider'    => 'blog_slider',
			'kalane_custom_widget'  => 'widget',
		);
	}

	public static function register() {
		add_shortcode( 'kalane_theme_toggle', array( __CLASS__, 'sc_toggle' ) );
		if ( ! shortcode_exists( 'ws_theme_toggle' ) ) { // سازگار با دیگر افزونه‌های Webisoft؛ اگر آن‌ها ثبتش کرده‌اند، تداخل پیش نیاید.
			add_shortcode( 'ws_theme_toggle', array( __CLASS__, 'sc_toggle' ) );
		}
		foreach ( self::tags() as $tag => $module ) {
			add_shortcode(
				$tag,
				function ( $atts ) use ( $module ) {
					return self::run( $module, $atts );
				}
			);
		}
	}

	/** دکمه‌ی تغییر حالت: در روشن «ماه» (رفتن به تاریک)، در تاریک «خورشید» نمایش داده می‌شود. */
	public static function toggle_button( $class = '' ) {
		$svg = function ( $inner ) {
			return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $inner . '</svg>';
		};
		$sun  = $svg( '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>' );
		$moon = $svg( '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5z"/>' );
		return '<button type="button" class="wsk-toggle ' . esc_attr( $class ) . '" data-wsk-theme-toggle aria-pressed="false" aria-label="تغییر حالت روشن/تاریک"><span class="wsk-toggle__sun">' . $sun . '</span><span class="wsk-toggle__moon">' . $moon . '</span></button>';
	}

	public static function sc_toggle() {
		if ( ! WSK_Assets::dark_enabled() ) {
			return '';
		}
		WSK_Assets::enqueue();
		return '<span class="wsk wsk-inline" dir="' . esc_attr( self::dir() ) . '">' . self::toggle_button( 'wsk-toggle--inline' ) . '</span>';
	}

	public static function floating_toggle() {
		$pos = WSK_Options::get( 'darkmode', 'toggle_position', 'bottom-end' );
		if ( ! WSK_Assets::dark_enabled() || ! in_array( $pos, array( 'bottom-start', 'bottom-end' ), true ) || is_admin() ) {
			return;
		}
		WSK_Assets::enqueue();
		echo '<div class="wsk wsk-float wsk-float--' . esc_attr( $pos ) . '">' . self::toggle_button( 'wsk-toggle--float' ) . '</div>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی کاملاً درون‌کلاسی Escape شده.
	}

	private static function run( $module, $atts ) {
		if ( 'widget' !== $module ) {
			$o = WSK_Options::sec( $module );
			if ( isset( $o['enabled'] ) && ! $o['enabled'] ) {
				return '';
			}
		}
		WSK_Assets::enqueue();
		$atts = is_array( $atts ) ? $atts : array();
		$fn   = 'mod_' . $module;
		return self::$fn( 'widget' === $module ? array() : $o, $atts );
	}

	/* =====================================================================
	 * ابزارهای مشترک
	 * ===================================================================== */
	private static function dir() {
		$d = WSK_Options::get( 'appearance', 'direction', 'rtl' );
		if ( 'rtl' === $d || 'ltr' === $d ) {
			return $d;
		}
		return is_rtl() ? 'rtl' : 'ltr';
	}

	private static function root( $class, $extra = '' ) {
		return '<div class="wsk wsk-' . esc_attr( $class ) . '" dir="' . esc_attr( self::dir() ) . '"' . $extra . '>';
	}

	private static function num( $n ) {
		$s = (string) $n;
		return WSK_Options::get( 'general', 'persian_digits', 1 ) ? WSK_Options::to_fa_digits( $s ) : $s;
	}

	private static function price_fmt( $n ) {
		return self::num( number_format( (float) $n, 0, '.', ',' ) );
	}

	/** تصویر از ID یا URL؛ رشته‌ی خالی اگر تصویری نبود. */
	private static function img( $val, $alt = '', $size = 'large', $attrs = array() ) {
		$attrs = array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $attrs );
		if ( is_numeric( $val ) && $val ) {
			$attrs['alt'] = $alt;
			return (string) wp_get_attachment_image( (int) $val, $size, false, $attrs );
		}
		$url = $val ? esc_url( (string) $val ) : '';
		if ( ! $url ) {
			return '';
		}
		$h = '<img src="' . $url . '" alt="' . esc_attr( $alt ) . '"';
		foreach ( $attrs as $k => $v ) {
			$h .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return $h . '>';
	}

	private static function placeholder( $alt = '' ) {
		return self::img( WSK_Options::get( 'general', 'placeholder', '' ), $alt, 'medium' );
	}

	private static function currency() {
		$icon = WSK_Options::get( 'general', 'currency_icon', '' );
		$lbl  = (string) WSK_Options::get( 'general', 'currency_label', 'تومان' );
		$url  = WSK_Options::image_url( $icon, 'thumbnail' );
		if ( $url ) {
			return '<img class="wsk-cur-icon" src="' . esc_url( $url ) . '" alt="' . esc_attr( $lbl ) . '" loading="lazy" decoding="async">';
		}
		return '<span class="wsk-cur">' . esc_html( $lbl ) . '</span>';
	}

	/** لینک امن؛ خالی → '#'. */
	private static function href( $url ) {
		$u = $url ? esc_url( (string) $url ) : '';
		return $u ? $u : '#';
	}

	private static function slider_open( $o, $class, $extra_attr = '' ) {
		$auto  = ! empty( $o['autoplay'] ) ? '1' : '0';
		$delay = isset( $o['delay'] ) ? (int) $o['delay'] : 4000;
		return '<div class="wsk-slider ' . esc_attr( $class ) . '" data-wsk-slider data-autoplay="' . $auto . '" data-delay="' . $delay . '"' . $extra_attr . '><div class="wsk-track" tabindex="0">';
	}

	private static function slider_close() {
		return '</div></div>';
	}

	/* =====================================================================
	 * استوری‌ها
	 * ===================================================================== */
	private static function mod_stories( $o, $atts ) {
		// کلیدهای اصلی حفظ می‌شود تا آمار با جابه‌جایی/حذف ردیف‌های ناقص قاطی نشود.
		$items = array_filter( (array) $o['items'], function ( $r ) {
			return ! empty( $r['cover'] ) && ! empty( $r['video'] );
		} );
		if ( ! $items ) {
			return '';
		}
		$g      = WSK_Options::sec( 'general' );
		$name   = (string) $g['brand_name'];
		$avatar = self::img( $g['brand_logo'], $name, 'thumbnail', array( 'class' => 'wsk-modal__avatar' ) );
		$ig     = ! empty( $g['instagram'] ) ? esc_url( $g['instagram'] ) : '';

		$h  = self::root( 'stories', ' data-wsk-stories' );
		$h .= '<div class="wsk-stories__row">';
		$pos = 0;
		foreach ( $items as $orig => $it ) {
			$alt = $it['alt'] ? $it['alt'] : $name . ' — ' . self::num( $pos + 1 );
			$h  .= '<button type="button" class="wsk-story" data-index="' . (int) $pos++ . '" data-hit="story:' . (int) $orig . '" data-video="' . esc_url( $it['video'] ) . '" aria-label="' . esc_attr( $alt ) . '"><span class="wsk-story__ring"></span>'
				. self::img( $it['cover'], $alt, 'thumbnail', array( 'class' => 'wsk-story__img' ) ) . '</button>';
		}
		$h .= '</div>';

		// پنجره‌ی پخش (یک بار برای هر نمونه)
		$h .= '<div class="wsk-modal" hidden role="dialog" aria-modal="true" aria-label="' . esc_attr( $name ) . '"><div class="wsk-modal__box">';
		$h .= '<div class="wsk-modal__bars">';
		foreach ( $items as $it ) {
			$h .= '<span class="wsk-modal__bar"><i></i></span>';
		}
		$h .= '</div><div class="wsk-modal__user">' . $avatar . '<span class="wsk-modal__txt"><b>' . esc_html( $name ) . '</b><small>' . esc_html( (string) $g['brand_desc'] ) . '</small></span></div>';
		$h .= '<button type="button" class="wsk-modal__close" aria-label="بستن">&times;</button>';
		$h .= '<button type="button" class="wsk-modal__nav wsk-modal__nav--prev" aria-label="قبلی"></button><button type="button" class="wsk-modal__nav wsk-modal__nav--next" aria-label="بعدی"></button>';
		$h .= '<video class="wsk-modal__video" playsinline preload="none"></video>';
		if ( $ig ) {
			$h .= '<a class="wsk-modal__ig" href="' . $ig . '" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.6"/><circle cx="16.8" cy="7.2" r=".6" fill="currentColor"/></svg>' . esc_html( $o['ig_label'] ) . '</a>';
		}
		$h .= '</div></div></div>';
		return $h;
	}

	/* =====================================================================
	 * بنرها
	 * ===================================================================== */
	private static function mod_banners( $o, $atts ) {
		$items = array_filter( (array) $o['items'], function ( $r ) {
			return ! empty( $r['image'] );
		} );
		if ( ! $items ) {
			return '';
		}
		$style = in_array( $o['style'], array( 'crescent', 'side_image', 'plain' ), true ) ? $o['style'] : 'crescent';
		$mask  = ( 'side_image' === $style ) ? WSK_Options::image_url( $o['mask_image'], 'medium' ) : '';
		$multi = count( $items ) > 1;

		$auto = 'auto' === WSK_Options::get( 'banners', 'ratio', '21/6' ) ? ' wsk-banners--auto' : '';
		$h    = self::root( 'banners wsk-banners--' . $style . $auto );
		$h .= $multi ? self::slider_open( $o, 'wsk-slider--banners' ) : '<div class="wsk-banners__one">';
		$pos = 0;
		foreach ( $items as $i => $it ) {
			$alt    = $it['alt'] ? $it['alt'] : 'بنر ' . self::num( $pos + 1 );
			$attrs  = array( 'class' => 'wsk-banner__img' );
			if ( self::$first_banner && 0 === $pos++ ) { // مهم برای LCP
				$attrs['loading']       = 'eager';
				$attrs['fetchpriority'] = 'high';
			}
			$target = ! empty( $it['new_tab'] ) ? ' target="_blank" rel="noopener"' : '';
			$h     .= ( $multi ? '<div class="wsk-slide">' : '' )
				. '<a class="wsk-banner" href="' . self::href( $it['link'] ) . '"' . $target . ' data-wsk-hit="banner:' . (int) $i . '">'
				. '<span class="wsk-banner__mask">' . self::img( $it['image'], $alt, 'full', $attrs ) . '</span>';
			if ( $mask ) {
				$h .= '<span class="wsk-side wsk-side--l"><img src="' . esc_url( $mask ) . '" alt="" aria-hidden="true" loading="lazy"></span><span class="wsk-side wsk-side--r"><img src="' . esc_url( $mask ) . '" alt="" aria-hidden="true" loading="lazy"></span>';
			}
			$h .= '</a>' . ( $multi ? '</div>' : '' );
		}
		self::$first_banner = false;
		$h .= $multi ? self::slider_close() : '</div>';
		return $h . '</div>';
	}

	/* =====================================================================
	 * دسته‌بندی‌ها
	 * ===================================================================== */
	private static function category_items( $o ) {
		$out = array();
		if ( 'woo' === $o['source'] && taxonomy_exists( 'product_cat' ) ) {
			$args = array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => (bool) $o['woo_hide_empty'],
				'number'     => max( 1, (int) $o['woo_limit'] ),
				'orderby'    => 'menu_order',
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			);
			if ( 'top' === $o['woo_parent'] ) {
				$args['parent'] = 0;
			}
			$terms = get_terms( $args );
			if ( is_wp_error( $terms ) ) {
				return $out;
			}
			foreach ( $terms as $t ) {
				$thumb = (int) get_term_meta( $t->term_id, 'thumbnail_id', true );
				$link  = get_term_link( $t );
				$out[] = array(
					'title' => $t->name,
					'image' => $thumb ? $thumb : WSK_Options::get( 'general', 'placeholder', '' ),
					'link'  => is_wp_error( $link ) ? '' : $link,
				);
			}
			return $out;
		}
		foreach ( (array) $o['items'] as $r ) {
			if ( ! empty( $r['title'] ) ) {
				$out[] = $r;
			}
		}
		return $out;
	}

	private static function mod_categories( $o, $atts ) {
		$items = self::category_items( $o );
		if ( ! $items ) {
			return '';
		}
		$h = self::root( 'categories' );
		if ( ! empty( $o['title'] ) ) {
			$h .= '<div class="wsk-divider"><span class="wsk-divider__t">' . esc_html( $o['title'] ) . '</span></div>';
		}
		$h .= self::slider_open( array( 'autoplay' => 0 ), 'wsk-slider--cats' );
		foreach ( $items as $i => $c ) {
			$h .= '<a class="wsk-cat" href="' . self::href( isset( $c['link'] ) ? $c['link'] : '' ) . '" data-wsk-hit="category:' . (int) $i . '">'
				. '<span class="wsk-cat__img">' . self::img( $c['image'], $c['title'], 'thumbnail', array( 'width' => 85, 'height' => 85 ) ) . '</span>'
				. '<span class="wsk-cat__t">' . esc_html( $c['title'] ) . '</span></a>';
		}
		return $h . self::slider_close() . '</div>';
	}

	/* =====================================================================
	 * جداکننده
	 * ===================================================================== */
	private static function mod_divider( $o, $atts ) {
		$a    = shortcode_atts( array( 'text' => $o['text'] ), $atts, 'kalane_divider' );
		$text = trim( (string) $a['text'] );
		return self::root( 'divider' ) . '<div class="wsk-divider"' . ( '' === $text ? ' role="separator"' : '' ) . '>' . ( '' !== $text ? '<span class="wsk-divider__t">' . esc_html( $text ) . '</span>' : '' ) . '</div></div>';
	}

	/* =====================================================================
	 * اسلایدر محصولات
	 * ===================================================================== */
	private static function product_ids( $o, $a ) {
		$types = array( 'latest', 'best_sellers', 'sale', 'most_viewed', 'featured', 'random' );
		$type  = in_array( $a['type'], $types, true ) ? $a['type'] : 'latest';
		$limit = max( 1, min( 40, (int) $a['limit'] ) );

		$cache_min = (int) $o['cache_minutes'];
		$ckey      = 'wsk_pq_' . md5( wp_json_encode( array( $type, $limit, $a['category'], (int) $o['in_stock_only'], get_option( 'wsk_cache_gen', 1 ) ) ) );
		if ( $cache_min > 0 && 'random' !== $type ) {
			$cached = get_transient( $ckey );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'tax_query'              => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_query'             => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		);

		// محصولات پنهان از کاتالوگ حذف شوند.
		if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
			$vis = wc_get_product_visibility_term_ids();
			if ( ! empty( $vis['exclude-from-catalog'] ) ) {
				$args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array( $vis['exclude-from-catalog'] ), 'operator' => 'NOT IN' );
			}
			if ( 'featured' === $type && ! empty( $vis['featured'] ) ) {
				$args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_taxonomy_id', 'terms' => array( $vis['featured'] ), 'operator' => 'IN' );
			}
		}

		if ( ! empty( $a['category'] ) ) {
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $a['category'] ) ) );
			if ( $slugs ) {
				$args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slugs );
			}
		}
		if ( ! empty( $o['in_stock_only'] ) ) {
			$args['meta_query'][] = array( 'key' => '_stock_status', 'value' => 'instock' );
		}

		switch ( $type ) {
			case 'best_sellers':
				$args = array_merge( $args, WSK_DB::meta_order_args( 'total_sales', $args['meta_query'] ) );
				break;
			case 'most_viewed':
				$args = array_merge( $args, WSK_DB::views_query_args( $args['meta_query'] ) );
				break;
			case 'sale':
				$on_sale          = function_exists( 'wc_get_product_ids_on_sale' ) ? wc_get_product_ids_on_sale() : array();
				$args['post__in'] = $on_sale ? array_map( 'absint', $on_sale ) : array( 0 );
				break;
			case 'random':
				$args['orderby'] = 'rand';
				break;
			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
		}
		if ( ! $args['meta_query'] ) {
			unset( $args['meta_query'] );
		}
		if ( ! $args['tax_query'] ) {
			unset( $args['tax_query'] );
		}

		$q   = new WP_Query( $args );
		$ids = array_map( 'absint', (array) $q->posts );
		if ( $cache_min > 0 && 'random' !== $type ) {
			set_transient( $ckey, $ids, $cache_min * MINUTE_IN_SECONDS );
		}
		return $ids;
	}

	private static function mod_product_slider( $o, $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$a   = shortcode_atts( array( 'limit' => $o['limit'], 'category' => '', 'type' => $o['type'] ), $atts, 'kalane_product_slider' );
		$ids = self::product_ids( $o, $a );
		if ( ! $ids ) {
			return '<p class="wsk-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		$h = self::root( 'products' ) . self::slider_open( $o, 'wsk-slider--cards' );
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$h .= '<div class="wsk-slide">' . self::product_card( $p, $o ) . '</div>';
		}
		return $h . self::slider_close() . '</div>';
	}

	private static function product_card( $p, $o ) {
		$price_html = '';
		$badge      = '';
		if ( ! $p->is_in_stock() ) {
			$price_html = '<span class="wsk-oos">' . esc_html( $o['out_of_stock'] ) . '</span>';
		} elseif ( $p->is_type( 'variable' ) ) {
			$min        = (float) $p->get_variation_price( 'min', true );
			$price_html = '<span class="wsk-price">از ' . esc_html( self::price_fmt( $min ) ) . '</span>' . self::currency();
		} else {
			$cur = (float) wc_get_price_to_display( $p );
			$reg = (float) wc_get_price_to_display( $p, array( 'price' => (float) $p->get_regular_price() ) );
			if ( ! empty( $o['show_discount'] ) && $p->is_on_sale() && $reg > $cur && $reg > 0 ) {
				$off        = (int) round( ( $reg - $cur ) / $reg * 100 );
				$badge      = $off > 0 ? '<span class="wsk-badge">' . esc_html( self::num( $off ) ) . '٪</span>' : '';
				$price_html = '<span class="wsk-old">' . esc_html( self::price_fmt( $reg ) ) . '</span>';
			}
			$price_html .= '<span class="wsk-price">' . esc_html( self::price_fmt( $cur ) ) . '</span>' . self::currency();
		}

		$img = $p->get_image( 'woocommerce_thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async' ) );
		return '<a class="wsk-card" href="' . esc_url( get_permalink( $p->get_id() ) ) . '">' . $badge
			. '<span class="wsk-card__img">' . $img . '</span>'
			. '<h4 class="wsk-card__t">' . esc_html( $p->get_name() ) . '</h4>'
			. '<span class="wsk-card__price">' . $price_html . '</span></a>';
	}

	/* =====================================================================
	 * اسلایدر مقالات
	 * ===================================================================== */
	private static function mod_blog_slider( $o, $atts ) {
		$a    = shortcode_atts( array( 'limit' => $o['limit'], 'category' => '' ), $atts, 'kalane_blog_slider' );
		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 40, (int) $a['limit'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( ! empty( $a['category'] ) ) {
			$args['category_name'] = sanitize_text_field( $a['category'] );
		}
		$q = new WP_Query( $args );
		if ( ! $q->have_posts() ) {
			return '<p class="wsk-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		$cpm = max( 200, (int) $o['chars_per_min'] );
		$h   = self::root( 'blog' ) . self::slider_open( $o, 'wsk-slider--cards wsk-slider--blog' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id    = get_the_ID();
			$len   = mb_strlen( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ) );
			$mins  = max( 1, (int) ceil( $len / $cpm ) );
			$thumb = has_post_thumbnail( $id ) ? get_the_post_thumbnail( $id, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) ) : self::placeholder( get_the_title() );
			$h    .= '<div class="wsk-slide"><a class="wsk-card wsk-card--blog" href="' . esc_url( get_permalink( $id ) ) . '">'
				. '<span class="wsk-card__img wsk-card__img--cover">' . $thumb . '</span>'
				. '<h3 class="wsk-card__t wsk-card__t--line">' . esc_html( get_the_title() ) . '</h3>'
				. '<span class="wsk-meta"><span>' . esc_html( self::num( $mins ) ) . ' دقیقه</span><span>انتشار: ' . esc_html( get_the_date( 'F', $id ) ) . ' ماه</span></span></a></div>';
		}
		wp_reset_postdata();
		return $h . self::slider_close() . '</div>';
	}

	/* =====================================================================
	 * ویجت ترکیبی (سازگار با شورت‌کد قدیمی)
	 * ===================================================================== */
	private static function mod_widget( $o, $atts ) {
		$out = '';
		foreach ( array( 'stories', 'banners', 'categories' ) as $m ) {
			$sec = WSK_Options::sec( $m );
			if ( isset( $sec['enabled'] ) && ! $sec['enabled'] ) {
				continue;
			}
			$fn   = 'mod_' . $m;
			$out .= self::$fn( $sec, array() );
		}
		return $out;
	}

	/* =====================================================================
	 * بازدید محصول + کش
	 * ===================================================================== */
	public static function product_beacon() {
		if ( function_exists( 'is_product' ) && is_product() && WSK_Options::get( 'system', 'track_stats', 1 ) && ! current_user_can( 'edit_posts' ) ) {
			echo '<span hidden data-wsk-product="' . (int) get_queried_object_id() . '"></span>' . "\n";
		}
	}

	public static function flush_cache() {
		update_option( 'wsk_cache_gen', time(), false );
	}
}
