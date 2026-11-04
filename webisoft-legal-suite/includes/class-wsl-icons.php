<?php
defined( 'ABSPATH' ) || exit;

/**
 * کتابخانه آیکون SVG داخلی (بدون وابستگی به Font Awesome).
 * مقدار آیکون می‌تواند کلید داخلی (مثل scale) یا کلاس Font Awesome باشد.
 */
class WSL_Icons {

	/** @return array<string,string> کلید => محتوای داخلی SVG (viewBox 24) */
	public static function set() {
		return array(
			'scale'      => '<path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7l-3 7a3.5 3.5 0 0 0 6 0L5 7zM19 7l-3 7a3.5 3.5 0 0 0 6 0l-3-7z"/>',
			'shield'     => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
			'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'users'      => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16.5 14.2c2.6.3 4.5 2.5 4.5 5.3"/>',
			'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>',
			'building'   => '<path d="M5 21V4h9v17M14 9h5v12M3 21h18M8 8h3M8 12h3M8 16h3"/>',
			'money'      => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 10h.01M18 14h.01"/>',
			'file'       => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
			'key'        => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
			'home'       => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10M10 20v-6h4v6"/>',
			'gavel'      => '<path d="M14 4l6 6-3 3-6-6 3-3zM11 7L4 14l3 3 7-7M3 21h9"/>',
			'lock'       => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'phone'      => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
			'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
			'pin'        => '<path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
			'award'      => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
			'search'     => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
			'route'      => '<circle cx="6" cy="19" r="2.5"/><circle cx="18" cy="5" r="2.5"/><path d="M8.5 19H16a3.5 3.5 0 0 0 0-7H8a3.5 3.5 0 0 1 0-7h7.5"/>',
			'chat'       => '<path d="M21 12a8 8 0 0 1-11.7 7L4 20l1.2-4.4A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4"/>',
			'clipboard'  => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM9 12h6M9 16h4"/>',
			'target'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
			'bulb'       => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z"/>',
			'headset'    => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20c0 1.5-2 2-5 2"/>',
			'briefcase'  => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
			'car'        => '<path d="M5 16v-5l2-5h10l2 5v5H5z"/><path d="M5 11h14M7 16v3M17 16v3"/><circle cx="8" cy="14" r=".6"/><circle cx="16" cy="14" r=".6"/>',
			'laptop'     => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 20h20"/>',
			'coins'      => '<ellipse cx="12" cy="6" rx="7" ry="3"/><path d="M5 6v6c0 1.7 3.1 3 7 3s7-1.3 7-3V6M5 12v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
			'wallet'     => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M16 13.5h2"/>',
			'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5h.01"/>',
			'check'      => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
			'star'       => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9L12 3z"/>',
			'quote'      => '<path d="M7 7h4v5c0 3-1.5 4.5-4 5M15 7h4v5c0 3-1.5 4.5-4 5"/>',
			'book'       => '<path d="M4 5a2 2 0 0 1 2-2h13v17H6a2 2 0 0 0-2 2V5z"/><path d="M8 7h7"/>',
			'calendar'   => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'send'       => '<path d="M21 3L3 10.5l6.5 2.5L12 20l2.5-5.5L21 3z"/><path d="M9.5 13L21 3"/>',
			'whatsapp'   => '<path d="M21 12a9 9 0 0 1-13.4 7.8L3 21l1.3-4.4A9 9 0 1 1 21 12z"/><path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-2-2.5-1-1 1a5 5 0 0 1-2.5-2.5l1-1-1-2.5-2 .5z"/>',
			'instagram'  => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
			'alert'      => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v5M12 18h.01"/>',
			'ban'        => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
			'hand'       => '<path d="M8 12V5a1.5 1.5 0 0 1 3 0v6M11 10V3.5a1.5 1.5 0 0 1 3 0V10M14 10V5a1.5 1.5 0 0 1 3 0v8c0 4-2.5 7-6.5 7S5 17 5 14v-2a1.5 1.5 0 0 1 3 0"/>',
			'store'      => '<path d="M4 9l1.5-5h13L20 9M4 9v11h16V9M4 9a3 3 0 0 0 5.3 1.9A3 3 0 0 0 14.7 10.9 3 3 0 0 0 20 9"/>',
			'chart'      => '<path d="M4 20V4M4 20h16M8 16v-5M13 16V8M18 16v-8"/>',
			'trophy'     => '<path d="M7 4h10v5a5 5 0 0 1-10 0V4zM7 6H4v1a3 3 0 0 0 3 3M17 6h3v1a3 3 0 0 1-3 3M12 14v4M8 21h8M10 18h4"/>',
			'globe'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
			'moon'       => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5z"/>',
			'share'      => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
			'thumbs-up'  => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.3a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3H14zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
			'sparkles'   => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8L19 16z"/>',
			'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
			'arrow-right'=> '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'plus'       => '<path d="M12 5v14M5 12h14"/>',
			'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
		);
	}

	/** نگاشت نام‌های رایج Font Awesome به آیکون داخلی. */
	private static function fa_map() {
		return array(
			'users' => 'users', 'users-slash' => 'users', 'building' => 'building', 'file-signature' => 'file', 'file-contract' => 'file',
			'money-check-dollar' => 'money', 'money-check' => 'money', 'handcuffs' => 'lock', 'car-burst' => 'car', 'laptop-code' => 'laptop',
			'briefcase' => 'briefcase', 'scale-balanced' => 'scale', 'shield-halved' => 'shield', 'clock-rotate-left' => 'clock',
			'bullseye' => 'target', 'lightbulb' => 'bulb', 'location-dot' => 'pin', 'phone' => 'phone', 'envelope' => 'mail',
			'headset' => 'headset', 'award' => 'award', 'clipboard-question' => 'clipboard', 'magnifying-glass-chart' => 'search',
			'magnifying-glass' => 'search', 'route' => 'route', 'comments' => 'chat', 'house-chimney-crack' => 'home', 'house-user' => 'home',
			'house' => 'home', 'key' => 'key', 'hand-back-fist' => 'hand', 'ban' => 'ban', 'coins' => 'coins', 'wallet' => 'wallet',
			'child-reaching' => 'user', 'user' => 'user', 'user-secret' => 'user', 'gavel' => 'gavel', 'lock' => 'lock',
			'circle-info' => 'info', 'store-slash' => 'store', 'store' => 'store', 'handshake-slash' => 'briefcase', 'handshake' => 'briefcase',
			'arrow-left' => 'arrow-left', 'arrow-right' => 'arrow-right', 'angle-left' => 'arrow-left', 'angle-right' => 'arrow-right',
			'book-open' => 'book', 'quote-right' => 'quote', 'star' => 'star', 'check' => 'check', 'calendar' => 'calendar',
			'whatsapp' => 'whatsapp', 'telegram' => 'send', 'instagram' => 'instagram', 'globe' => 'globe',
		);
	}

	/**
	 * خروجی HTML آیکون (SVG یا تگ i برای Font Awesome).
	 *
	 * @param string $key   کلید آیکون یا کلاس FA.
	 * @param string $class کلاس اضافه.
	 */
	public static function render( $key, $class = '' ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return '';
		}
		$set = self::set();
		$map = self::fa_map();

		if ( ! isset( $set[ $key ] ) && preg_match( '/^[a-z0-9\- ]+$/i', $key ) ) {
			foreach ( array_reverse( preg_split( '/\s+/', $key ) ) as $token ) {
				$token = preg_replace( '/^fa-/', '', $token );
				if ( isset( $map[ $token ] ) ) {
					$key = $map[ $token ];
					break;
				}
			}
		}

		if ( isset( $set[ $key ] ) ) {
			return '<svg class="wsl-svg ' . esc_attr( $class ) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $set[ $key ] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت داخلی.
		}

		if ( preg_match( '/^[a-z0-9\- ]+$/i', $key ) && false !== strpos( $key, 'fa-' ) ) {
			return '<i class="' . esc_attr( trim( $key . ' ' . $class ) ) . '" aria-hidden="true"></i>';
		}
		return '';
	}

	public static function sanitize( $value ) {
		return trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', (string) $value ) );
	}
}
