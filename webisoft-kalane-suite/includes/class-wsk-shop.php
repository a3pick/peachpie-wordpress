<?php
defined( 'ABSPATH' ) || exit;

/**
 * بخش ووکامرس: نوار مرتب‌سازی دیجی‌کالایی و ورود اجباری قبل از تسویه‌حساب.
 */
class WSK_Shop {

	const COOKIE = 'wsk_checkout_redirect';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'late_init' ), 30 );
	}

	public static function late_init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$o = WSK_Options::sec( 'shop' );

		/* ---- مرتب‌سازی ---- */
		add_filter( 'woocommerce_default_catalog_orderby_options', array( __CLASS__, 'sort_options' ) );
		add_filter( 'woocommerce_catalog_orderby', array( __CLASS__, 'sort_options' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( __CLASS__, 'ordering_args' ), 10, 3 );
		add_action( 'woocommerce_product_query', array( __CLASS__, 'views_query' ) );
		if ( ! empty( $o['default_sort'] ) ) {
			add_filter( 'woocommerce_default_catalog_orderby', array( __CLASS__, 'default_sort' ) );
		}
		if ( ! empty( $o['sortbar'] ) ) {
			add_action( 'woocommerce_before_shop_loop', array( __CLASS__, 'sortbar' ), 5 );
			if ( ! empty( $o['hide_default_ordering'] ) ) {
				remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
				add_filter( 'woocommerce_catalog_ordering', '__return_false' );
			}
		}

		/* ---- ورود اجباری ---- */
		if ( ! empty( $o['force_login'] ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'force_login' ) );
			add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'after_login' ), 20 );
			add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'after_login' ), 20 );
		}
	}

	/* =====================================================================
	 * مرتب‌سازی
	 * ===================================================================== */
	/** گزینه‌های فعال به ترتیب تنظیم‌شده. */
	public static function items() {
		$keys = WSK_Schema::sort_keys();
		$out  = array();
		foreach ( (array) WSK_Options::get( 'shop', 'sort_items', array() ) as $r ) {
			if ( isset( $r['key'], $keys[ $r['key'] ] ) ) {
				$out[ $r['key'] ] = $r['label'] ? $r['label'] : $keys[ $r['key'] ];
			}
		}
		return $out ? $out : array( 'relevance' => $keys['relevance'], 'latest' => $keys['latest'] );
	}

	public static function sort_options( $options ) {
		return self::items();
	}

	public static function default_sort( $v ) {
		$d = (string) WSK_Options::get( 'shop', 'default_sort', '' );
		return isset( self::items()[ $d ] ) ? $d : $v;
	}

	/** نگاشت کلیدهای ما به مرتب‌سازی‌های بومی ووکامرس (lookup table، سازگار با فیلتر قیمت). */
	public static function ordering_args( $args, $orderby = '', $order = '' ) {
		static $busy = false;
		$map = array(
			'latest'       => 'date',
			'best_selling' => 'popularity',
			'price_low'    => 'price',
			'price_high'   => 'price-desc',
			'rating'       => 'rating',
		);
		if ( $busy || ! isset( $map[ $orderby ] ) ) {
			return $args;
		}
		$busy = true;
		$res  = WC()->query->get_catalog_ordering_args( $map[ $orderby ] );
		$busy = false;
		return $res;
	}

	/** مرتب‌سازی «پربازدیدترین» روی کوئری اصلی فروشگاه (بدون حذف محصولات فاقد متا). */
	public static function views_query( $q ) {
		if ( ! $q->is_main_query() ) {
			return;
		}
		$ob = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'most_viewed' !== $ob || ! isset( self::items()['most_viewed'] ) ) {
			return;
		}
		$args = WSK_DB::views_query_args( (array) $q->get( 'meta_query' ) );
		$q->set( 'meta_query', $args['meta_query'] );
		$q->set( 'orderby', $args['orderby'] );
		$q->set( 'meta_key', '' );
		$q->set( 'order', '' );
	}

	public static function sortbar() {
		global $wp_query;
		WSK_Assets::enqueue(); // حالت بارگذاری شرطی هم استایل نوار را داشته باشد.
		$items   = self::items();
		$default = (string) apply_filters( 'woocommerce_default_catalog_orderby', get_option( 'woocommerce_default_catalog_orderby', 'menu_order' ) );
		$current = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ( isset( $items[ $default ] ) ? $default : 'relevance' ); // phpcs:ignore WordPress.Security.NonceVerification
		$total   = (int) $wp_query->found_posts;
		$num     = function ( $n ) {
			$s = number_format( $n );
			return WSK_Options::get( 'general', 'persian_digits', 1 ) ? WSK_Options::to_fa_digits( $s ) : $s;
		};

		echo '<div class="wsk-sort" dir="' . ( is_rtl() ? 'rtl' : 'ltr' ) . '"><div class="wsk-sort__flex"><div class="wsk-sort__label"><svg class="wsk-sort__icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 18h6v-2H3v2zm0-5h12v-2H3v2zm0-7v2h18V6H3z"/></svg><span class="wsk-sort__title">مرتب‌سازی:</span></div><div class="wsk-sort__list">';
		foreach ( $items as $key => $label ) {
			$url = esc_url( add_query_arg( 'orderby', $key, remove_query_arg( 'paged' ) ) );
			echo '<a href="' . $url . '" class="wsk-sort__item' . ( $key === $current ? ' is-active' : '' ) . '" rel="nofollow"' . ( $key === $current ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</div></div><div class="wsk-sort__count">' . esc_html( $num( $total ) ) . ' کالا</div></div>';
	}

	/* =====================================================================
	 * ورود اجباری قبل از تسویه‌حساب
	 *
	 * نسخه‌ی قبلی (Snippet) آدرس بازگشت را از کوکی کاربر می‌خواند و به آن redirect می‌کرد؛
	 * این یک Open Redirect بود. اینجا کوکی فقط یک پرچم است و مقصد همیشه آدرس تسویه‌حساب خود سایت است.
	 * ===================================================================== */
	private static function set_flag() {
		setcookie(
			self::COOKIE,
			'1',
			array(
				'expires'  => time() + HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	private static function clear_flag() {
		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		unset( $_COOKIE[ self::COOKIE ] );
	}

	public static function force_login() {
		if ( is_user_logged_in() ) {
			if ( function_exists( 'is_account_page' ) && is_account_page() && ! empty( $_COOKIE[ self::COOKIE ] ) ) {
				self::clear_flag();
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
			return;
		}
		if ( is_checkout() && ! is_wc_endpoint_url() && WC()->cart && ! WC()->cart->is_empty() ) {
			self::set_flag();
			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;
		}
	}

	public static function after_login( $redirect ) {
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			self::clear_flag();
			return wc_get_checkout_url();
		}
		return $redirect;
	}
}
