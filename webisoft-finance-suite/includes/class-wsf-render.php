<?php
defined( 'ABSPATH' ) || exit;

/**
 * رندر شورت‌کدهای ماژول‌ها. تمام متن‌ها و آیکون‌ها از تنظیمات خوانده می‌شود.
 */
class WSF_Render {

	private static $faq_schema_done = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 11 );
		add_action( 'wp_footer', array( __CLASS__, 'dock' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'orbs' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'bottom_nav' ), 15 );
		add_action( 'wp_head', array( __CLASS__, 'schema_org' ), 8 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hashtag_search' ) );
		add_action( 'wp_body_open', array( __CLASS__, 'tip_bar' ) );
	}

	public static function tags() {
		return array(
			'fin_hero_photo'     => 'hero_photo',
			'fin_search_hero'    => 'search_hero',
			'fin_domains'        => 'domains',
			'fin_process'        => 'process',
			'fin_case_studies'   => 'case_studies',
			'fin_faq'            => 'faq',
			'fin_path'             => 'path_picker',
			'fin_workshop'         => 'workshop',
			'fin_downloads'        => 'downloads',
			'fin_plans'            => 'plans',
			'fin_track'            => 'track_record',
			'fin_disclaimer'       => 'disclaimer',
			'fin_tag_search'       => 'tag_search',
			'fin_tip'              => 'tipjar',
			'fin_stats'          => 'stats',
			'fin_testimonials'   => 'testimonials',
			'fin_cta'            => 'cta',
			'fin_about_page'     => 'about_page',
			'fin_contact_page'   => 'contact_page',
			'fin_booking_form'   => 'booking_form',
			'fin_articles'       => 'articles',
			'fin_blog_archive'   => 'blog_archive',
		);
	}

	public static function register() {
		foreach ( self::tags() as $tag => $module ) {
			add_shortcode(
				$tag,
				function ( $atts ) use ( $module ) {
					return self::run( $module, $atts );
				}
			);
		}
		add_shortcode( 'ws_theme_toggle', array( __CLASS__, 'sc_toggle' ) );
		add_shortcode( 'ws_logo', array( __CLASS__, 'sc_logo' ) );
	}

	private static function run( $module, $atts ) {
		$o = WSF_Options::sec( $module );
		if ( isset( $o['enabled'] ) && ! $o['enabled'] ) {
			return '';
		}
		$atts = is_array( $atts ) ? $atts : array();
		$fn   = 'mod_' . $module;
		return self::tokens( self::$fn( $o, $atts ) );
	}

	/** جایگزینی {minutes} و {firm_name} در خروجی ماژول‌ها. */
	public static function tokens( $html ) {
		if ( false === strpos( $html, '{' ) ) {
			return $html;
		}
		return str_replace(
			array( '{minutes}', '{firm_name}' ),
			array( self::num( (int) WSF_Options::get( 'general', 'consult_minutes', 30 ) ), esc_html( WSF_Options::firm( 'firm_name' ) ) ),
			$html
		);
	}

	/* =====================================================================
	 * ابزارهای مشترک
	 * ===================================================================== */
	public static function dir() {
		$d = WSF_Options::get( 'appearance', 'direction', 'auto' );
		if ( 'rtl' === $d || 'ltr' === $d ) {
			return $d;
		}
		return is_rtl() ? 'rtl' : 'ltr';
	}

	private static function root( $class, $attrs = '' ) {
		return '<div class="wsf wsf-' . esc_attr( $class ) . '" dir="' . esc_attr( self::dir() ) . '"' . $attrs . '>';
	}

	private static function arrow() {
		return WSF_Icons::render( 'rtl' === self::dir() ? 'arrow-left' : 'arrow-right', 'wsf-arrow' );
	}

	private static function eyebrow( $text ) {
		if ( '' === trim( (string) $text ) ) {
			return '';
		}
		return '<div class="wsf-eyebrow"><span class="wsf-eyebrow__line"></span>' . esc_html( $text ) . '<span class="wsf-eyebrow__line"></span></div>';
	}

	/** هدر استاندارد بخش. */
	private static function head( $o, $tag = 'h2', $eyebrow_key = 'badge', $class = '' ) {
		$h  = '<header class="wsf-head ' . esc_attr( $class ) . '">';
		$h .= self::eyebrow( isset( $o[ $eyebrow_key ] ) ? $o[ $eyebrow_key ] : '' );
		if ( ! empty( $o['title'] ) ) {
			$h .= '<' . $tag . ' class="wsf-title">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		}
		if ( ! empty( $o['subtitle'] ) ) {
			$h .= '<p class="wsf-sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		return $h . '</header>';
	}

	private static function tag( $val, $default = 'h2' ) {
		return in_array( $val, array( 'h1', 'h2', 'h3' ), true ) ? $val : $default;
	}

	/** عنوان با عبارت برجسته. */
	private static function hl( $title, $highlight ) {
		$t = esc_html( $title );
		$h = esc_html( $highlight );
		if ( '' !== $h && false !== strpos( $t, $h ) ) {
			$t = preg_replace( '/' . preg_quote( $h, '/' ) . '/u', '<span class="wsf-hl">' . $h . '</span>', $t, 1 );
		}
		return $t;
	}

	/** مقصد لینک: URL مستقیم یا جستجوی سایت. */
	private static function target( $row ) {
		if ( ! empty( $row['url'] ) ) {
			return esc_url( $row['url'] );
		}
		$q = ! empty( $row['q'] ) ? $row['q'] : ( isset( $row['title'] ) ? $row['title'] : ( isset( $row['label'] ) ? $row['label'] : '' ) );
		return esc_url( add_query_arg( 's', rawurlencode( $q ), home_url( '/' ) ) );
	}

	/** اعداد فارسی در سایت‌های فارسی. */
	public static function num( $n ) {
		$s = (string) $n;
		if ( 0 === strpos( determine_locale(), 'fa' ) ) {
			$s = str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), $s );
		}
		return $s;
	}

	private static function image( $val, $size = 'large', $class = '', $alt = '' ) {
		if ( is_numeric( $val ) && (int) $val > 0 ) {
			return wp_get_attachment_image( (int) $val, $size, false, array( 'class' => $class, 'loading' => 'lazy', 'alt' => $alt ) );
		}
		if ( $val ) {
			return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $val ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
		}
		return '';
	}

	/** آدرس لوگوی حالت تاریک (خالی اگر تعریف نشده). */
	public static function logo_dark_url() {
		$d = WSF_Options::get( 'general', 'logo_dark', '' );
		if ( is_numeric( $d ) && (int) $d > 0 ) {
			$u = wp_get_attachment_image_url( (int) $d, 'full' );
			return $u ? $u : '';
		}
		return $d ? (string) $d : '';
	}

	/** [ws_logo height="48" link="1"] — لوگوی روشن/تاریک. */
	public static function sc_logo( $atts = array() ) {
		$a     = shortcode_atts( array( 'height' => 48, 'link' => 1, 'class' => '' ), $atts, 'ws_logo' );
		$light = self::logo_url();
		$dark  = self::logo_dark_url();
		$name  = WSF_Options::firm( 'firm_name' ) ? WSF_Options::firm( 'firm_name' ) : get_bloginfo( 'name' );
		if ( ! $light && ! $dark ) {
			return '<span class="wsf wsf-inline wsf-logo-text">' . esc_html( $name ) . '</span>';
		}
		$h    = max( 16, min( 300, (int) $a['height'] ) );
		$img  = '<img class="wsf-logo__light" src="' . esc_url( $light ? $light : $dark ) . '" alt="' . esc_attr( $name ) . '" style="height:' . $h . 'px;width:auto" decoding="async">';
		if ( $dark ) {
			$img .= '<img class="wsf-logo__dark" src="' . esc_url( $dark ) . '" alt="" aria-hidden="true" style="height:' . $h . 'px;width:auto" decoding="async">';
		}
		$cls  = 'wsf wsf-inline wsf-logo' . ( $dark ? ' wsf-logo--has-dark' : '' ) . ( $a['class'] ? ' ' . sanitize_html_class( $a['class'] ) : '' );
		$html = '<span class="' . esc_attr( $cls ) . '">' . $img . '</span>';
		return $a['link'] ? '<a class="wsf-logo-link" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $name ) . '">' . $html . '</a>' : $html;
	}

	public static function logo_url() {
		$logo = WSF_Options::get( 'general', 'logo', '' );
		if ( is_numeric( $logo ) && (int) $logo > 0 ) {
			$u = wp_get_attachment_image_url( (int) $logo, 'medium' );
			if ( $u ) {
				return $u;
			}
		} elseif ( $logo ) {
			return $logo;
		}
		$id = get_theme_mod( 'custom_logo' );
		if ( $id ) {
			$u = wp_get_attachment_image_url( $id, 'medium' );
			if ( $u ) {
				return $u;
			}
		}
		return get_site_icon_url( 192 );
	}

	private static function placeholder_card( $template ) {
		$text = str_replace(
			array( '{firm_name}', '{firm_title}' ),
			array( WSF_Options::firm( 'firm_name' ), WSF_Options::firm( 'firm_title' ) ),
			(string) $template
		);
		$lines = array_filter( array_map( 'trim', explode( '|', $text ) ) );
		$logo  = self::logo_url();
		$h     = '<div class="wsf-ph">';
		if ( $logo ) {
			$h .= '<img class="wsf-ph__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy">';
		}
		if ( $lines ) {
			$h .= '<span>' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</span>';
		}
		return $h . '</div>';
	}

	private static function lines( $text ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
	}

	/* =====================================================================
	 * ماژول‌ها
	 * ===================================================================== */
	private static function mod_hero_photo( $o, $atts ) {
		$tag  = self::tag( $o['heading_tag'], 'h1' );
		$tel  = WSF_Options::tel_href();
		$b2t  = $o['btn2_text'];
		$b2u  = $o['btn2_url'];
		if ( '' === $b2u && $tel ) {
			$b2u = $tel;
			$b2t = $b2t ? $b2t : WSF_Options::firm( 'phone' );
		}
		$h  = self::root( 'hero' ) . '<div class="wsf-hero__grid wsf-glass"><div class="wsf-hero__content">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsf-pill"><span class="wsf-dot"></span>' . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsf-hero__title">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsf-hero__desc">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '<div class="wsf-hero__btns">';
		if ( $o['btn1_text'] ) {
			$h .= '<a class="wsf-btn wsf-btn--primary" href="' . esc_url( $o['btn1_url'] ) . '">' . esc_html( $o['btn1_text'] ) . self::arrow() . '</a>';
		}
		if ( $b2t && $b2u ) {
			$h .= '<a class="wsf-btn wsf-btn--outline" href="' . esc_url( $b2u ) . '" dir="auto">' . WSF_Icons::render( 'phone' ) . esc_html( $b2t ) . '</a>';
		}
		$h .= '</div></div><div class="wsf-hero__media"><div class="wsf-hero__img">';
		$img = self::image( $o['image'], 'large', '', $o['image_alt'] ? $o['image_alt'] : WSF_Options::firm( 'firm_name' ) );
		if ( $img ) {
			$h .= $img;
		} else {
			$initial = mb_substr( WSF_Options::firm( 'firm_name' ), 0, 1 );
			$h .= '<div class="wsf-hero__ph" aria-hidden="true">' . esc_html( $initial ) . '</div>';
		}
		$h .= '</div>';
		if ( $o['show_float'] && ( $o['float_title'] || $o['float_sub'] ) ) {
			$h .= '<div class="wsf-hero__float wsf-glass"><span class="wsf-iconbox wsf-iconbox--accent">' . WSF_Icons::render( $o['float_icon'] ) . '</span><div><strong>' . esc_html( $o['float_title'] ) . '</strong><small>' . esc_html( $o['float_sub'] ) . '</small></div></div>';
		}
		return $h . '</div></div></div>';
	}

	private static function mod_search_hero( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h2' );
		$h   = self::root( 'sh' ) . '<section class="wsf-sh">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsf-pill wsf-pill--glass"><span class="wsf-dot wsf-dot--ping"></span>' . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsf-sh__title">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsf-sh__sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '<form class="wsf-sh__form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><div class="wsf-sh__field">' . WSF_Icons::render( 'search' ) . '<input type="search" name="s" value="' . esc_attr( get_search_query() ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '" aria-label="' . esc_attr( $o['placeholder'] ) . '" autocomplete="off" required></div><button type="submit" class="wsf-btn wsf-btn--primary">' . esc_html( $o['button'] ) . '</button></form>';
		if ( ! empty( $o['tags'] ) ) {
			$h .= '<div class="wsf-sh__tags">';
			if ( $o['tags_label'] ) {
				$h .= '<span class="wsf-sh__taglabel">' . esc_html( $o['tags_label'] ) . '</span>';
			}
			foreach ( $o['tags'] as $t ) {
				$h .= '<a class="wsf-tag" href="' . self::target( $t ) . '">' . esc_html( $t['label'] ) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</section></div>';
	}

	private static function mod_domains( $o, $atts ) {
		$cols = in_array( (string) $o['cols'], array( '2', '3', '4', '5' ), true ) ? $o['cols'] : '4';
		$h    = self::root( 'domains' );
		$h   .= '<div class="wsf-domains__head"><h2 class="wsf-domains__title">' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['view_all_url'] ) {
			$h .= '<a class="wsf-chip" href="' . esc_url( $o['view_all_url'] ) . '">' . esc_html( $o['view_all_text'] ) . self::arrow() . '</a>';
		}
		$h .= '</div><div class="wsf-grid wsf-cols-' . esc_attr( $cols ) . '">';
		foreach ( $o['items'] as $it ) {
			$h .= '<a class="wsf-dcard wsf-glass" href="' . self::target( $it ) . '"><span class="wsf-iconbox">' . WSF_Icons::render( $it['icon'] ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3>';
			if ( $it['desc'] ) {
				$h .= '<p>' . esc_html( $it['desc'] ) . '</p>';
			}
			$h .= '</a>';
		}
		return $h . '</div></div>';
	}



	private static function mod_process( $o, $atts ) {
		$h = self::root( 'process' ) . self::head( $o, 'h2' ) . '<div class="wsf-grid wsf-cols-3">';
		foreach ( $o['steps'] as $i => $st ) {
			$h .= '<div class="wsf-step wsf-glass"><span class="wsf-step__num">' . esc_html( trim( $o['step_label'] . ' ' . self::num( sprintf( '%02d', $i + 1 ) ) ) ) . '</span><span class="wsf-iconbox wsf-iconbox--lg">' . WSF_Icons::render( $st['icon'] ) . '</span><h3>' . esc_html( $st['title'] ) . '</h3><p>' . esc_html( $st['desc'] ) . '</p></div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_case_studies( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$rtl  = 'rtl' === self::dir();
		$auto = $o['autoplay'] ? ' data-autoplay="' . (int) $o['interval'] . '"' : '';
		$h    = self::root( 'cases', ' data-wsf="slider"' . $auto ) . '<div class="wsf-cases__box"><div class="wsf-cases__info">';
		if ( $o['badge'] ) {
			$h .= '<span class="wsf-cases__badge">' . esc_html( $o['badge'] ) . '</span>';
		}
		$h .= '<h3 class="wsf-cases__title">' . esc_html( $o['title'] ) . '</h3>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsf-cases__desc">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '<div class="wsf-cases__ctrl"><button type="button" class="wsf-round" data-wsf-prev aria-label="' . esc_attr( 'قبلی' ) . '">' . WSF_Icons::render( $rtl ? 'arrow-right' : 'arrow-left' ) . '</button><button type="button" class="wsf-round" data-wsf-next aria-label="' . esc_attr( 'بعدی' ) . '">' . WSF_Icons::render( $rtl ? 'arrow-left' : 'arrow-right' ) . '</button></div></div>';
		$h .= '<div class="wsf-cases__slides" aria-live="polite">';
		foreach ( $o['items'] as $i => $it ) {
			$h .= '<article class="wsf-case' . ( 0 === $i ? ' is-active' : '' ) . '"' . ( 0 === $i ? '' : ' aria-hidden="true"' ) . '><div class="wsf-case__head"><span class="wsf-iconbox wsf-iconbox--lg">' . WSF_Icons::render( $it['icon'] ) . '</span><div><h4>' . esc_html( $it['title'] ) . '</h4>';
			if ( $it['domain'] ) {
				$h .= '<p>' . esc_html( trim( $o['domain_label'] . ' ' . $it['domain'] ) ) . '</p>';
			}
			$h .= '</div></div><div class="wsf-case__body">' . wp_kses_post( $it['body'] ) . '</div></article>';
		}
		return $h . '</div></div></div>';
	}


	private static function mod_faq( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$uid = wp_unique_id( 'wsf-faq-' );
		$h   = self::root( 'faq', ' data-wsf="faq" data-single="' . ( $o['single_open'] ? '1' : '0' ) . '"' ) . '<section aria-labelledby="' . esc_attr( $uid ) . '-title"><header class="wsf-head"><h2 class="wsf-title" id="' . esc_attr( $uid ) . '-title">' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsf-sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><div class="wsf-faq__list">';
		$ld = array();
		foreach ( $o['items'] as $i => $it ) {
			$open = ( 0 === $i && $o['first_open'] );
			$h   .= '<div class="wsf-faq__item' . ( $open ? ' is-open' : '' ) . '"><h3 class="wsf-faq__h"><button type="button" class="wsf-faq__q" id="' . esc_attr( $uid . '-q' . $i ) . '" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $uid . '-a' . $i ) . '"><span>' . esc_html( $it['q'] ) . '</span><span class="wsf-faq__icon" aria-hidden="true">' . WSF_Icons::render( 'plus' ) . '</span></button></h3><div class="wsf-faq__a" id="' . esc_attr( $uid . '-a' . $i ) . '" role="region" aria-labelledby="' . esc_attr( $uid . '-q' . $i ) . '"><div class="wsf-faq__ai"><div class="wsf-faq__text">' . wpautop( wp_kses_post( $it['a'] ) ) . '</div></div></div></div>';
			$ld[] = array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( $it['q'] ),
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $it['a'] ) ),
			);
		}
		$h .= '</div></section>';
		if ( $o['schema'] && ! self::$faq_schema_done ) {
			self::$faq_schema_done = true;
			$h .= self::jsonld( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $ld ) );
		}
		return $h . '</div>';
	}

	private static function mod_stats( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$fa = ( 0 === strpos( determine_locale(), 'fa' ) ) ? '1' : '0';
		$h  = self::root( 'stats', ' data-wsf="stats" data-animate="' . ( $o['animate'] ? '1' : '0' ) . '" data-fa="' . $fa . '"' );
		if ( $o['title'] ) {
			$h .= '<h2 class="wsf-title wsf-title--sm wsf-center">' . esc_html( $o['title'] ) . '</h2>';
		}
		$h .= '<div class="wsf-stats__row wsf-glass">';
		foreach ( $o['items'] as $it ) {
			$h .= '<div class="wsf-stat"><span class="wsf-iconbox">' . WSF_Icons::render( $it['icon'] ) . '</span><div class="wsf-stat__val"><span class="wsf-stat__num" data-count="' . (int) $it['value'] . '" data-suffix="' . esc_attr( $it['suffix'] ) . '">' . esc_html( self::num( (int) $it['value'] ) . $it['suffix'] ) . '</span></div><div class="wsf-stat__label">' . esc_html( $it['label'] ) . '</div></div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_testimonials( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$h = self::root( 'tm' ) . self::head( $o, 'h2' ) . '<div class="wsf-grid wsf-cols-3">';
		foreach ( $o['items'] as $it ) {
			$h .= '<figure class="wsf-tm__card wsf-glass">';
			$r  = (int) $it['rating'];
			if ( $r > 0 ) {
				$h .= '<div class="wsf-stars" role="img" aria-label="' . esc_attr( $r . '/5' ) . '">';
				for ( $i = 1; $i <= 5; $i++ ) {
					$h .= '<span class="' . ( $i <= $r ? 'is-on' : '' ) . '">' . WSF_Icons::render( 'star' ) . '</span>';
				}
				$h .= '</div>';
			}
			$h .= '<blockquote>' . esc_html( $it['text'] ) . '</blockquote><figcaption>';
			$av = self::image( $it['avatar'], 'thumbnail', 'wsf-tm__av', $it['name'] );
			$h .= $av ? $av : '<span class="wsf-tm__av wsf-tm__av--ph">' . esc_html( mb_substr( $it['name'], 0, 1 ) ) . '</span>';
			$h .= '<span><strong>' . esc_html( $it['name'] ) . '</strong><small>' . esc_html( $it['role'] ) . '</small></span></figcaption></figure>';
		}
		return $h . '</div></div>';
	}


	private static function mod_cta( $o, $atts ) {
		$style = in_array( $o['style'], array( 'gradient', 'dark', 'light' ), true ) ? $o['style'] : 'gradient';
		$h     = self::root( 'cta' ) . '<div class="wsf-cta__box wsf-cta__box--' . esc_attr( $style ) . '"><div class="wsf-cta__txt"><h2>' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['text'] ) {
			$h .= '<p>' . esc_html( $o['text'] ) . '</p>';
		}
		$h .= '</div><div class="wsf-cta__btns">';
		if ( $o['btn1_text'] ) {
			$h .= '<a class="wsf-btn wsf-btn--accent" href="' . esc_url( $o['btn1_url'] ) . '">' . esc_html( $o['btn1_text'] ) . self::arrow() . '</a>';
		}
		$tel = WSF_Options::tel_href();
		if ( $o['show_phone'] && $tel ) {
			$h .= '<a class="wsf-btn wsf-btn--ghost" href="' . esc_url( $tel ) . '" dir="auto">' . WSF_Icons::render( 'phone' ) . esc_html( WSF_Options::firm( 'phone' ) ) . '</a>';
		}
		return $h . '</div></div></div>';
	}

	/* ---------- ماژول‌های مخصوص کسب‌وکار مالی ---------- */
	private static function path_panel( $items, $id, $parent, $o, $visible, $title = '' ) {
		$n  = count( $items );
		$h  = '<div class="wsf-path__panel' . ( $visible ? ' is-active' : '' ) . '" data-panel="' . esc_attr( $id ) . '"' . ( $visible ? '' : ' hidden' ) . '>';
		if ( $parent ) {
			$h .= '<div class="wsf-path__bar"><button type="button" class="wsf-chip wsf-path__back" data-back="' . esc_attr( $parent ) . '">' . self::arrow() . esc_html( $o['back_label'] ) . '</button><span class="wsf-path__crumb">' . esc_html( $title ) . '</span></div>';
		}
		$h  .= '<div class="wsf-grid wsf-cols-' . ( $n >= 4 ? '4' : ( 3 === $n ? '3' : '2' ) ) . '">';
		$sub = '';
		foreach ( $items as $i => $it ) {
			$cid   = $id . '-' . $i;
			$inner = '<span class="wsf-iconbox">' . WSF_Icons::render( $it['icon'] ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3>' . ( $it['desc'] ? '<p>' . esc_html( $it['desc'] ) . '</p>' : '' );
			if ( ! empty( $it['items'] ) ) {
				$h   .= '<button type="button" class="wsf-pathcard wsf-glass" data-open="' . esc_attr( $cid ) . '">' . $inner . '</button>';
				$sub .= self::path_panel( $it['items'], $cid, $id, $o, false, $it['title'] );
			} else {
				$h .= '<a class="wsf-pathcard wsf-glass" href="' . self::target( $it ) . '">' . $inner . '</a>';
			}
		}
		return $h . '</div></div>' . $sub;
	}

	private static function mod_path_picker( $o, $atts ) {
		if ( empty( $o['tree'] ) ) {
			return '';
		}
		$uid = wp_unique_id( 'wsf-path-' );
		$h   = self::root( 'path', ' data-wsf="path"' ) . '<header class="wsf-head"><h2 class="wsf-title">' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsf-sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		return $h . '</header><div class="wsf-path__stage">' . self::path_panel( $o['tree'], $uid, null, $o, true ) . '</div></div>';
	}

	private static function mod_workshop( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$h = self::root( 'workshop' ) . self::head( $o, 'h2' ) . '<div class="wsf-grid wsf-cols-3">';
		foreach ( $o['items'] as $it ) {
			$locked = ( 'member' === $it['lock'] && ! WSF_Access::can( 'member' ) );
			$purl   = WSF_Woo::plans_url( $o['plans_url'] );
			$href   = $locked ? $purl : $it['url'];
			$badge  = $locked ? WSF_Icons::render( 'lock' ) . esc_html( $o['locked_label'] ) : esc_html( $it['badge'] );
			$inner  = '<span class="wsf-iconbox">' . WSF_Icons::render( $it['icon'] ) . '</span>' . ( $badge ? '<span class="wsf-lockbadge' . ( $locked ? ' is-locked' : '' ) . '">' . $badge . '</span>' : '' ) . '<h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['desc'] ) . '</p>';
			if ( $locked && $purl ) {
				$inner .= '<span class="wsf-link">' . esc_html( is_user_logged_in() ? $o['locked_btn'] : $o['login_btn'] ) . self::arrow() . '</span>';
			}
			$cls = 'wsf-wcard wsf-glass' . ( $locked ? ' is-locked' : '' );
			$h  .= $href ? '<a class="' . $cls . '" href="' . esc_url( $href ) . '">' . $inner . '</a>' : '<div class="' . $cls . '">' . $inner . '</div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_downloads( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$h = self::root( 'dlsec' ) . self::head( $o, 'h2' ) . '<div class="wsf-grid wsf-cols-3 wsf-grid--tight">';
		foreach ( $o['items'] as $it ) {
			$locked = ( 'member' === $it['lock'] && ! WSF_Access::can( 'member' ) );
			$h     .= '<div class="wsf-dl wsf-glass' . ( $locked ? ' is-locked' : '' ) . '"><span class="wsf-iconbox">' . WSF_Icons::render( $it['icon'] ) . '</span><div class="wsf-dl__t"><h3>' . esc_html( $it['title'] ) . '</h3>' . ( $it['desc'] ? '<p>' . esc_html( $it['desc'] ) . '</p>' : '' ) . '</div>';
			if ( $locked ) {
				$purl = WSF_Woo::plans_url( $o['plans_url'] );
				$lbl  = is_user_logged_in() ? $o['locked_btn'] : $o['login_btn'];
				$h   .= $purl ? '<a class="wsf-chip" href="' . esc_url( $purl ) . '">' . WSF_Icons::render( 'lock' ) . esc_html( $lbl ) . '</a>' : '<span class="wsf-chip">' . WSF_Icons::render( 'lock' ) . esc_html( $lbl ) . '</span>';
			} elseif ( $it['file'] ) {
				$h .= '<a class="wsf-chip" href="' . esc_url( WSF_Access::download_url( $it['file'] ) ) . '">' . WSF_Icons::render( 'download' ) . esc_html( $o['download_btn'] ) . '</a>';
			} else {
				$h .= '<span class="wsf-chip is-off">' . esc_html( 'به‌زودی' ) . '</span>';
			}
			$h .= '</div>';
		}
		return $h . '</div></div>';
	}

	/** پلن‌ها از عضویت ویژه‌ی Webisoft Woo، به‌شکل همان ساختار ردیف‌های دستی. */
	private static function woo_plan_items( $o ) {
		$items  = array();
		$member = WSF_Woo::member( get_current_user_id() );
		$cta    = WSF_Woo::cta_url();
		foreach ( WSF_Woo::plans() as $i => $p ) {
			$feat = self::lines( $o['woo_features'] );
			array_unshift( $feat, sprintf( 'مدت اعتبار: %s روز', self::num( $p['days'] ) ) );
			if ( $p['off'] > 0 ) {
				$feat[] = sprintf( '%s٪ تخفیف خودکار روی خریدها', self::num( (int) $p['off'] ) );
			}
			$mine = $member && (int) $member['plan'] === (int) $i;
			$btn  = $member ? $o['woo_renew_btn'] : ( is_user_logged_in() ? $o['woo_btn'] : $o['woo_login_btn'] );
			$items[] = array(
				'name'     => $p['name'],
				'price'    => $p['price'] > 0 ? self::num( number_format_i18n( $p['price'] ) ) . ' تومان' : 'رایگان',
				'period'   => '',
				'desc'     => $mine ? 'اشتراک فعال تا ' . self::num( WSF_Woo::date( $member['until'] ) ) : '',
				'features' => implode( "\n", $feat ),
				'btn_text' => $btn,
				'btn_url'  => $cta,
				'badge'    => $mine ? 'اشتراک فعلی شما' : '',
				'featured' => ( (int) $o['woo_featured'] === (int) $i ) ? 1 : 0,
			);
		}
		return $items;
	}

	private static function mod_plans( $o, $atts ) {
		if ( 'manual' !== $o['source'] ) {
			$woo = self::woo_plan_items( $o );
			if ( $woo ) {
				$o['items'] = $woo;
			} elseif ( 'woo' === $o['source'] ) {
				$o['items'] = array();
			}
		}
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$h = self::root( 'plansec' ) . self::head( $o, 'h2' ) . '<div class="wsf-plans">';
		foreach ( $o['items'] as $it ) {
			$h .= '<div class="wsf-plan wsf-glass' . ( $it['featured'] ? ' is-featured' : '' ) . '">';
			if ( $it['badge'] ) {
				$h .= '<span class="wsf-plan__badge">' . esc_html( $it['badge'] ) . '</span>';
			}
			$h .= '<h3>' . esc_html( $it['name'] ) . '</h3>';
			if ( $it['price'] ) {
				$h .= '<div class="wsf-plan__price">' . esc_html( $it['price'] ) . ( $it['period'] ? '<small>' . esc_html( $it['period'] ) . '</small>' : '' ) . '</div>';
			}
			if ( $it['desc'] ) {
				$h .= '<p>' . esc_html( $it['desc'] ) . '</p>';
			}
			$feat = self::lines( $it['features'] );
			if ( $feat ) {
				$h .= '<ul>';
				foreach ( $feat as $f ) {
					$h .= '<li>' . WSF_Icons::render( 'check' ) . esc_html( $f ) . '</li>';
				}
				$h .= '</ul>';
			}
			if ( $it['btn_text'] && $it['btn_url'] ) {
				$h .= '<a class="wsf-btn ' . ( $it['featured'] ? 'wsf-btn--primary' : 'wsf-btn--outline' ) . '" href="' . esc_url( $it['btn_url'] ) . '">' . esc_html( $it['btn_text'] ) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_disclaimer( $o, $atts ) {
		return '<aside class="wsf wsf-disclaimer" dir="' . esc_attr( self::dir() ) . '" role="note"><strong>' . WSF_Icons::render( 'info' ) . esc_html( $o['title'] ) . '</strong>' . wp_kses_post( $o['text'] ) . '</aside>';
	}

	private static function mod_track_record( $o, $atts ) {
		if ( empty( $o['rows'] ) ) {
			return '';
		}
		$h  = self::root( 'track' ) . self::head( $o, 'h2' ) . '<div class="wsf-table-wrap"><table class="wsf-table"><thead><tr>';
		$cols = array( 'period' => $o['col_period'], 'asset' => $o['col_asset'], 'entry' => $o['col_entry'], 'exit' => $o['col_exit'], 'note' => $o['col_note'] );
		foreach ( $cols as $label ) {
			$h .= '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		$h .= '</tr></thead><tbody>';
		foreach ( $o['rows'] as $r ) {
			$h .= '<tr>';
			foreach ( $cols as $k => $label ) {
				$h .= '<td data-label="' . esc_attr( $label ) . '">' . ( '' !== $r[ $k ] ? esc_html( $r[ $k ] ) : '—' ) . '</td>';
			}
			$h .= '</tr>';
		}
		$h .= '</tbody></table></div>';
		if ( $o['show_disclaimer'] ) {
			$d = WSF_Options::sec( 'disclaimer' );
			if ( ! isset( $d['enabled'] ) || $d['enabled'] ) {
				$h .= self::mod_disclaimer( $d, array() );
			}
		}
		return $h . '</div>';
	}

	private static function mod_tag_search( $o, $atts ) {
		$h = self::root( 'tags' ) . '<div class="wsf-tags__box wsf-glass"><h2 class="wsf-title wsf-title--sm">' . esc_html( $o['title'] ) . '</h2><form class="wsf-sh__form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><div class="wsf-sh__field">' . WSF_Icons::render( 'search' ) . '<input type="search" name="s" placeholder="' . esc_attr( $o['placeholder'] ) . '" aria-label="' . esc_attr( $o['placeholder'] ) . '" autocomplete="off" required></div><button type="submit" class="wsf-btn wsf-btn--primary">' . esc_html( $o['button'] ) . '</button></form>';
		$n = (int) $o['popular'];
		if ( $n > 0 ) {
			$tags = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => $n, 'hide_empty' => true ) );
			if ( $tags && ! is_wp_error( $tags ) ) {
				$h .= '<div class="wsf-sh__tags"><span class="wsf-sh__taglabel">' . esc_html( $o['popular_label'] ) . '</span>';
				foreach ( $tags as $t ) {
					$h .= '<a class="wsf-tag" href="' . esc_url( get_tag_link( $t ) ) . '">#' . esc_html( $t->name ) . '</a>';
				}
				$h .= '</div>';
			}
		}
		return $h . '</div></div>';
	}

	private static function tip_inner( $o ) {
		return '<span class="wsf-tip__ic">' . WSF_Icons::render( $o['icon'] ) . '</span><span class="wsf-tip__t">' . esc_html( $o['text'] ) . '</span>' . ( ( $o['btn_text'] && $o['btn_url'] ) ? '<a class="wsf-btn wsf-btn--accent wsf-btn--sm" href="' . esc_url( $o['btn_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $o['btn_text'] ) . '</a>' : '' );
	}

	private static function mod_tipjar( $o, $atts ) {
		return self::root( 'tipsec' ) . '<div class="wsf-tip wsf-glass">' . self::tip_inner( $o ) . '</div></div>';
	}

	/** نوار بالای سایت (wp_body_open). */
	public static function tip_bar() {
		$o = WSF_Options::sec( 'tipjar' );
		if ( empty( $o['enabled'] ) || empty( $o['show_bar'] ) || empty( $o['text'] ) ) {
			return;
		}
		echo self::tokens( '<div class="wsf wsf-tipbar" data-wsf="tipbar" dir="' . esc_attr( self::dir() ) . '" role="region" aria-label="' . esc_attr( $o['text'] ) . '">' . self::tip_inner( $o ) . ( $o['dismissible'] ? '<button type="button" class="wsf-tipbar__x" data-wsf-close aria-label="close">×</button>' : '' ) . '</div>' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** جستجوی هشتگ: عبارتی که با # شروع شود فقط روی برچسب‌ها فیلتر می‌شود. */
	public static function hashtag_search( $q ) {
		if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
			return;
		}
		$s = trim( (string) $q->get( 's' ) );
		if ( '' === $s || ( '#' !== $s[0] ) ) {
			return;
		}
		$name = trim( ltrim( $s, '#' ) );
		$term = $name ? get_term_by( 'name', $name, 'post_tag' ) : false;
		if ( ! $term && $name ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), 'post_tag' );
		}
		$q->set( 's', '' );
		if ( $term ) {
			$q->set( 'tag_id', (int) $term->term_id );
		} else {
			$q->set( 'post__in', array( 0 ) );
		}
	}

	private static function mod_about_page( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h1' );
		$h   = self::root( 'about' ) . '<header class="wsf-head wsf-head--hero">' . self::eyebrow( $o['eyebrow'] ) . '<' . $tag . ' class="wsf-title wsf-title--xl">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsf-sub wsf-sub--lg">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '</header><div class="wsf-grid wsf-cols-3">';
		foreach ( $o['features'] as $f ) {
			$h .= '<div class="wsf-card wsf-glass"><span class="wsf-iconbox wsf-iconbox--lg">' . WSF_Icons::render( $f['icon'] ) . '</span><h3>' . esc_html( $f['title'] ) . '</h3><p>' . esc_html( $f['desc'] ) . '</p></div>';
		}
		$h .= '</div><div class="wsf-grid wsf-cols-2 wsf-mt">';
		$h .= '<div class="wsf-card wsf-glass"><div class="wsf-card__head"><span class="wsf-iconbox">' . WSF_Icons::render( $o['mission_icon'] ) . '</span><h3>' . esc_html( $o['mission_title'] ) . '</h3></div><p>' . esc_html( $o['mission_text'] ) . '</p></div>';
		$h .= '<div class="wsf-card wsf-card--dark"><div class="wsf-card__head"><span class="wsf-iconbox wsf-iconbox--accent">' . WSF_Icons::render( $o['vision_icon'] ) . '</span><h3>' . esc_html( $o['vision_title'] ) . '</h3></div><p>' . esc_html( $o['vision_text'] ) . '</p></div>';
		return $h . '</div></div>';
	}

	private static function mod_contact_page( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h1' );
		$g   = WSF_Options::sec( 'general' );
		$h   = self::root( 'contact' ) . '<header class="wsf-head wsf-head--hero">' . self::eyebrow( $o['eyebrow'] ) . '<' . $tag . ' class="wsf-title wsf-title--xl">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsf-sub wsf-sub--lg">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><div class="wsf-grid wsf-cols-3 wsf-info">';
		if ( $g['address'] ) {
			$h .= '<div class="wsf-icard wsf-glass"><span class="wsf-iconbox">' . WSF_Icons::render( 'pin' ) . '</span><h3>' . esc_html( $o['address_label'] ) . '</h3><p>' . esc_html( $g['address'] ) . '</p>' . ( ( $o['show_hours'] && $g['hours'] ) ? '<small>' . esc_html( $g['hours'] ) . '</small>' : '' ) . '</div>';
		}
		$tel = WSF_Options::tel_href();
		if ( $tel ) {
			$h .= '<div class="wsf-icard wsf-glass"><span class="wsf-iconbox">' . WSF_Icons::render( 'phone' ) . '</span><h3>' . esc_html( $o['phone_label'] ) . '</h3><a class="wsf-icard__val" href="' . esc_url( $tel ) . '" dir="ltr">' . esc_html( $g['phone'] ) . '</a><small>' . esc_html( $o['phone_note'] ) . '</small></div>';
		}
		if ( $g['email'] && is_email( $g['email'] ) ) {
			$h .= '<div class="wsf-icard wsf-glass"><span class="wsf-iconbox">' . WSF_Icons::render( 'mail' ) . '</span><h3>' . esc_html( $o['email_label'] ) . '</h3><a class="wsf-icard__val" href="mailto:' . esc_attr( antispambot( $g['email'] ) ) . '" dir="ltr">' . esc_html( antispambot( $g['email'] ) ) . '</a><small>' . esc_html( $o['email_note'] ) . '</small></div>';
		}
		$h .= '</div><div class="wsf-grid wsf-cols-2 wsf-mt"><div class="wsf-box wsf-glass"><h3 class="wsf-box__title">' . esc_html( $o['form_title'] ) . '</h3><p class="wsf-box__sub">' . esc_html( $o['form_sub'] ) . '</p>';
		$h .= WSF_Leads::render_form(
			array(
				'source'  => 'contact_page',
				'fields'  => array( 'name', 'phone', 'email' => $o['show_email_field'], 'subject' => $o['show_subject'], 'message' => true ),
				'button'  => $o['form_button'],
				'success' => $o['form_success'],
			)
		);
		$h .= '</div><div class="wsf-box wsf-glass wsf-box--side"><div><h3 class="wsf-box__title">' . esc_html( $o['side_title'] ) . '</h3><div class="wsf-box__text">' . wpautop( wp_kses_post( $o['side_text'] ) ) . '</div>';
		$social = self::social_links();
		if ( $o['quick_title'] || ( $o['show_social'] && $social ) ) {
			$h .= '<div class="wsf-quick"><div class="wsf-quick__t">' . WSF_Icons::render( 'headset' ) . esc_html( $o['quick_title'] ) . '</div><p>' . esc_html( $o['quick_text'] ) . '</p>';
			if ( $o['show_social'] && $social ) {
				$h .= '<div class="wsf-quick__links">';
				foreach ( $social as $s ) {
					$h .= '<a class="wsf-chip" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener noreferrer">' . WSF_Icons::render( $s['icon'] ) . esc_html( $s['label'] ) . '</a>';
				}
				$h .= '</div>';
			}
			$h .= '</div>';
		}
		$h .= '</div>';
		if ( $o['show_map'] && $g['map_embed'] && 0 === strpos( $g['map_embed'], 'https://' ) ) {
			$h .= '<div class="wsf-map"><iframe src="' . esc_url( $g['map_embed'] ) . '" title="' . esc_attr( $o['address_label'] ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
		}
		return $h . '</div></div></div>';
	}

	public static function social_links() {
		$g   = WSF_Options::sec( 'general' );
		$out = array();
		$wa  = preg_replace( '/[^0-9]/', '', WSF_Options::normalize_digits( $g['whatsapp'] ) );
		if ( $wa ) {
			$out[] = array( 'icon' => 'whatsapp', 'label' => 'واتساپ', 'url' => 'https://wa.me/' . $wa );
		}
		$tg = preg_replace( '/[^A-Za-z0-9_]/', '', ltrim( (string) $g['telegram'], '@' ) );
		if ( $tg ) {
			$out[] = array( 'icon' => 'send', 'label' => 'تلگرام', 'url' => 'https://t.me/' . $tg );
		}
		if ( $g['instagram'] ) {
			$out[] = array( 'icon' => 'instagram', 'label' => 'اینستاگرام', 'url' => $g['instagram'] );
		}
		return $out;
	}

	private static function mod_booking_form( $o, $atts ) {
		$anchor = sanitize_key( $o['anchor'] );
		$h      = self::root( 'booking', $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '' ) . '<div class="wsf-booking">' . self::head( $o, 'h2' ) . '<div class="wsf-box wsf-glass">';
		$h     .= WSF_Leads::render_form(
			array(
				'source'             => 'booking_form',
				'fields'             => array( 'name', 'phone', 'email' => $o['show_email_field'], 'subject' => $o['show_subject'], 'domain' => $o['show_domain'], 'message' => $o['show_message'] ),
				'domain_options'     => self::lines( $o['domain_options'] ),
				'domain_placeholder' => $o['domain_placeholder'],
				'button'             => $o['button'],
				'success'            => $o['success'],
				'note'               => $o['privacy_note'],
			)
		);
		return $h . '</div></div></div>';
	}

	private static function post_card( $o, $class_prefix ) {
		$cats = get_the_category();
		$cat  = ! empty( $cats ) ? $cats[0]->name : '';
		$h    = '<article class="wsf-post wsf-glass"><div class="wsf-post__img"><a href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">';
		if ( has_post_thumbnail() ) {
			$h .= get_the_post_thumbnail( null, 'large', array( 'loading' => 'lazy', 'alt' => the_title_attribute( array( 'echo' => false ) ) ) );
		} else {
			$h .= self::placeholder_card( $o['placeholder_text'] );
		}
		$h .= '</a>';
		if ( $cat ) {
			$h .= '<span class="wsf-post__cat">' . esc_html( $cat ) . '</span>';
		}
		$h .= '</div><div class="wsf-post__body"><h3 class="wsf-post__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3><p class="wsf-post__desc">' . esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 30 ) ) . '</p><div class="wsf-post__foot">';
		if ( ! isset( $o['show_date'] ) || $o['show_date'] ) {
			$h .= '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
		}
		$h .= '<a class="wsf-link" href="' . esc_url( get_permalink() ) . '">' . esc_html( $o['readmore'] ) . self::arrow() . '</a></div></div></article>';
		return $h;
	}

	private static function mod_articles( $o, $atts ) {
		$atts  = shortcode_atts( array( 'count' => $o['count'], 'category' => $o['default_category'], 'columns' => $o['columns'] ), $atts, 'fin_articles' );
		$cols  = in_array( (string) $atts['columns'], array( '2', '3', '4' ), true ) ? $atts['columns'] : '3';
		$args  = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 24, (int) $atts['count'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( $atts['category'] ) {
			$args['category_name'] = sanitize_title( $atts['category'] );
		}
		$q  = new WP_Query( $args );
		$h  = self::root( 'articles' ) . self::head( $o, 'h2' ) . '<div class="wsf-grid wsf-cols-' . esc_attr( $cols ) . '">';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$h .= self::post_card( $o, 'a' );
			}
			wp_reset_postdata();
		} else {
			$h .= '<p class="wsf-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		return $h . '</div></div>';
	}

	private static function archive_base_url() {
		return remove_query_arg( array( 'fin_page', 'fin_cat' ), add_query_arg( array() ) );
	}

	private static function mod_blog_archive( $o, $atts ) {
		$atts   = shortcode_atts( array( 'per_page' => $o['per_page'] ), $atts, 'fin_blog_archive' );
		$tag    = self::tag( $o['heading_tag'], 'h2' );
		$paged  = isset( $_GET['fin_page'] ) ? max( 1, absint( wp_unslash( $_GET['fin_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$cat    = isset( $_GET['fin_cat'] ) ? sanitize_title( wp_unslash( $_GET['fin_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$args   = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 60, (int) $atts['per_page'] ) ),
			'paged'               => $paged,
			'ignore_sticky_posts' => true,
		);
		if ( $cat ) {
			$args['category_name'] = $cat;
		}
		$q    = new WP_Query( $args );
		$base = self::archive_base_url();
		$h    = self::root( 'archive' ) . '<header class="wsf-head">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsf-pill wsf-pill--glass">' . WSF_Icons::render( 'book' ) . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsf-title wsf-title--xl">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsf-sub wsf-sub--lg">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><nav class="wsf-filter" aria-label="' . esc_attr( $o['badge'] ) . '"><a class="wsf-fbtn' . ( $cat ? '' : ' is-active' ) . '" href="' . esc_url( $base ) . '">' . esc_html( $o['all_label'] ) . '</a>';
		foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) {
			$h .= '<a class="wsf-fbtn' . ( $cat === $c->slug ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'fin_cat', $c->slug, $base ) ) . '">' . esc_html( $c->name ) . '</a>';
		}
		$h .= '</nav><div class="wsf-grid wsf-cols-3">';
		if ( $q->have_posts() ) {
			$o['show_date'] = 1;
			while ( $q->have_posts() ) {
				$q->the_post();
				$h .= self::post_card( $o, 'b' );
			}
			wp_reset_postdata();
		} else {
			$h .= '<p class="wsf-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		$h .= '</div>';
		if ( $q->max_num_pages > 1 ) {
			$with_cat = $cat ? add_query_arg( 'fin_cat', $cat, $base ) : $base;
			$pg_base  = $with_cat . ( false === strpos( $with_cat, '?' ) ? '?' : '&' ) . 'fin_page=%#%';
			$links    = paginate_links(
				array(
					'base'      => $pg_base,
					'format'    => '',
					'current'   => $paged,
					'total'     => $q->max_num_pages,
					'prev_text' => esc_html( $o['prev_text'] ),
					'next_text' => esc_html( $o['next_text'] ),
				)
			);
			$h .= '<nav class="wsf-pagination" aria-label="Pagination">' . wp_kses_post( $links ) . '</nav>';
		}
		return $h . '</div>';
	}

	/* =====================================================================
	 * تغییر حالت روشن/تاریک و داک شناور
	 * ===================================================================== */
	private static function toggle_button( $extra = '' ) {
		return '<button type="button" class="wsf-toggle ' . esc_attr( $extra ) . '" data-wsf-theme-toggle aria-pressed="false" aria-label="' . esc_attr( 'تغییر حالت روشن/تاریک' ) . '"><span class="wsf-toggle__sun">' . WSF_Icons::render( 'sun' ) . '</span><span class="wsf-toggle__moon">' . WSF_Icons::render( 'moon' ) . '</span></button>';
	}

	public static function sc_toggle() {
		if ( ! WSF_Assets::dark_enabled() ) {
			return '';
		}
		return '<span class="wsf wsf-inline" dir="' . esc_attr( self::dir() ) . '">' . self::toggle_button( 'wsf-toggle--inline' ) . '</span>';
	}

	/* ---------- نوار ناوبری پایین ---------- */
	public static function bnav_visible() {
		$o = WSF_Options::sec( 'bottom_nav' );
		if ( empty( $o['enabled'] ) || empty( $o['items'] ) || is_admin() ) {
			return false;
		}
		if ( ! empty( $o['hide_checkout'] ) && ( ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_cart' ) && is_cart() ) ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security
		foreach ( self::lines( $o['hide_paths'] ) as $frag ) {
			if ( false !== strpos( rawurldecode( $uri ), $frag ) ) {
				return false;
			}
		}
		return true;
	}

	private static function bnav_url( $raw ) {
		switch ( trim( $raw ) ) {
			case '{home}':
				return home_url( '/' );
			case '{blog}':
				$id = (int) get_option( 'page_for_posts' );
				return $id ? get_permalink( $id ) : home_url( '/?post_type=post' );
			case '{account}':
				if ( function_exists( 'wc_get_page_permalink' ) ) {
					return wc_get_page_permalink( 'myaccount' );
				}
				return is_user_logged_in() ? admin_url( 'profile.php' ) : wp_login_url();
			case '{consult}':
				$a = sanitize_key( WSF_Options::get( 'booking_form', 'anchor', 'consultation' ) );
				return ( is_front_page() ? '' : home_url( '/' ) ) . '#' . $a;
		}
		return $raw;
	}

	private static function bnav_active( $raw, $url ) {
		$raw = trim( $raw );
		if ( '{account}' === $raw ) {
			return function_exists( 'is_account_page' ) && is_account_page();
		}
		if ( '{consult}' === $raw || false !== strpos( $url, '#' ) || false !== strpos( $url, '?' ) ) {
			return false;
		}
		$cur  = untrailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security
		$path = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$home = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		if ( $path === $home ) {
			return $cur === $home || '' === $cur;
		}
		return '' !== $path && ( $cur === $path || 0 === strpos( $cur . '/', $path . '/' ) );
	}

	public static function bottom_nav() {
		if ( ! self::bnav_visible() ) {
			return;
		}
		$o  = WSF_Options::sec( 'bottom_nav' );
		$h  = '<nav class="wsf wsf-bnav wsf-bnav--' . esc_attr( 'flat' === $o['style'] ? 'flat' : 'floating' ) . ( 'all' === $o['show_on'] ? ' wsf-bnav--all' : '' ) . '" dir="' . esc_attr( self::dir() ) . '" aria-label="' . esc_attr( 'ناوبری اصلی' ) . '"><ul>';
		$n  = 0;
		foreach ( $o['items'] as $it ) {
			$url = self::bnav_url( $it['url'] );
			if ( '' === $url ) {
				continue;
			}
			$n++;
			$act = self::bnav_active( $it['url'], $url );
			$h  .= '<li class="' . ( $it['highlight'] ? 'is-hl' : '' ) . '"><a href="' . esc_url( $url ) . '"' . ( $act ? ' class="is-active" aria-current="page"' : '' ) . '><span class="wsf-bnav__ic">' . WSF_Icons::render( $it['icon'] ) . '</span>' . ( ( $o['show_labels'] || $it['highlight'] ) && $it['label'] ? '<span class="wsf-bnav__t">' . esc_html( $it['label'] ) . '</span>' : '<span class="wsf-sr">' . esc_html( $it['label'] ) . '</span>' ) . '</a></li>';
		}
		if ( $n ) {
			echo $h . '</ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** هاله‌های نوری پس‌زمینه (سه المان محو، رنگ از پالت). */
	public static function orbs() {
		if ( WSF_Options::get( 'utilities', 'orbs', 1 ) ) {
			echo '<div class="wsf-orbs" aria-hidden="true"><span class="o1"></span><span class="o2"></span><span class="o3"></span></div>';
		}
	}

	public static function dock() {
		$items = array( 'bottom-start' => '', 'bottom-end' => '' );

		if ( WSF_Assets::dark_enabled() ) {
			$pos = WSF_Options::get( 'darkmode', 'toggle_position', 'bottom-end' );
			if ( isset( $items[ $pos ] ) ) {
				$items[ $pos ] .= self::toggle_button( 'wsf-toggle--float' );
			}
		}

		$f = WSF_Options::sec( 'floating' );
		if ( $f['enabled'] ) {
			$btns = '';
			$tel  = WSF_Options::tel_href();
			if ( $f['show_phone'] && $tel ) {
				$btns .= '<a class="wsf-fab wsf-fab--call" href="' . esc_url( $tel ) . '" aria-label="' . esc_attr( 'تماس تلفنی' ) . '">' . WSF_Icons::render( 'phone' ) . '</a>';
			}
			foreach ( self::social_links() as $s ) {
				if ( ( 'whatsapp' === $s['icon'] && $f['show_whatsapp'] ) || ( 'send' === $s['icon'] && $f['show_telegram'] ) ) {
					$btns .= '<a class="wsf-fab wsf-fab--' . esc_attr( $s['icon'] ) . '" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $s['label'] ) . '">' . WSF_Icons::render( $s['icon'] ) . '</a>';
				}
			}
			if ( $f['cta_label'] ) {
				$btns .= '<a class="wsf-fab wsf-fab--cta" href="' . esc_url( $f['cta_url'] ) . '">' . esc_html( $f['cta_label'] ) . '</a>';
			}
			$fpos = isset( $items[ $f['position'] ] ) ? $f['position'] : 'bottom-start';
			if ( $btns ) {
				$items[ $fpos ] .= '<div class="wsf-fabs' . ( $f['mobile_only'] ? ' wsf-mobile-only' : '' ) . '">' . $btns . '</div>';
			}
		}

		foreach ( $items as $pos => $html ) {
			if ( $html ) {
				echo '<div class="wsf wsf-dock wsf-dock--' . esc_attr( $pos ) . '" dir="' . esc_attr( self::dir() ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- قطعات بالا escape شده‌اند.
			}
		}
	}

	/* =====================================================================
	 * سئو
	 * ===================================================================== */
	private static function jsonld( $data ) {
		return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>';
	}

	public static function schema_org() {
		$s = WSF_Options::sec( 'seo' );
		if ( empty( $s['schema_org'] ) || ! is_front_page() ) {
			return;
		}
		$g    = WSF_Options::sec( 'general' );
		$name = $g['firm_name'] ? $g['firm_name'] : get_bloginfo( 'name' );
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => $s['schema_type'],
			'name'     => $name,
			'url'      => home_url( '/' ),
		);
		$logo = self::logo_url();
		if ( $logo ) {
			$data['logo']  = $logo;
			$data['image'] = $logo;
		}
		if ( WSF_Options::tel_href() ) {
			$data['telephone'] = $g['phone'];
		}
		if ( $g['email'] && is_email( $g['email'] ) ) {
			$data['email'] = $g['email'];
		}
		if ( $g['address'] || $g['city'] ) {
			$data['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $g['address'],
					'addressLocality' => $g['city'],
					'addressCountry'  => $s['country'],
				)
			);
		}
		if ( $s['price_range'] ) {
			$data['priceRange'] = $s['price_range'];
		}
		if ( $s['area_served'] ) {
			$data['areaServed'] = $s['area_served'];
		}
		if ( $s['hours_spec'] ) {
			$data['openingHours'] = array_map( 'trim', explode( ',', $s['hours_spec'] ) );
		}
		if ( $s['geo'] && preg_match( '/^\s*(-?\d+(\.\d+)?)\s*,\s*(-?\d+(\.\d+)?)\s*$/', $s['geo'], $m ) ) {
			$data['geo'] = array( '@type' => 'GeoCoordinates', 'latitude' => (float) $m[1], 'longitude' => (float) $m[3] );
		}
		$same = array();
		foreach ( self::social_links() as $l ) {
			$same[] = $l['url'];
		}
		if ( $same ) {
			$data['sameAs'] = $same;
		}
		echo self::jsonld( $data ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** صفحات فیلتر/صفحه‌بندی آرشیو مقالات: noindex,follow. */
	public static function robots( $robots ) {
		if ( is_singular() && ( isset( $_GET['fin_cat'] ) || ( isset( $_GET['fin_page'] ) && absint( wp_unslash( $_GET['fin_page'] ) ) > 1 ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
}
