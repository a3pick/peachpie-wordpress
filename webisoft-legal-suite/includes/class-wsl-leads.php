<?php
defined( 'ABSPATH' ) || exit;

/**
 * فرم‌های دریافت درخواست: رندر، اعتبارسنجی، ضد اسپم، ذخیره (CPT) و ایمیل.
 */
class WSL_Leads {

	const CPT = 'wsl_lead';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_post_nopriv_wsl_lead', array( __CLASS__, 'handle_post' ) );
		add_action( 'admin_post_wsl_lead', array( __CLASS__, 'handle_post' ) );

		if ( is_admin() ) {
			add_filter( 'manage_' . self::CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::CPT . '_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
			add_action( 'add_meta_boxes_' . self::CPT, array( __CLASS__, 'metabox' ) );
			add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save_status' ) );
			add_action( 'restrict_manage_posts', array( __CLASS__, 'status_filter' ) );
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ) );
		}
	}

	public static function statuses() {
		return array( 'new' => 'جدید', 'contacted' => 'تماس گرفته شد', 'closed' => 'بسته‌شده' );
	}

	public static function sources() {
		return array( 'booking_form' => 'فرم درخواست بررسی پرونده', 'contact_page' => 'صفحه تماس' );
	}

	public static function register_cpt() {
		$cap = 'manage_options';
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => 'درخواست‌ها',
					'singular_name' => 'درخواست',
					'search_items'  => 'جستجوی درخواست',
					'not_found'     => 'درخواستی ثبت نشده است.',
					'edit_item'     => 'جزئیات درخواست',
					'all_items'     => 'درخواست‌ها (Leads)',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'wsl-settings',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_private_posts'   => $cap,
					'delete_published_posts' => $cap,
					'delete_others_posts'    => $cap,
					'edit_private_posts'     => $cap,
					'edit_published_posts'   => $cap,
					'create_posts'           => 'do_not_allow',
				),
			)
		);
	}

	/* =====================================================================
	 * فرم (HTML)
	 * ===================================================================== */
	public static function render_form( $a ) {
		$a = wp_parse_args(
			$a,
			array(
				'source'             => 'booking_form',
				'fields'             => array( 'name', 'phone' ),
				'domain_options'     => array(),
				'domain_placeholder' => '',
				'button'             => 'ارسال',
				'success'            => '',
				'note'               => '',
			)
		);
		$f   = WSL_Options::sec( 'forms' );
		$src = isset( self::sources()[ $a['source'] ] ) ? $a['source'] : 'booking_form';

		$fields = array();
		foreach ( $a['fields'] as $k => $v ) {
			if ( is_int( $k ) ) {
				$fields[ $v ] = true;
			} elseif ( $v ) {
				$fields[ $k ] = true;
			}
		}

		$uid    = wp_unique_id( 'wsl-form-' );
		$notice = '';
		$class  = '';
		if ( isset( $_GET['wsl_form'], $_GET['wsl_lead'] ) && sanitize_key( wp_unslash( $_GET['wsl_form'] ) ) === $src ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'ok' === sanitize_key( wp_unslash( $_GET['wsl_lead'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$notice = $a['success'] ? $a['success'] : 'درخواست شما ثبت شد.';
				$class  = 'is-ok';
			} else {
				$code   = isset( $_GET['wsl_err'] ) ? sanitize_key( wp_unslash( $_GET['wsl_err'] ) ) : 'generic'; // phpcs:ignore WordPress.Security.NonceVerification
				$notice = self::error_message( $code );
				$class  = 'is-err';
			}
		}

		$ph = function ( $key, $default ) use ( $f ) {
			return ! empty( $f[ $key ] ) ? $f[ $key ] : $default;
		};

		$h  = '<form class="wsl-form" id="' . esc_attr( $uid ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-wsl="form" data-success="' . esc_attr( $a['success'] ? $a['success'] : 'درخواست شما ثبت شد.' ) . '">';
		$h .= '<input type="hidden" name="action" value="wsl_lead"><input type="hidden" name="source" value="' . esc_attr( $src ) . '"><input type="hidden" name="wsl_t" value="' . esc_attr( self::token() ) . '">';
		$h .= '<div class="wsl-form__grid">';

		if ( isset( $fields['name'] ) ) {
			$h .= self::input( 'text', 'name', $ph( 'ph_name', 'نام و نام خانوادگی' ), true, 'name' );
		}
		if ( isset( $fields['phone'] ) ) {
			$h .= self::input( 'tel', 'phone', $ph( 'ph_phone', 'شماره موبایل' ), true, 'tel', ' inputmode="tel" dir="auto"' );
		}
		if ( isset( $fields['email'] ) ) {
			$h .= self::input( 'email', 'email', $ph( 'ph_email', 'ایمیل (اختیاری)' ), false, 'email', ' dir="auto"' );
		}
		if ( isset( $fields['subject'] ) ) {
			$h .= self::input( 'text', 'subject', $ph( 'ph_subject', 'موضوع دعوا (مثلاً: تخلیه ملک، چک برگشتی)' ), false, 'off' );
		}
		if ( isset( $fields['domain'] ) && ! empty( $a['domain_options'] ) ) {
			$h .= '<label class="wsl-field wsl-field--select"><span class="wsl-sr">' . esc_html( $a['domain_placeholder'] ) . '</span><select name="domain" class="wsl-input"><option value="" selected>' . esc_html( $a['domain_placeholder'] ) . '</option>';
			foreach ( $a['domain_options'] as $opt ) {
				$h .= '<option value="' . esc_attr( $opt ) . '">' . esc_html( $opt ) . '</option>';
			}
			$h .= '</select><span class="wsl-field__chev">' . WSL_Icons::render( 'chevron-down' ) . '</span></label>';
		}
		if ( isset( $fields['message'] ) ) {
			$req = ( 'contact_page' === $src );
			$h  .= '<label class="wsl-field wsl-field--full"><span class="wsl-sr">' . esc_html( $ph( 'ph_message', 'شرح مختصر مشکل حقوقی...' ) ) . '</span><textarea name="message" class="wsl-input" rows="5" maxlength="3000" placeholder="' . esc_attr( $ph( 'ph_message', 'شرح مختصر مشکل حقوقی...' ) ) . '"' . ( $req ? ' required' : '' ) . '></textarea></label>';
		}
		$h .= '</div>';

		// تله‌ی ربات (honeypot)؛ برای کاربر پنهان است.
		$h .= '<div class="wsl-hp" aria-hidden="true"><label>Website<input type="text" name="wsl_hp" tabindex="-1" autocomplete="off"></label></div>';

		if ( ! empty( $f['require_consent'] ) ) {
			$h .= '<label class="wsl-consent"><input type="checkbox" name="consent" value="1" required><span>' . esc_html( $f['consent_text'] ) . '</span></label>';
		}

		$h .= '<button type="submit" class="wsl-btn wsl-btn--primary wsl-btn--block">' . esc_html( $a['button'] ) . '</button>';
		$h .= '<div class="wsl-form__msg ' . esc_attr( $class ) . '" role="status" aria-live="polite">' . esc_html( $notice ) . '</div>';
		if ( $a['note'] ) {
			$h .= '<p class="wsl-form__note">' . WSL_Icons::render( 'shield' ) . '<span>' . esc_html( $a['note'] ) . '</span></p>';
		}
		$h .= '<noscript><p class="wsl-form__note">' . esc_html( 'برای ارسال بدون جاوااسکریپت نیز می‌توانید دکمه ارسال را بزنید.' ) . '</p></noscript></form>';
		return $h;
	}

	private static function input( $type, $name, $placeholder, $required, $autocomplete, $extra = '' ) {
		return '<label class="wsl-field"><span class="wsl-sr">' . esc_html( $placeholder ) . '</span><input class="wsl-input" type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" maxlength="200"' . ( $required ? ' required' : '' ) . $extra . '></label>'; // phpcs:ignore WordPress.Security.EscapeOutput -- $extra ثابت داخلی.
	}

	/* =====================================================================
	 * امنیت
	 * ===================================================================== */
	private static function token() {
		$ts = time();
		return $ts . '.' . hash_hmac( 'sha256', 'wsl|' . $ts, wp_salt( 'nonce' ) );
	}

	private static function token_age( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([a-f0-9]{64})$/', $token, $m ) ) {
			return false;
		}
		if ( ! hash_equals( hash_hmac( 'sha256', 'wsl|' . $m[1], wp_salt( 'nonce' ) ), $m[2] ) ) {
			return false;
		}
		return time() - (int) $m[1];
	}

	private static function client_ip() {
		$src = WSL_Options::get( 'forms', 'ip_source', 'REMOTE_ADDR' );
		$src = ( 'HTTP_CF_CONNECTING_IP' === $src ) ? $src : 'REMOTE_ADDR';
		$ip  = isset( $_SERVER[ $src ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $src ] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	public static function error_message( $code ) {
		$f = WSL_Options::sec( 'forms' );
		$m = array(
			'required' => $f['error_required'],
			'phone'    => $f['error_phone'],
			'rate'     => $f['error_rate'],
		);
		return isset( $m[ $code ] ) && $m[ $code ] ? $m[ $code ] : $f['error_generic'];
	}

	/* =====================================================================
	 * پردازش
	 * ===================================================================== */
	/**
	 * @return array{ok:bool,code:string}
	 */
	public static function process( $in ) {
		$f = WSL_Options::sec( 'forms' );

		if ( ! empty( $in['wsl_hp'] ) ) {
			return array( 'ok' => true, 'code' => 'spam' ); // ربات؛ بی‌صدا نادیده گرفته می‌شود.
		}

		$age = self::token_age( isset( $in['wsl_t'] ) ? (string) $in['wsl_t'] : '' );
		if ( false === $age || $age < (int) $f['min_seconds'] || $age > 90 * DAY_IN_SECONDS ) {
			return array( 'ok' => false, 'code' => 'generic' );
		}

		$src = isset( $in['source'] ) ? sanitize_key( $in['source'] ) : 'booking_form';
		$src = isset( self::sources()[ $src ] ) ? $src : 'booking_form';

		$d = array(
			'name'    => mb_substr( sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ), 0, 100 ),
			'phone'   => preg_replace( '/[^0-9+]/', '', WSL_Options::normalize_digits( isset( $in['phone'] ) ? sanitize_text_field( $in['phone'] ) : '' ) ),
			'email'   => isset( $in['email'] ) ? sanitize_email( $in['email'] ) : '',
			'subject' => mb_substr( sanitize_text_field( isset( $in['subject'] ) ? $in['subject'] : '' ), 0, 200 ),
			'domain'  => mb_substr( sanitize_text_field( isset( $in['domain'] ) ? $in['domain'] : '' ), 0, 100 ),
			'message' => mb_substr( sanitize_textarea_field( isset( $in['message'] ) ? $in['message'] : '' ), 0, 3000 ),
		);

		if ( '' === $d['name'] || ( 'contact_page' === $src && '' === $d['message'] ) ) {
			return array( 'ok' => false, 'code' => 'required' );
		}
		$digits = strlen( preg_replace( '/\D/', '', $d['phone'] ) );
		if ( $digits < 7 || $digits > 15 ) {
			return array( 'ok' => false, 'code' => 'phone' );
		}
		if ( '' !== $d['email'] && ! is_email( $d['email'] ) ) {
			$d['email'] = '';
		}
		if ( ! empty( $f['require_consent'] ) && empty( $in['consent'] ) ) {
			return array( 'ok' => false, 'code' => 'required' );
		}

		$key   = 'wsl_rl_' . md5( self::client_ip() );
		$count = (int) get_transient( $key );
		if ( $count >= (int) $f['rate_limit'] ) {
			return array( 'ok' => false, 'code' => 'rate' );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		$page  = wp_get_referer();
		$store = ! empty( $f['store'] ) || empty( $f['send_email'] );
		if ( $store ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::CPT,
					'post_status' => 'publish',
					'post_title'  => trim( $d['name'] . ( $d['subject'] ? ' — ' . $d['subject'] : '' ) ),
					'post_content' => $d['message'],
				),
				true
			);
			if ( ! is_wp_error( $id ) && $id ) {
				foreach ( array( 'name', 'phone', 'email', 'subject', 'domain' ) as $k ) {
					update_post_meta( $id, '_wsl_' . $k, $d[ $k ] );
				}
				update_post_meta( $id, '_wsl_source', $src );
				update_post_meta( $id, '_wsl_page', $page ? esc_url_raw( $page ) : '' );
				update_post_meta( $id, '_wsl_status', 'new' );
			} elseif ( empty( $f['send_email'] ) ) {
				return array( 'ok' => false, 'code' => 'generic' );
			}
		}
		if ( ! empty( $f['send_email'] ) ) {
			self::notify( $d, $src, $page );
		}
		return array( 'ok' => true, 'code' => 'ok' );
	}

	private static function notify( $d, $src, $page ) {
		$f  = WSL_Options::sec( 'forms' );
		$to = array();
		foreach ( explode( ',', (string) $f['notify_email'] ) as $e ) {
			$e = sanitize_email( trim( $e ) );
			if ( is_email( $e ) ) {
				$to[] = $e;
			}
		}
		if ( ! $to ) {
			$to[] = get_option( 'admin_email' );
		}
		$labels  = self::sources();
		$subject = str_replace( array( '{name}', '{source}' ), array( $d['name'], $labels[ $src ] ), $f['subject'] );
		$subject = sanitize_text_field( $subject );

		$body  = "درخواست جدید از سایت " . get_bloginfo( 'name' ) . "\n";
		$body .= "---------------------------------\n";
		$body .= 'منبع: ' . $labels[ $src ] . "\n";
		$body .= 'نام: ' . $d['name'] . "\n";
		$body .= 'تلفن: ' . $d['phone'] . "\n";
		if ( $d['email'] ) {
			$body .= 'ایمیل: ' . $d['email'] . "\n";
		}
		if ( $d['subject'] ) {
			$body .= 'موضوع: ' . $d['subject'] . "\n";
		}
		if ( $d['domain'] ) {
			$body .= 'حوزه: ' . $d['domain'] . "\n";
		}
		if ( $d['message'] ) {
			$body .= "\n" . $d['message'] . "\n";
		}
		if ( $page ) {
			$body .= "\nصفحه: " . esc_url_raw( $page ) . "\n";
		}
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $d['email'] ) {
			$headers[] = 'Reply-To: ' . $d['email'];
		}
		wp_mail( $to, $subject, $body, $headers );
	}

	/* ---------- ورودی‌ها: REST و admin-post ---------- */
	public static function routes() {
		register_rest_route(
			'wsl/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_lead' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function rest_lead( $request ) {
		$res  = self::process( $request->get_params() );
		$msg  = $res['ok'] ? '' : self::error_message( $res['code'] );
		$code = $res['ok'] ? 200 : ( 'rate' === $res['code'] ? 429 : 400 );
		return new WP_REST_Response( array( 'ok' => $res['ok'], 'message' => $msg ), $code );
	}

	public static function handle_post() {
		$in  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- فرم عمومی؛ حفاظت: توکن زمانی + honeypot + rate limit.
		$res = self::process( $in );
		$src = isset( $in['source'] ) ? sanitize_key( $in['source'] ) : 'booking_form';
		$ref = wp_get_referer();
		$url = $ref ? $ref : home_url( '/' );
		$url = remove_query_arg( array( 'wsl_lead', 'wsl_form', 'wsl_err' ), $url );
		$url = add_query_arg(
			array_filter(
				array(
					'wsl_lead' => $res['ok'] ? 'ok' : 'err',
					'wsl_form' => $src,
					'wsl_err'  => $res['ok'] ? '' : $res['code'],
				)
			),
			$url
		);
		wp_safe_redirect( $url );
		exit;
	}

	/* =====================================================================
	 * ادمین: ستون‌ها، متاباکس، فیلتر
	 * ===================================================================== */
	public static function columns( $cols ) {
		return array(
			'cb'          => $cols['cb'],
			'title'       => 'نام / موضوع',
			'wsl_phone'   => 'تلفن',
			'wsl_domain'  => 'حوزه',
			'wsl_source'  => 'منبع',
			'wsl_status'  => 'وضعیت',
			'date'        => 'تاریخ',
		);
	}

	public static function column_value( $col, $post_id ) {
		switch ( $col ) {
			case 'wsl_phone':
				$p = get_post_meta( $post_id, '_wsl_phone', true );
				echo $p ? '<a href="tel:' . esc_attr( $p ) . '" dir="ltr">' . esc_html( $p ) . '</a>' : '—';
				break;
			case 'wsl_domain':
				echo esc_html( get_post_meta( $post_id, '_wsl_domain', true ) ?: '—' );
				break;
			case 'wsl_source':
				$s = get_post_meta( $post_id, '_wsl_source', true );
				echo esc_html( isset( self::sources()[ $s ] ) ? self::sources()[ $s ] : '—' );
				break;
			case 'wsl_status':
				$st = get_post_meta( $post_id, '_wsl_status', true ) ?: 'new';
				echo '<strong>' . esc_html( isset( self::statuses()[ $st ] ) ? self::statuses()[ $st ] : $st ) . '</strong>';
				break;
		}
	}

	public static function metabox() {
		add_meta_box( 'wsl_lead_details', 'جزئیات درخواست', array( __CLASS__, 'metabox_html' ), self::CPT, 'normal', 'high' );
	}

	public static function metabox_html( $post ) {
		wp_nonce_field( 'wsl_lead_status', 'wsl_lead_nonce' );
		$rows = array(
			'نام'        => get_post_meta( $post->ID, '_wsl_name', true ),
			'تلفن'       => get_post_meta( $post->ID, '_wsl_phone', true ),
			'ایمیل'      => get_post_meta( $post->ID, '_wsl_email', true ),
			'موضوع'      => get_post_meta( $post->ID, '_wsl_subject', true ),
			'حوزه'       => get_post_meta( $post->ID, '_wsl_domain', true ),
			'پیام'       => $post->post_content,
			'صفحه ارسال' => get_post_meta( $post->ID, '_wsl_page', true ),
		);
		echo '<table class="form-table" role="presentation">';
		foreach ( $rows as $label => $val ) {
			if ( '' === (string) $val ) {
				continue;
			}
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $val ) ) . '</td></tr>';
		}
		$st = get_post_meta( $post->ID, '_wsl_status', true ) ?: 'new';
		echo '<tr><th><label for="wsl_status">وضعیت</label></th><td><select name="wsl_status" id="wsl_status">';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $st, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr></table>';
	}

	public static function save_status( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['wsl_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsl_lead_nonce'] ) ), 'wsl_lead_status' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$st = isset( $_POST['wsl_status'] ) ? sanitize_key( wp_unslash( $_POST['wsl_status'] ) ) : 'new';
		if ( isset( self::statuses()[ $st ] ) ) {
			update_post_meta( $post_id, '_wsl_status', $st );
		}
	}

	public static function status_filter( $post_type ) {
		if ( self::CPT !== $post_type ) {
			return;
		}
		$cur = isset( $_GET['wsl_status'] ) ? sanitize_key( wp_unslash( $_GET['wsl_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<select name="wsl_status"><option value="">همه وضعیت‌ها</option>';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public static function filter_query( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || self::CPT !== $q->get( 'post_type' ) || empty( $_GET['wsl_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$st = sanitize_key( wp_unslash( $_GET['wsl_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( self::statuses()[ $st ] ) ) {
			$q->set( 'meta_key', '_wsl_status' );
			$q->set( 'meta_value', $st );
		}
	}
}
