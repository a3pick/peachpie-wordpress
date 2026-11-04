<?php
defined( 'ABSPATH' ) || exit;

/**
 * رندر شورت‌کدهای ماژول‌ها. تمام متن‌ها و آیکون‌ها از تنظیمات خوانده می‌شود.
 */
class WSL_Render {

	private static $faq_schema_done = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 11 );
		add_action( 'wp_footer', array( __CLASS__, 'dock' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'schema_org' ), 8 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	public static function tags() {
		return array(
			'legal_hero_photo'     => 'hero_photo',
			'legal_search_hero'    => 'search_hero',
			'legal_domains'        => 'domains',
			'legal_sticky_cards'   => 'sticky_cards',
			'legal_problem_solver' => 'problem_solver',
			'legal_process'        => 'process',
			'legal_case_studies'   => 'case_studies',
			'legal_certificates'   => 'certificates',
			'legal_faq'            => 'faq',
			'legal_stats'          => 'stats',
			'legal_testimonials'   => 'testimonials',
			'legal_team'           => 'team',
			'legal_cta'            => 'cta',
			'legal_about_page'     => 'about_page',
			'legal_contact_page'   => 'contact_page',
			'legal_booking_form'   => 'booking_form',
			'legal_articles'       => 'articles',
			'legal_blog_archive'   => 'blog_archive',
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
		$o = WSL_Options::sec( $module );
		if ( isset( $o['enabled'] ) && ! $o['enabled'] ) {
			return '';
		}
		$atts = is_array( $atts ) ? $atts : array();
		$fn   = 'mod_' . $module;
		return self::$fn( $o, $atts );
	}

	/* =====================================================================
	 * ابزارهای مشترک
	 * ===================================================================== */
	public static function dir() {
		$d = WSL_Options::get( 'appearance', 'direction', 'auto' );
		if ( 'rtl' === $d || 'ltr' === $d ) {
			return $d;
		}
		return is_rtl() ? 'rtl' : 'ltr';
	}

	private static function root( $class, $attrs = '' ) {
		return '<div class="wsl wsl-' . esc_attr( $class ) . '" dir="' . esc_attr( self::dir() ) . '"' . $attrs . '>';
	}

	private static function arrow() {
		return WSL_Icons::render( 'rtl' === self::dir() ? 'arrow-left' : 'arrow-right', 'wsl-arrow' );
	}

	private static function eyebrow( $text ) {
		if ( '' === trim( (string) $text ) ) {
			return '';
		}
		return '<div class="wsl-eyebrow"><span class="wsl-eyebrow__line"></span>' . esc_html( $text ) . '<span class="wsl-eyebrow__line"></span></div>';
	}

	/** هدر استاندارد بخش. */
	private static function head( $o, $tag = 'h2', $eyebrow_key = 'badge', $class = '' ) {
		$h  = '<header class="wsl-head ' . esc_attr( $class ) . '">';
		$h .= self::eyebrow( isset( $o[ $eyebrow_key ] ) ? $o[ $eyebrow_key ] : '' );
		if ( ! empty( $o['title'] ) ) {
			$h .= '<' . $tag . ' class="wsl-title">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		}
		if ( ! empty( $o['subtitle'] ) ) {
			$h .= '<p class="wsl-sub">' . esc_html( $o['subtitle'] ) . '</p>';
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
			$t = preg_replace( '/' . preg_quote( $h, '/' ) . '/u', '<span class="wsl-hl">' . $h . '</span>', $t, 1 );
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
		$d = WSL_Options::get( 'general', 'logo_dark', '' );
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
		$name  = WSL_Options::firm( 'firm_name' ) ? WSL_Options::firm( 'firm_name' ) : get_bloginfo( 'name' );
		if ( ! $light && ! $dark ) {
			return '<span class="wsl wsl-inline wsl-logo-text">' . esc_html( $name ) . '</span>';
		}
		$h    = max( 16, min( 300, (int) $a['height'] ) );
		$img  = '<img class="wsl-logo__light" src="' . esc_url( $light ? $light : $dark ) . '" alt="' . esc_attr( $name ) . '" style="height:' . $h . 'px;width:auto" decoding="async">';
		if ( $dark ) {
			$img .= '<img class="wsl-logo__dark" src="' . esc_url( $dark ) . '" alt="" aria-hidden="true" style="height:' . $h . 'px;width:auto" decoding="async">';
		}
		$cls  = 'wsl wsl-inline wsl-logo' . ( $dark ? ' wsl-logo--has-dark' : '' ) . ( $a['class'] ? ' ' . sanitize_html_class( $a['class'] ) : '' );
		$html = '<span class="' . esc_attr( $cls ) . '">' . $img . '</span>';
		return $a['link'] ? '<a class="wsl-logo-link" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $name ) . '">' . $html . '</a>' : $html;
	}

	public static function logo_url() {
		$logo = WSL_Options::get( 'general', 'logo', '' );
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
			array( WSL_Options::firm( 'firm_name' ), WSL_Options::firm( 'firm_title' ) ),
			(string) $template
		);
		$lines = array_filter( array_map( 'trim', explode( '|', $text ) ) );
		$logo  = self::logo_url();
		$h     = '<div class="wsl-ph">';
		if ( $logo ) {
			$h .= '<img class="wsl-ph__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" loading="lazy">';
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
		$tel  = WSL_Options::tel_href();
		$b2t  = $o['btn2_text'];
		$b2u  = $o['btn2_url'];
		if ( '' === $b2u && $tel ) {
			$b2u = $tel;
			$b2t = $b2t ? $b2t : WSL_Options::firm( 'phone' );
		}
		$h  = self::root( 'hero' ) . '<div class="wsl-hero__grid wsl-glass"><div class="wsl-hero__content">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsl-pill"><span class="wsl-dot"></span>' . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsl-hero__title">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsl-hero__desc">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '<div class="wsl-hero__btns">';
		if ( $o['btn1_text'] ) {
			$h .= '<a class="wsl-btn wsl-btn--primary" href="' . esc_url( $o['btn1_url'] ) . '">' . esc_html( $o['btn1_text'] ) . self::arrow() . '</a>';
		}
		if ( $b2t && $b2u ) {
			$h .= '<a class="wsl-btn wsl-btn--outline" href="' . esc_url( $b2u ) . '" dir="auto">' . WSL_Icons::render( 'phone' ) . esc_html( $b2t ) . '</a>';
		}
		$h .= '</div></div><div class="wsl-hero__media"><div class="wsl-hero__img">';
		$img = self::image( $o['image'], 'large', '', $o['image_alt'] ? $o['image_alt'] : WSL_Options::firm( 'firm_name' ) );
		if ( $img ) {
			$h .= $img;
		} else {
			$initial = mb_substr( WSL_Options::firm( 'firm_name' ), 0, 1 );
			$h .= '<div class="wsl-hero__ph" aria-hidden="true">' . esc_html( $initial ) . '</div>';
		}
		$h .= '</div>';
		if ( $o['show_float'] && ( $o['float_title'] || $o['float_sub'] ) ) {
			$h .= '<div class="wsl-hero__float wsl-glass"><span class="wsl-iconbox wsl-iconbox--accent">' . WSL_Icons::render( $o['float_icon'] ) . '</span><div><strong>' . esc_html( $o['float_title'] ) . '</strong><small>' . esc_html( $o['float_sub'] ) . '</small></div></div>';
		}
		return $h . '</div></div></div>';
	}

	private static function mod_search_hero( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h2' );
		$h   = self::root( 'sh' ) . '<section class="wsl-sh">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsl-pill wsl-pill--glass"><span class="wsl-dot wsl-dot--ping"></span>' . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsl-sh__title">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsl-sh__sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '<form class="wsl-sh__form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><div class="wsl-sh__field">' . WSL_Icons::render( 'search' ) . '<input type="search" name="s" value="' . esc_attr( get_search_query() ) . '" placeholder="' . esc_attr( $o['placeholder'] ) . '" aria-label="' . esc_attr( $o['placeholder'] ) . '" autocomplete="off" required></div><button type="submit" class="wsl-btn wsl-btn--primary">' . esc_html( $o['button'] ) . '</button></form>';
		if ( ! empty( $o['tags'] ) ) {
			$h .= '<div class="wsl-sh__tags">';
			if ( $o['tags_label'] ) {
				$h .= '<span class="wsl-sh__taglabel">' . esc_html( $o['tags_label'] ) . '</span>';
			}
			foreach ( $o['tags'] as $t ) {
				$h .= '<a class="wsl-tag" href="' . self::target( $t ) . '">' . esc_html( $t['label'] ) . '</a>';
			}
			$h .= '</div>';
		}
		return $h . '</section></div>';
	}

	private static function mod_domains( $o, $atts ) {
		$cols = in_array( (string) $o['cols'], array( '3', '4', '5' ), true ) ? $o['cols'] : '4';
		$h    = self::root( 'domains' );
		$h   .= '<div class="wsl-domains__head"><h2 class="wsl-domains__title">' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['view_all_url'] ) {
			$h .= '<a class="wsl-chip" href="' . esc_url( $o['view_all_url'] ) . '">' . esc_html( $o['view_all_text'] ) . self::arrow() . '</a>';
		}
		$h .= '</div><div class="wsl-grid wsl-cols-' . esc_attr( $cols ) . '">';
		foreach ( $o['items'] as $it ) {
			$h .= '<a class="wsl-dcard wsl-glass" href="' . self::target( $it ) . '"><span class="wsl-iconbox">' . WSL_Icons::render( $it['icon'] ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3>';
			if ( $it['desc'] ) {
				$h .= '<p>' . esc_html( $it['desc'] ) . '</p>';
			}
			$h .= '</a>';
		}
		return $h . '</div></div>';
	}

	private static function mod_sticky_cards( $o, $atts ) {
		$top = (int) $o['sticky_top'];
		$h   = self::root( 'sticky', ' style="--wsl-sticky-top:' . $top . 'px"' );
		$h  .= self::head( $o, 'h2' );
		$i   = 0;
		foreach ( $o['cards'] as $c ) {
			$i++;
			$style = in_array( $c['style'], array( 'glass', 'solid', 'gradient' ), true ) ? $c['style'] : 'glass';
			$dark  = ( 'solid' === $style ) ? ' wsl-iconbox--dark' : '';
			$h    .= '<div class="wsl-sticky__item" style="z-index:' . ( 10 + $i ) . '"><div class="wsl-sticky__card wsl-sticky__card--' . esc_attr( $style ) . '"><div class="wsl-sticky__side"><span class="wsl-iconbox wsl-iconbox--lg' . $dark . '">' . WSL_Icons::render( $c['icon'] ) . '</span>';
			if ( $c['en_title'] ) {
				$h .= '<h3 class="wsl-sticky__en">' . esc_html( $c['en_title'] ) . '</h3>';
			}
			if ( $c['fa_title'] ) {
				$h .= '<h4 class="wsl-sticky__fa">' . esc_html( $c['fa_title'] ) . '</h4>';
			}
			$h .= '</div><div class="wsl-sticky__body"><p>' . wp_kses_post( $c['desc'] ) . '</p></div></div></div>';
		}
		return $h . '</div>';
	}

	private static function mod_problem_solver( $o, $atts ) {
		if ( empty( $o['tabs'] ) ) {
			return '';
		}
		$uid = wp_unique_id( 'wsl-ps-' );
		$h   = self::root( 'ps', ' data-wsl="tabs"' ) . '<div class="wsl-ps__panel wsl-glass"><header class="wsl-head"><h3 class="wsl-title wsl-title--sm">' . esc_html( $o['title'] ) . '</h3>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsl-sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><div class="wsl-tabs" role="tablist">';
		foreach ( $o['tabs'] as $i => $tab ) {
			$h .= '<button type="button" role="tab" class="wsl-tab' . ( 0 === $i ? ' is-active' : '' ) . '" id="' . esc_attr( $uid . '-t' . $i ) . '" aria-controls="' . esc_attr( $uid . '-p' . $i ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" tabindex="' . ( 0 === $i ? '0' : '-1' ) . '">' . esc_html( $tab['label'] ) . '</button>';
		}
		$h .= '</div>';
		foreach ( $o['tabs'] as $i => $tab ) {
			$h .= '<div class="wsl-tabpanel' . ( 0 === $i ? ' is-active' : '' ) . '" role="tabpanel" id="' . esc_attr( $uid . '-p' . $i ) . '" aria-labelledby="' . esc_attr( $uid . '-t' . $i ) . '"' . ( 0 === $i ? '' : ' hidden' ) . '><div class="wsl-grid wsl-cols-3 wsl-grid--tight">';
			foreach ( (array) $tab['items'] as $it ) {
				$h .= '<a class="wsl-pcard" href="' . self::target( $it ) . '"><span class="wsl-iconbox">' . WSL_Icons::render( $it['icon'] ) . '</span><h4>' . esc_html( $it['title'] ) . '</h4></a>';
			}
			$h .= '</div></div>';
		}
		$h .= '<footer class="wsl-ps__foot"><p>' . WSL_Icons::render( 'info', 'wsl-accent' ) . esc_html( $o['footer_text'] ) . '</p>';
		if ( $o['footer_link_url'] ) {
			$h .= '<a class="wsl-link" href="' . esc_url( $o['footer_link_url'] ) . '">' . esc_html( $o['footer_link_text'] ) . self::arrow() . '</a>';
		}
		return $h . '</footer></div></div>';
	}

	private static function mod_process( $o, $atts ) {
		$h = self::root( 'process' ) . self::head( $o, 'h2' ) . '<div class="wsl-grid wsl-cols-3">';
		foreach ( $o['steps'] as $i => $st ) {
			$h .= '<div class="wsl-step wsl-glass"><span class="wsl-step__num">' . esc_html( trim( $o['step_label'] . ' ' . self::num( sprintf( '%02d', $i + 1 ) ) ) ) . '</span><span class="wsl-iconbox wsl-iconbox--lg">' . WSL_Icons::render( $st['icon'] ) . '</span><h3>' . esc_html( $st['title'] ) . '</h3><p>' . esc_html( $st['desc'] ) . '</p></div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_case_studies( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$rtl  = 'rtl' === self::dir();
		$auto = $o['autoplay'] ? ' data-autoplay="' . (int) $o['interval'] . '"' : '';
		$h    = self::root( 'cases', ' data-wsl="slider"' . $auto ) . '<div class="wsl-cases__box"><div class="wsl-cases__info">';
		if ( $o['badge'] ) {
			$h .= '<span class="wsl-cases__badge">' . esc_html( $o['badge'] ) . '</span>';
		}
		$h .= '<h3 class="wsl-cases__title">' . esc_html( $o['title'] ) . '</h3>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsl-cases__desc">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '<div class="wsl-cases__ctrl"><button type="button" class="wsl-round" data-wsl-prev aria-label="' . esc_attr( 'قبلی' ) . '">' . WSL_Icons::render( $rtl ? 'arrow-right' : 'arrow-left' ) . '</button><button type="button" class="wsl-round" data-wsl-next aria-label="' . esc_attr( 'بعدی' ) . '">' . WSL_Icons::render( $rtl ? 'arrow-left' : 'arrow-right' ) . '</button></div></div>';
		$h .= '<div class="wsl-cases__slides" aria-live="polite">';
		foreach ( $o['items'] as $i => $it ) {
			$h .= '<article class="wsl-case' . ( 0 === $i ? ' is-active' : '' ) . '"' . ( 0 === $i ? '' : ' aria-hidden="true"' ) . '><div class="wsl-case__head"><span class="wsl-iconbox wsl-iconbox--lg">' . WSL_Icons::render( $it['icon'] ) . '</span><div><h4>' . esc_html( $it['title'] ) . '</h4>';
			if ( $it['domain'] ) {
				$h .= '<p>' . esc_html( trim( $o['domain_label'] . ' ' . $it['domain'] ) ) . '</p>';
			}
			$h .= '</div></div><div class="wsl-case__body">' . wp_kses_post( $it['body'] ) . '</div></article>';
		}
		return $h . '</div></div></div>';
	}

	private static function mod_certificates( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$speed = (int) $o['speed'];
		$h     = self::root( 'certs', ' style="--wsl-marquee:' . $speed . 's"' ) . self::head( $o, 'h2' ) . '<div class="wsl-marquee-wrap"><div class="wsl-marquee">';
		foreach ( array( false, true ) as $dup ) {
			foreach ( $o['items'] as $it ) {
				$h .= '<div class="wsl-cert wsl-glass"' . ( $dup ? ' aria-hidden="true"' : '' ) . '><span class="wsl-cert__icon">' . WSL_Icons::render( $it['icon'] ) . '</span>';
				if ( $it['badge'] ) {
					$h .= '<span class="wsl-badge">' . esc_html( $it['badge'] ) . '</span>';
				}
				$h .= '<h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['desc'] ) . '</p></div>';
			}
		}
		return $h . '</div></div></div>';
	}

	private static function mod_faq( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$uid = wp_unique_id( 'wsl-faq-' );
		$h   = self::root( 'faq', ' data-wsl="faq" data-single="' . ( $o['single_open'] ? '1' : '0' ) . '"' ) . '<section aria-labelledby="' . esc_attr( $uid ) . '-title"><header class="wsl-head"><h2 class="wsl-title" id="' . esc_attr( $uid ) . '-title">' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsl-sub">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><div class="wsl-faq__list">';
		$ld = array();
		foreach ( $o['items'] as $i => $it ) {
			$open = ( 0 === $i && $o['first_open'] );
			$h   .= '<div class="wsl-faq__item' . ( $open ? ' is-open' : '' ) . '"><h3 class="wsl-faq__h"><button type="button" class="wsl-faq__q" id="' . esc_attr( $uid . '-q' . $i ) . '" aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $uid . '-a' . $i ) . '"><span>' . esc_html( $it['q'] ) . '</span><span class="wsl-faq__icon" aria-hidden="true">' . WSL_Icons::render( 'plus' ) . '</span></button></h3><div class="wsl-faq__a" id="' . esc_attr( $uid . '-a' . $i ) . '" role="region" aria-labelledby="' . esc_attr( $uid . '-q' . $i ) . '"><div class="wsl-faq__ai"><div class="wsl-faq__text">' . wpautop( wp_kses_post( $it['a'] ) ) . '</div></div></div></div>';
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
		$h  = self::root( 'stats', ' data-wsl="stats" data-animate="' . ( $o['animate'] ? '1' : '0' ) . '" data-fa="' . $fa . '"' );
		if ( $o['title'] ) {
			$h .= '<h2 class="wsl-title wsl-title--sm wsl-center">' . esc_html( $o['title'] ) . '</h2>';
		}
		$h .= '<div class="wsl-stats__row wsl-glass">';
		foreach ( $o['items'] as $it ) {
			$h .= '<div class="wsl-stat"><span class="wsl-iconbox">' . WSL_Icons::render( $it['icon'] ) . '</span><div class="wsl-stat__val"><span class="wsl-stat__num" data-count="' . (int) $it['value'] . '" data-suffix="' . esc_attr( $it['suffix'] ) . '">' . esc_html( self::num( (int) $it['value'] ) . $it['suffix'] ) . '</span></div><div class="wsl-stat__label">' . esc_html( $it['label'] ) . '</div></div>';
		}
		return $h . '</div></div>';
	}

	private static function mod_testimonials( $o, $atts ) {
		if ( empty( $o['items'] ) ) {
			return '';
		}
		$h = self::root( 'tm' ) . self::head( $o, 'h2' ) . '<div class="wsl-grid wsl-cols-3">';
		foreach ( $o['items'] as $it ) {
			$h .= '<figure class="wsl-tm__card wsl-glass">';
			$r  = (int) $it['rating'];
			if ( $r > 0 ) {
				$h .= '<div class="wsl-stars" role="img" aria-label="' . esc_attr( $r . '/5' ) . '">';
				for ( $i = 1; $i <= 5; $i++ ) {
					$h .= '<span class="' . ( $i <= $r ? 'is-on' : '' ) . '">' . WSL_Icons::render( 'star' ) . '</span>';
				}
				$h .= '</div>';
			}
			$h .= '<blockquote>' . esc_html( $it['text'] ) . '</blockquote><figcaption>';
			$av = self::image( $it['avatar'], 'thumbnail', 'wsl-tm__av', $it['name'] );
			$h .= $av ? $av : '<span class="wsl-tm__av wsl-tm__av--ph">' . esc_html( mb_substr( $it['name'], 0, 1 ) ) . '</span>';
			$h .= '<span><strong>' . esc_html( $it['name'] ) . '</strong><small>' . esc_html( $it['role'] ) . '</small></span></figcaption></figure>';
		}
		return $h . '</div></div>';
	}

	private static function mod_team( $o, $atts ) {
		if ( empty( $o['members'] ) ) {
			return '';
		}
		$h = self::root( 'team' ) . self::head( $o, 'h2' ) . '<div class="wsl-grid wsl-cols-3">';
		foreach ( $o['members'] as $m ) {
			$open  = $m['url'] ? '<a class="wsl-team__card wsl-glass" href="' . esc_url( $m['url'] ) . '">' : '<div class="wsl-team__card wsl-glass">';
			$close = $m['url'] ? '</a>' : '</div>';
			$img   = self::image( $m['photo'], 'medium_large', '', $m['name'] );
			$h    .= $open . '<div class="wsl-team__photo">' . ( $img ? $img : '<span class="wsl-team__ph">' . WSL_Icons::render( 'user' ) . '</span>' ) . '</div><h3>' . esc_html( $m['name'] ) . '</h3>';
			if ( $m['role'] ) {
				$h .= '<span class="wsl-team__role">' . esc_html( $m['role'] ) . '</span>';
			}
			if ( $m['bio'] ) {
				$h .= '<p>' . esc_html( $m['bio'] ) . '</p>';
			}
			$h .= $close;
		}
		return $h . '</div></div>';
	}

	private static function mod_cta( $o, $atts ) {
		$style = in_array( $o['style'], array( 'gradient', 'dark', 'light' ), true ) ? $o['style'] : 'gradient';
		$h     = self::root( 'cta' ) . '<div class="wsl-cta__box wsl-cta__box--' . esc_attr( $style ) . '"><div class="wsl-cta__txt"><h2>' . esc_html( $o['title'] ) . '</h2>';
		if ( $o['text'] ) {
			$h .= '<p>' . esc_html( $o['text'] ) . '</p>';
		}
		$h .= '</div><div class="wsl-cta__btns">';
		if ( $o['btn1_text'] ) {
			$h .= '<a class="wsl-btn wsl-btn--accent" href="' . esc_url( $o['btn1_url'] ) . '">' . esc_html( $o['btn1_text'] ) . self::arrow() . '</a>';
		}
		$tel = WSL_Options::tel_href();
		if ( $o['show_phone'] && $tel ) {
			$h .= '<a class="wsl-btn wsl-btn--ghost" href="' . esc_url( $tel ) . '" dir="auto">' . WSL_Icons::render( 'phone' ) . esc_html( WSL_Options::firm( 'phone' ) ) . '</a>';
		}
		return $h . '</div></div></div>';
	}

	private static function mod_about_page( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h1' );
		$h   = self::root( 'about' ) . '<header class="wsl-head wsl-head--hero">' . self::eyebrow( $o['eyebrow'] ) . '<' . $tag . ' class="wsl-title wsl-title--xl">' . self::hl( $o['title'], $o['highlight'] ) . '</' . $tag . '>';
		if ( $o['desc'] ) {
			$h .= '<p class="wsl-sub wsl-sub--lg">' . esc_html( $o['desc'] ) . '</p>';
		}
		$h .= '</header><div class="wsl-grid wsl-cols-3">';
		foreach ( $o['features'] as $f ) {
			$h .= '<div class="wsl-card wsl-glass"><span class="wsl-iconbox wsl-iconbox--lg">' . WSL_Icons::render( $f['icon'] ) . '</span><h3>' . esc_html( $f['title'] ) . '</h3><p>' . esc_html( $f['desc'] ) . '</p></div>';
		}
		$h .= '</div><div class="wsl-grid wsl-cols-2 wsl-mt">';
		$h .= '<div class="wsl-card wsl-glass"><div class="wsl-card__head"><span class="wsl-iconbox">' . WSL_Icons::render( $o['mission_icon'] ) . '</span><h3>' . esc_html( $o['mission_title'] ) . '</h3></div><p>' . esc_html( $o['mission_text'] ) . '</p></div>';
		$h .= '<div class="wsl-card wsl-card--dark"><div class="wsl-card__head"><span class="wsl-iconbox wsl-iconbox--accent">' . WSL_Icons::render( $o['vision_icon'] ) . '</span><h3>' . esc_html( $o['vision_title'] ) . '</h3></div><p>' . esc_html( $o['vision_text'] ) . '</p></div>';
		return $h . '</div></div>';
	}

	private static function mod_contact_page( $o, $atts ) {
		$tag = self::tag( $o['heading_tag'], 'h1' );
		$g   = WSL_Options::sec( 'general' );
		$h   = self::root( 'contact' ) . '<header class="wsl-head wsl-head--hero">' . self::eyebrow( $o['eyebrow'] ) . '<' . $tag . ' class="wsl-title wsl-title--xl">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsl-sub wsl-sub--lg">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><div class="wsl-grid wsl-cols-3 wsl-info">';
		if ( $g['address'] ) {
			$h .= '<div class="wsl-icard wsl-glass"><span class="wsl-iconbox">' . WSL_Icons::render( 'pin' ) . '</span><h3>' . esc_html( $o['address_label'] ) . '</h3><p>' . esc_html( $g['address'] ) . '</p>' . ( ( $o['show_hours'] && $g['hours'] ) ? '<small>' . esc_html( $g['hours'] ) . '</small>' : '' ) . '</div>';
		}
		$tel = WSL_Options::tel_href();
		if ( $tel ) {
			$h .= '<div class="wsl-icard wsl-glass"><span class="wsl-iconbox">' . WSL_Icons::render( 'phone' ) . '</span><h3>' . esc_html( $o['phone_label'] ) . '</h3><a class="wsl-icard__val" href="' . esc_url( $tel ) . '" dir="ltr">' . esc_html( $g['phone'] ) . '</a><small>' . esc_html( $o['phone_note'] ) . '</small></div>';
		}
		if ( $g['email'] && is_email( $g['email'] ) ) {
			$h .= '<div class="wsl-icard wsl-glass"><span class="wsl-iconbox">' . WSL_Icons::render( 'mail' ) . '</span><h3>' . esc_html( $o['email_label'] ) . '</h3><a class="wsl-icard__val" href="mailto:' . esc_attr( antispambot( $g['email'] ) ) . '" dir="ltr">' . esc_html( antispambot( $g['email'] ) ) . '</a><small>' . esc_html( $o['email_note'] ) . '</small></div>';
		}
		$h .= '</div><div class="wsl-grid wsl-cols-2 wsl-mt"><div class="wsl-box wsl-glass"><h3 class="wsl-box__title">' . esc_html( $o['form_title'] ) . '</h3><p class="wsl-box__sub">' . esc_html( $o['form_sub'] ) . '</p>';
		$h .= WSL_Leads::render_form(
			array(
				'source'  => 'contact_page',
				'fields'  => array( 'name', 'phone', 'email' => $o['show_email_field'], 'subject' => $o['show_subject'], 'message' => true ),
				'button'  => $o['form_button'],
				'success' => $o['form_success'],
			)
		);
		$h .= '</div><div class="wsl-box wsl-glass wsl-box--side"><div><h3 class="wsl-box__title">' . esc_html( $o['side_title'] ) . '</h3><div class="wsl-box__text">' . wpautop( wp_kses_post( $o['side_text'] ) ) . '</div>';
		$social = self::social_links();
		if ( $o['quick_title'] || ( $o['show_social'] && $social ) ) {
			$h .= '<div class="wsl-quick"><div class="wsl-quick__t">' . WSL_Icons::render( 'headset' ) . esc_html( $o['quick_title'] ) . '</div><p>' . esc_html( $o['quick_text'] ) . '</p>';
			if ( $o['show_social'] && $social ) {
				$h .= '<div class="wsl-quick__links">';
				foreach ( $social as $s ) {
					$h .= '<a class="wsl-chip" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener noreferrer">' . WSL_Icons::render( $s['icon'] ) . esc_html( $s['label'] ) . '</a>';
				}
				$h .= '</div>';
			}
			$h .= '</div>';
		}
		$h .= '</div>';
		if ( $o['show_map'] && $g['map_embed'] && 0 === strpos( $g['map_embed'], 'https://' ) ) {
			$h .= '<div class="wsl-map"><iframe src="' . esc_url( $g['map_embed'] ) . '" title="' . esc_attr( $o['address_label'] ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
		}
		return $h . '</div></div></div>';
	}

	public static function social_links() {
		$g   = WSL_Options::sec( 'general' );
		$out = array();
		$wa  = preg_replace( '/[^0-9]/', '', WSL_Options::normalize_digits( $g['whatsapp'] ) );
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
		$h      = self::root( 'booking', $anchor ? ' id="' . esc_attr( $anchor ) . '"' : '' ) . '<div class="wsl-booking">' . self::head( $o, 'h2' ) . '<div class="wsl-box wsl-glass">';
		$h     .= WSL_Leads::render_form(
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
		$h    = '<article class="wsl-post wsl-glass"><div class="wsl-post__img"><a href="' . esc_url( get_permalink() ) . '" tabindex="-1" aria-hidden="true">';
		if ( has_post_thumbnail() ) {
			$h .= get_the_post_thumbnail( null, 'large', array( 'loading' => 'lazy', 'alt' => the_title_attribute( array( 'echo' => false ) ) ) );
		} else {
			$h .= self::placeholder_card( $o['placeholder_text'] );
		}
		$h .= '</a>';
		if ( $cat ) {
			$h .= '<span class="wsl-post__cat">' . esc_html( $cat ) . '</span>';
		}
		$h .= '</div><div class="wsl-post__body"><h3 class="wsl-post__title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3><p class="wsl-post__desc">' . esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 30 ) ) . '</p><div class="wsl-post__foot">';
		if ( ! isset( $o['show_date'] ) || $o['show_date'] ) {
			$h .= '<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
		}
		$h .= '<a class="wsl-link" href="' . esc_url( get_permalink() ) . '">' . esc_html( $o['readmore'] ) . self::arrow() . '</a></div></div></article>';
		return $h;
	}

	private static function mod_articles( $o, $atts ) {
		$atts  = shortcode_atts( array( 'count' => $o['count'], 'category' => $o['default_category'], 'columns' => $o['columns'] ), $atts, 'legal_articles' );
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
		$h  = self::root( 'articles' ) . self::head( $o, 'h2' ) . '<div class="wsl-grid wsl-cols-' . esc_attr( $cols ) . '">';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$h .= self::post_card( $o, 'a' );
			}
			wp_reset_postdata();
		} else {
			$h .= '<p class="wsl-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		return $h . '</div></div>';
	}

	private static function archive_base_url() {
		return remove_query_arg( array( 'legal_page', 'legal_cat' ), add_query_arg( array() ) );
	}

	private static function mod_blog_archive( $o, $atts ) {
		$atts   = shortcode_atts( array( 'per_page' => $o['per_page'] ), $atts, 'legal_blog_archive' );
		$tag    = self::tag( $o['heading_tag'], 'h2' );
		$paged  = isset( $_GET['legal_page'] ) ? max( 1, absint( wp_unslash( $_GET['legal_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$cat    = isset( $_GET['legal_cat'] ) ? sanitize_title( wp_unslash( $_GET['legal_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
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
		$h    = self::root( 'archive' ) . '<header class="wsl-head">';
		if ( $o['badge'] ) {
			$h .= '<div class="wsl-pill wsl-pill--glass">' . WSL_Icons::render( 'book' ) . esc_html( $o['badge'] ) . '</div>';
		}
		$h .= '<' . $tag . ' class="wsl-title wsl-title--xl">' . esc_html( $o['title'] ) . '</' . $tag . '>';
		if ( $o['subtitle'] ) {
			$h .= '<p class="wsl-sub wsl-sub--lg">' . esc_html( $o['subtitle'] ) . '</p>';
		}
		$h .= '</header><nav class="wsl-filter" aria-label="' . esc_attr( $o['badge'] ) . '"><a class="wsl-fbtn' . ( $cat ? '' : ' is-active' ) . '" href="' . esc_url( $base ) . '">' . esc_html( $o['all_label'] ) . '</a>';
		foreach ( get_categories( array( 'hide_empty' => true ) ) as $c ) {
			$h .= '<a class="wsl-fbtn' . ( $cat === $c->slug ? ' is-active' : '' ) . '" href="' . esc_url( add_query_arg( 'legal_cat', $c->slug, $base ) ) . '">' . esc_html( $c->name ) . '</a>';
		}
		$h .= '</nav><div class="wsl-grid wsl-cols-3">';
		if ( $q->have_posts() ) {
			$o['show_date'] = 1;
			while ( $q->have_posts() ) {
				$q->the_post();
				$h .= self::post_card( $o, 'b' );
			}
			wp_reset_postdata();
		} else {
			$h .= '<p class="wsl-empty">' . esc_html( $o['empty_text'] ) . '</p>';
		}
		$h .= '</div>';
		if ( $q->max_num_pages > 1 ) {
			$with_cat = $cat ? add_query_arg( 'legal_cat', $cat, $base ) : $base;
			$pg_base  = $with_cat . ( false === strpos( $with_cat, '?' ) ? '?' : '&' ) . 'legal_page=%#%';
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
			$h .= '<nav class="wsl-pagination" aria-label="Pagination">' . wp_kses_post( $links ) . '</nav>';
		}
		return $h . '</div>';
	}

	/* =====================================================================
	 * تغییر حالت روشن/تاریک و داک شناور
	 * ===================================================================== */
	private static function toggle_button( $extra = '' ) {
		return '<button type="button" class="wsl-toggle ' . esc_attr( $extra ) . '" data-wsl-theme-toggle aria-pressed="false" aria-label="' . esc_attr( 'تغییر حالت روشن/تاریک' ) . '"><span class="wsl-toggle__sun">' . WSL_Icons::render( 'sun' ) . '</span><span class="wsl-toggle__moon">' . WSL_Icons::render( 'moon' ) . '</span></button>';
	}

	public static function sc_toggle() {
		if ( ! WSL_Assets::dark_enabled() ) {
			return '';
		}
		return '<span class="wsl wsl-inline" dir="' . esc_attr( self::dir() ) . '">' . self::toggle_button( 'wsl-toggle--inline' ) . '</span>';
	}

	public static function dock() {
		$items = array( 'bottom-start' => '', 'bottom-end' => '' );

		if ( WSL_Assets::dark_enabled() ) {
			$pos = WSL_Options::get( 'darkmode', 'toggle_position', 'bottom-end' );
			if ( isset( $items[ $pos ] ) ) {
				$items[ $pos ] .= self::toggle_button( 'wsl-toggle--float' );
			}
		}

		$f = WSL_Options::sec( 'floating' );
		if ( $f['enabled'] ) {
			$btns = '';
			$tel  = WSL_Options::tel_href();
			if ( $f['show_phone'] && $tel ) {
				$btns .= '<a class="wsl-fab wsl-fab--call" href="' . esc_url( $tel ) . '" aria-label="' . esc_attr( 'تماس تلفنی' ) . '">' . WSL_Icons::render( 'phone' ) . '</a>';
			}
			foreach ( self::social_links() as $s ) {
				if ( ( 'whatsapp' === $s['icon'] && $f['show_whatsapp'] ) || ( 'send' === $s['icon'] && $f['show_telegram'] ) ) {
					$btns .= '<a class="wsl-fab wsl-fab--' . esc_attr( $s['icon'] ) . '" href="' . esc_url( $s['url'] ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $s['label'] ) . '">' . WSL_Icons::render( $s['icon'] ) . '</a>';
				}
			}
			if ( $f['cta_label'] ) {
				$btns .= '<a class="wsl-fab wsl-fab--cta" href="' . esc_url( $f['cta_url'] ) . '">' . esc_html( $f['cta_label'] ) . '</a>';
			}
			$fpos = isset( $items[ $f['position'] ] ) ? $f['position'] : 'bottom-start';
			if ( $btns ) {
				$items[ $fpos ] .= '<div class="wsl-fabs' . ( $f['mobile_only'] ? ' wsl-mobile-only' : '' ) . '">' . $btns . '</div>';
			}
		}

		foreach ( $items as $pos => $html ) {
			if ( $html ) {
				echo '<div class="wsl wsl-dock wsl-dock--' . esc_attr( $pos ) . '" dir="' . esc_attr( self::dir() ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- قطعات بالا escape شده‌اند.
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
		$s = WSL_Options::sec( 'seo' );
		if ( empty( $s['schema_org'] ) || ! is_front_page() ) {
			return;
		}
		$g    = WSL_Options::sec( 'general' );
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
		if ( WSL_Options::tel_href() ) {
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
		if ( is_singular() && ( isset( $_GET['legal_cat'] ) || ( isset( $_GET['legal_page'] ) && absint( wp_unslash( $_GET['legal_page'] ) ) > 1 ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}
}
