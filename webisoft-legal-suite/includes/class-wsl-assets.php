<?php
defined( 'ABSPATH' ) || exit;

/**
 * بارگذاری CSS/JS، متغیرهای رنگ، فونت و حالت تاریک.
 */
class WSL_Assets {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'early_theme_script' ), 1 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function dark_enabled() {
		return (bool) WSL_Options::get( 'darkmode', 'enabled', 1 );
	}

	/** اسکریپت درون‌خطی بسیار کوچک برای جلوگیری از فلش سفید. */
	public static function early_theme_script() {
		if ( ! self::dark_enabled() ) {
			return;
		}
		$mode = WSL_Options::get( 'darkmode', 'default_mode', 'light' );
		$mode = in_array( $mode, array( 'light', 'dark', 'auto' ), true ) ? $mode : 'light';
		echo '<script data-cfasync="false" data-no-optimize="1" data-no-defer="1" data-no-minify="1" data-pagespeed-no-defer nowprocket>(function(){var d=document.documentElement,t="' . esc_js( $mode ) . '";try{var s=localStorage.getItem("wsl-theme");if(s==="light"||s==="dark"){t=s;}else if(t==="auto"){t=(window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches)?"dark":"light";}}catch(e){if(t==="auto"){t="light";}}d.setAttribute("data-wsl-theme",t);})();</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function body_class( $classes ) {
		$classes[] = 'wsl-site';
		if ( self::dark_enabled() && WSL_Options::get( 'darkmode', 'global', 1 ) ) {
			$classes[] = 'wsl-global';
		}
		return $classes;
	}

	public static function enqueue() {
		$ver = WSL_VERSION;

		// فونت
		$mode = WSL_Options::get( 'appearance', 'font_mode', 'vazirmatn_cdn' );
		if ( 'vazirmatn_cdn' === $mode ) {
			wp_enqueue_style( 'wsl-font-vazirmatn', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.0.0/Vazirmatn-font-face.css', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		}
		if ( WSL_Options::get( 'utilities', 'load_fa', 0 ) ) {
			wp_enqueue_style( 'wsl-fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters
		}

		wp_enqueue_style( 'wsl-frontend', WSL_URL . 'assets/css/wsl-frontend.css', array(), $ver );
		wp_add_inline_style( 'wsl-frontend', self::inline_css() );

		if ( self::dark_enabled() && WSL_Options::get( 'darkmode', 'global', 1 ) ) {
			wp_enqueue_style( 'wsl-dark', WSL_URL . 'assets/css/wsl-dark.css', array( 'wsl-frontend' ), $ver );
		}
		if ( WSL_Options::get( 'utilities', 'util_classes', 1 ) ) {
			wp_enqueue_style( 'wsl-utilities', WSL_URL . 'assets/css/wsl-utilities.css', array( 'wsl-frontend' ), $ver );
		}

		wp_enqueue_script( 'wsl-frontend', WSL_URL . 'assets/js/wsl-frontend.js', array(), $ver, true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'wsl-frontend', 'strategy', 'defer' );
		}
		wp_localize_script(
			'wsl-frontend',
			'WSL',
			array(
				'lead'  => esc_url_raw( rest_url( 'wsl/v1/lead' ) ),
				'like'  => esc_url_raw( rest_url( 'wsl/v1/like' ) ),
				'dark'  => self::dark_enabled(),
				'logo'  => ( self::dark_enabled() && WSL_Options::get( 'general', 'logo_swap', 1 ) && WSL_Render::logo_dark_url() ) ? array( 'dark' => WSL_Render::logo_dark_url() ) : false,
				'adapt' => self::dark_enabled() && (bool) WSL_Options::get( 'darkmode', 'auto_adapt', 1 ) && (bool) WSL_Options::get( 'darkmode', 'global', 1 ),
				'toc'   => array(
					'enabled' => (bool) WSL_Options::get( 'article', 'toc_expand', 1 ),
					'open'    => WSL_Options::get( 'article', 'toc_open', 'مشاهده تمام فهرست' ),
					'close'   => WSL_Options::get( 'article', 'toc_close', 'بستن فهرست' ),
				),
				'i18n'  => array(
					'sending'     => 'در حال ارسال...',
					'copied'      => WSL_Options::get( 'article', 'copied_label', 'لینک کپی شد!' ),
					'liked'       => WSL_Options::get( 'article', 'liked_label', 'ثبت شد' ),
					'toDark'      => 'تغییر به حالت تاریک',
					'toLight'     => 'تغییر به حالت روشن',
					'errGeneric'  => WSL_Options::get( 'forms', 'error_generic', '' ),
				),
			)
		);
	}

	/* ---------- CSS پویا ---------- */
	private static function tokens() {
		return array(
			'primary' => 'primary', 'btn-a' => 'btn_a', 'btn-b' => 'btn_b', 'on-btn' => 'on_btn', 'accent' => 'accent',
			'accent-text' => 'accent_text', 'ink' => 'ink', 'text' => 'text', 'muted' => 'muted',
			'surface' => 'surface', 'surface-alt' => 'surface_alt', 'border' => 'border',
		);
	}

	private static function var_block( $sid ) {
		$css = '';
		foreach ( self::tokens() as $var => $id ) {
			$val = WSL_Options::color( $sid, $id );
			if ( $val ) {
				$css .= '--wsl-' . $var . ':' . $val . ';';
			}
		}
		$bg = WSL_Options::color( $sid, 'page_bg' );
		if ( $bg ) {
			$css .= '--wsl-page-bg:' . $bg . ';';
		}
		return $css;
	}

	private static function font_stack() {
		$mode = WSL_Options::get( 'appearance', 'font_mode', 'vazirmatn_cdn' );
		if ( 'inherit' === $mode ) {
			return 'inherit';
		}
		$name = preg_replace( '/[^\p{L}\p{N} _\-]/u', '', (string) WSL_Options::get( 'appearance', 'font_family', 'Vazirmatn' ) );
		$name = $name ? $name : 'Vazirmatn';
		return "'" . $name . "', Tahoma, 'Segoe UI', sans-serif";
	}

	private static function font_face() {
		if ( 'custom_file' !== WSL_Options::get( 'appearance', 'font_mode', '' ) ) {
			return '';
		}
		$url = esc_url_raw( (string) WSL_Options::get( 'appearance', 'font_file', '' ) );
		if ( ! $url ) {
			return '';
		}
		$ext     = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$formats = array( 'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype' );
		if ( ! isset( $formats[ $ext ] ) ) {
			return '';
		}
		$name = preg_replace( '/[^\p{L}\p{N} _\-]/u', '', (string) WSL_Options::get( 'appearance', 'font_family', 'Vazirmatn' ) );
		return "@font-face{font-family:'" . $name . "';src:url('" . esc_url( $url ) . "') format('" . $formats[ $ext ] . "');font-weight:100 900;font-style:normal;font-display:swap;}";
	}

	private static function inline_css() {
		$a   = 'appearance';
		$css = self::font_face();

		$radius = (int) WSL_Options::get( $a, 'radius', 24 );
		$max    = (int) WSL_Options::get( $a, 'container_max', 1200 );
		$hw     = (int) WSL_Options::get( $a, 'heading_weight', 900 );
		$latin  = preg_replace( '/[^A-Za-z0-9 ,\'\-_]/', '', (string) WSL_Options::get( $a, 'font_latin', 'Inter, system-ui, sans-serif' ) );

		$css .= ':root{--wsl-font:' . self::font_stack() . ';--wsl-font-latin:' . $latin . ';--wsl-radius:' . $radius . 'px;--wsl-max:' . $max . 'px;--wsl-hw:' . $hw . ';--wsl-danger:#b91c1c;--wsl-success:#15803d;--wsl-toc-h:' . (int) WSL_Options::get( 'article', 'toc_height', 330 ) . 'px;' . self::var_block( 'appearance' ) . '}';

		if ( self::dark_enabled() ) {
			$css .= 'html[data-wsl-theme="dark"]{color-scheme:dark;--wsl-danger:#f87171;--wsl-success:#4ade80;' . self::var_block( 'darkmode' ) . '}';
			$light_bg = WSL_Options::color( 'appearance', 'page_bg' );
			if ( $light_bg ) {
				$css .= 'body.wsl-global{background-color:var(--wsl-page-bg);}';
			}

			if ( WSL_Options::get( 'darkmode', 'force_text', 1 ) ) {
				$css .= 'html[data-wsl-theme="dark"] :is(.elementor-heading-title,.elementor-widget-heading .elementor-heading-title,.elementor-widget-text-editor,.elementor-widget-text-editor p,.elementor-icon-list-text,.elementor-icon-list-item>a,.elementor-post__title a,.elementor-widget-theme-post-title .elementor-heading-title):not(.wsl *):not(.elementor-button){color:var(--wsl-ink)!important}html[data-wsl-theme="dark"] :is(.elementor-widget-text-editor,.elementor-widget-text-editor p,.elementor-icon-list-text,.elementor-post__excerpt):not(.wsl *){color:var(--wsl-text)!important}html[data-wsl-theme="dark"] :is(.elementor-post,.elementor-post__card,.elementor-post__text,.elementor-post__badge-wrap):not(.wsl *){background-color:var(--wsl-surface)!important;border-color:var(--wsl-border)!important}html[data-wsl-theme="dark"] :is(.elementor-post__meta-data,.elementor-post-date,.elementor-post-avatar,.elementor-post-author,.elementor-post-time):not(.wsl *){color:var(--wsl-muted)!important}html[data-wsl-theme="dark"] .elementor-post__read-more:not(.wsl *){color:var(--wsl-primary)!important}html[data-wsl-theme="dark"] .elementor-post__title,html[data-wsl-theme="dark"] .elementor-post__title a{color:var(--wsl-ink)!important}html[data-wsl-theme="dark"] .elementor-widget-table-of-contents :is(.elementor-toc__wrapper,.elementor-toc__body,.elementor-toc__header,.elementor-toc__list-wrapper){background-color:transparent!important;background-image:none!important}html[data-wsl-theme="dark"] .elementor-toc__header-title,html[data-wsl-theme="dark"] .elementor-toc__header-title *{color:var(--wsl-ink)!important}html[data-wsl-theme="dark"] .elementor-toc__list-item-text{color:var(--wsl-primary)!important}html[data-wsl-theme="dark"] .elementor-toc__list-item-text.elementor-item-active{color:var(--wsl-accent)!important}html[data-wsl-theme="dark"] .elementor-toc__list-item::marker,html[data-wsl-theme="dark"] .elementor-toc__list-item:before{color:var(--wsl-muted)!important}html[data-wsl-theme="dark"] .ws-toc-expand-button{background:var(--wsl-surface-alt)!important;color:var(--wsl-primary)!important;border-color:var(--wsl-border)!important}html[data-wsl-theme="dark"] .elementor-social-icon:not(.wsl *){background-color:var(--wsl-surface-alt)!important;border:1px solid var(--wsl-border)}html[data-wsl-theme="dark"] .elementor-social-icon:not(.wsl *) :is(svg,i){fill:var(--wsl-ink)!important;color:var(--wsl-ink)!important}html[data-wsl-theme="dark"] .elementor-social-icon:not(.wsl *):hover{background-color:var(--wsl-btn-a)!important}html[data-wsl-theme="dark"] .elementor-social-icon:not(.wsl *):hover :is(svg,i){fill:var(--wsl-on-btn)!important;color:var(--wsl-on-btn)!important}html[data-wsl-theme="dark"] .elementor-icon-list-icon:not(.wsl *) :is(svg,i){fill:var(--wsl-primary)!important;color:var(--wsl-primary)!important}';
			}

			$sel = array( 'surface_selectors' => 'surface', 'surface_alt_selectors' => 'surface-alt' );
			foreach ( $sel as $id => $tok ) {
				$list = preg_split( '/\r\n|\r|\n/', (string) WSL_Options::get( 'darkmode', $id, '' ) );
				$list = array_filter( array_map( 'trim', $list ) );
				if ( $list ) {
					$css .= 'html[data-wsl-theme="dark"] :is(' . implode( ',', $list ) . '){background-color:var(--wsl-' . $tok . ')!important;color:var(--wsl-text);border-color:var(--wsl-border);}';
				}
			}
		}

		// ربط رنگ‌های سراسری المنتور
		$map = array( 'el_primary' => 'primary', 'el_secondary' => 'secondary', 'el_text' => 'text', 'el_accent' => 'accent' );
		$el  = '';
		foreach ( $map as $opt => $el_name ) {
			$tok = WSL_Options::get( 'darkmode', $opt, '' );
			if ( $tok && isset( self::tokens()[ $tok ] ) ) {
				$el .= '--e-global-color-' . $el_name . ':var(--wsl-' . $tok . ');';
			}
		}
		if ( $el ) {
			$css .= 'html body[class*="elementor-kit-"]{' . $el . '}';
		}

		if ( WSL_Options::get( 'utilities', 'logo_shimmer', 0 ) ) {
			$css .= '.elementor-widget-theme-site-logo{display:inline-block;-webkit-mask:linear-gradient(-60deg,#000 30%,#0005,#000 50%) right/300% 100%;mask:linear-gradient(-60deg,#000 30%,#0005,#000 50%) right/300% 100%;background-repeat:no-repeat;animation:wsl-shimmer 3s infinite;transition:transform .3s}.elementor-widget-theme-site-logo:hover{transform:scale(1.08)}@keyframes wsl-shimmer{0%{-webkit-mask-position:140% 140%;mask-position:140% 140%}100%{-webkit-mask-position:-20% -20%;mask-position:-20% -20%}}@media (prefers-reduced-motion:reduce){.elementor-widget-theme-site-logo{animation:none}}';
		}

		if ( WSL_Options::get( 'article', 'post_style', 1 ) ) {
			$css .= self::post_style_css();
		}

		$custom = (string) WSL_Options::get( 'utilities', 'custom_css', '' );
		if ( $custom ) {
			$css .= $custom;
		}
		return $css;
	}

	/** استایل محتوای مقاله (نسخه تمیز SP CSS). */
	private static function post_style_css() {
		$scope = (string) WSL_Options::get( 'article', 'post_scope', '.elementor-widget-theme-post-content' );
		$parts = array_filter( array_map( 'trim', explode( ',', $scope ) ) );
		if ( ! $parts ) {
			return '';
		}
		$s = function ( $suffix = '' ) use ( $parts ) {
			$out = array();
			foreach ( $parts as $p ) {
				$out[] = $p . $suffix;
			}
			return implode( ',', $out );
		};
		$m = 'var(--wsl-primary)';
		$r  = '';
		$r .= $s() . '{color:var(--wsl-text);font-size:17px;line-height:2.2;font-weight:400}';
		$r .= $s( ' p' ) . '{margin-bottom:2rem;text-align:justify;color:var(--wsl-text)}';
		$r .= $s( ' h2' ) . ',' . $s( ' h3' ) . ',' . $s( ' h4' ) . '{color:var(--wsl-ink);font-weight:var(--wsl-hw);margin:3.5rem 0 1.5rem;line-height:1.6}';
		$r .= $s( ' h2:first-child' ) . '{margin-top:0}';
		$r .= $s( ' h2' ) . '{font-size:26px;padding-bottom:12px;position:relative}';
		$r .= $s( ' h2::after' ) . '{content:"";position:absolute;bottom:0;inset-inline-start:0;width:100%;height:2px;background:var(--wsl-border)}';
		$r .= $s( ' h2::before' ) . '{content:"";position:absolute;bottom:0;inset-inline-start:0;width:60px;height:4px;background:' . $m . ';z-index:1;border-radius:4px}';
		$r .= $s( ' h3' ) . '{font-size:22px}';
		$r .= $s( ' ul' ) . ',' . $s( ' ol' ) . '{margin:2rem 0 3rem;padding:2.5rem;padding-inline-end:1.5rem;background:var(--wsl-surface-alt);border-radius:20px;border:1px solid var(--wsl-border);box-shadow:0 10px 30px -5px rgba(15,23,42,.05);list-style:none}';
		$r .= $s( ' li' ) . '{margin-bottom:1.5rem;color:var(--wsl-text);line-height:2.2;font-size:16px;position:relative}';
		$r .= $s( ' li:last-child' ) . '{margin-bottom:0}';
		$r .= $s( ' ol' ) . '{counter-reset:wsl-c}' . $s( ' ol li' ) . '{counter-increment:wsl-c}';
		$r .= $s( ' ol li::before' ) . '{content:counter(wsl-c);color:' . $m . ';font-weight:900;font-size:1rem;margin-inline-end:10px;display:inline-block;width:28px;height:28px;line-height:26px;text-align:center;background:color-mix(in srgb,var(--wsl-primary) 12%,transparent);border-radius:50%;border:1px solid ' . $m . '}';
		$r .= $s( ' ul li::before' ) . '{content:"\2025";color:' . $m . ';font-weight:900;font-size:1.5em;line-height:1.2;margin-inline-end:10px}';
		$r .= $s( ' strong' ) . ',' . $s( ' b' ) . '{color:var(--wsl-ink);font-weight:900}';
		$r .= $s( ' a' ) . '{color:' . $m . ';text-decoration:none;border-bottom:1px dashed ' . $m . ';transition:all .3s ease;font-weight:700}';
		$r .= $s( ' a:hover' ) . '{color:var(--wsl-danger);border-bottom-style:solid;background:color-mix(in srgb,var(--wsl-danger) 8%,transparent)}';
		$r .= $s( ' img' ) . '{border-radius:24px;box-shadow:0 20px 40px -10px rgba(15,23,42,.18);margin:3rem auto;display:block;max-width:100%;height:auto}';
		$r .= $s( ' blockquote' ) . '{background:color-mix(in srgb,var(--wsl-danger) 8%,var(--wsl-surface));border:1px solid color-mix(in srgb,var(--wsl-danger) 25%,transparent);border-inline-start:5px solid ' . $m . ';padding:2rem;margin:3.5rem 0;border-radius:20px}';
		$r .= $s( ' blockquote p' ) . '{margin-bottom:0;color:var(--wsl-danger);font-weight:600;font-size:16px;line-height:2}';
		return $r;
	}
}
