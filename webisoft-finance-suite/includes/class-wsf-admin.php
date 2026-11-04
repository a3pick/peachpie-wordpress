<?php
defined( 'ABSPATH' ) || exit;

/**
 * پنل مدیریت: ساخت خودکار فرم‌ها از روی اسکیما، ذخیره، ایمپورت/اکسپورت و راهنما.
 */
class WSF_Admin {

	const SLUG = 'wsf-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wsf_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_wsf_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_wsf_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WSF_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">تنظیمات</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page( 'وبی‌سافت حقوقی', 'وبی‌سافت حقوقی', 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-bank', 58 );
		add_submenu_page( self::SLUG, 'تنظیمات', 'تنظیمات', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wsf-admin', WSF_URL . 'assets/css/wsf-admin.css', array(), WSF_VERSION );
		wp_enqueue_script( 'wsf-admin', WSF_URL . 'assets/js/wsf-admin.js', array( 'jquery', 'wp-color-picker' ), WSF_VERSION, true );
		wp_localize_script(
			'wsf-admin',
			'WSFAdmin',
			array(
				'icons' => WSF_Icons::set(),
				'i18n'  => array(
					'newItem'  => 'مورد جدید',
					'confirm'  => 'این تنظیمات به مقادیر پیش‌فرض بازگردانده می‌شود. ادامه می‌دهید؟',
					'del'      => 'این مورد حذف شود؟',
					'pick'     => 'انتخاب تصویر',
					'use'      => 'استفاده از این تصویر',
					'copied'   => 'کپی شد',
				),
			)
		);
	}

	/* =====================================================================
	 * اعلان‌ها
	 * ===================================================================== */
	public static function notices() {
		$screen = get_current_screen();
		$ours   = $screen && ( 'toplevel_page_' . self::SLUG === $screen->id || self::is_plugins_screen( $screen ) );

		if ( $ours && isset( $_GET['wsf_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$map = array(
				'saved'    => array( 'success', 'تنظیمات ذخیره شد.' ),
				'reset'    => array( 'success', 'تنظیمات این بخش به حالت پیش‌فرض بازگشت.' ),
				'imported' => array( 'success', 'تنظیمات با موفقیت وارد شد.' ),
				'badjson'  => array( 'error', 'فایل/متن JSON معتبر نیست.' ),
			);
			$k = sanitize_key( wp_unslash( $_GET['wsf_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $map[ $k ] ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $k ][0] ), esc_html( $map[ $k ][1] ) );
			}
		}

	}

	private static function is_plugins_screen( $screen ) {
		return in_array( $screen->id, array( 'plugins', 'edit-wsf_lead' ), true );
	}

	/* =====================================================================
	 * صفحه اصلی
	 * ===================================================================== */
	private static function url( $tab ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'webisoft-finance-suite' ) );
		}
		$sections = WSF_Schema::sections();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $sections[ $tab ] ) && ! in_array( $tab, array( 'tools', 'help' ), true ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap wsf-admin"><h1 class="wsf-admin__h1"><span class="dashicons dashicons-bank"></span> Webisoft Finance Suite <small>v' . esc_html( WSF_VERSION ) . '</small></h1>';
		echo '<div class="wsf-layout"><nav class="wsf-nav" aria-label="بخش‌ها">';

		foreach ( WSF_Schema::groups() as $gid => $glabel ) {
			echo '<div class="wsf-nav__group"><div class="wsf-nav__title">' . esc_html( $glabel ) . '</div>';
			foreach ( $sections as $sid => $sec ) {
				if ( $sec['group'] !== $gid ) {
					continue;
				}
				echo '<a class="wsf-nav__link' . ( $tab === $sid ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $sid ) ) . '"><span class="dashicons ' . esc_attr( $sec['icon'] ) . '"></span>' . esc_html( $sec['label'] ) . '</a>';
				if ( 'forms' === $sid ) {
					echo '<a class="wsf-nav__link" href="' . esc_url( admin_url( 'edit.php?post_type=' . WSF_Leads::CPT ) ) . '"><span class="dashicons dashicons-email-alt2"></span>لیست درخواست‌ها (Leads)</a>';
				}
			}
			echo '</div>';
		}
		echo '<div class="wsf-nav__group"><div class="wsf-nav__title">ابزارها</div>';
		echo '<a class="wsf-nav__link' . ( 'tools' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'tools' ) ) . '"><span class="dashicons dashicons-database-export"></span>ایمپورت / اکسپورت</a>';
		echo '<a class="wsf-nav__link' . ( 'help' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'help' ) ) . '"><span class="dashicons dashicons-editor-help"></span>راهنما و شورت‌کدها</a></div>';
		echo '</nav><main class="wsf-main">';

		if ( 'tools' === $tab ) {
			self::tools_page();
		} elseif ( 'help' === $tab ) {
			self::help_page();
		} else {
			self::section_form( $tab, $sections[ $tab ] );
		}
		echo '</main></div></div>';
	}

	private static function section_form( $sid, $sec ) {
		$values = WSF_Options::sec( $sid );
		echo '<h2 class="wsf-main__h">' . esc_html( $sec['label'] ) . '</h2>';
		if ( ! empty( $sec['desc'] ) ) {
			echo '<p class="wsf-main__desc">' . esc_html( $sec['desc'] ) . '</p>';
		}
		if ( ! empty( $sec['shortcode'] ) ) {
			echo '<div class="wsf-sc"><span>شورت‌کد:</span> <code dir="ltr">' . esc_html( $sec['shortcode'] ) . '</code> <button type="button" class="button button-small wsf-copy" data-copy="' . esc_attr( $sec['shortcode'] ) . '">کپی</button></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wsf-form-admin">';
		wp_nonce_field( 'wsf_save_' . $sid );
		echo '<input type="hidden" name="action" value="wsf_save"><input type="hidden" name="section" value="' . esc_attr( $sid ) . '">';
		echo '<div class="wsf-fields">';
		foreach ( $sec['fields'] as $f ) {
			self::field( $f, isset( $values[ $f['id'] ] ) ? $values[ $f['id'] ] : $f['default'], 'wsf[' . $sid . '][' . $f['id'] . ']', 0 );
		}
		echo '</div><p class="submit wsf-submit"><button type="submit" class="button button-primary button-hero">ذخیره تغییرات</button> <button type="submit" name="wsf_reset" value="1" class="button wsf-reset">بازگشت به پیش‌فرض</button></p></form>';
	}

	/* =====================================================================
	 * فیلدها
	 * ===================================================================== */
	public static function field( $f, $value, $name, $depth ) {
		$id   = 'wsf-' . md5( $name );
		$type = $f['type'];
		$cls  = 'wsf-f wsf-f--' . $type . ( ! empty( $f['class'] ) ? ' ' . $f['class'] : '' );

		echo '<div class="' . esc_attr( $cls ) . '">';
		if ( 'toggle' !== $type ) {
			echo '<label class="wsf-f__label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
		}
		echo '<div class="wsf-f__control">';

		switch ( $type ) {
			case 'toggle':
				echo '<label class="wsf-switch" for="' . esc_attr( $id ) . '"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . '><span class="wsf-switch__ui"></span><span class="wsf-switch__txt">' . esc_html( $f['label'] ) . '</span></label>';
				break;

			case 'textarea':
			case 'html':
			case 'lines':
				$rows = isset( $f['rows'] ) ? (int) $f['rows'] : 3;
				$code = ( isset( $f['class'] ) && 'code' === $f['class'] ) ? ' dir="ltr" spellcheck="false"' : '';
				echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . $rows . '" class="large-text"' . $code . '>' . esc_textarea( (string) $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'number':
				$min = isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '';
				$max = isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '';
				echo '<input type="number" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text" dir="ltr" step="1"' . $min . $max . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'select':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $f['options'] as $k => $label ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) $value, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select>';
				break;

			case 'color':
				$def = $f['default'];
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsf-color" data-default-color="' . esc_attr( $def ) . '" dir="ltr">';
				break;

			case 'icon':
				echo '<div class="wsf-icon-field"><span class="wsf-icon-field__prev">' . WSF_Icons::render( $value ) . '</span><input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsf-icon-input" dir="ltr" placeholder="scale / fa-solid fa-users"><button type="button" class="button wsf-icon-pick">انتخاب</button></div>';
				break;

			case 'image':
				$thumb = '';
				if ( is_numeric( $value ) && $value ) {
					$thumb = wp_get_attachment_image_url( (int) $value, 'thumbnail' );
				} elseif ( $value ) {
					$thumb = $value;
				}
				echo '<div class="wsf-img-field"><span class="wsf-img-field__prev">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '' ) . '</span><input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsf-img-input" dir="ltr" placeholder="ID یا URL"><button type="button" class="button wsf-img-pick">انتخاب تصویر</button> <button type="button" class="button-link wsf-img-clear">حذف</button></div>';
				break;

			case 'repeater':
				self::repeater( $f, $value, $name, $depth );
				break;

			case 'url':
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="large-text" dir="ltr" placeholder="https://  /  #anchor  /  tel:">';
				break;

			case 'text':
			default:
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="large-text">';
		}

		if ( ! empty( $f['desc'] ) ) {
			echo '<p class="description">' . esc_html( $f['desc'] ) . '</p>';
		}
		echo '</div></div>';
	}

	private static function repeater( $f, $rows, $name, $depth ) {
		$title = isset( $f['title_field'] ) ? $f['title_field'] : '';
		echo '<div class="wsf-rep" data-depth="' . (int) $depth . '" data-title-field="' . esc_attr( $title ) . '"><div class="wsf-rep__rows">';
		foreach ( array_values( (array) $rows ) as $i => $row ) {
			self::row( $f, (array) $row, $name . '[' . $i . ']', $depth, false );
		}
		echo '</div><template class="wsf-rep__tpl">';
		self::row( $f, WSF_Schema::field_defaults( $f['fields'] ), $name . '[__ROW' . (int) $depth . '__]', $depth, true );
		echo '</template><button type="button" class="button wsf-rep__add">+ افزودن مورد</button></div>';
	}

	private static function row( $f, $row, $name, $depth, $is_tpl ) {
		$title = '';
		if ( ! empty( $f['title_field'] ) && ! $is_tpl && isset( $row[ $f['title_field'] ] ) ) {
			$title = wp_strip_all_tags( (string) $row[ $f['title_field'] ] );
		}
		echo '<details class="wsf-row"><summary><span class="wsf-row__title">' . esc_html( $title ? $title : 'مورد جدید' ) . '</span><span class="wsf-row__tools"><button type="button" class="wsf-row__btn wsf-up" title="بالا">↑</button><button type="button" class="wsf-row__btn wsf-down" title="پایین">↓</button><button type="button" class="wsf-row__btn wsf-del" title="حذف">✕</button></span></summary><div class="wsf-row__body">';
		foreach ( $f['fields'] as $sub ) {
			$val = array_key_exists( $sub['id'], $row ) ? $row[ $sub['id'] ] : $sub['default'];
			self::field( $sub, $val, $name . '[' . $sub['id'] . ']', 'repeater' === $sub['type'] ? $depth + 1 : $depth );
		}
		echo '</div></details>';
	}

	/* =====================================================================
	 * ذخیره / اکسپورت / ایمپورت
	 * ===================================================================== */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		$sid = isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : '';
		check_admin_referer( 'wsf_save_' . $sid );
		$sections = WSF_Schema::sections();
		if ( ! isset( $sections[ $sid ] ) ) {
			wp_safe_redirect( self::url( 'general' ) );
			exit;
		}
		$reset = ! empty( $_POST['wsf_reset'] );
		$input = ( isset( $_POST['wsf'][ $sid ] ) && is_array( $_POST['wsf'][ $sid ] ) ) ? wp_unslash( $_POST['wsf'][ $sid ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- در save_section بر اساس اسکیما پاک‌سازی می‌شود.
		WSF_Options::save_section( $sid, $input, $reset );
		wp_safe_redirect( add_query_arg( 'wsf_msg', $reset ? 'reset' : 'saved', self::url( $sid ) ) );
		exit;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsf_export' );
		$data = get_option( WSF_OPT, array() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webisoft-legal-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsf_import' );
		$raw = '';
		if ( ! empty( $_FILES['wsf_file']['tmp_name'] ) && is_uploaded_file( $_FILES['wsf_file']['tmp_name'] ) && $_FILES['wsf_file']['size'] < 2 * MB_IN_BYTES ) { // phpcs:ignore WordPress.Security
			$raw = file_get_contents( $_FILES['wsf_file']['tmp_name'] ); // phpcs:ignore WordPress.Security, WordPress.WP.AlternativeFunctions
		} elseif ( ! empty( $_POST['wsf_json'] ) ) {
			$raw = wp_unslash( $_POST['wsf_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( 'wsf_msg', 'badjson', self::url( 'tools' ) ) );
			exit;
		}
		WSF_Options::import( $data );
		wp_safe_redirect( add_query_arg( 'wsf_msg', 'imported', self::url( 'tools' ) ) );
		exit;
	}

	private static function tools_page() {
		echo '<h2 class="wsf-main__h">ایمپورت / اکسپورت تنظیمات</h2><p class="wsf-main__desc">برای انتقال تنظیمات و محتوای ماژول‌ها بین سایت‌ها (مثلاً از سایت نمونه به سایت جدید).</p>';
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=wsf_export' ), 'wsf_export' );
		echo '<p><a class="button button-primary" href="' . esc_url( $export ) . '">دانلود فایل تنظیمات (JSON)</a></p>';
		echo '<h3>ایمپورت</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'wsf_import' );
		echo '<input type="hidden" name="action" value="wsf_import"><p><input type="file" name="wsf_file" accept="application/json,.json"></p><p>یا متن JSON را اینجا بچسبانید:</p><textarea name="wsf_json" rows="6" class="large-text" dir="ltr"></textarea><p class="description">هشدار: ایمپورت، بخش‌های موجود در فایل را جایگزین تنظیمات فعلی می‌کند. قبل از آن اکسپورت بگیرید.</p><p><button class="button button-primary" type="submit">ایمپورت</button></p></form>';
	}

	/* =====================================================================
	 * راهنما
	 * ===================================================================== */
	private static function help_page() {
		echo '<h2 class="wsf-main__h">راهنما و شورت‌کدها</h2>';
		echo '<h3>شورت‌کدها</h3><table class="widefat striped wsf-help-table"><thead><tr><th>ماژول</th><th>شورت‌کد</th></tr></thead><tbody>';
		foreach ( WSF_Schema::sections() as $sid => $sec ) {
			if ( empty( $sec['shortcode'] ) ) {
				continue;
			}
			echo '<tr><td><a href="' . esc_url( self::url( $sid ) ) . '">' . esc_html( $sec['label'] ) . '</a></td><td><code dir="ltr">' . esc_html( $sec['shortcode'] ) . '</code></td></tr>';
		}
		echo '<tr><td>دکمه حالت روشن/تاریک</td><td><code dir="ltr">[ws_theme_toggle]</code></td></tr></tbody></table>';

		echo '<h3>استفاده در المنتور</h3><ol><li>ویجت «Shortcode» را در صفحه بکشید و شورت‌کد را وارد کنید.</li><li>برای هیرو و بخش‌های تمام‌عرض، ظرف المنتور را «Full Width» یا مناسب عرض دلخواه تنظیم کنید؛ ماژول‌ها خودشان حداکثر عرض دارند.</li><li>برای کارت‌های Sticky، ظرف‌های والد نباید <code>overflow: hidden</code> داشته باشند.</li><li>بعد از هر تغییر، کش (کش‌افزونه/CDN/Elementor Regenerate CSS) را پاک کنید.</li></ol>';

		echo '<h3>حالت تاریک و المنتور</h3><ul class="ul-disc"><li>رنگ‌های افزونه و بدنه/هدر/فوتر Hello Elementor خودکار تغییر می‌کنند.</li><li>بخش‌هایی که در المنتور رنگ پس‌زمینه یا رنگ متن «دستی» گرفته‌اند خودکار تغییر نمی‌کنند. سه راه: (۱) کلاس <code>ws-surface</code> یا <code>ws-surface-alt</code> را در فیلد «CSS Classes» ظرف المنتور بدهید؛ (۲) در «حالت روشن/تاریک» سلکتورها را وارد کنید؛ (۳) در المنتور به‌جای رنگ ثابت از Global Colors استفاده کنید و در همان بخش آن‌ها را به رنگ‌های افزونه نگاشت کنید.</li><li>کلاس‌های دیگر: <code>ws-text</code>، <code>ws-muted</code>، <code>ws-glass</code>، <code>ws-hover-border</code>، <code>ws-pulse</code>، و کلاس‌های قدیمی <code>glass-panel</code>، <code>btn-emergency</code>، <code>border-hover-1..7</code>.</li></ul>';

		echo '<h3>اتصال افزونه اشتراک (ووکامرس)</h3><ul class="ul-disc"><li>قفل‌ها از فیلتر <code>wsf_user_has_access</code> تصمیم می‌گیرند: <code dir="ltr">add_filter( \'wsf_user_has_access\', function ( $has, $level, $user_id ) { return my_user_is_subscribed( $user_id ); }, 10, 3 );</code></li><li>بدون این فیلتر فقط مدیران دسترسی دارند. شورت‌کد قفل داخل مقاله: <code dir="ltr">[fin_lock plans="/plans"]محتوای ویژه[/fin_lock]</code></li><li>صفحه‌هایی که برای کاربران وارد‌شده متفاوت‌اند را از کش صفحه معاف کنید (اغلب کش‌افزونه‌ها کاربران واردشده را خودکار معاف می‌کنند).</li><li>هشتگ همان «برچسب» وردپرس است؛ جستجوی <code>#مالیات</code> فقط مقاله‌های دارای آن برچسب را نشان می‌دهد.</li></ul>';

		echo '<h3>نکات فنی</h3><ul class="ul-disc"><li>اگر فرم‌های تنظیمات بزرگ (مثلاً ده‌ها آیتم) ذخیره نمی‌شوند، مقدار <code>max_input_vars</code> در PHP (پیش‌فرض ۱۰۰۰) را بالا ببرید.</li><li>برای سرعت بهتر، فونت وزیرمتن را روی هاست خودتان آپلود و گزینه «فایل فونت اختصاصی» را انتخاب کنید.</li><li>فرم‌ها: ضد اسپم (honeypot، زمان‌سنج و محدودیت IP) فعال است. اگر ایمیل نمی‌رسد، SMTP سایت را تنظیم کنید؛ درخواست‌ها در «لیست درخواست‌ها» ذخیره می‌شوند.</li></ul>';
	}
}
