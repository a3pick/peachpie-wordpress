<?php
defined( 'ABSPATH' ) || exit;

/**
 * اسکیمای تنظیمات: تمام بخش‌ها، فیلدها، مقادیر پیش‌فرض.
 * همین اسکیما هم برای ساخت فرم ادمین، هم پاک‌سازی ورودی و هم مقادیر پیش‌فرض استفاده می‌شود.
 */
class WSL_Schema {

	private static $sections = null;

	/* ---------- سازنده‌های فیلد ---------- */
	public static function f( $type, $id, $label, $default = '', $extra = array() ) {
		return array_merge(
			array(
				'type'    => $type,
				'id'      => $id,
				'label'   => $label,
				'default' => $default,
			),
			$extra
		);
	}
	private static function t( $id, $l, $d = '', $x = array() ) { return self::f( 'text', $id, $l, $d, $x ); }
	private static function ta( $id, $l, $d = '', $x = array() ) { return self::f( 'textarea', $id, $l, $d, $x ); }
	private static function h( $id, $l, $d = '', $x = array() ) { return self::f( 'html', $id, $l, $d, $x ); }
	private static function u( $id, $l, $d = '', $x = array() ) { return self::f( 'url', $id, $l, $d, $x ); }
	private static function img( $id, $l, $d = '', $x = array() ) { return self::f( 'image', $id, $l, $d, $x ); }
	private static function ic( $id, $l, $d = '', $x = array() ) { return self::f( 'icon', $id, $l, $d, $x ); }
	private static function n( $id, $l, $d = 0, $x = array() ) { return self::f( 'number', $id, $l, $d, $x ); }
	private static function s( $id, $l, $opts, $d = '', $x = array() ) { return self::f( 'select', $id, $l, $d, array_merge( array( 'options' => $opts ), $x ) ); }
	private static function tg( $id, $l, $d = 1, $x = array() ) { return self::f( 'toggle', $id, $l, $d, $x ); }
	private static function c( $id, $l, $d = '', $x = array() ) { return self::f( 'color', $id, $l, $d, $x ); }
	private static function lines( $id, $l, $d = '', $x = array() ) { return self::f( 'lines', $id, $l, $d, $x ); }
	private static function rep( $id, $l, $fields, $default = array(), $x = array() ) {
		return self::f( 'repeater', $id, $l, $default, array_merge( array( 'fields' => $fields ), $x ) );
	}
	private static function enabled() { return self::tg( 'enabled', 'فعال بودن این ماژول', 1, array( 'desc' => 'با غیرفعال کردن، شورت‌کد چیزی نمایش نمی‌دهد.' ) ); }

	private static function head_fields( $badge = '', $title = '', $sub = '' ) {
		return array(
			self::t( 'badge', 'متن بالای عنوان (Badge / Eyebrow)', $badge ),
			self::t( 'title', 'عنوان', $title ),
			self::ta( 'subtitle', 'زیرعنوان', $sub, array( 'rows' => 2 ) ),
		);
	}

	private static function color_fields( $d ) {
		$labels = array(
			'primary'     => 'رنگ اصلی برند (آیکون‌ها و لینک‌ها)',
			'btn_a'       => 'دکمه‌ها — شروع گرادیان',
			'btn_b'       => 'دکمه‌ها — پایان گرادیان / هاور',
			'on_btn'      => 'متن روی دکمه‌ها',
			'accent'      => 'رنگ تأکیدی (طلایی/نقطه‌ها)',
			'accent_text' => 'رنگ تأکیدی برای متن کوچک (خوانایی بهتر)',
			'ink'         => 'رنگ عنوان‌ها',
			'text'        => 'رنگ متن اصلی',
			'muted'       => 'رنگ متن کم‌رنگ',
			'surface'     => 'پس‌زمینه کارت‌ها',
			'surface_alt' => 'پس‌زمینه ثانویه (باکس‌ها)',
			'border'      => 'رنگ خطوط و حاشیه',
			'page_bg'     => 'پس‌زمینه صفحه (خالی = تغییر نکند)',
		);
		$out = array();
		foreach ( $labels as $id => $label ) {
			$out[] = self::c( $id, $label, isset( $d[ $id ] ) ? $d[ $id ] : '' );
		}
		return $out;
	}

	/* ---------- بخش‌ها ---------- */
	public static function groups() {
		return array(
			'base'    => 'تنظیمات پایه',
			'modules' => 'ماژول‌های صفحه',
			'content' => 'مقاله و فرم',
			'system'  => 'سیستم',
		);
	}

	public static function sections() {
		if ( null !== self::$sections ) {
			return self::$sections;
		}
		$s = array();

		/* ===== پایه ===== */
		$s['general'] = array(
			'group' => 'base', 'label' => 'اطلاعات دفتر', 'icon' => 'dashicons-building',
			'desc'  => 'این اطلاعات در تمام ماژول‌ها (هیرو، تماس، دکمه شناور، اسکیما و ...) استفاده می‌شود. فیلدهای خالی نمایش داده نمی‌شوند.',
			'fields' => array(
				self::t( 'firm_name', 'نام دفتر / وکیل', 'دفتر حقوقی نمونه' ),
				self::t( 'firm_title', 'عنوان حرفه‌ای', 'وکیل پایه یک دادگستری' ),
				self::img( 'logo', 'لوگو — حالت روشن (اختیاری؛ در غیر این صورت لوگوی سایت)' ),
				self::img( 'logo_dark', 'لوگو — حالت تاریک', '', array( 'desc' => 'مثلاً نسخه‌ی سفید یا روشن‌تر لوگو (ترجیحاً PNG شفاف / SVG / WebP).' ) ),
				self::tg( 'logo_swap', 'جایگزینی خودکار لوگوی قالب/ویجت لوگوی المنتور در حالت تاریک', 1, array( 'desc' => 'در حالت تاریک تصویر ویجت «Site Logo» المنتور و لوگوی قالب با لوگوی تاریک عوض می‌شود. شورت‌کد: [ws_logo height=\"48\" link=\"1\"]' ) ),
				self::t( 'phone', 'شماره تماس (مثلاً 09120000000)', '' ),
				self::t( 'email', 'ایمیل عمومی', '' ),
				self::t( 'whatsapp', 'واتساپ (شماره با کد کشور، مثلاً 989120000000)', '' ),
				self::t( 'telegram', 'تلگرام (نام کاربری بدون @)', '' ),
				self::u( 'instagram', 'اینستاگرام (آدرس کامل)', '' ),
				self::ta( 'address', 'آدرس دفتر', '', array( 'rows' => 2 ) ),
				self::t( 'city', 'شهر (برای اسکیما)', '' ),
				self::t( 'hours', 'ساعات کاری (متن آزاد)', '' ),
				self::u( 'map_embed', 'آدرس Embed نقشه (src از iframe گوگل‌مپ/نشان/بلد)', '', array( 'desc' => 'فقط آدرس https. در صفحه تماس نمایش داده می‌شود.' ) ),
			),
		);

		$s['appearance'] = array(
			'group' => 'base', 'label' => 'رنگ و فونت (حالت روشن)', 'icon' => 'dashicons-art',
			'desc'  => 'رنگ‌ها و فونت واحد برای همه ماژول‌ها. رنگ‌های حالت تاریک در بخش «حالت روشن/تاریک» تنظیم می‌شود.',
			'fields' => array_merge(
				array(
					self::s( 'font_mode', 'منبع فونت', array(
						'inherit'       => 'ارث‌بری از قالب/المنتور (بدون بارگذاری فونت)',
						'vazirmatn_cdn' => 'وزیرمتن (بارگذاری از jsDelivr)',
						'custom_file'   => 'فایل فونت اختصاصی (آپلود در کتابخانه رسانه)',
						'custom_name'   => 'فقط نام فونت (فونت از قبل در سایت لود شده)',
					), 'vazirmatn_cdn', array( 'desc' => 'برای بهترین سرعت و حریم خصوصی، فونت را روی هاست خودتان بارگذاری کنید (گزینه فایل اختصاصی).' ) ),
					self::t( 'font_family', 'نام خانواده فونت (برای فایل اختصاصی/نام فونت)', 'Vazirmatn' ),
					self::u( 'font_file', 'آدرس فایل فونت (woff2/woff/ttf/otf)', '' ),
					self::t( 'font_latin', 'فونت متن‌های لاتین (Eyebrow)', "Inter, system-ui, -apple-system, 'Segoe UI', sans-serif" ),
					self::s( 'heading_weight', 'ضخامت عنوان‌ها', array( '700' => '700', '800' => '800', '900' => '900' ), '900' ),
					self::n( 'radius', 'گردی گوشه کارت‌ها (px)', 24, array( 'min' => 0, 'max' => 48 ) ),
					self::n( 'container_max', 'حداکثر عرض ماژول‌های بزرگ (px)', 1200, array( 'min' => 600, 'max' => 2000 ) ),
					self::s( 'direction', 'جهت متن ماژول‌ها', array( 'auto' => 'مطابق جهت سایت', 'rtl' => 'همیشه راست‌به‌چپ', 'ltr' => 'همیشه چپ‌به‌راست' ), 'auto' ),
				),
				self::color_fields( array(
					'primary' => '#2a6e6d', 'btn_a' => '#2a6e6d', 'btn_b' => '#1f5352', 'on_btn' => '#ffffff', 'accent' => '#cb9833',
					'accent_text' => '#94691a', 'ink' => '#0f172a', 'text' => '#475569', 'muted' => '#94a3b8',
					'surface' => '#ffffff', 'surface_alt' => '#f8fafc', 'border' => '#e2e8f0', 'page_bg' => '',
				) )
			),
		);

		$s['darkmode'] = array(
			'group' => 'base', 'label' => 'حالت روشن / تاریک', 'icon' => 'dashicons-lightbulb',
			'desc'  => 'کاربر می‌تواند با دکمه تغییر حالت، بین روشن و تاریک جابه‌جا شود؛ انتخاب او در مرورگرش ذخیره می‌شود.',
			'fields' => array_merge(
				array(
					self::tg( 'enabled', 'فعال‌سازی حالت تاریک', 1 ),
					self::s( 'default_mode', 'حالت پیش‌فرض بازدیدکننده جدید', array( 'light' => 'روشن', 'dark' => 'تاریک', 'auto' => 'مطابق تنظیم سیستم‌عامل کاربر' ), 'light' ),
					self::s( 'toggle_position', 'دکمه شناور تغییر حالت', array( 'none' => 'نمایش نده (فقط شورت‌کد)', 'bottom-start' => 'پایین — ابتدای صفحه (راست در RTL)', 'bottom-end' => 'پایین — انتهای صفحه (چپ در RTL)' ), 'bottom-end' ),
					self::tg( 'global', 'اعمال تاریک‌سازی روی کل سایت (بدنه، هدر/فوتر Hello، فرم‌ها، مقالات)', 1, array( 'desc' => 'ظاهر المان‌هایی که در المنتور رنگ پس‌زمینه دستی دارند، با گزینه‌های پایین هماهنگ می‌شود.' ) ),
					self::s( 'el_primary', 'رنگ سراسری Primary المنتور ← ', self::token_options(), '' ),
					self::s( 'el_secondary', 'رنگ سراسری Secondary المنتور ← ', self::token_options(), '' ),
					self::s( 'el_text', 'رنگ سراسری Text المنتور ← ', self::token_options(), '' ),
					self::s( 'el_accent', 'رنگ سراسری Accent المنتور ← ', self::token_options(), '' ),
					self::tg( 'auto_adapt', 'تطبیق هوشمند خودکار رنگ‌های المنتور در حالت تاریک', 1, array( 'desc' => 'در حالت تاریک، پس‌زمینه‌های روشن و متن‌های تیره‌ای که در المنتور دستی رنگ شده‌اند را شناسایی و هماهنگ می‌کند (هر ویجت/کانتینر). برای معاف‌کردن یک بخش، کلاس ws-keep-light را به آن بدهید. تصاویر و گرادیان‌ها تغییر نمی‌کنند.' ) ),
					self::tg( 'force_text', 'اجبار رنگ عنوان‌ها و متن ویجت‌های المنتور در حالت تاریک (برای وقتی رنگ دستی دارند)', 1, array( 'desc' => 'عنوان، لیست آیکون‌دار و ویرایشگر متن المنتور (مثلاً در فوتر) را با !important هم‌رنگ حالت تاریک می‌کند. دکمه‌ها و ماژول‌های افزونه تغییر نمی‌کنند.' ) ),
					self::ta( 'surface_selectors', 'سلکتورهایی که در حالت تاریک «پس‌زمینه کارت» بگیرند (هر خط یک سلکتور)', '', array( 'san' => 'selectors', 'rows' => 3, 'desc' => 'مثال: .my-box یا .elementor-element-abc123 — یا کلاس ws-surface را در فیلد CSS Classes المنتور بدهید.' ) ),
					self::ta( 'surface_alt_selectors', 'سلکتورهایی که «پس‌زمینه ثانویه» بگیرند (هر خط یک سلکتور)', '', array( 'san' => 'selectors', 'rows' => 3 ) ),
				),
				self::color_fields( array(
					'primary' => '#5ec2bf', 'btn_a' => '#2f7f7e', 'btn_b' => '#1f5352', 'on_btn' => '#ffffff', 'accent' => '#e0b04d',
					'accent_text' => '#e5b95c', 'ink' => '#f1f5f9', 'text' => '#cbd5e1', 'muted' => '#94a3b8',
					'surface' => '#1e293b', 'surface_alt' => '#172033', 'border' => '#334155', 'page_bg' => '#0b1220',
				) )
			),
		);

		$s['floating'] = array(
			'group' => 'base', 'label' => 'دکمه‌های تماس شناور', 'icon' => 'dashicons-phone',
			'desc'  => 'دکمه‌های تماس/واتساپ/تلگرام در گوشه صفحه. مقدارها از «اطلاعات دفتر» خوانده می‌شود.',
			'fields' => array(
				self::tg( 'enabled', 'نمایش در کل سایت', 0 ),
				self::s( 'position', 'محل نمایش', array( 'bottom-start' => 'پایین — ابتدای صفحه', 'bottom-end' => 'پایین — انتهای صفحه' ), 'bottom-start' ),
				self::tg( 'show_phone', 'دکمه تماس', 1 ),
				self::tg( 'show_whatsapp', 'دکمه واتساپ', 1 ),
				self::tg( 'show_telegram', 'دکمه تلگرام', 1 ),
				self::t( 'cta_label', 'دکمه درخواست مشاوره — متن (خالی = بدون دکمه)', '' ),
				self::u( 'cta_url', 'دکمه درخواست مشاوره — لینک', '#consultation' ),
				self::tg( 'mobile_only', 'فقط در موبایل/تبلت', 0 ),
			),
		);

		$s['utilities'] = array(
			'group' => 'base', 'label' => 'ابزارهای المنتور و CSS', 'icon' => 'dashicons-admin-tools',
			'desc'  => 'کلاس‌های کمکی برای المنتور، جلوه‌ها و CSS اختصاصی.',
			'fields' => array(
				self::tg( 'util_classes', 'کلاس‌های کمکی (ws-glass, ws-surface, ws-hover-border, border-hover-1..7, btn-emergency, glow-orb ...)', 1 ),
				self::tg( 'logo_shimmer', 'انیمیشن درخشش و بزرگ‌شدن لوگوی سایت (ویجت لوگوی المنتور)', 0 ),
				self::tg( 'load_fa', 'بارگذاری Font Awesome 6 (فقط اگر خودتان از کلاس‌های fa-* استفاده می‌کنید)', 0, array( 'desc' => 'آیکون‌های داخلی افزونه به Font Awesome نیازی ندارند.' ) ),
				self::ta( 'custom_css', 'CSS اختصاصی (روی همه صفحات)', '', array( 'san' => 'css', 'rows' => 8, 'desc' => 'برای استایل حالت تاریک از سلکتور html[data-wsl-theme="dark"] .your-class استفاده کنید.', 'class' => 'code' ) ),
			),
		);

		/* ===== ماژول‌ها ===== */
		$s['hero_photo'] = array(
			'group' => 'modules', 'label' => 'هیرو با عکس', 'icon' => 'dashicons-id', 'shortcode' => '[legal_hero_photo]',
			'desc' => 'معرفی وکیل با عنوان، دکمه‌ها و عکس. در صورت خالی بودن شماره تلفن، دکمه تماس نشان داده نمی‌شود.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب بالای عنوان', 'وکیل پایه یک دادگستری' ),
				self::t( 'title', 'عنوان اصلی', 'تخصص، صداقت و قاطعیت در دفاع از حقوق شما' ),
				self::t( 'highlight', 'کلمه/عبارت برجسته داخل عنوان', 'قاطعیت' ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1', array( 'desc' => 'در هر صفحه فقط یک H1 داشته باشید؛ اگر عنوان صفحه قبلاً H1 است، H2 را انتخاب کنید.' ) ),
				self::ta( 'desc', 'توضیح', 'با سال‌ها سابقه در پیگیری دعاوی تخصصی ملکی، خانواده، کیفری و اسناد تجاری. از ارزیابی مستندات تا پیروزی نهایی در مراجع قضایی، در کنار شما هستیم.', array( 'rows' => 3 ) ),
				self::t( 'btn1_text', 'دکمه اصلی — متن', 'درخواست بررسی پرونده' ),
				self::u( 'btn1_url', 'دکمه اصلی — لینک', '#consultation' ),
				self::t( 'btn2_text', 'دکمه دوم — متن (خالی = شماره تماس دفتر)', '' ),
				self::u( 'btn2_url', 'دکمه دوم — لینک (خالی = تماس)', '' ),
				self::img( 'image', 'عکس (ID کتابخانه رسانه یا آدرس)', '' ),
				self::t( 'image_alt', 'متن جایگزین عکس (alt)', '' ),
				self::tg( 'show_float', 'نمایش کارت شناور روی عکس', 1 ),
				self::ic( 'float_icon', 'آیکون کارت شناور', 'award' ),
				self::t( 'float_title', 'کارت شناور — عنوان', 'عضو رسمی کانون وکلا' ),
				self::t( 'float_sub', 'کارت شناور — زیرعنوان', 'تاییدیه صلاحیت تخصصی دادرسی' ),
			),
		);

		$s['search_hero'] = array(
			'group' => 'modules', 'label' => 'هیرو جستجو', 'icon' => 'dashicons-search', 'shortcode' => '[legal_search_hero]',
			'desc' => 'کادر جستجوی سراسری وردپرس با موضوعات محبوب. (بدون Tailwind/CDN)',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'همراه شما تا احقاق حق' ),
				self::t( 'title', 'عنوان', 'قبل از هر اقدام حقوقی، اول آگاه باشید.' ),
				self::t( 'highlight', 'عبارت برجسته', 'اول آگاه باشید.' ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h2' ),
				self::ta( 'subtitle', 'زیرعنوان', 'مسیر پرونده خود را با اطلاعات دقیق آغاز کنید. جستجو کنید، راهنمای مربوطه را بخوانید و در صورت نیاز بررسی تخصصی پرونده خود را به ما بسپارید.', array( 'rows' => 2 ) ),
				self::t( 'placeholder', 'متن داخل کادر جستجو', 'موضوع یا مشکل حقوقی خودتان را جستجو کنید... (مثلاً: تخلیه ملک)' ),
				self::t( 'button', 'متن دکمه', 'جستجو در مرجع' ),
				self::t( 'tags_label', 'عنوان موضوعات محبوب', 'موضوعات محبوب:' ),
				self::rep( 'tags', 'موضوعات محبوب', array(
					self::t( 'label', 'عنوان' ),
					self::t( 'q', 'عبارت جستجو (خالی = عنوان)' ),
					self::u( 'url', 'یا لینک مستقیم' ),
				), array(
					array( 'label' => 'چک برگشتی' ), array( 'label' => 'تخلیه ملک' ), array( 'label' => 'طلاق توافقی' ), array( 'label' => 'مطالبه مهریه' ),
				), array( 'title_field' => 'label' ) ),
			),
		);

		$s['domains'] = array(
			'group' => 'modules', 'label' => 'حوزه‌های حقوقی (کارت‌ها)', 'icon' => 'dashicons-screenoptions', 'shortcode' => '[legal_domains]',
			'desc' => 'گرید کارت‌های حوزه تخصصی. اگر لینک خالی باشد، کارت به نتیجه جستجوی سایت می‌رود.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'حوزه‌های حقوقی' ),
				self::t( 'view_all_text', 'متن «مشاهده همه»', 'مشاهده همه' ),
				self::u( 'view_all_url', 'لینک «مشاهده همه» (خالی = مخفی)', '' ),
				self::s( 'cols', 'تعداد ستون در دسکتاپ', array( '3' => '۳', '4' => '۴', '5' => '۵' ), '4' ),
				self::rep( 'items', 'حوزه‌ها', array(
					self::ic( 'icon', 'آیکون', 'scale' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ),
					self::t( 'q', 'عبارت جستجو (خالی = عنوان)' ), self::u( 'url', 'یا لینک مستقیم' ),
				), self::default_domains(), array( 'title_field' => 'title' ) ),
			),
		);

		$s['sticky_cards'] = array(
			'group' => 'modules', 'label' => 'کارت‌های چسبان (Sticky)', 'icon' => 'dashicons-images-alt2', 'shortcode' => '[legal_sticky_cards]',
			'desc' => 'کارت‌ها هنگام اسکرول روی هم می‌نشینند. اگر ظرف‌های والدِ المنتور overflow:hidden داشته باشند sticky کار نمی‌کند.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'حوزه‌های تخصصی', 'حوزه‌های تخصصی فعالیت ما', 'تمرکز بر تخصص، کلید پیروزی در پرونده‌های پیچیده است. مسیر درست را با آگاهی انتخاب کنید.' ),
				array(
					self::n( 'sticky_top', 'فاصله از بالا هنگام چسبیدن (px) — ارتفاع هدر ثابت', 90, array( 'min' => 0, 'max' => 400 ) ),
					self::rep( 'cards', 'کارت‌ها', array(
						self::t( 'en_title', 'عنوان لاتین' ), self::t( 'fa_title', 'عنوان فارسی' ),
						self::h( 'desc', 'توضیح (تگ strong مجاز است)', '', array( 'rows' => 4 ) ),
						self::ic( 'icon', 'آیکون', 'scale' ),
						self::s( 'style', 'سبک کارت', array( 'glass' => 'شیشه‌ای', 'solid' => 'ساده (آیکون تیره)', 'gradient' => 'گرادیان طلایی' ), 'glass' ),
					), self::default_sticky(), array( 'title_field' => 'fa_title' ) ),
				)
			),
		);

		$s['problem_solver'] = array(
			'group' => 'modules', 'label' => 'مشکل شما چیست؟ (تب‌ها)', 'icon' => 'dashicons-index-card', 'shortcode' => '[legal_problem_solver]',
			'desc' => 'تب‌های دسته‌بندی مشکل با کارت‌های لینک‌دار. هر کارت به جستجو یا لینک دلخواه می‌رود.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'مشکل شما چیه؟' ),
				self::ta( 'subtitle', 'زیرعنوان', 'دسته‌بندی کلی مشکل خود را انتخاب کنید تا مسیرهای قانونی و مقالات مرتبط را به شما نشان دهیم.', array( 'rows' => 2 ) ),
				self::t( 'footer_text', 'متن پایین', 'با انتخاب هر مسیر، مقالات و راهکارهای قانونی آن را مشاهده می‌کنید.' ),
				self::t( 'footer_link_text', 'متن لینک پایین', 'مشاهده تمام حوزه‌ها' ),
				self::u( 'footer_link_url', 'لینک پایین (خالی = مخفی)', '' ),
				self::rep( 'tabs', 'تب‌ها', array(
					self::t( 'label', 'عنوان تب' ),
					self::rep( 'items', 'کارت‌های این تب', array(
						self::ic( 'icon', 'آیکون', 'scale' ), self::t( 'title', 'عنوان' ), self::t( 'q', 'عبارت جستجو (خالی = عنوان)' ), self::u( 'url', 'یا لینک مستقیم' ),
					), array(), array( 'title_field' => 'title' ) ),
				), self::default_problem_tabs(), array( 'title_field' => 'label' ) ),
			),
		);

		$s['process'] = array(
			'group' => 'modules', 'label' => 'فرآیند پیگیری پرونده', 'icon' => 'dashicons-networking', 'shortcode' => '[legal_process]',
			'desc' => 'مراحل کار به‌صورت کارت‌های شماره‌دار (شماره‌ها خودکار ساخته می‌شود).',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Legal Workflow & Strategy', 'فرآیند شفاف و تخصصی پیگیری پرونده', 'از ارزیابی دقیق مستندات تا دفاع قاطعانه در مراجع قضایی؛ در تمامی مراحل دادرسی در کنار شما هستیم.' ),
				array(
					self::t( 'step_label', 'برچسب شماره مرحله', 'STEP' ),
					self::rep( 'steps', 'مراحل', array( self::ic( 'icon', 'آیکون', 'clipboard' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 3 ) ) ), self::default_steps(), array( 'title_field' => 'title' ) ),
				)
			),
		);

		$s['case_studies'] = array(
			'group' => 'modules', 'label' => 'تجربه‌های موفق (اسلایدر)', 'icon' => 'dashicons-slides', 'shortcode' => '[legal_case_studies]',
			'desc' => 'اسلایدر پرونده‌های موفق. مراقب باشید هویت موکل افشا نشود و ادعاها قابل اثبات باشند.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'داستان‌های واقعی' ),
				self::t( 'title', 'عنوان', 'تجربه‌های موفق ما در حل پرونده‌ها' ),
				self::ta( 'desc', 'توضیح', 'به جای رزومه‌های خشک، بگذارید به شما بگوییم چگونه گره از کار موکلین خود باز کرده‌ایم. (بدون ذکر نام برای حفظ حریم خصوصی)', array( 'rows' => 3 ) ),
				self::t( 'domain_label', 'پیشوند حوزه', 'حوزه:' ),
				self::tg( 'autoplay', 'پخش خودکار', 0 ),
				self::n( 'interval', 'فاصله پخش خودکار (ثانیه)', 7, array( 'min' => 3, 'max' => 30 ) ),
				self::rep( 'items', 'پرونده‌ها', array(
					self::ic( 'icon', 'آیکون', 'gavel' ), self::t( 'title', 'عنوان' ), self::t( 'domain', 'حوزه' ), self::h( 'body', 'متن (strong مجاز)', '', array( 'rows' => 4 ) ),
				), self::default_cases(), array( 'title_field' => 'title' ) ),
			),
		);

		$s['certificates'] = array(
			'group' => 'modules', 'label' => 'پروانه‌ها و افتخارات (نوار متحرک)', 'icon' => 'dashicons-awards', 'shortcode' => '[legal_certificates]',
			'desc' => 'نوار متحرک گواهی‌ها. فقط مدارکی را درج کنید که واقعاً دارید.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Certificates & Honors', 'پروانه‌ها، افتخارات و تاییدیه‌های رسمی', '' ),
				array(
					self::n( 'speed', 'مدت یک دور حرکت (ثانیه؛ بیشتر = کندتر)', 35, array( 'min' => 10, 'max' => 180 ) ),
					self::rep( 'items', 'گواهی‌ها', array(
						self::ic( 'icon', 'آیکون', 'award' ), self::t( 'badge', 'برچسب لاتین' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 3 ) ),
					), self::default_certs(), array( 'title_field' => 'title' ) ),
				)
			),
		);

		$s['faq'] = array(
			'group' => 'modules', 'label' => 'سوالات پرتکرار (FAQ)', 'icon' => 'dashicons-editor-help', 'shortcode' => '[legal_faq]',
			'desc' => 'آکاردئون دسترس‌پذیر با پشتیبانی از اسکیمای FAQPage.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'سوالات پرتکرار حقوقی (FAQ)' ),
				self::ta( 'subtitle', 'زیرعنوان', 'پاسخ‌های کوتاه و کاربردی به متداول‌ترین سوالات موکلین پیش از شروع پروسه قانونی', array( 'rows' => 2 ) ),
				self::tg( 'single_open', 'فقط یک پاسخ هم‌زمان باز باشد', 1 ),
				self::tg( 'first_open', 'اولین سوال در ابتدا باز باشد', 0 ),
				self::tg( 'schema', 'خروجی اسکیمای FAQPage (JSON-LD)', 1, array( 'desc' => 'گوگل نمایش rich result سوالات پرتکرار را عمدتاً به سایت‌های دولتی/سلامت محدود کرده است؛ اسکیما بی‌ضرر است ولی تضمینی برای نمایش نیست.' ) ),
				self::rep( 'items', 'سوال‌ها', array( self::t( 'q', 'سوال' ), self::h( 'a', 'پاسخ', '', array( 'rows' => 4 ) ) ), self::default_faq(), array( 'title_field' => 'q' ) ),
			),
		);

		$s['stats'] = array(
			'group' => 'modules', 'label' => 'آمار و اعداد (جدید)', 'icon' => 'dashicons-chart-bar', 'shortcode' => '[legal_stats]',
			'desc' => 'نوار آمار با شمارنده متحرک. فقط اعداد واقعی وارد کنید.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان (اختیاری)', '' ),
				self::tg( 'animate', 'شمارش متحرک هنگام دیده‌شدن', 1 ),
				self::rep( 'items', 'آمارها', array(
					self::ic( 'icon', 'آیکون', 'chart' ), self::n( 'value', 'عدد', 0, array( 'min' => 0, 'max' => 99999999 ) ), self::t( 'suffix', 'پسوند (مثل + یا ٪)' ), self::t( 'label', 'برچسب' ),
				), array(
					array( 'icon' => 'calendar', 'value' => 10, 'suffix' => '+', 'label' => 'سال تجربه وکالت' ),
					array( 'icon' => 'gavel', 'value' => 500, 'suffix' => '+', 'label' => 'پرونده بررسی‌شده' ),
					array( 'icon' => 'users', 'value' => 300, 'suffix' => '+', 'label' => 'موکل راضی' ),
				), array( 'title_field' => 'label' ) ),
			),
		);

		$s['testimonials'] = array(
			'group' => 'modules', 'label' => 'نظرات موکلین (جدید)', 'icon' => 'dashicons-format-quote', 'shortcode' => '[legal_testimonials]',
			'desc' => 'فقط نظرات واقعی و با رضایت صاحبان آن‌ها را درج کنید. (بدون اسکیمای Review؛ نظرات داخلی خودفروشی محسوب می‌شود.)',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Client Reviews', 'نظر موکلین ما', '' ),
				array(
					self::rep( 'items', 'نظرات', array(
						self::t( 'name', 'نام (می‌تواند ابتدای نام باشد)' ), self::t( 'role', 'عنوان/نوع پرونده' ), self::ta( 'text', 'متن نظر', '', array( 'rows' => 3 ) ),
						self::n( 'rating', 'امتیاز (۰ تا ۵؛ ۰ = مخفی)', 5, array( 'min' => 0, 'max' => 5 ) ), self::img( 'avatar', 'تصویر (اختیاری)' ),
					), array(), array( 'title_field' => 'name' ) ),
				)
			),
		);

		$s['team'] = array(
			'group' => 'modules', 'label' => 'تیم / وکلا (جدید)', 'icon' => 'dashicons-groups', 'shortcode' => '[legal_team]',
			'desc' => 'کارت معرفی وکلا و همکاران.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Our Team', 'تیم حقوقی ما', '' ),
				array(
					self::rep( 'members', 'اعضا', array(
						self::img( 'photo', 'عکس' ), self::t( 'name', 'نام' ), self::t( 'role', 'سمت / تخصص' ), self::ta( 'bio', 'معرفی کوتاه', '', array( 'rows' => 3 ) ), self::u( 'url', 'لینک پروفایل (اختیاری)' ),
					), array(), array( 'title_field' => 'name' ) ),
				)
			),
		);

		$s['cta'] = array(
			'group' => 'modules', 'label' => 'بنر دعوت به اقدام (جدید)', 'icon' => 'dashicons-megaphone', 'shortcode' => '[legal_cta]',
			'desc' => 'بنر تماس/مشاوره برای انتهای صفحه یا بین بخش‌ها.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'برای بررسی پرونده خود همین امروز اقدام کنید' ),
				self::ta( 'text', 'متن', 'اطلاعات اولیه را برای ما ارسال کنید تا پس از ارزیابی مدارک با شما تماس بگیریم.', array( 'rows' => 2 ) ),
				self::t( 'btn1_text', 'دکمه اصلی — متن', 'درخواست مشاوره' ),
				self::u( 'btn1_url', 'دکمه اصلی — لینک', '#consultation' ),
				self::tg( 'show_phone', 'دکمه تماس (شماره دفتر)', 1 ),
				self::s( 'style', 'سبک', array( 'gradient' => 'گرادیان برند', 'dark' => 'تیره', 'light' => 'روشن' ), 'gradient' ),
			),
		);

		$s['about_page'] = array(
			'group' => 'modules', 'label' => 'صفحه درباره ما', 'icon' => 'dashicons-info-outline', 'shortcode' => '[legal_about_page]',
			'desc' => 'هیرو، ویژگی‌ها، ماموریت و چشم‌انداز.',
			'fields' => array(
				self::enabled(),
				self::t( 'eyebrow', 'برچسب بالا', 'Professional Advocacy & Trust' ),
				self::t( 'title', 'عنوان', 'تلفیق دانش تخصصی حقوق با صداقت و قاطعیت' ),
				self::t( 'highlight', 'عبارت برجسته', 'صداقت و قاطعیت' ),
				self::ta( 'desc', 'توضیح', 'دفتر حقوقی ما با سال‌ها تجربه مستمر در وکالت دعاوی تخصصی (ملکی، خانواده، کیفری و تجاری)، باوری عمیق دارد که پیشگیری از بروز دعوا و اتخاذ استراتژی درست در دادگاه، کلید اصلی حفظ آرامش و منافع موکلان است.', array( 'rows' => 4 ) ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1', array( 'desc' => 'اگر عنوان صفحه توسط قالب/المنتور به‌صورت H1 نمایش داده می‌شود، H2 را انتخاب کنید.' ) ),
				self::rep( 'features', 'ویژگی‌ها', array( self::ic( 'icon', 'آیکون', 'scale' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 3 ) ) ), self::default_features(), array( 'title_field' => 'title' ) ),
				self::ic( 'mission_icon', 'ماموریت — آیکون', 'target' ),
				self::t( 'mission_title', 'ماموریت — عنوان', 'ماموریت ما' ),
				self::ta( 'mission_text', 'ماموریت — متن', 'توانمندسازی شهروندان و مدیران کسب‌وکارها با آگاهی حقوقی، ارائه مشاوره‌های صادقانه پیش از اقدام قضایی، و دفاع قاطعانه از حقوق موکلین در تمامی مراجع قضایی و شبه‌قضایی کشور.', array( 'rows' => 3 ) ),
				self::ic( 'vision_icon', 'چشم‌انداز — آیکون', 'bulb' ),
				self::t( 'vision_title', 'چشم‌انداز — عنوان', 'چشم‌انداز حرفه‌ای' ),
				self::ta( 'vision_text', 'چشم‌انداز — متن', 'تبدیل شدن به معتبرترین مرجع ارزیابی و حل‌وفصل تخصصی دعاوی، و ساخت الگویی نوین از ارتباط شفاف و صمیمی بین وکیل و موکل در فضای دیجیتال و واقعی.', array( 'rows' => 3 ) ),
			),
		);

		$s['contact_page'] = array(
			'group' => 'modules', 'label' => 'صفحه تماس', 'icon' => 'dashicons-email-alt', 'shortcode' => '[legal_contact_page]',
			'desc' => 'کارت‌های تماس (از اطلاعات دفتر)، فرم پیام و راهنمای مشاوره. پیام‌ها در «درخواست‌ها» ذخیره و ایمیل می‌شوند.',
			'fields' => array(
				self::enabled(),
				self::t( 'eyebrow', 'برچسب بالا', 'Get in touch with Legal Office' ),
				self::t( 'title', 'عنوان', 'راه‌های ارتباط با دفتر حقوقی' ),
				self::ta( 'subtitle', 'زیرعنوان', 'جهت هماهنگی مشاوره تخصصی، بررسی اولیه مدارک و پیگیری پرونده‌های حقوقی، از طریق راه‌های زیر با ما در ارتباط باشید.', array( 'rows' => 2 ) ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1' ),
				self::t( 'address_label', 'عنوان کارت آدرس', 'آدرس دفتر وکالت' ),
				self::t( 'phone_label', 'عنوان کارت تلفن', 'شماره تماس' ),
				self::t( 'phone_note', 'توضیح کارت تلفن', 'خط ویژه مشاوره' ),
				self::t( 'email_label', 'عنوان کارت ایمیل', 'پست الکترونیک' ),
				self::t( 'email_note', 'توضیح کارت ایمیل', 'ارسال اسناد و مدارک' ),
				self::t( 'form_title', 'عنوان فرم', 'ارسال پیام مستقیم' ),
				self::ta( 'form_sub', 'توضیح فرم', 'سوالات یا توضیحات اولیه پرونده خود را بنویسید.', array( 'rows' => 2 ) ),
				self::t( 'form_button', 'متن دکمه ارسال', 'ارسال پیام به دفتر حقوقی' ),
				self::t( 'form_success', 'پیام موفقیت فرم', 'پیام شما ارسال شد. به‌زودی با شما تماس می‌گیریم.' ),
				self::t( 'side_title', 'باکس کناری — عنوان', 'راهنمای مشاوره حضوری و تلفنی' ),
				self::h( 'side_text', 'باکس کناری — متن', 'جهت احترام به وقت موکلین گرامی، جلسات مشاوره حضوری منحصراً با وقت قبلی تنظیم می‌شود. توصیه می‌شود پیش از مراجعه، اسناد مربوط به قرارداد، چک یا اوراق قضایی خود را آماده نمایید.', array( 'rows' => 4 ) ),
				self::t( 'quick_title', 'کادر ارتباط سریع — عنوان', 'ارتباط سریع در پیام‌رسان‌ها' ),
				self::ta( 'quick_text', 'کادر ارتباط سریع — متن', 'برای ارسال سریع اسناد و بررسی اولیه پرونده، می‌توانید از طریق شماره همراه، پیام‌رسان‌ها یا ایمیل رسمی اقدام کنید.', array( 'rows' => 2 ) ),
				self::tg( 'show_social', 'دکمه‌های واتساپ/تلگرام/اینستاگرام در کادر ارتباط سریع', 1 ),
				self::tg( 'show_map', 'نمایش نقشه (اگر آدرس Embed وارد شده باشد)', 1 ),
				self::tg( 'show_hours', 'نمایش ساعات کاری', 1 ),
				self::tg( 'show_email_field', 'فیلد ایمیل در فرم', 0 ),
				self::tg( 'show_subject', 'فیلد موضوع در فرم', 1 ),
			),
		);

		$s['booking_form'] = array(
			'group' => 'modules', 'label' => 'فرم درخواست بررسی پرونده', 'icon' => 'dashicons-feedback', 'shortcode' => '[legal_booking_form]',
			'desc' => 'فرم کوتاه جذب مشتری. درخواست‌ها در «درخواست‌ها» ذخیره و به ایمیل ارسال می‌شود. شناسه لنگر: #consultation',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Expert Review', 'درخواست بررسی تخصصی پرونده', 'اطلاعات اولیه خود را ثبت کنید تا برای ارزیابی دقیق مدارک و مشاوره با شما تماس بگیریم.' ),
				array(
					self::t( 'anchor', 'شناسه لنگر (ID) برای لینک‌های #consultation', 'consultation' ),
					self::tg( 'show_email_field', 'فیلد ایمیل', 0 ),
					self::tg( 'show_subject', 'فیلد موضوع دعوا', 1 ),
					self::tg( 'show_domain', 'فیلد انتخاب حوزه', 1 ),
					self::tg( 'show_message', 'فیلد توضیحات', 0 ),
					self::t( 'domain_placeholder', 'متن اولیه انتخاب حوزه', 'انتخاب حوزه تخصصی مرتبط...' ),
					self::lines( 'domain_options', 'گزینه‌های حوزه (هر خط یک گزینه)', "امور ملکی و سرقفلی\nدعاوی خانواده و مهریه\nچک و اسناد تجاری\nدعاوی کیفری و جرایم رایانه‌ای\nسایر حوزه‌ها" ),
					self::t( 'button', 'متن دکمه', 'ثبت درخواست بررسی تخصصی' ),
					self::t( 'privacy_note', 'یادداشت حریم خصوصی', 'اطلاعات و اسناد شما نزد ما کاملاً محفوظ و محرمانه است.' ),
					self::t( 'success', 'پیام موفقیت', 'درخواست شما ثبت شد. به‌زودی با شما تماس می‌گیریم.' ),
				)
			),
		);

		$s['articles'] = array(
			'group' => 'modules', 'label' => 'آخرین مقالات', 'icon' => 'dashicons-media-text', 'shortcode' => '[legal_articles count="3" category=""]',
			'desc' => 'کارت آخرین نوشته‌ها. پارامترهای شورت‌کد: count, category (نامک دسته), columns.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Legal Articles', 'دانشنامه و مقالات حقوقی', 'پاسخ به پرتکرارترین سوالات شما و راهنماهای کاربردی پیش از طرح دعوا' ),
				array(
					self::n( 'count', 'تعداد مقالات', 3, array( 'min' => 1, 'max' => 24 ) ),
					self::s( 'columns', 'تعداد ستون', array( '2' => '۲', '3' => '۳', '4' => '۴' ), '3' ),
					self::t( 'default_category', 'نامک دسته‌بندی (خالی = همه)', '' ),
					self::t( 'readmore', 'متن «مطالعه مقاله»', 'مطالعه مقاله' ),
					self::t( 'empty_text', 'متن بدون مقاله', 'هنوز مقاله‌ای منتشر نشده است.' ),
					self::t( 'placeholder_text', 'متن روی تصویر جایگزین (مقاله بدون تصویر شاخص)', '{firm_name}|{firm_title}', array( 'desc' => 'از {firm_name} و {firm_title} و علامت | برای خط جدید استفاده کنید.' ) ),
					self::tg( 'show_date', 'نمایش تاریخ', 1 ),
				)
			),
		);

		$s['blog_archive'] = array(
			'group' => 'modules', 'label' => 'آرشیو مقالات', 'icon' => 'dashicons-archive', 'shortcode' => '[legal_blog_archive per_page="6"]',
			'desc' => 'آرشیو با فیلتر دسته و صفحه‌بندی (پارامترهای legal_cat و legal_page). برای سئو، تمام صفحات فیلترشده به‌صورت noindex,follow علامت می‌خورند.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'دانشنامه و مقالات حقوقی' ),
				self::t( 'title', 'عنوان', 'مرجع مقالات و راهنماهای قانونی' ),
				self::ta( 'subtitle', 'زیرعنوان', 'پاسخ به چالش‌ها و سوالات پرتکرار حقوقی؛ پیش از طرح هر دعوا یا اقدام قضایی، ابتدا با آگاهی تصمیم بگیرید.', array( 'rows' => 2 ) ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h2' ),
				self::n( 'per_page', 'تعداد مقاله در هر صفحه', 6, array( 'min' => 1, 'max' => 60 ) ),
				self::t( 'all_label', 'متن دکمه «همه»', 'همه مقالات' ),
				self::t( 'readmore', 'متن «مطالعه مقاله»', 'مطالعه مقاله' ),
				self::t( 'empty_text', 'متن بدون نتیجه', 'مقاله‌ای در این دسته‌بندی یافت نشد.' ),
				self::t( 'prev_text', 'متن صفحه قبل', '← صفحه قبل' ),
				self::t( 'next_text', 'متن صفحه بعد', 'صفحه بعد →' ),
				self::t( 'placeholder_text', 'متن روی تصویر جایگزین', '{firm_name}|{firm_title}' ),
			),
		);

		/* ===== مقاله و فرم ===== */
		$s['article'] = array(
			'group' => 'content', 'label' => 'ابزارهای مقاله', 'icon' => 'dashicons-edit', 'shortcode' => '[reading_time] [article_summary] [article_actions]',
			'desc' => 'زمان مطالعه، خلاصه مطلب (متاباکس)، دکمه‌های «مفید بود/اشتراک‌گذاری»، استایل محتوای مقاله و فهرست مطالب.',
			'fields' => array(
				self::t( 'rt_label', 'قالب زمان مطالعه ({n} = تعداد دقیقه)', 'زمان مطالعه: {n} دقیقه' ),
				self::n( 'rt_wpm', 'سرعت مطالعه (کلمه در دقیقه)', 200, array( 'min' => 80, 'max' => 600 ) ),
				self::tg( 'rt_fa_digits', 'نمایش اعداد فارسی', 1 ),
				self::lines( 'summary_types', 'نوع نوشته‌هایی که فیلد «خلاصه مطلب» دارند (هر خط یک نوع)', 'post' ),
				self::t( 'summary_title', 'عنوان باکس خلاصه', 'خلاصه مطلب' ),
				self::t( 'like_label', 'دکمه «مفید بود»', 'مفید بود' ),
				self::t( 'liked_label', 'پس از ثبت', 'ثبت شد' ),
				self::t( 'share_label', 'دکمه اشتراک‌گذاری', 'اشتراک‌گذاری' ),
				self::t( 'copied_label', 'پس از کپی لینک', 'لینک کپی شد!' ),
				self::tg( 'show_like_count', 'نمایش تعداد «مفید بود»', 0 ),
				self::tg( 'post_style', 'استایل زیبای محتوای مقاله (تیترها، لیست‌ها، نقل‌قول، لینک‌ها)', 1 ),
				self::t( 'post_scope', 'سلکتور محتوای مقاله', '.elementor-widget-theme-post-content, .ws-article-content', array( 'desc' => 'اگر محتوا داخل ID خاصی است، مثل #main-content .elementor-widget-theme-post-content بنویسید.' ) ),
				self::tg( 'toc_expand', 'دکمه «مشاهده تمام فهرست» برای ویجت فهرست مطالب المنتور', 1 ),
				self::n( 'toc_height', 'ارتفاع فهرست در حالت جمع‌شده (px)', 330, array( 'min' => 120, 'max' => 800 ) ),
				self::t( 'toc_open', 'دکمه TOC — متن باز کردن', 'مشاهده تمام فهرست' ),
				self::t( 'toc_close', 'دکمه TOC — متن بستن', 'بستن فهرست' ),
			),
		);

		$s['forms'] = array(
			'group' => 'content', 'label' => 'فرم‌ها و درخواست‌ها', 'icon' => 'dashicons-forms',
			'desc' => 'تنظیمات ذخیره، ایمیل و ضد اسپم همه فرم‌های افزونه. لیست درخواست‌ها: منوی «وبی‌سافت حقوقی ← درخواست‌ها».',
			'fields' => array(
				self::tg( 'store', 'ذخیره درخواست‌ها در پایگاه داده', 1 ),
				self::tg( 'send_email', 'ارسال ایمیل اطلاع‌رسانی', 1 ),
				self::t( 'notify_email', 'ایمیل(های) گیرنده (با کاما جدا کنید؛ خالی = ایمیل مدیر سایت)', '' ),
				self::t( 'subject', 'موضوع ایمیل ({name} و {source} قابل استفاده)', 'درخواست جدید از {name} — {source}' ),
				self::tg( 'require_consent', 'چک‌باکس رضایت‌نامه حریم خصوصی اجباری باشد', 0 ),
				self::t( 'consent_text', 'متن رضایت‌نامه', 'با ارسال این فرم، با ذخیره و بررسی اطلاعات خود برای پاسخ‌گویی موافقت می‌کنم.' ),
				self::n( 'min_seconds', 'حداقل زمان پر کردن فرم (ثانیه؛ ضد ربات)', 3, array( 'min' => 0, 'max' => 60 ) ),
				self::n( 'rate_limit', 'حداکثر ارسال از یک IP در ساعت', 5, array( 'min' => 1, 'max' => 100 ) ),
				self::s( 'ip_source', 'منبع IP کاربر', array( 'REMOTE_ADDR' => 'REMOTE_ADDR (پیش‌فرض، امن)', 'HTTP_CF_CONNECTING_IP' => 'Cloudflare (فقط اگر سایت پشت Cloudflare است)' ), 'REMOTE_ADDR' ),
				self::t( 'ph_name', 'متن اولیه فیلد نام', 'نام و نام خانوادگی' ),
				self::t( 'ph_phone', 'متن اولیه فیلد تلفن', 'شماره موبایل' ),
				self::t( 'ph_email', 'متن اولیه فیلد ایمیل', 'ایمیل (اختیاری)' ),
				self::t( 'ph_subject', 'متن اولیه فیلد موضوع', 'موضوع دعوا (مثلاً: تخلیه ملک، چک برگشتی)' ),
				self::t( 'ph_message', 'متن اولیه فیلد پیام', 'شرح مختصر مشکل حقوقی...' ),
				self::t( 'error_generic', 'پیام خطای عمومی', 'ارسال انجام نشد. لطفاً دوباره تلاش کنید یا تماس بگیرید.' ),
				self::t( 'error_required', 'پیام خطای فیلد خالی', 'لطفاً فیلدهای ضروری را کامل کنید.' ),
				self::t( 'error_phone', 'پیام شماره نامعتبر', 'شماره تماس معتبر وارد کنید.' ),
				self::t( 'error_rate', 'پیام محدودیت ارسال', 'تعداد درخواست‌های شما زیاد است؛ کمی بعد دوباره تلاش کنید.' ),
			),
		);

		/* ===== سیستم ===== */
		$s['seo'] = array(
			'group' => 'system', 'label' => 'سئو و اسکیما', 'icon' => 'dashicons-chart-line',
			'desc' => 'داده ساختاریافته LegalService در صفحه اصلی. پس از فعال‌سازی با Rich Results Test گوگل بررسی کنید.',
			'fields' => array(
				self::tg( 'schema_org', 'خروجی JSON-LD دفتر حقوقی در صفحه اصلی', 0 ),
				self::s( 'schema_type', 'نوع', array( 'LegalService' => 'LegalService', 'Attorney' => 'Attorney' ), 'LegalService' ),
				self::t( 'price_range', 'محدوده قیمت (اختیاری، مثل $$)', '' ),
				self::t( 'country', 'کد کشور (ISO)', 'IR' ),
				self::t( 'hours_spec', 'ساعات کاری استاندارد (مثل Sa-We 09:00-17:00، با کاما)', '' ),
				self::t( 'geo', 'مختصات (lat,lng) اختیاری', '' ),
				self::t( 'area_served', 'محدوده خدمت (اختیاری)', '' ),
			),
		);

		$s['system'] = array(
			'group' => 'system', 'label' => 'سیستم و آواتار', 'icon' => 'dashicons-admin-generic',
			'desc' => 'تنظیمات عمومی افزونه.',
			'fields' => array(
				self::tg( 'avatar', 'آواتار اختصاصی کاربران از کتابخانه رسانه (جایگزین Gravatar)', 1 ),
				self::tg( 'font_uploads', 'اجازه آپلود فایل فونت (ttf, otf, woff, woff2) برای مدیران', 1 ),
				self::tg( 'delete_on_uninstall', 'با حذف افزونه، تنظیمات و درخواست‌ها پاک شود', 0 ),
			),
		);

		self::$sections = $s;
		return $s;
	}

	public static function token_options() {
		return array(
			''            => '— دست نزن —',
			'primary'     => 'رنگ اصلی برند',
			'accent'      => 'رنگ تأکیدی',
			'ink'         => 'رنگ عنوان‌ها',
			'text'        => 'رنگ متن',
			'muted'       => 'رنگ کم‌رنگ',
			'btn-a'       => 'رنگ دکمه',
		);
	}

	/* ---------- مقادیر پیش‌فرض ---------- */
	public static function defaults() {
		$out = array();
		foreach ( self::sections() as $id => $sec ) {
			$out[ $id ] = self::field_defaults( $sec['fields'] );
		}
		return $out;
	}

	public static function field_defaults( $fields ) {
		$out = array();
		foreach ( $fields as $f ) {
			$out[ $f['id'] ] = $f['default'];
		}
		return $out;
	}

	private static function default_domains() {
		return array(
			array( 'icon' => 'users', 'title' => 'خانواده', 'desc' => 'طلاق توافقی، مهریه، نفقه، حضانت و تمکین' ),
			array( 'icon' => 'building', 'title' => 'امور ملکی', 'desc' => 'تخلیه، الزام به سند، سرقفلی و خلع ید', 'q' => 'ملکی' ),
			array( 'icon' => 'file', 'title' => 'قراردادها', 'desc' => 'تنظیم، فسخ، ابطال و مطالبه خسارت', 'q' => 'قرارداد' ),
			array( 'icon' => 'money', 'title' => 'چک و سفته', 'desc' => 'مطالبه وجه، شکایت کیفری و توقیف اموال', 'q' => 'چک' ),
			array( 'icon' => 'lock', 'title' => 'امور کیفری', 'desc' => 'کلاهبرداری، سرقت، خیانت در امانت و ضرب', 'q' => 'کیفری' ),
			array( 'icon' => 'car', 'title' => 'تصادفات و دیه', 'desc' => 'مطالبه دیه، دعاوی بیمه و افت قیمت', 'q' => 'تصادفات' ),
			array( 'icon' => 'laptop', 'title' => 'جرایم اینترنتی', 'desc' => 'کلاهبرداری رایانه‌ای، فیشینگ و توهین', 'q' => 'رایانه' ),
			array( 'icon' => 'briefcase', 'title' => 'شرکت‌ها و تجارت', 'desc' => 'ثبت شرکت، انحلال، ورشکستگی و اسناد', 'q' => 'شرکت' ),
		);
	}

	private static function default_sticky() {
		return array(
			array( 'en_title' => 'Family Law', 'fa_title' => 'دعاوی خانواده و مهریه', 'icon' => 'users', 'style' => 'glass',
				'desc' => 'پرونده‌های خانواده نیازمند دقت، سرعت و حفظ حریم خصوصی هستند. ما در مسیر <strong>طلاق توافقی سریع، مطالبه مهریه، نفقه و حضانت</strong> با رویکردی صلح‌جویانه و در صورت نیاز قاطعانه در دادگاه، همراه شما هستیم.' ),
			array( 'en_title' => 'Real Estate', 'fa_title' => 'دعاوی ملکی و سرقفلی', 'icon' => 'building', 'style' => 'solid',
				'desc' => 'پیچیده‌ترین گره‌های ملکی با استراتژی درست باز می‌شوند. از <strong>تخلیه فوری، الزام به تنظیم سند رسمی، خلع ید تا دعاوی پیچیده سرقفلی و مشارکت در ساخت</strong>، تخصص ما حفظ سرمایه شماست.' ),
			array( 'en_title' => 'Commercial', 'fa_title' => 'چک صیادی و قراردادها', 'icon' => 'money', 'style' => 'gradient',
				'desc' => 'زمان در وصول مطالبات حیاتی است. با استفاده از <strong>قانون جدید چک و توقیف سریع اموال، تأمین خواسته</strong>، سرمایه شما را باز می‌گردانیم. همچنین تنظیم دقیق قراردادها، پیشگیری از دعاوی آینده است.' ),
		);
	}

	private static function default_problem_tabs() {
		return array(
			array( 'label' => 'من مشکل ملکی دارم', 'items' => array(
				array( 'icon' => 'home', 'title' => 'تخلیه ملک' ), array( 'icon' => 'file', 'title' => 'الزام به تنظیم سند', 'q' => 'الزام به تنظیم سند' ),
				array( 'icon' => 'key', 'title' => 'سرقفلی' ), array( 'icon' => 'hand', 'title' => 'تصرف عدوانی' ),
				array( 'icon' => 'ban', 'title' => 'خلع ید' ), array( 'icon' => 'file', 'title' => 'مشکلات اجاره‌نامه', 'q' => 'اجاره' ),
			) ),
			array( 'label' => 'مشکل خانوادگیه', 'items' => array(
				array( 'icon' => 'users', 'title' => 'طلاق توافقی' ), array( 'icon' => 'coins', 'title' => 'مطالبه مهریه' ),
				array( 'icon' => 'wallet', 'title' => 'عدم پرداخت نفقه', 'q' => 'نفقه' ), array( 'icon' => 'user', 'title' => 'حضانت فرزند', 'q' => 'حضانت' ),
				array( 'icon' => 'home', 'title' => 'الزام به تمکین', 'q' => 'تمکین' ), array( 'icon' => 'scale', 'title' => 'انحصار وراثت و ارث', 'q' => 'ارث' ),
			) ),
			array( 'label' => 'چکم برگشت خورده', 'items' => array(
				array( 'icon' => 'money', 'title' => 'مطالبه وجه چک', 'q' => 'مطالبه وجه چک' ), array( 'icon' => 'gavel', 'title' => 'شکایت کیفری چک', 'q' => 'شکایت کیفری چک' ),
				array( 'icon' => 'lock', 'title' => 'توقیف اموال (تأمین خواسته)', 'q' => 'تامین خواسته' ), array( 'icon' => 'money', 'title' => 'مشکلات چک صیادی', 'q' => 'چک صیادی' ),
			) ),
			array( 'label' => 'ازم شکایت شده / کیفری', 'items' => array(
				array( 'icon' => 'shield', 'title' => 'دفاع در برابر شکایت', 'q' => 'دفاع در برابر شکایت' ), array( 'icon' => 'user', 'title' => 'کلاهبرداری' ),
				array( 'icon' => 'lock', 'title' => 'خیانت در امانت' ), array( 'icon' => 'laptop', 'title' => 'جرایم اینترنتی و رایانه‌ای', 'q' => 'جرایم رایانه ای' ),
			) ),
		);
	}

	private static function default_steps() {
		return array(
			array( 'icon' => 'clipboard', 'title' => 'ثبت درخواست اولیه و ارسال مدارک', 'desc' => 'ثبت شرح مختصری از دعوا یا مشکل حقوقی پیش‌آمده از طریق فرم آنلاین، پیام‌رسان یا تماس تلفنی. در این مرحله تمامی اسناد، اوراق قضایی، قراردادها یا چک‌های مرتبط به صورت محرمانه دریافت می‌شوند.' ),
			array( 'icon' => 'search', 'title' => 'مطالعه و ارزیابی موشکافانه مدارک', 'desc' => 'بررسی دقیق حقوقی اوراق و مستندات توسط وکیل. تحلیل ریشه‌ای چالش‌ها، شناخت نقاط قوت پرونده و انطباق موضوع با آخرین رویه‌های قضایی و آرای وحدت رویه.' ),
			array( 'icon' => 'route', 'title' => 'طراحی استراتژی و مسیر راه قانونی', 'desc' => 'تدوین نقشه راه اختصاصی؛ انتخاب کوتاه‌ترین، کم‌هزینه‌ترین و کارآمدترین مسیر قانونی (حقوقی، کیفری، ثبتی یا داوری) جهت جلوگیری از اطاله دادرسی و حفظ حداکثری منافع موکل.' ),
			array( 'icon' => 'chat', 'title' => 'جلسه مشاوره، تبادل نظر و عقد قرارداد', 'desc' => 'برگزاری جلسه مشاوره حضوری یا تلفنی برای تشریح کامل چشم‌انداز پرونده، پاسخ به ابهامات موکل، شفاف‌سازی هزینه‌ها و تعیین وکالت‌نامه رسمی جهت آغاز اقدامات اجرایی.' ),
			array( 'icon' => 'scale', 'title' => 'تنظیم تخصصی اوراق و دفاع قاطعانه', 'desc' => 'نگارش حرفه‌ای دادخواست، شکواییه یا لایحه دفاعیه با ادبیات حقوقی قوی، و پیگیری مستمر در مراجع قضایی، شوراهای حل اختلاف و کلانتری‌ها تا حصول نتیجه مطلوب.' ),
		);
	}

	private static function default_cases() {
		return array(
			array( 'icon' => 'store', 'title' => 'تخلیه ملک تجاری در کمتر از ۴۰ روز', 'domain' => 'ملکی و سرقفلی',
				'body' => 'موکل ما مالک یک مغازه تجاری بود که مستأجر ماه‌ها اجاره نپرداخته بود و از تخلیه نیز امتناع می‌کرد. با بررسی دقیق قرارداد و انتخاب مسیر قانونی <strong>«دستور تخلیه فوری»</strong> به جای <strong>«حکم تخلیه»</strong>، ملک در زمانی کوتاه تخلیه و تحویل داده شد. انتخاب مسیر درست، ماه‌ها زمان موکل را ذخیره کرد.' ),
			array( 'icon' => 'money', 'title' => 'وصول چک برگشتی پس از دو سال', 'domain' => 'اسناد تجاری',
				'body' => 'چک موکل برگشت خورده بود و صادرکننده با انتقال اموال خود، قصد فرار از دین را داشت. با استفاده از <strong>قانون جدید چک</strong> سریعاً اجراییه دریافت شد و با پیگیری قضایی، <strong>اموال قابل توقیف</strong> صادرکننده شناسایی شد.' ),
			array( 'icon' => 'briefcase', 'title' => 'رهایی از قرارداد مشارکت یک‌طرفه', 'domain' => 'قراردادها و پیمانکاری',
				'body' => 'موکل در یک قرارداد مشارکت در ساخت گرفتار سازنده‌ای شده بود که پروژه را نیمه‌کاره رها کرده بود. با اثبات <strong>تخلف از شروط ضمن عقد</strong> و استفاده از راهکارهای حقوقی مربوط به خیارات قانونی، دادخواست فسخ به نفع موکل پیش رفت.' ),
		);
	}

	private static function default_certs() {
		return array(
			array( 'badge' => 'Bar Association', 'title' => 'پروانه تخصصی وکالت پایه یک دادگستری', 'desc' => 'مجوز رسمی و پروانه فعالیت حرفه‌ای در مراجع قضایی سراسر کشور.' ),
			array( 'badge' => 'Official Member', 'title' => 'عضو رسمی مرکز وکلای قوه قضاییه', 'desc' => 'دارای صلاحیت علمی و حقوقی تایید شده جهت پیگیری تخصصی پرونده‌های پیچیده.' ),
			array( 'badge' => 'Contract Law', 'title' => 'گواهی تخصصی تنظیم قراردادهای تجاری', 'desc' => 'گواهینامه تسلط بر انعقاد قراردادها و پیشگیری از بروز دعاوی حقوقی.' ),
			array( 'badge' => 'Top Advocate', 'title' => 'لوح تقدیر عملکرد حرفه‌ای', 'desc' => 'تقدیرنامه موفقیت در به سرانجام رساندن پرونده‌های سنگین مالی و اسناد تجاری.' ),
			array( 'badge' => 'Legal Arbitration', 'title' => 'متخصص داوری و حل‌وفصل جایگزین', 'desc' => 'دارای مدرک تخصصی داوری جهت صلح و سازش سریع خارج از دادگاه.' ),
		);
	}

	private static function default_faq() {
		return array(
			array( 'q' => 'طلاق توافقی چقدر زمان می‌برد؟', 'a' => 'مدت‌زمان طلاق توافقی بسته به شرایط پرونده، الزام به مشاوره و ازدحام شعبه‌ها متفاوت است و معمولاً از چند هفته تا چند ماه طول می‌کشد. برای برآورد دقیق‌تر، شرایط پرونده خود را با ما در میان بگذارید.' ),
			array( 'q' => 'اگر مستأجر ملک را تخلیه نکند، بهترین اقدام چیست؟', 'a' => 'اگر قرارداد اجاره کتبی و دارای شرایط قانونی باشد و مدت آن تمام شده باشد، درخواست «دستور تخلیه» از طریق مرجع صالح معمولاً سریع‌ترین مسیر است. نوع قرارداد و مدارک شما در انتخاب مسیر تعیین‌کننده است.' ),
			array( 'q' => 'آیا پیام‌ها و چت‌های پیام‌رسان‌ها در دادگاه مدرک محسوب می‌شود؟', 'a' => 'داده‌پیام‌ها در شرایطی می‌توانند به‌عنوان ادله الکترونیکی مورد استناد قرار گیرند؛ اعتبار آن‌ها در صورت انکار طرف مقابل از طریق کارشناسی بررسی می‌شود. پیش از هر اقدام، مدارک را حذف یا ویرایش نکنید.' ),
			array( 'q' => 'برای مطالبه وجه چک برگشتی از کجا باید شروع کرد؟', 'a' => 'ابتدا باید وضعیت چک (ثبت در سامانه صیاد، گواهی عدم پرداخت و مهلت‌های قانونی) بررسی شود؛ سپس بسته به شرایط، مسیر حقوقی یا کیفری انتخاب می‌شود.' ),
			array( 'q' => 'تفاوت تصرف عدوانی و خلع ید در چیست؟', 'a' => 'در دعوای «خلع ید» مالک رسمی ملک، از متصرف غاصب رفع تصرف می‌خواهد؛ در «تصرف عدوانی» خواهان سابقه تصرف داشته و بدون رضایت از ملک خارج شده است. نوع دعوا بر سرعت و مدارک لازم اثر می‌گذارد.' ),
		);
	}

	private static function default_features() {
		return array(
			array( 'icon' => 'scale', 'title' => 'تسلط کامل بر قوانین روز', 'desc' => 'بررسی دقیق و علمی آخرین رویه‌های قضایی، آرای وحدت رویه و قوانین جدید جهت بالاترین ضریب موفقیت در پرونده.' ),
			array( 'icon' => 'shield', 'title' => 'محرمانگی کامل اطلاعات', 'desc' => 'اسناد و اسرار حرفه‌ای موکلین ارزشمندترین دارایی ماست؛ حفظ حریم شخصی و محرمانگی داده‌ها خط قرمز دفتر ماست.' ),
			array( 'icon' => 'clock', 'title' => 'سرعت عمل و پیگیری مستمر', 'desc' => 'در دعاوی حقوقی زمان حیاتی است. تلاش ما کوتاه‌ترین و کم‌هزینه‌ترین مسیر برای احقاق حق شماست.' ),
		);
	}
}
