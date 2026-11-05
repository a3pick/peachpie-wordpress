<?php
defined( 'ABSPATH' ) || exit;

/**
 * بارگذاری CSS/JS و متغیرهای رنگ. اسلایدرها بدون وابستگی به Swiper (CSS scroll-snap + JS سبک) کار می‌کنند.
 */
class WSK_Assets {

	private static $registered = false;
	private static $inline_done = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_all' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function body_class( $classes ) {
		$classes[] = 'wsk-site';
		return $classes;
	}

	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		$ver              = WSK_VERSION;

		wp_register_style( 'wsk-frontend', WSK_URL . 'assets/css/wsk-frontend.css', array(), $ver );
		wp_register_script( 'wsk-frontend', WSK_URL . 'assets/js/wsk-frontend.js', array(), $ver, true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'wsk-frontend', 'strategy', 'defer' );
		}
		wp_localize_script(
			'wsk-frontend',
			'WSK',
			array(
				'track' => WSK_Options::get( 'system', 'track_stats', 1 ) ? esc_url_raw( rest_url( 'wsk/v1/hit' ) ) : '',
				'i18n'  => array(
					'close' => 'بستن',
					'prev'  => 'قبلی',
					'next'  => 'بعدی',
				),
			)
		);

		wp_register_style( 'wsk-woodmart', WSK_URL . 'assets/css/wsk-woodmart.css', array(), $ver );
		wp_register_script( 'wsk-woodmart', WSK_URL . 'assets/js/wsk-woodmart.js', array(), $ver, true );
		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( 'wsk-woodmart', 'strategy', 'defer' );
		}
	}

	/** وقتی «بارگذاری در همه‌ی صفحه‌ها» روشن است. */
	public static function maybe_enqueue_all() {
		self::theme_assets();
		if ( WSK_Options::get( 'system', 'assets_everywhere', 1 ) ) {
			self::enqueue();
		}
		// شمارش بازدید محصول: روی صفحه‌ی تک‌محصول اسکریپت باید باشد.
		if ( function_exists( 'is_product' ) && is_product() && WSK_Options::get( 'system', 'track_stats', 1 ) ) {
			self::enqueue();
		}
	}

	/** از داخل شورت‌کدها صدا زده می‌شود (حالت بارگذاری شرطی). */
	public static function enqueue() {
		self::register();
		wp_enqueue_style( 'wsk-frontend' );
		wp_enqueue_script( 'wsk-frontend' );
		if ( ! self::$inline_done ) {
			self::$inline_done = true;
			wp_add_inline_style( 'wsk-frontend', self::inline_css() );
		}
	}

	private static function theme_assets() {
		$t = 'theme';
		$on = WSK_Options::get( $t, 'woodmart_css', 1 )
			|| WSK_Options::get( $t, 'hide_timer_days', 1 )
			|| WSK_Options::get( $t, 'category_align', 1 )
			|| WSK_Options::get( $t, 'hide_stories_nav', 1 )
			|| WSK_Options::get( $t, 'logo_shimmer', 0 )
			|| WSK_Options::get( $t, 'hover_tabs', 1 );
		if ( $on ) {
			wp_enqueue_style( 'wsk-woodmart' );
			wp_add_inline_style( 'wsk-woodmart', self::theme_css() );
		}
		$js = array();
		if ( WSK_Options::get( $t, 'hover_tabs', 1 ) ) {
			$js[] = 'hoverTabs';
		}
		if ( WSK_Options::get( $t, 'convert_titles', 1 ) ) {
			$js[] = 'convertTitles';
		}
		if ( $js ) {
			wp_enqueue_script( 'wsk-woodmart' );
			wp_localize_script( 'wsk-woodmart', 'WSKTheme', array( 'features' => $js ) );
		}
	}

	/** بلوک‌های CSS قابل‌خاموش‌شدن (جایگزین اسنیپت‌های قدیمی). */
	private static function theme_css() {
		$t   = 'theme';
		$css = '';
		// فایل wsk-woodmart.css فقط شامل بخش بزرگ استایل است؛ بخش‌های کوچک اینجا شرطی تزریق می‌شوند.
		if ( WSK_Options::get( $t, 'hide_timer_days', 1 ) ) {
			$css .= '.wd-timer-days,.wd-timer-text{display:none!important}.wd-timer>span{background:#fff}.wd-timer-days+.wd-sep{display:none!important}';
		}
		if ( WSK_Options::get( $t, 'category_align', 1 ) ) {
			$css .= '.category-align .wd-grid-g{display:flex;flex-wrap:wrap;gap:0;justify-content:center}.category-align .wd-cat{text-align:center;min-width:20%;max-width:20%}@media (max-width:599px){.category-align .wd-cat{min-width:33%;max-width:33%}}';
		}
		if ( WSK_Options::get( $t, 'hide_stories_nav', 1 ) ) {
			$css .= '.webisoft-stories-nav-btn{display:none!important}';
		}
		if ( WSK_Options::get( $t, 'logo_shimmer', 0 ) ) {
			$css .= '.site-logo,.logo-webisoft{display:inline-block;-webkit-mask:linear-gradient(-60deg,#000 30%,#0005,#000 50%) right/300% 100%;mask:linear-gradient(-60deg,#000 30%,#0005,#000 50%) right/300% 100%;background-repeat:no-repeat;animation:wsk-shimmer 3s infinite}.site-logo:hover,.logo-webisoft:hover{transform:scale(1.1);transition:.3s}@keyframes wsk-shimmer{0%{-webkit-mask-position:140% 140%;mask-position:140% 140%}100%{-webkit-mask-position:-20% -20%;mask-position:-20% -20%}}@media (prefers-reduced-motion:reduce){.site-logo,.logo-webisoft{animation:none}}';
		}
		if ( WSK_Options::get( $t, 'hover_tabs', 1 ) ) {
			$css .= '.hoverTabs .e-n-tabs-content .e-con.e-active{animation:wsk-fadeTabs .8s ease forwards}@keyframes wsk-fadeTabs{0%{opacity:0}100%{opacity:1}}@media (prefers-reduced-motion:reduce){.hoverTabs .e-n-tabs-content .e-con.e-active{animation:none}}';
		}
		return $css;
	}

	private static function font_stack() {
		if ( 'custom_name' !== WSK_Options::get( 'appearance', 'font_mode', 'inherit' ) ) {
			return 'inherit';
		}
		$name = preg_replace( '/[^\p{L}\p{N} _\-]/u', '', (string) WSK_Options::get( 'appearance', 'font_family', 'Vazirmatn' ) );
		$name = $name ? $name : 'Vazirmatn';
		return "'" . $name . "', Tahoma, sans-serif";
	}

	private static function inline_css() {
		$a    = 'appearance';
		$vars = array(
			'primary' => 'primary', 'accent' => 'accent', 'ink' => 'ink', 'text' => 'text', 'muted' => 'muted',
			'price' => 'price', 'surface' => 'surface', 'border' => 'border', 'danger' => 'danger',
		);
		$css  = ':root{--wsk-font:' . self::font_stack() . ';--wsk-radius:' . (int) WSK_Options::get( $a, 'radius', 12 ) . 'px;--wsk-max:' . (int) WSK_Options::get( $a, 'container_max', 1300 ) . 'px;';
		foreach ( $vars as $var => $id ) {
			$val = WSK_Options::color( $a, $id );
			if ( $val ) {
				$css .= '--wsk-' . $var . ':' . $val . ';';
			}
		}
		$css .= '}';

		$ratio = (string) WSK_Options::get( 'banners', 'ratio', '21/6' );
		$ratio = in_array( $ratio, array( '21/6', '16/5', '16/9', 'auto' ), true ) ? $ratio : '21/6';
		$maxh  = (int) WSK_Options::get( 'banners', 'max_height', 320 );
		$css  .= '@media (min-width:1025px){.wsk-banner{' . ( 'auto' === $ratio ? '' : 'aspect-ratio:' . $ratio . ';' ) . ( $maxh > 0 ? 'max-height:' . $maxh . 'px;' : '' ) . '}}'; // تبلت/موبایل نسبت‌های خودشان را دارند.

		$custom = (string) WSK_Options::get( 'system', 'custom_css', '' );
		if ( $custom ) {
			$css .= $custom;
		}
		return $css;
	}
}
