<?php
defined( 'ABSPATH' ) || exit;

/**
 * پل اتصال به افزونه Webisoft Woo (پنل کاربری + عضویت ویژه).
 * هیچ وابستگی سخت‌ای وجود ندارد؛ اگر افزونه Woo نباشد، همه‌چیز مثل قبل کار می‌کند.
 *
 * - قفل محتوا: فیلتر wsf_user_has_access با وضعیت «عضویت ویژه» (متای _wsw_member) تصمیم می‌گیرد.
 *     سطح 'member'  ← هر پلن فعال    |   سطح 'plan:2' ← فقط پلن شماره ۲
 * - لینک خرید/ورود: به تب «عضویت ویژه» پنل کاربری، یا صفحه ورود اگر کاربر وارد نشده باشد.
 * - پلن‌ها: [fin_plans] می‌تواند پلن‌ها را مستقیم از تنظیمات Woo بخواند.
 */
class WSF_Woo {

	const META = '_wsw_member';

	public static function init() {
		add_filter( 'wsf_user_has_access', array( __CLASS__, 'access' ), 5, 3 );
	}

	/** آیا افزونه Woo و ماژول عضویت آن فعال است؟ */
	public static function available() {
		return defined( 'WSW_VERSION' ) && has_action( 'admin_post_wsw_member_buy' );
	}

	/** @return array<int,array{name:string,price:float,days:int,off:float}> */
	public static function plans() {
		$out = array();
		if ( ! self::available() || ! class_exists( '\Webisoft\Woo\Core\Settings' ) ) {
			return $out;
		}
		for ( $i = 1; $i <= 3; $i++ ) {
			$name = trim( (string) \Webisoft\Woo\Core\Settings::get( "membership.p{$i}_name", '' ) );
			$days = (int) \Webisoft\Woo\Core\Settings::get( "membership.p{$i}_days", 0 );
			if ( '' === $name || $days < 1 ) {
				continue;
			}
			$out[ $i ] = array(
				'name'  => $name,
				'price' => max( 0, (float) \Webisoft\Woo\Core\Settings::get( "membership.p{$i}_price", 0 ) ),
				'days'  => $days,
				'off'   => min( 90, max( 0, (float) \Webisoft\Woo\Core\Settings::get( "membership.p{$i}_off", 0 ) ) ),
			);
		}
		return $out;
	}

	/** @return array{plan:int,until:int}|null */
	public static function member( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id < 1 || ! self::available() ) {
			return null;
		}
		$m = get_user_meta( $user_id, self::META, true );
		if ( ! is_array( $m ) || (int) ( isset( $m['until'] ) ? $m['until'] : 0 ) <= time() ) {
			return null;
		}
		$plan = (int) ( isset( $m['plan'] ) ? $m['plan'] : 0 );
		return isset( self::plans()[ $plan ] ) ? array( 'plan' => $plan, 'until' => (int) $m['until'] ) : null;
	}

	/** فیلتر دسترسی. */
	public static function access( $has, $level, $user_id ) {
		if ( $has || ! self::available() ) {
			return $has;
		}
		$m = self::member( $user_id );
		if ( ! $m ) {
			return false;
		}
		if ( 'member' === $level ) {
			return true;
		}
		if ( preg_match( '/^plan:(\d)$/', (string) $level, $x ) ) {
			return (int) $x[1] === $m['plan'];
		}
		return false;
	}

	/** آدرس تب عضویت (یا صفحه ورود برای مهمان). */
	public static function cta_url() {
		if ( ! self::available() || ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}
		if ( ! is_user_logged_in() ) {
			return wc_get_page_permalink( 'myaccount' );
		}
		if ( class_exists( '\Webisoft\Woo\Modules\Account\Tabs' ) ) {
			return \Webisoft\Woo\Modules\Account\Tabs::url( 'membership' );
		}
		return add_query_arg( 'tab', 'membership', wc_get_page_permalink( 'myaccount' ) );
	}

	/** آدرس صفحه اشتراک: مقدار دستی تنظیمات، وگرنه تب عضویت Woo. */
	public static function plans_url( $configured ) {
		return $configured ? $configured : self::cta_url();
	}

	/** تاریخ شمسی انقضا (اگر کتابخانه Jalali افزونه Woo موجود باشد). */
	public static function date( $ts ) {
		if ( class_exists( '\Webisoft\Woo\Core\Jalali' ) ) {
			return \Webisoft\Woo\Core\Jalali::format( (int) $ts, 'Y/m/d' );
		}
		return wp_date( get_option( 'date_format' ), (int) $ts );
	}
}
