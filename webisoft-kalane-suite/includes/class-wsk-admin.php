<?php
defined( 'ABSPATH' ) || exit;

/**
 * پنل مدیریت: ساخت خودکار فرم‌ها از روی اسکیما، ذخیره، ایمپورت/اکسپورت و راهنما.
 */
class WSK_Admin {

	const SLUG = 'wsk-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wsk_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_wsk_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_wsk_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_wsk_tool', array( __CLASS__, 'handle_tool' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WSK_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">تنظیمات</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page( 'وبی‌سافت کالانه', 'وبی‌سافت کالانه', 'manage_options', self::SLUG, array( __CLASS__, 'page' ), 'dashicons-store', 58 );
		add_submenu_page( self::SLUG, 'تنظیمات', 'تنظیمات', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'wsk-admin', WSK_URL . 'assets/css/wsk-admin.css', array(), WSK_VERSION );
		wp_enqueue_script( 'wsk-admin', WSK_URL . 'assets/js/wsk-admin.js', array( 'jquery', 'wp-color-picker' ), WSK_VERSION, true );
		wp_localize_script(
			'wsk-admin',
			'WSKAdmin',
			array(
				'i18n'  => array(
					'newItem'  => 'مورد جدید',
					'confirm'  => 'این تنظیمات به مقادیر پیش‌فرض بازگردانده می‌شود. ادامه می‌دهید؟',
					'del'      => 'این مورد حذف شود؟',
					'pick'     => 'انتخاب تصویر',
					'pickVideo' => 'انتخاب ویدیو',
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

		if ( $ours && isset( $_GET['wsk_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$map = array(
				'saved'    => array( 'success', 'تنظیمات ذخیره شد.' ),
				'reset'    => array( 'success', 'تنظیمات این بخش به حالت پیش‌فرض بازگشت.' ),
				'imported' => array( 'success', 'تنظیمات با موفقیت وارد شد.' ),
				'badjson'  => array( 'error', 'فایل/متن JSON معتبر نیست.' ),
				'backfill' => array( 'success', 'شمارنده‌ی بازدید برای محصولات قدیمی ساخته شد.' ),
				'pruned'   => array( 'success', 'آمار قدیمی‌تر از مدت نگهداری حذف شد.' ),
				'statsreset' => array( 'success', 'همه‌ی آمار پاک شد.' ),
			);
			$k = sanitize_key( wp_unslash( $_GET['wsk_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $map[ $k ] ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $k ][0] ), esc_html( $map[ $k ][1] ) );
			}
		}

	}

	private static function is_plugins_screen( $screen ) {
		return 'plugins' === $screen->id;
	}

	/* =====================================================================
	 * صفحه اصلی
	 * ===================================================================== */
	private static function url( $tab ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&tab=' . $tab );
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'webisoft-kalane-suite' ) );
		}
		$sections = WSK_Schema::sections();
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $sections[ $tab ] ) && ! in_array( $tab, array( 'stats', 'tools', 'help' ), true ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap wsk-admin"><h1 class="wsk-admin__h1"><span class="dashicons dashicons-store"></span> Webisoft Kalane Suite <small>v' . esc_html( WSK_VERSION ) . '</small></h1>';
		echo '<div class="wsk-layout"><nav class="wsk-nav" aria-label="بخش‌ها">';

		foreach ( WSK_Schema::groups() as $gid => $glabel ) {
			echo '<div class="wsk-nav__group"><div class="wsk-nav__title">' . esc_html( $glabel ) . '</div>';
			foreach ( $sections as $sid => $sec ) {
				if ( $sec['group'] !== $gid ) {
					continue;
				}
				echo '<a class="wsk-nav__link' . ( $tab === $sid ? ' is-active' : '' ) . '" href="' . esc_url( self::url( $sid ) ) . '"><span class="dashicons ' . esc_attr( $sec['icon'] ) . '"></span>' . esc_html( $sec['label'] ) . '</a>';
			}
			echo '</div>';
		}
		echo '<div class="wsk-nav__group"><div class="wsk-nav__title">گزارش و ابزارها</div>';
		echo '<a class="wsk-nav__link' . ( 'stats' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'stats' ) ) . '"><span class="dashicons dashicons-chart-bar"></span>آمار کلیک و بازدید</a>';
		echo '<a class="wsk-nav__link' . ( 'tools' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'tools' ) ) . '"><span class="dashicons dashicons-database-export"></span>ایمپورت / اکسپورت</a>';
		echo '<a class="wsk-nav__link' . ( 'help' === $tab ? ' is-active' : '' ) . '" href="' . esc_url( self::url( 'help' ) ) . '"><span class="dashicons dashicons-editor-help"></span>راهنما و شورت‌کدها</a></div>';
		echo '</nav><main class="wsk-main">';

		if ( 'stats' === $tab ) {
			self::stats_page();
		} elseif ( 'tools' === $tab ) {
			self::tools_page();
		} elseif ( 'help' === $tab ) {
			self::help_page();
		} else {
			self::section_form( $tab, $sections[ $tab ] );
		}
		echo '</main></div></div>';
	}

	private static function section_form( $sid, $sec ) {
		$values = WSK_Options::sec( $sid );
		echo '<h2 class="wsk-main__h">' . esc_html( $sec['label'] ) . '</h2>';
		if ( ! empty( $sec['desc'] ) ) {
			echo '<p class="wsk-main__desc">' . esc_html( $sec['desc'] ) . '</p>';
		}
		if ( ! empty( $sec['shortcode'] ) ) {
			echo '<div class="wsk-sc"><span>شورت‌کد:</span> <code dir="ltr">' . esc_html( $sec['shortcode'] ) . '</code> <button type="button" class="button button-small wsk-copy" data-copy="' . esc_attr( $sec['shortcode'] ) . '">کپی</button></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wsk-form-admin">';
		wp_nonce_field( 'wsk_save_' . $sid );
		echo '<input type="hidden" name="action" value="wsk_save"><input type="hidden" name="section" value="' . esc_attr( $sid ) . '">';
		echo '<div class="wsk-fields">';
		foreach ( $sec['fields'] as $f ) {
			self::field( $f, isset( $values[ $f['id'] ] ) ? $values[ $f['id'] ] : $f['default'], 'wsk[' . $sid . '][' . $f['id'] . ']', 0 );
		}
		echo '</div><p class="submit wsk-submit"><button type="submit" class="button button-primary button-hero">ذخیره تغییرات</button> <button type="submit" name="wsk_reset" value="1" class="button wsk-reset">بازگشت به پیش‌فرض</button></p></form>';
	}

	/* =====================================================================
	 * فیلدها
	 * ===================================================================== */
	public static function field( $f, $value, $name, $depth ) {
		$id   = 'wsk-' . md5( $name );
		$type = $f['type'];
		$cls  = 'wsk-f wsk-f--' . $type . ( ! empty( $f['class'] ) ? ' ' . $f['class'] : '' );

		echo '<div class="' . esc_attr( $cls ) . '">';
		if ( 'toggle' !== $type ) {
			echo '<label class="wsk-f__label" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
		}
		echo '<div class="wsk-f__control">';

		switch ( $type ) {
			case 'toggle':
				echo '<label class="wsk-switch" for="' . esc_attr( $id ) . '"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $value ), true, false ) . '><span class="wsk-switch__ui"></span><span class="wsk-switch__txt">' . esc_html( $f['label'] ) . '</span></label>';
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
				echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsk-color" data-default-color="' . esc_attr( $def ) . '" dir="ltr">';
				break;

			case 'video':
				echo '<div class="wsk-img-field"><input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsk-img-input large-text" dir="ltr" placeholder="https://.../story.mp4"><button type="button" class="button wsk-img-pick" data-media="video">انتخاب ویدیو</button></div>';
				break;

			case 'image':
				$thumb = '';
				if ( is_numeric( $value ) && $value ) {
					$thumb = wp_get_attachment_image_url( (int) $value, 'thumbnail' );
				} elseif ( $value ) {
					$thumb = $value;
				}
				echo '<div class="wsk-img-field"><span class="wsk-img-field__prev">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" alt="">' : '' ) . '</span><input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="wsk-img-input" dir="ltr" placeholder="ID یا URL"><button type="button" class="button wsk-img-pick">انتخاب تصویر</button> <button type="button" class="button-link wsk-img-clear">حذف</button></div>';
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
		echo '<div class="wsk-rep" data-depth="' . (int) $depth . '" data-title-field="' . esc_attr( $title ) . '"><div class="wsk-rep__rows">';
		foreach ( array_values( (array) $rows ) as $i => $row ) {
			self::row( $f, (array) $row, $name . '[' . $i . ']', $depth, false );
		}
		echo '</div><template class="wsk-rep__tpl">';
		self::row( $f, WSK_Schema::field_defaults( $f['fields'] ), $name . '[__ROW' . (int) $depth . '__]', $depth, true );
		echo '</template><button type="button" class="button wsk-rep__add">+ افزودن مورد</button></div>';
	}

	private static function row( $f, $row, $name, $depth, $is_tpl ) {
		$title = '';
		if ( ! empty( $f['title_field'] ) && ! $is_tpl && isset( $row[ $f['title_field'] ] ) ) {
			$title = wp_strip_all_tags( (string) $row[ $f['title_field'] ] );
		}
		echo '<details class="wsk-row"><summary><span class="wsk-row__title">' . esc_html( $title ? $title : 'مورد جدید' ) . '</span><span class="wsk-row__tools"><button type="button" class="wsk-row__btn wsk-up" title="بالا">↑</button><button type="button" class="wsk-row__btn wsk-down" title="پایین">↓</button><button type="button" class="wsk-row__btn wsk-del" title="حذف">✕</button></span></summary><div class="wsk-row__body">';
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
		check_admin_referer( 'wsk_save_' . $sid );
		$sections = WSK_Schema::sections();
		if ( ! isset( $sections[ $sid ] ) ) {
			wp_safe_redirect( self::url( 'general' ) );
			exit;
		}
		$reset = ! empty( $_POST['wsk_reset'] );
		$input = ( isset( $_POST['wsk'][ $sid ] ) && is_array( $_POST['wsk'][ $sid ] ) ) ? wp_unslash( $_POST['wsk'][ $sid ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- در save_section بر اساس اسکیما پاک‌سازی می‌شود.
		WSK_Options::save_section( $sid, $input, $reset );
		wp_safe_redirect( add_query_arg( 'wsk_msg', $reset ? 'reset' : 'saved', self::url( $sid ) ) );
		exit;
	}

	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsk_export' );
		$data = get_option( WSK_OPT, array() );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="webisoft-kalane-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'wsk_import' );
		$raw = '';
		if ( ! empty( $_FILES['wsk_file']['tmp_name'] ) && is_uploaded_file( $_FILES['wsk_file']['tmp_name'] ) && $_FILES['wsk_file']['size'] < 2 * MB_IN_BYTES ) { // phpcs:ignore WordPress.Security
			$raw = file_get_contents( $_FILES['wsk_file']['tmp_name'] ); // phpcs:ignore WordPress.Security, WordPress.WP.AlternativeFunctions
		} elseif ( ! empty( $_POST['wsk_json'] ) ) {
			$raw = wp_unslash( $_POST['wsk_json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( 'wsk_msg', 'badjson', self::url( 'tools' ) ) );
			exit;
		}
		WSK_Options::import( $data );
		wp_safe_redirect( add_query_arg( 'wsk_msg', 'imported', self::url( 'tools' ) ) );
		exit;
	}

	/* =====================================================================
	 * آمار
	 * ===================================================================== */
	private static function stats_page() {
		$days = isset( $_GET['days'] ) ? max( 1, min( 365, absint( $_GET['days'] ) ) ) : 30; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<h2 class="wsk-main__h">آمار کلیک و بازدید</h2><p class="wsk-main__desc">شمارش بر اساس هر IP و هر آیتم حداکثر یک‌بار در چند ثانیه (محصول: هر ۳۰ دقیقه) است تا کلیک‌های پشت‌سرهم یا ربات‌های ساده آمار را باد نکنند. آمار دقیق‌تر تجاری را از GA4 بگیرید.</p>';
		echo '<p>';
		foreach ( array( 7, 30, 90 ) as $d ) {
			echo '<a class="button' . ( $d === $days ? ' button-primary' : '' ) . '" href="' . esc_url( add_query_arg( 'days', $d, self::url( 'stats' ) ) ) . '">' . esc_html( $d ) . ' روز</a> ';
		}
		echo '</p>';

		$groups = array( 'banner' => 'بنرها (کلیک)', 'category' => 'دسته‌بندی‌ها (کلیک)', 'story' => 'استوری‌ها (نمایش)', 'product' => 'محصولات (بازدید)' );
		foreach ( $groups as $type => $title ) {
			$rows = WSK_DB::report( $type, $days, 20 );
			echo '<h3>' . esc_html( $title ) . '</h3>';
			if ( ! $rows ) {
				echo '<p class="description">هنوز داده‌ای ثبت نشده است.</p>';
				continue;
			}
			echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>آیتم</th><th style="width:100px">تعداد</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( $r['label'] ? $r['label'] : $r['obj_key'] ) . '</td><td>' . esc_html( number_format_i18n( (int) $r['hits'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/* =====================================================================
	 * ابزارها
	 * ===================================================================== */
	public static function handle_tool() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) );
		}
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
		check_admin_referer( 'wsk_tool_' . $tool );
		$msg = '';
		if ( 'backfill' === $tool ) {
			WSK_DB::backfill_views();
			$msg = 'backfill';
		} elseif ( 'prune' === $tool ) {
			WSK_DB::prune();
			$msg = 'pruned';
		} elseif ( 'statsreset' === $tool ) {
			WSK_DB::reset_stats();
			$msg = 'statsreset';
		}
		wp_safe_redirect( add_query_arg( 'wsk_msg', $msg, self::url( 'tools' ) ) );
		exit;
	}

	private static function tool_form( $tool, $label, $desc, $danger = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 14px">';
		wp_nonce_field( 'wsk_tool_' . $tool );
		echo '<input type="hidden" name="action" value="wsk_tool"><input type="hidden" name="tool" value="' . esc_attr( $tool ) . '"><button class="button' . ( $danger ? ' wsk-reset' : '' ) . '" type="submit">' . esc_html( $label ) . '</button> <span class="description">' . esc_html( $desc ) . '</span></form>';
	}

	private static function tools_page() {
		echo '<h2 class="wsk-main__h">ایمپورت / اکسپورت و ابزارها</h2><p class="wsk-main__desc">برای انتقال تنظیمات و محتوای ماژول‌ها بین سایت‌ها (مثلاً از سایت تست به سایت اصلی). تصاویر با ID ذخیره می‌شوند؛ هنگام انتقال به سایت دیگر، ID ها معتبر نیستند — در آن‌صورت تصاویر را دوباره انتخاب کنید یا از URL استفاده کنید.</p>';
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=wsk_export' ), 'wsk_export' );
		echo '<p><a class="button button-primary" href="' . esc_url( $export ) . '">دانلود فایل تنظیمات (JSON)</a></p>';
		echo '<h3>ایمپورت</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'wsk_import' );
		echo '<input type="hidden" name="action" value="wsk_import"><p><input type="file" name="wsk_file" accept="application/json,.json"></p><p>یا متن JSON را اینجا بچسبانید:</p><textarea name="wsk_json" rows="6" class="large-text" dir="ltr"></textarea><p class="description">هشدار: ایمپورت، بخش‌های موجود در فایل را جایگزین تنظیمات فعلی می‌کند. قبل از آن اکسپورت بگیرید.</p><p><button class="button button-primary" type="submit">ایمپورت</button></p></form>';

		echo '<h3>دیتابیس</h3>';
		self::tool_form( 'backfill', 'ساخت شمارنده‌ی بازدید محصولات قدیمی', 'برای مرتب‌سازی «پربازدیدترین»؛ محصولاتی که هنوز متای بازدید ندارند با صفر ثبت می‌شوند (بی‌خطر و تکرارپذیر).' );
		self::tool_form( 'prune', 'پاک‌سازی آمار قدیمی', 'حذف ردیف‌های قدیمی‌تر از «مدت نگهداری آمار».' );
		self::tool_form( 'statsreset', 'پاک کردن همه‌ی آمار', 'غیرقابل بازگشت. شمارنده‌ی بازدید محصولات (برای مرتب‌سازی) دست‌نخورده می‌ماند.', true );
	}

	/* =====================================================================
	 * راهنما
	 * ===================================================================== */
	private static function help_page() {
		echo '<h2 class="wsk-main__h">راهنما و شورت‌کدها</h2>';
		echo '<h3>شورت‌کدها</h3><table class="widefat striped wsk-help-table"><thead><tr><th>ماژول</th><th>شورت‌کد</th></tr></thead><tbody>';
		foreach ( WSK_Schema::sections() as $sid => $sec ) {
			if ( empty( $sec['shortcode'] ) ) {
				continue;
			}
			echo '<tr><td><a href="' . esc_url( self::url( $sid ) ) . '">' . esc_html( $sec['label'] ) . '</a></td><td><code dir="ltr">' . esc_html( $sec['shortcode'] ) . '</code></td></tr>';
		}
		echo '<tr><td>ویجت ترکیبی (سازگار با نسخه‌ی قبلی)</td><td><code dir="ltr">[kalane_custom_widget]</code> — استوری + بنر + دسته‌بندی</td></tr></tbody></table>';

		echo '<h3>مهاجرت از Code Snippets</h3><ol><li>افزونه را فعال کنید و در هر بخش، داده‌ها را از پنل جدید وارد کنید (داده‌های قدیمی در گزینه‌های kw_* و kalane_* ماندند و پاک نشده‌اند).</li><li>شورت‌کدهای قبلی با همان نام کار می‌کنند: <code>kalane_custom_widget</code>، <code>kalane_banner</code>، <code>kalane_categories</code>، <code>kalane_divider</code>، <code>kalane_product_slider</code>، <code>kalane_blog_slider</code>.</li><li>بعد از اطمینان، همه‌ی اسنیپت‌های قدیمی (کالانه، digikala sorting، force login، CSS ها، menu heading correction) را در WPCode غیرفعال کنید تا شورت‌کدها دوبار ثبت نشوند.</li></ol>';

		echo '<h3>نکات</h3><ul class="ul-disc"><li>اسلایدرها به Swiper نیاز ندارند؛ CSS scroll-snap و یک اسکریپت کوچک استفاده می‌شود.</li><li>مرتب‌سازی «پربازدیدترین» از شمارنده‌ی داخلی افزونه استفاده می‌کند؛ بعد از فعال‌سازی یک‌بار «ساخت شمارنده‌ی بازدید» را بزنید.</li><li>اگر بازدیدها ثبت نمی‌شود، کش صفحه مشکلی ندارد (ثبت با REST انجام می‌شود)؛ مطمئن شوید مسیر <code>/wp-json/wsk/v1/hit</code> توسط فایروال/افزونه امنیتی بسته نشده باشد.</li><li>اگر فرم تنظیمات بزرگ ذخیره نمی‌شود، <code>max_input_vars</code> در PHP را بالا ببرید.</li></ul>';
	}
}
