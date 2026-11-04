<?php
defined( 'ABSPATH' ) || exit;

/**
 * اسکیمای تنظیمات: تمام بخش‌ها، فیلدها، مقادیر پیش‌فرض.
 * همین اسکیما هم برای ساخت فرم ادمین، هم پاک‌سازی ورودی و هم مقادیر پیش‌فرض استفاده می‌شود.
 */
class WSF_Schema {

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
				self::t( 'firm_name', 'نام برند / دفتر', 'آلیس‌یار' ),
				self::t( 'firm_title', 'عنوان حرفه‌ای', 'مشاوره مالی و حسابداری کسب‌وکارهای خرد' ),
				self::n( 'consult_minutes', 'مدت مشاوره رایگان (دقیقه) — جایگزین {minutes} در همه متن‌ها', 30, array( 'min' => 5, 'max' => 240 ) ),
				self::img( 'logo', 'لوگو — حالت روشن (اختیاری؛ در غیر این صورت لوگوی سایت)', WSF_URL . 'assets/img/logo.png' ),
				self::img( 'logo_dark', 'لوگو — حالت تاریک', WSF_URL . 'assets/img/logo-dark.png', array( 'desc' => 'پیش‌فرض: لوگوی آلیس‌یار (نسخه‌ی روشن). برای تغییر، تصویر دیگری انتخاب کنید.' ) ),
				self::tg( 'logo_swap', 'جایگزینی خودکار لوگوی قالب/ویجت لوگوی المنتور در حالت تاریک', 1, array( 'desc' => 'در حالت تاریک تصویر ویجت «Site Logo» المنتور و لوگوی قالب با لوگوی تاریک عوض می‌شود. شورت‌کد: [ws_logo height=\"48\" link=\"1\"]' ) ),
				self::t( 'phone', 'شماره تماس (مثلاً 09120000000)', '' ),
				self::t( 'email', 'ایمیل عمومی', '' ),
				self::t( 'whatsapp', 'واتساپ (شماره با کد کشور، مثلاً 989120000000)', '' ),
				self::t( 'telegram', 'تلگرام (نام کاربری بدون @)', '' ),
				self::u( 'instagram', 'اینستاگرام (آدرس کامل)', '' ),
				self::ta( 'address', 'آدرس دفتر', '', array( 'rows' => 2 ) ),
				self::t( 'city', 'شهر (برای اسکیما)', 'همدان' ),
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
					'primary' => '#2a47a8', 'btn_a' => '#2a47a8', 'btn_b' => '#14224a', 'on_btn' => '#ffffff', 'accent' => '#f47920',
					'accent_text' => '#c2570c', 'ink' => '#14224a', 'text' => '#4b5568', 'muted' => '#8d97b0',
					'surface' => '#ffffff', 'surface_alt' => '#f6f8fe', 'border' => '#e3e8f5', 'page_bg' => '',
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
					'primary' => '#8aa4f5', 'btn_a' => '#3a55b8', 'btn_b' => '#243b8f', 'on_btn' => '#ffffff', 'accent' => '#ff944d',
					'accent_text' => '#ff944d', 'ink' => '#eef2ff', 'text' => '#c5cde4', 'muted' => '#8a94b4',
					'surface' => '#141b30', 'surface_alt' => '#0e1424', 'border' => '#28325a', 'page_bg' => '#0a0f1f',
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

		$s['bottom_nav'] = array(
			'group' => 'base', 'label' => 'نوار ناوبری پایین صفحه', 'icon' => 'dashicons-menu-alt3',
			'desc' => 'نوار ناوبری شبیه اپ، پایین صفحه (مخصوص موبایل). آدرس‌ها می‌توانند این توکن‌ها باشند: {home} صفحه اصلی، {blog} صفحه مقالات، {account} حساب کاربری (ورود/پنل)، {consult} لنگر فرم مشاوره.',
			'fields' => array(
				self::tg( 'enabled', 'نمایش نوار پایین', 1 ),
				self::s( 'show_on', 'نمایش در', array( 'mobile' => 'موبایل و تبلت (تا ۱۰۲۴ پیکسل)', 'all' => 'همه‌ی دستگاه‌ها' ), 'mobile' ),
				self::s( 'style', 'سبک', array( 'floating' => 'شناور و گرد (شیشه‌ای)', 'flat' => 'چسبیده به پایین (تخت)' ), 'floating' ),
				self::tg( 'show_labels', 'نمایش متن زیر آیکون‌ها', 1 ),
				self::tg( 'hide_checkout', 'مخفی در سبد خرید و تسویه حساب', 1 ),
				self::lines( 'hide_paths', 'صفحه‌هایی که نوار در آن‌ها نشان داده نشود (بخشی از آدرس؛ هر خط یکی)', '' ),
				self::rep( 'items', 'آیتم‌ها (۳ تا ۵ مورد بهتر است)', array(
					self::ic( 'icon', 'آیکون', 'home' ), self::t( 'label', 'برچسب' ), self::t( 'url', 'آدرس یا توکن' ),
					self::tg( 'highlight', 'دکمه برجسته (وسط، گرد)', 0 ),
				), array(
					array( 'icon' => 'home', 'label' => 'خانه', 'url' => '{home}', 'highlight' => 0 ),
					array( 'icon' => 'book', 'label' => 'مقالات', 'url' => '{blog}', 'highlight' => 0 ),
					array( 'icon' => 'calendar', 'label' => 'مشاوره', 'url' => '{consult}', 'highlight' => 1 ),
					array( 'icon' => 'user', 'label' => 'حساب من', 'url' => '{account}', 'highlight' => 0 ),
				), array( 'title_field' => 'label' ) ),
			),
		);

		$s['utilities'] = array(
			'group' => 'base', 'label' => 'ابزارهای المنتور و CSS', 'icon' => 'dashicons-admin-tools',
			'desc'  => 'کلاس‌های کمکی برای المنتور، جلوه‌ها و CSS اختصاصی.',
			'fields' => array(
				self::tg( 'util_classes', 'کلاس‌های کمکی (ws-glass, ws-surface, ws-hover-border, border-hover-1..7, btn-emergency, glow-orb ...)', 1 ),
				self::tg( 'mobile_menu', 'استایل منوی موبایل المنتور (دکمه همبرگری و پنل کشویی شیشه‌ای، هماهنگ با پالت و حالت تاریک)', 1, array( 'desc' => 'جایگزین CSS قدیمی منوی موبایل. اگر آن را در Elementor Custom CSS یا WPCode دارید، حذفش کنید.' ) ),
				self::tg( 'orbs', 'هاله‌های نوری پس‌زمینه (سه هاله‌ی محو و شناور)', 1, array( 'desc' => 'جایگزین کانتینرهای glow-orb در المنتور؛ بدون نیاز به ساخت المان. اگر قبلاً کانتینر هاله ساخته‌اید، آن‌ها را حذف کنید.' ) ),
				self::s( 'orb_c1', 'هاله ۱ (بالا-ابتدا) — رنگ از پالت', self::orb_tokens(), 'primary' ),
				self::s( 'orb_c2', 'هاله ۲ (وسط-انتها) — رنگ از پالت', self::orb_tokens(), 'accent' ),
				self::s( 'orb_c3', 'هاله ۳ (پایین) — رنگ از پالت', self::orb_tokens(), 'muted' ),
				self::n( 'orb_opacity', 'شفافیت هاله‌ها — حالت روشن (٪؛ کمتر = سفیدتر و سبک‌تر)', 9, array( 'min' => 0, 'max' => 80 ) ),
				self::n( 'orb_opacity_dark', 'شفافیت هاله‌ها — حالت تاریک (٪)', 20, array( 'min' => 0, 'max' => 80 ) ),
				self::tg( 'logo_shimmer', 'انیمیشن درخشش و بزرگ‌شدن لوگوی سایت (ویجت لوگوی المنتور)', 0 ),
				self::tg( 'load_fa', 'بارگذاری Font Awesome 6 (فقط اگر خودتان از کلاس‌های fa-* استفاده می‌کنید)', 0, array( 'desc' => 'آیکون‌های داخلی افزونه به Font Awesome نیازی ندارند.' ) ),
				self::ta( 'custom_css', 'CSS اختصاصی (روی همه صفحات)', '', array( 'san' => 'css', 'rows' => 8, 'desc' => 'برای استایل حالت تاریک از سلکتور html[data-wsf-theme="dark"] .your-class استفاده کنید.', 'class' => 'code' ) ),
			),
		);

		/* ===== ماژول‌ها ===== */
		$s['hero_photo'] = array(
			'group' => 'modules', 'label' => 'هیرو با عکس', 'icon' => 'dashicons-id', 'shortcode' => '[fin_hero_photo]',
			'desc' => 'معرفی با عنوان، دکمه‌ها و عکس. در متن‌ها می‌توانید از {minutes} (مدت مشاوره رایگان) استفاده کنید.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب بالای عنوان', 'با کم شروع کن، درست بساز' ),
				self::t( 'title', 'عنوان اصلی', 'خودت حسابدار کسب‌وکارت باش' ),
				self::t( 'highlight', 'کلمه/عبارت برجسته داخل عنوان', 'حسابدار' ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1' ),
				self::ta( 'desc', 'توضیح', 'مالیات، بیمه، حسابداری و پس‌انداز را ساده و شفاف یاد بگیر؛ با تحلیل‌های روزانه بازار و داستان کارآفرین‌ها و نیروهای کار. برای کسب‌وکارهای خرد و کوچک و کسانی که خودشان می‌سازند.', array( 'rows' => 3 ) ),
				self::t( 'btn1_text', 'دکمه اصلی — متن', 'رزرو مشاوره رایگان {minutes} دقیقه‌ای' ),
				self::u( 'btn1_url', 'دکمه اصلی — لینک', '#consultation' ),
				self::t( 'btn2_text', 'دکمه دوم — متن (خالی = شماره تماس دفتر)', '' ),
				self::u( 'btn2_url', 'دکمه دوم — لینک (خالی = تماس)', '' ),
				self::img( 'image', 'عکس (ID کتابخانه رسانه یا آدرس)', '' ),
				self::t( 'image_alt', 'متن جایگزین عکس (alt)', '' ),
				self::tg( 'show_float', 'نمایش کارت شناور روی عکس', 1 ),
				self::ic( 'float_icon', 'آیکون کارت شناور', 'award' ),
				self::t( 'float_title', 'کارت شناور — عنوان', '۱۰ سال تجربه حسابداری' ),
				self::t( 'float_sub', 'کارت شناور — زیرعنوان', 'و فعال در بازارهای مالی از ۱۳۹۹' ),
			),
		);

		$s['search_hero'] = array(
			'group' => 'modules', 'label' => 'هیرو جستجو (با هشتگ)', 'icon' => 'dashicons-search', 'shortcode' => '[fin_search_hero]',
			'desc' => 'کادر جستجوی سایت. اگر کاربر با # شروع کند (مثل #مالیات) فقط مقالات دارای آن برچسب (هشتگ) نشان داده می‌شود.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'یاد بگیر، نه وابسته بشو' ),
				self::t( 'title', 'عنوان', 'قبل از هر تصمیم مالی، اول آگاه باش.' ),
				self::t( 'highlight', 'عبارت برجسته', 'اول آگاه باش.' ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h2' ),
				self::ta( 'subtitle', 'زیرعنوان', 'مالیات، بیمه، حسابداری و قوانین کار را جستجو کنید؛ یا با هشتگ، مثل #مالیات، مقاله‌های همان موضوع را ببینید.', array( 'rows' => 2 ) ),
				self::t( 'placeholder', 'متن داخل کادر جستجو', 'جستجو کنید... (مثلاً: مالیات یا #بیمه)' ),
				self::t( 'button', 'متن دکمه', 'جستجو' ),
				self::t( 'tags_label', 'عنوان موضوعات محبوب', 'موضوعات محبوب:' ),
				self::rep( 'tags', 'موضوعات محبوب', array( self::t( 'label', 'عنوان' ), self::t( 'q', 'عبارت جستجو (خالی = عنوان؛ با # برای هشتگ)' ), self::u( 'url', 'یا لینک مستقیم' ) ), array(
					array( 'label' => 'مالیات' ), array( 'label' => 'بیمه' ), array( 'label' => 'حسابداری' ), array( 'label' => 'قوانین کار' ),
				), array( 'title_field' => 'label' ) ),
			),
		);

		$s['path_picker'] = array(
			'group' => 'modules', 'label' => 'تو کدوم یکی هستی؟ (مسیر چندمرحله‌ای)', 'icon' => 'dashicons-randomize', 'shortcode' => '[fin_path]',
			'desc' => 'کاربر قدم‌به‌قدم انتخاب می‌کند (مثلاً صاحب کسب‌وکار ← خدماتی ← مالیات). تا ۳ سطح؛ هر کارت یا زیرمجموعه دارد یا لینک (خالی = جستجوی عنوان).',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'تو کدوم یکی هستی؟' ),
				self::ta( 'subtitle', 'زیرعنوان', 'یکی را انتخاب کن تا مسیر مناسب خودت را ببینی.', array( 'rows' => 2 ) ),
				self::t( 'back_label', 'متن دکمه بازگشت', 'بازگشت' ),
				self::rep( 'tree', 'سطح ۱', array(
					self::ic( 'icon', 'آیکون', 'briefcase' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ), self::t( 'q', 'عبارت جستجو (اگر زیرمجموعه ندارد)' ), self::u( 'url', 'لینک (اگر زیرمجموعه ندارد)' ),
					self::rep( 'items', 'سطح ۲', array(
						self::ic( 'icon', 'آیکون', 'file' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ), self::t( 'q', 'عبارت جستجو' ), self::u( 'url', 'لینک' ),
						self::rep( 'items', 'سطح ۳', array( self::ic( 'icon', 'آیکون', 'file' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ), self::t( 'q', 'عبارت جستجو' ), self::u( 'url', 'لینک' ) ), array(), array( 'title_field' => 'title' ) ),
					), array(), array( 'title_field' => 'title' ) ),
				), self::default_tree(), array( 'title_field' => 'title' ) ),
			),
		);

		$s['domains'] = array(
			'group' => 'modules', 'label' => 'خدمات (کارت‌ها)', 'icon' => 'dashicons-screenoptions', 'shortcode' => '[fin_domains]',
			'desc' => 'گرید کارت‌های خدمات. اگر لینک خالی باشد، کارت به نتیجه جستجوی سایت می‌رود.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'چه کاری برایت انجام می‌دهیم؟' ),
				self::t( 'view_all_text', 'متن «مشاهده همه»', 'مشاهده همه' ),
				self::u( 'view_all_url', 'لینک «مشاهده همه» (خالی = مخفی)', '' ),
				self::s( 'cols', 'تعداد ستون در دسکتاپ', array( '2' => '۲', '3' => '۳', '4' => '۴' ), '4' ),
				self::rep( 'items', 'خدمات', array( self::ic( 'icon', 'آیکون', 'coins' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ), self::t( 'q', 'عبارت جستجو (خالی = عنوان)' ), self::u( 'url', 'یا لینک مستقیم' ) ), self::default_services(), array( 'title_field' => 'title' ) ),
			),
		);

		$s['workshop'] = array(
			'group' => 'modules', 'label' => 'کارگاه (محتوای رایگان و قفل‌دار)', 'icon' => 'dashicons-lock', 'shortcode' => '[fin_workshop]',
			'desc' => 'کارت‌های بخش‌های کارگاه. آیتم‌های «ویژه اعضا» برای غیرمشترک‌ها قفل نشان داده می‌شود و به صفحه اشتراک می‌رود. نتیجه دسترسی از فیلتر wsf_user_has_access می‌آید (افزونه اشتراک شما).',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Workshop', 'کارگاه آلیس', 'یاد بگیر خودت حساب‌وکتاب کسب‌وکارت را نگه داری.' ),
				array(
					self::u( 'plans_url', 'صفحه اشتراک (خالی = تب عضویت Webisoft Woo)', '' ),
					self::t( 'locked_label', 'برچسب قفل', 'ویژه اعضا' ),
					self::t( 'locked_btn', 'متن دکمه روی آیتم قفل', 'دریافت اشتراک' ),
					self::t( 'login_btn', 'متن دکمه برای مهمان (وارد نشده)', 'ورود یا ثبت‌نام' ),
					self::rep( 'items', 'بخش‌ها', array(
						self::ic( 'icon', 'آیکون', 'book' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 2 ) ), self::u( 'url', 'لینک' ), self::t( 'badge', 'برچسب (مثلاً به‌زودی)' ),
						self::s( 'lock', 'دسترسی', array( 'free' => 'رایگان', 'member' => 'ویژه اعضا (قفل)' ), 'free' ),
					), self::default_workshop(), array( 'title_field' => 'title' ) ),
				)
			),
		);

		$s['downloads'] = array(
			'group' => 'modules', 'label' => 'فرم‌ها و قالب‌های آماده', 'icon' => 'dashicons-download', 'shortcode' => '[fin_downloads]',
			'desc' => 'لیست فایل‌ها. دانلود از طریق آدرس محافظت‌شده انجام می‌شود (بررسی اشتراک). توجه: برای محافظت واقعی، فایل را از کتابخانه رسانه انتخاب کنید و آدرس مستقیم را منتشر نکنید.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Templates', 'فرم‌ها و قالب‌های آماده', 'فرم‌های آماده تا سریع‌تر کار کنی و چیزی از قلم نیفتد.' ),
				array(
					self::u( 'plans_url', 'صفحه اشتراک (خالی = تب عضویت Webisoft Woo)', '' ),
					self::t( 'locked_btn', 'متن دکمه قفل', 'نیاز به اشتراک' ),
					self::t( 'login_btn', 'متن دکمه برای مهمان (وارد نشده)', 'ورود یا ثبت‌نام' ),
					self::t( 'download_btn', 'متن دکمه دانلود', 'دانلود' ),
					self::rep( 'items', 'فایل‌ها', array(
						self::ic( 'icon', 'آیکون', 'file' ), self::t( 'title', 'عنوان' ), self::t( 'desc', 'توضیح کوتاه' ),
						self::img( 'file', 'فایل (ID از کتابخانه رسانه یا URL)' ),
						self::s( 'lock', 'دسترسی', array( 'free' => 'رایگان', 'member' => 'ویژه اعضا (قفل)' ), 'member' ),
					), self::default_downloads(), array( 'title_field' => 'title' ) ),
				)
			),
		);

		$s['plans'] = array(
			'group' => 'modules', 'label' => 'پلن‌های اشتراک (نمایشی)', 'icon' => 'dashicons-tickets-alt', 'shortcode' => '[fin_plans]',
			'desc' => 'کارت‌های معرفی اشتراک. دکمه‌ها به محصول/صفحه پرداخت (مثلاً ووکامرس) لینک می‌شوند. قیمت را خودتان وارد کنید؛ خالی = نمایش داده نمی‌شود.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Membership', 'اشتراک آلیس‌یار', 'هر روز تحلیل تازه، قوانین به‌روز، فرم‌های کامل و مشاوره بیشتر.' ),
				array(
					self::s( 'source', 'منبع پلن‌ها', array( 'auto' => 'خودکار (اگر Webisoft Woo فعال بود از عضویت ویژه آن، وگرنه دستی)', 'woo' => 'فقط از Webisoft Woo', 'manual' => 'فقط دستی (لیست پایین)' ), 'auto' ),
					self::lines( 'woo_features', 'ویژگی‌های مشترک پلن‌های Woo (هر خط یک مورد)', "دسترسی به آخرین قوانین مالیات و بیمه\nتحلیل‌های روزانه بازار\nفرم‌ها و قالب‌های کامل" ),
					self::t( 'woo_btn', 'متن دکمه پلن‌های Woo', 'خرید اشتراک' ),
					self::t( 'woo_login_btn', 'متن دکمه برای مهمان', 'ورود و خرید اشتراک' ),
					self::t( 'woo_renew_btn', 'متن دکمه برای عضو فعال', 'تمدید اشتراک' ),
					self::n( 'woo_featured', 'شماره پلن برجسته در Woo (۰ = هیچ)', 2, array( 'min' => 0, 'max' => 3 ) ),
					self::rep( 'items', 'پلن‌ها', array(
						self::t( 'name', 'نام پلن' ), self::t( 'price', 'قیمت (متن آزاد، مثلاً ۲۹۰٬۰۰۰ تومان)' ), self::t( 'period', 'دوره (مثلاً در ماه)' ),
						self::ta( 'desc', 'توضیح کوتاه', '', array( 'rows' => 2 ) ), self::lines( 'features', 'ویژگی‌ها (هر خط یک مورد)', '' ),
						self::t( 'btn_text', 'متن دکمه', 'شروع کن' ), self::u( 'btn_url', 'لینک دکمه' ), self::t( 'badge', 'برچسب (مثلاً پیشنهادی)' ),
						self::tg( 'featured', 'پلن برجسته', 0 ),
					), self::default_plans(), array( 'title_field' => 'name' ) ),
				)
			),
		);

		$s['process'] = array(
			'group' => 'modules', 'label' => 'مراحل کار', 'icon' => 'dashicons-networking', 'shortcode' => '[fin_process]',
			'desc' => 'مراحل کار به‌صورت کارت‌های شماره‌دار (شماره‌ها خودکار).',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'How it works', 'چطور کار می‌کنیم؟', 'از اولین جلسه تا اینکه خودت حسابدار کسب‌وکارت بشوی.' ),
				array(
					self::t( 'step_label', 'برچسب شماره مرحله', 'مرحله' ),
					self::rep( 'steps', 'مراحل', array( self::ic( 'icon', 'آیکون', 'clipboard' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 3 ) ) ), self::default_steps(), array( 'title_field' => 'title' ) ),
				)
			),
		);

		$s['case_studies'] = array(
			'group' => 'modules', 'label' => 'نمونه کسب‌وکارها (اسلایدر)', 'icon' => 'dashicons-slides', 'shortcode' => '[fin_case_studies]',
			'desc' => 'اسلایدر نمونه‌ها. هویت کسب‌وکار فاش نشود و ادعاها قابل اثبات باشند.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'نمونه‌های واقعی' ),
				self::t( 'title', 'عنوان', 'کسب‌وکارهایی که اصلاح شدند' ),
				self::ta( 'desc', 'توضیح', 'چند نمونه‌ی واقعی از کسب‌وکارهای خرد (بدون ذکر نام، برای حفظ حریم خصوصی).', array( 'rows' => 3 ) ),
				self::t( 'domain_label', 'پیشوند حوزه', 'حوزه:' ),
				self::tg( 'autoplay', 'پخش خودکار', 0 ),
				self::n( 'interval', 'فاصله پخش خودکار (ثانیه)', 7, array( 'min' => 3, 'max' => 30 ) ),
				self::rep( 'items', 'نمونه‌ها', array( self::ic( 'icon', 'آیکون', 'briefcase' ), self::t( 'title', 'عنوان' ), self::t( 'domain', 'حوزه' ), self::h( 'body', 'متن (strong مجاز)', '', array( 'rows' => 4 ) ) ), self::default_cases(), array( 'title_field' => 'title' ) ),
			),
		);

		$s['track_record'] = array(
			'group' => 'modules', 'label' => 'نمونه‌کار بازار (جدول)', 'icon' => 'dashicons-chart-line', 'shortcode' => '[fin_track]',
			'desc' => 'جدول سابقه‌ی معرفی بازارها. هشدار: فقط موارد مستند را وارد کنید، بدون وعده سود، و سلب مسئولیت زیر جدول نمایش داده می‌شود. ارائه توصیه سرمایه‌گذاری ممکن است نیازمند مجوز باشد؛ با مشاور حقوقی بررسی کنید.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Track Record', 'نمونه‌کارها', 'کارنامه‌ی ما، بر اساس داده و تاریخ.' ),
				array(
					self::t( 'col_period', 'عنوان ستون: سال', 'سال' ), self::t( 'col_asset', 'عنوان ستون: بازار/نماد', 'بازار' ),
					self::t( 'col_entry', 'عنوان ستون: معرفی', 'معرفی' ), self::t( 'col_exit', 'عنوان ستون: فروش', 'فروش' ), self::t( 'col_note', 'عنوان ستون: توضیح', 'توضیح' ),
					self::tg( 'show_disclaimer', 'نمایش سلب مسئولیت زیر جدول', 1 ),
					self::rep( 'rows', 'ردیف‌ها', array(
						self::t( 'period', 'سال' ), self::t( 'asset', 'بازار / نماد' ), self::t( 'entry', 'معرفی (قیمت/تاریخ)' ), self::t( 'exit', 'فروش (قیمت/تاریخ)' ), self::t( 'note', 'توضیح' ),
					), self::default_track(), array( 'title_field' => 'asset' ) ),
				)
			),
		);

		$s['disclaimer'] = array(
			'group' => 'modules', 'label' => 'سلب مسئولیت', 'icon' => 'dashicons-warning', 'shortcode' => '[fin_disclaimer]',
			'desc' => 'باکس سلب مسئولیت (در جدول نمونه‌کار بازار هم استفاده می‌شود).',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'سلب مسئولیت' ),
				self::h( 'text', 'متن', "<p>آلیس‌یار تحلیل‌گر طلا و دلار نیست و هیچ‌گونه پیشنهاد خرید یا فروش ارائه نمی‌دهد. خدمات ما صرفاً معرفی بازار مناسب بر اساس داده‌ها و بررسی‌های کارشناسی است.</p><p>مسئولیت هرگونه تصمیم‌گیری در زمینه سرمایه‌گذاری، تماماً بر عهده کاربر است و آلیس‌یار در قبال سود یا زیان احتمالی هیچ مسئولیتی نمی‌پذیرد.</p>", array( 'rows' => 5 ) ),
			),
		);

		$s['stats'] = array(
			'group' => 'modules', 'label' => 'آمار و اعداد', 'icon' => 'dashicons-chart-bar', 'shortcode' => '[fin_stats]',
			'desc' => 'نوار آمار با شمارنده متحرک. فقط اعداد واقعی وارد کنید.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان (اختیاری)', '' ),
				self::tg( 'animate', 'شمارش متحرک هنگام دیده‌شدن', 1 ),
				self::rep( 'items', 'آمارها', array( self::ic( 'icon', 'آیکون', 'chart' ), self::n( 'value', 'عدد', 0, array( 'min' => 0, 'max' => 99999999 ) ), self::t( 'suffix', 'پسوند (مثل + یا ٪)' ), self::t( 'label', 'برچسب' ) ), array(
					array( 'icon' => 'calc', 'value' => 10, 'suffix' => '+', 'label' => 'سال تجربه حسابداری' ),
					array( 'icon' => 'trending-up', 'value' => 6, 'suffix' => '+', 'label' => 'سال فعالیت در بازارهای مالی' ),
				), array( 'title_field' => 'label' ) ),
			),
		);

		$s['testimonials'] = array(
			'group' => 'modules', 'label' => 'نظرات کاربران', 'icon' => 'dashicons-format-quote', 'shortcode' => '[fin_testimonials]',
			'desc' => 'فقط نظرات واقعی و با رضایت صاحبان آن‌ها.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Reviews', 'نظر کاربران', '' ),
				array( self::rep( 'items', 'نظرات', array( self::t( 'name', 'نام' ), self::t( 'role', 'عنوان' ), self::ta( 'text', 'متن نظر', '', array( 'rows' => 3 ) ), self::n( 'rating', 'امتیاز (۰ تا ۵؛ ۰ = مخفی)', 5, array( 'min' => 0, 'max' => 5 ) ), self::img( 'avatar', 'تصویر (اختیاری)' ) ), array(), array( 'title_field' => 'name' ) ) )
			),
		);

		$s['faq'] = array(
			'group' => 'modules', 'label' => 'سوالات پرتکرار (FAQ)', 'icon' => 'dashicons-editor-help', 'shortcode' => '[fin_faq]',
			'desc' => 'آکاردئون دسترس‌پذیر با پشتیبانی از اسکیمای FAQPage.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'سوالات پرتکرار' ),
				self::ta( 'subtitle', 'زیرعنوان', 'پاسخ کوتاه به چیزهایی که بیشتر می‌پرسند.', array( 'rows' => 2 ) ),
				self::tg( 'single_open', 'فقط یک پاسخ هم‌زمان باز باشد', 1 ),
				self::tg( 'first_open', 'اولین سوال در ابتدا باز باشد', 0 ),
				self::tg( 'schema', 'خروجی اسکیمای FAQPage (JSON-LD)', 1 ),
				self::rep( 'items', 'سوال‌ها', array( self::t( 'q', 'سوال' ), self::h( 'a', 'پاسخ', '', array( 'rows' => 4 ) ) ), self::default_faq(), array( 'title_field' => 'q' ) ),
			),
		);

		$s['cta'] = array(
			'group' => 'modules', 'label' => 'بنر دعوت به اقدام', 'icon' => 'dashicons-megaphone', 'shortcode' => '[fin_cta]',
			'desc' => 'بنر مشاوره رایگان؛ از {minutes} استفاده کنید.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', '{minutes} دقیقه مشاوره رایگان' ),
				self::ta( 'text', 'متن', 'قبل از هر تصمیمی، یک جلسه کوتاه رزرو کن. اعدادت را با هم بررسی می‌کنیم. اگر به نفعت بود، ادامه می‌دهیم؛ اگر نه، هیچ هزینه‌ای نمی‌دهی.', array( 'rows' => 3 ) ),
				self::t( 'btn1_text', 'دکمه اصلی — متن', 'رزرو مشاوره رایگان' ),
				self::u( 'btn1_url', 'دکمه اصلی — لینک', '#consultation' ),
				self::tg( 'show_phone', 'دکمه تماس (شماره دفتر)', 1 ),
				self::s( 'style', 'سبک', array( 'gradient' => 'گرادیان برند', 'dark' => 'تیره', 'light' => 'روشن' ), 'gradient' ),
			),
		);

		$s['tag_search'] = array(
			'group' => 'modules', 'label' => 'جستجوی هشتگ (برچسب‌ها)', 'icon' => 'dashicons-tag', 'shortcode' => '[fin_tag_search]  [fin_post_tags]',
			'desc' => 'هشتگ همان «برچسب» وردپرس است: در هر مقاله برچسب بزنید (نوشته‌ها ← برچسب‌ها). شورت‌کد [fin_post_tags] برچسب‌های مقاله‌ی جاری را به‌صورت #هشتگ نشان می‌دهد.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان', 'با هشتگ جستجو کن' ),
				self::t( 'placeholder', 'متن داخل کادر', '#مالیات یا هر کلمه‌ای...' ),
				self::t( 'button', 'متن دکمه', 'جستجو' ),
				self::n( 'popular', 'تعداد برچسب‌های محبوب', 12, array( 'min' => 0, 'max' => 40 ) ),
				self::t( 'popular_label', 'عنوان برچسب‌های محبوب', 'پرطرفدارترین هشتگ‌ها:' ),
			),
		);

		$s['tipjar'] = array(
			'group' => 'modules', 'label' => 'زنگ تفریح — دعوت به چای', 'icon' => 'dashicons-coffee', 'shortcode' => '[fin_tip]',
			'desc' => 'نوار/کارت حمایت (مثلاً «ما را به یک چای دعوت کن»). می‌تواند به‌صورت نوار بالای کل سایت هم نشان داده شود.',
			'fields' => array(
				self::enabled(),
				self::tg( 'show_bar', 'نمایش به‌صورت نوار بالای کل سایت', 0 ),
				self::tg( 'dismissible', 'کاربر بتواند نوار را ببندد', 1 ),
				self::ic( 'icon', 'آیکون', 'tea' ),
				self::t( 'text', 'متن', 'اگر دوست داشتی، ما را به یک چای دعوت کن ☕' ),
				self::t( 'btn_text', 'متن دکمه', 'دعوت به چای' ),
				self::u( 'btn_url', 'لینک پرداخت/حمایت', '' ),
			),
		);

		$s['about_page'] = array(
			'group' => 'modules', 'label' => 'درباره ما', 'icon' => 'dashicons-info-outline', 'shortcode' => '[fin_about_page]',
			'desc' => 'داستان، دلایل انتخاب، ماموریت و چشم‌انداز.',
			'fields' => array(
				self::enabled(),
				self::t( 'eyebrow', 'برچسب بالا', 'Our Story' ),
				self::t( 'title', 'عنوان', 'آلیس‌یار؛ با کم شروع کن، درست بساز' ),
				self::t( 'highlight', 'عبارت برجسته', 'درست بساز' ),
				self::ta( 'desc', 'داستان', 'تا حالا به خودت گفتی: «من به‌عنوان یک کسب‌وکار خرد می‌توانم مالیات کمتری بدهم؟»، «چرا هرچه کار می‌کنم نمی‌توانم پس‌انداز کنم؟»، «بازار مناسب سرمایه‌گذاری کجاست؟» اگر حتی یکی را گفتی، تنها نیستی. آلیس‌یار برای همین ساخته شد: ده سال تجربه حسابداری، سال‌ها همکاری با تاجرهای بزرگ و از ۱۳۹۹ فعالیت در بازارهای مالی؛ ولی حالا کنار کسب‌وکارهای خرد و نیروهای کار هستیم، چون اینجاست که کمتر کسی کنارشان است.', array( 'rows' => 6 ) ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1' ),
				self::rep( 'features', 'چرا آلیس‌یار؟', array( self::ic( 'icon', 'آیکون', 'award' ), self::t( 'title', 'عنوان' ), self::ta( 'desc', 'توضیح', '', array( 'rows' => 3 ) ) ), self::default_features(), array( 'title_field' => 'title' ) ),
				self::ic( 'mission_icon', 'ماموریت — آیکون', 'target' ),
				self::t( 'mission_title', 'ماموریت — عنوان', 'ماموریت ما' ),
				self::ta( 'mission_text', 'ماموریت — متن', 'کنار کسانی بودن که خودشان می‌سازند: کسب‌وکارهای خرد و نیروهای کار، تا مالیات، بیمه و پس‌انداز را خودشان بفهمند و خودشان تصمیم بگیرند.', array( 'rows' => 3 ) ),
				self::ic( 'vision_icon', 'چشم‌انداز — آیکون', 'bulb' ),
				self::t( 'vision_title', 'چشم‌انداز — عنوان', 'نه وام، نه سرمایه‌گذار، نه وعده' ),
				self::ta( 'vision_text', 'چشم‌انداز — متن', 'فقط درست چیدن همان چیزی که هست. هر کسی از یک جایی شروع می‌کند؛ هیچ درآمدی آن‌قدر کم نیست که نشود از آن ساخت.', array( 'rows' => 3 ) ),
			),
		);

		$s['contact_page'] = array(
			'group' => 'modules', 'label' => 'صفحه تماس', 'icon' => 'dashicons-email-alt', 'shortcode' => '[fin_contact_page]',
			'desc' => 'کارت‌های تماس (از اطلاعات دفتر)، فرم پیام و راهنما. پیام‌ها در «درخواست‌ها» ذخیره و ایمیل می‌شوند.',
			'fields' => array(
				self::enabled(),
				self::t( 'eyebrow', 'برچسب بالا', 'Contact' ),
				self::t( 'title', 'عنوان', 'با ما در ارتباط باش' ),
				self::ta( 'subtitle', 'زیرعنوان', 'برای رزرو مشاوره رایگان یا پرسیدن هر سوالی درباره مالیات، بیمه و حسابداری پیام بگذار.', array( 'rows' => 2 ) ),
				self::s( 'heading_tag', 'تگ عنوان اصلی', array( 'h1' => 'H1', 'h2' => 'H2' ), 'h1' ),
				self::t( 'address_label', 'عنوان کارت آدرس', 'آدرس دفتر' ),
				self::t( 'phone_label', 'عنوان کارت تلفن', 'شماره تماس' ),
				self::t( 'phone_note', 'توضیح کارت تلفن', 'رزرو مشاوره' ),
				self::t( 'email_label', 'عنوان کارت ایمیل', 'ایمیل' ),
				self::t( 'email_note', 'توضیح کارت ایمیل', 'ارسال سوال و مدارک' ),
				self::t( 'form_title', 'عنوان فرم', 'ارسال پیام' ),
				self::ta( 'form_sub', 'توضیح فرم', 'سوال یا توضیح اولیه‌ات را بنویس.', array( 'rows' => 2 ) ),
				self::t( 'form_button', 'متن دکمه ارسال', 'ارسال پیام' ),
				self::t( 'form_success', 'پیام موفقیت فرم', 'پیامت ارسال شد. به‌زودی پاسخ می‌دهیم.' ),
				self::t( 'side_title', 'باکس کناری — عنوان', 'مشاوره حضوری و آنلاین' ),
				self::h( 'side_text', 'باکس کناری — متن', 'جلسه‌های حضوری و آنلاین با وقت قبلی تنظیم می‌شود. {minutes} دقیقه اول رایگان است؛ پیش از جلسه اعداد، پرونده مالیاتی یا قراردادهایت را آماده کن.', array( 'rows' => 4 ) ),
				self::t( 'quick_title', 'کادر ارتباط سریع — عنوان', 'ارتباط سریع در پیام‌رسان‌ها' ),
				self::ta( 'quick_text', 'کادر ارتباط سریع — متن', 'برای پرسش سریع می‌توانی از طریق پیام‌رسان‌ها یا ایمیل اقدام کنی.', array( 'rows' => 2 ) ),
				self::tg( 'show_social', 'دکمه‌های واتساپ/تلگرام/اینستاگرام', 1 ),
				self::tg( 'show_map', 'نمایش نقشه (اگر آدرس Embed وارد شده باشد)', 1 ),
				self::tg( 'show_hours', 'نمایش ساعات کاری', 1 ),
				self::tg( 'show_email_field', 'فیلد ایمیل در فرم', 0 ),
				self::tg( 'show_subject', 'فیلد موضوع در فرم', 1 ),
			),
		);

		$s['booking_form'] = array(
			'group' => 'modules', 'label' => 'فرم رزرو مشاوره رایگان', 'icon' => 'dashicons-feedback', 'shortcode' => '[fin_booking_form]',
			'desc' => 'فرم کوتاه رزرو. درخواست‌ها ذخیره و ایمیل می‌شوند. شناسه لنگر: #consultation',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Free Consultation', 'رزرو مشاوره رایگان {minutes} دقیقه‌ای', 'اطلاعات اولیه را ثبت کن تا برای هماهنگی زمان جلسه با تو تماس بگیریم.' ),
				array(
					self::t( 'anchor', 'شناسه لنگر (ID)', 'consultation' ),
					self::tg( 'show_email_field', 'فیلد ایمیل', 0 ),
					self::tg( 'show_subject', 'فیلد موضوع', 1 ),
					self::tg( 'show_domain', 'فیلد انتخاب موضوع کلی', 1 ),
					self::tg( 'show_message', 'فیلد توضیحات', 0 ),
					self::t( 'domain_placeholder', 'متن اولیه انتخاب موضوع', 'انتخاب موضوع مشاوره...' ),
					self::lines( 'domain_options', 'گزینه‌ها (هر خط یکی)', "پس‌انداز و زندگی مالی\nاصلاح و رشد کسب‌وکار\nمالیات و بیمه\nحسابداری\nبررسی سرمایه‌گذاری\nقوانین کار و استخدام\nسایر" ),
					self::t( 'button', 'متن دکمه', 'رزرو مشاوره رایگان' ),
					self::t( 'privacy_note', 'یادداشت حریم خصوصی', 'اطلاعات شما نزد ما محفوظ و محرمانه است.' ),
					self::t( 'success', 'پیام موفقیت', 'درخواستت ثبت شد. به‌زودی با تو تماس می‌گیریم.' ),
				)
			),
		);

		$s['articles'] = array(
			'group' => 'modules', 'label' => 'آخرین مقالات', 'icon' => 'dashicons-media-text', 'shortcode' => '[fin_articles count="3" category=""]',
			'desc' => 'کارت آخرین نوشته‌ها. پارامترها: count, category (نامک دسته), columns.',
			'fields' => array_merge(
				array( self::enabled() ),
				self::head_fields( 'Blog', 'مقاله‌ها و آموزش‌ها', 'مالیات، بیمه، حسابداری و پس‌انداز، ساده و کاربردی.' ),
				array(
					self::n( 'count', 'تعداد مقالات', 3, array( 'min' => 1, 'max' => 24 ) ),
					self::s( 'columns', 'تعداد ستون', array( '2' => '۲', '3' => '۳', '4' => '۴' ), '3' ),
					self::t( 'default_category', 'نامک دسته‌بندی (خالی = همه)', '' ),
					self::t( 'readmore', 'متن «مطالعه مقاله»', 'مطالعه مقاله' ),
					self::t( 'empty_text', 'متن بدون مقاله', 'هنوز مقاله‌ای منتشر نشده است.' ),
					self::t( 'placeholder_text', 'متن روی تصویر جایگزین', '{firm_name}|{firm_title}' ),
					self::tg( 'show_date', 'نمایش تاریخ', 1 ),
				)
			),
		);

		$s['blog_archive'] = array(
			'group' => 'modules', 'label' => 'آرشیو مقالات', 'icon' => 'dashicons-archive', 'shortcode' => '[fin_blog_archive per_page="6"]',
			'desc' => 'آرشیو با فیلتر دسته و صفحه‌بندی. صفحات فیلترشده noindex,follow می‌شوند.',
			'fields' => array(
				self::enabled(),
				self::t( 'badge', 'برچسب', 'مقاله‌ها و آموزش‌ها' ),
				self::t( 'title', 'عنوان', 'مرجع آموزش مالی کسب‌وکارهای خرد' ),
				self::ta( 'subtitle', 'زیرعنوان', 'مالیات، بیمه، حسابداری، قوانین کار و سرمایه‌گذاری؛ ساده و شفاف.', array( 'rows' => 2 ) ),
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
			'desc' => 'تنظیمات ذخیره، ایمیل و ضد اسپم همه فرم‌های افزونه. لیست درخواست‌ها: منوی «وبی‌سافت مالی ← درخواست‌ها».',
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
				self::t( 'ph_subject', 'متن اولیه فیلد موضوع', 'موضوع (مثلاً: مالیات، بیمه، حسابداری)' ),
				self::t( 'ph_message', 'متن اولیه فیلد پیام', 'شرح مختصر موضوع...' ),
				self::t( 'error_generic', 'پیام خطای عمومی', 'ارسال انجام نشد. لطفاً دوباره تلاش کنید یا تماس بگیرید.' ),
				self::t( 'error_required', 'پیام خطای فیلد خالی', 'لطفاً فیلدهای ضروری را کامل کنید.' ),
				self::t( 'error_phone', 'پیام شماره نامعتبر', 'شماره تماس معتبر وارد کنید.' ),
				self::t( 'error_rate', 'پیام محدودیت ارسال', 'تعداد درخواست‌های شما زیاد است؛ کمی بعد دوباره تلاش کنید.' ),
			),
		);

		/* ===== سیستم ===== */
		$s['seo'] = array(
			'group' => 'system', 'label' => 'سئو و اسکیما', 'icon' => 'dashicons-chart-line',
			'desc' => 'داده ساختاریافته AccountingService در صفحه اصلی. پس از فعال‌سازی با Rich Results Test گوگل بررسی کنید.',
			'fields' => array(
				self::tg( 'schema_org', 'خروجی JSON-LD دفتر حقوقی در صفحه اصلی', 0 ),
				self::s( 'schema_type', 'نوع', array( 'AccountingService' => 'AccountingService', 'FinancialService' => 'FinancialService', 'ProfessionalService' => 'ProfessionalService' ), 'AccountingService' ),
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

	public static function orb_tokens() {
		return array( 'primary' => 'رنگ اصلی', 'btn-a' => 'رنگ دکمه', 'accent' => 'رنگ تأکیدی (طلایی)', 'muted' => 'رنگ کم‌رنگ' );
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

	private static function default_tree() {
		$leaf = function ( $t ) { return array( 'icon' => 'file', 'title' => $t ); };
		$biz  = function ( $title, $icon ) use ( $leaf ) {
			return array( 'icon' => $icon, 'title' => $title, 'items' => array( array( 'icon' => 'shield', 'title' => 'بیمه' ), array( 'icon' => 'calc', 'title' => 'مالیات' ), array( 'icon' => 'book', 'title' => 'حسابداری' ) ) );
		};
		return array(
			array( 'icon' => 'briefcase', 'title' => 'صاحب کسب‌وکار', 'desc' => 'کسب‌وکار خرد یا کوچک دارم', 'items' => array( $biz( 'خدماتی', 'users' ), $biz( 'بازرگانی', 'store' ), $biz( 'تولیدی', 'building' ) ) ),
			array( 'icon' => 'user', 'title' => 'نیروی کار', 'desc' => 'حقوق‌بگیر یا کارگر هستم', 'items' => array( array( 'icon' => 'scale', 'title' => 'قوانین کار', 'q' => 'قوانین کار' ), array( 'icon' => 'chat', 'title' => 'نکات مصاحبه استخدامی', 'q' => 'مصاحبه استخدامی' ) ) ),
		);
	}

	private static function default_services() {
		return array(
			array( 'icon' => 'wallet', 'title' => 'پس‌انداز و زندگی مالی', 'desc' => 'با کم شروع کن، درست بساز. هیچ درآمدی آن‌قدر کم نیست که نشود از آن ساخت.' ),
			array( 'icon' => 'trending-up', 'title' => 'اصلاح و رشد کسب‌وکار', 'desc' => 'با منابع خودت، نه با پول اضافه. کسب‌وکارت را درست بچین و سودش را ببین.' ),
			array( 'icon' => 'chart', 'title' => 'بررسی سرمایه‌گذاری', 'desc' => 'قبل از تصمیم، اعدادت را بررسی کن. با آگاهی قدم بردار، نه با حدس.' ),
			array( 'icon' => 'calc', 'title' => 'مشاوره مالی و حسابداری', 'desc' => 'مالیات، بیمه، قوانین کار و قرارداد؛ ساده و شفاف.' ),
		);
	}

	private static function default_workshop() {
		return array(
			array( 'icon' => 'book', 'title' => 'آموزش حسابداری', 'desc' => 'مقاله‌های پایه تا پیشرفته؛ از صفر تا نگه‌داشتن حساب‌وکتاب کسب‌وکارت.', 'lock' => 'free' ),
			array( 'icon' => 'shield', 'title' => 'قوانین مالیات و بیمه', 'desc' => 'چطور مالیات کمتری بدهیم؟ معافیت‌ها کدامند؟ آخرین قوانین به‌روز.', 'lock' => 'member' ),
			array( 'icon' => 'file', 'title' => 'فرم‌ها و قالب‌های آماده', 'desc' => 'فرم‌های آماده برای سرعت بیشتر و جا نیفتادن چیزی.', 'lock' => 'member' ),
			array( 'icon' => 'trending-up', 'title' => 'تحلیل روزانه بازار', 'desc' => 'هر روز یک تحلیل تازه از بازار؛ طلا، نقره، بورس.', 'lock' => 'member' ),
			array( 'icon' => 'award', 'title' => 'دوره‌های آموزشی', 'desc' => 'دوره‌های کامل و کاربردی.', 'badge' => 'به‌زودی', 'lock' => 'member' ),
		);
	}

	private static function default_downloads() {
		$t = array( 'حواله ورود و خروج انبار', 'رسید انبار', 'فرم تحویل کالا', 'فیش حقوقی', 'تنخواه‌گردان', 'تاییدیه حساب‌ها', 'فرم مرخصی', 'فرم اضافه‌کاری', 'قراردادها' );
		$out = array();
		foreach ( $t as $title ) {
			$out[] = array( 'icon' => 'file', 'title' => $title, 'lock' => 'member' );
		}
		return $out;
	}

	private static function default_plans() {
		return array(
			array( 'name' => 'رایگان', 'period' => '', 'desc' => 'شروع بدون هزینه', 'features' => "مقاله‌ها و آموزش‌های پایه\nجستجوی هشتگ\n{minutes} دقیقه مشاوره رایگان", 'btn_text' => 'شروع کن', 'btn_url' => '', 'featured' => 0 ),
			array( 'name' => 'اشتراک', 'period' => '', 'desc' => 'همه امکانات کارگاه', 'features' => "آخرین قوانین مالیات و بیمه\nتحلیل‌های روزانه بازار\nفرم‌ها و قالب‌های کامل\nمشاوره مالی تا بیش از ۲ ساعت\nدستیار حسابدار", 'btn_text' => 'دریافت اشتراک', 'btn_url' => '', 'badge' => 'پیشنهادی', 'featured' => 1 ),
		);
	}

	private static function default_steps() {
		return array(
			array( 'icon' => 'calendar', 'title' => 'رزرو مشاوره رایگان', 'desc' => 'یک جلسه کوتاه {minutes} دقیقه‌ای رزرو می‌کنی و وضعیتت را توضیح می‌دهی.' ),
			array( 'icon' => 'search', 'title' => 'بررسی اعداد و پرونده', 'desc' => 'اعدادت، پرونده مالیاتی و ساختار کسب‌وکارت را با هم بررسی می‌کنیم.' ),
			array( 'icon' => 'route', 'title' => 'برنامه عملی', 'desc' => 'یک مسیر ساده و قابل اجرا با همان منابعی که داری.' ),
			array( 'icon' => 'book', 'title' => 'خودت یاد می‌گیری', 'desc' => 'هدف ما این نیست که همیشه به ما نیاز داشته باشی؛ هدف این است که خودت بفهمی و تصمیم بگیری.' ),
			array( 'icon' => 'star', 'title' => 'اشتراک و ادامه مسیر', 'desc' => 'با اشتراک، به تحلیل‌ها، قوانین به‌روز، فرم‌ها و مشاوره بیشتر دسترسی داری.' ),
		);
	}

	private static function default_cases() {
		return array(
			array( 'icon' => 'file', 'title' => 'پرونده مالیاتی که اشتباه تشکیل شده بود', 'domain' => 'مالیات',
				'body' => 'یک کسب‌وکار خرد به‌جای پرونده شخصی، <strong>پرونده حقوقی</strong> تشکیل داده بود و درگیر سامانه مودیان و هزینه حسابدار بود. با بررسی پرونده و پایین بودن تراکنش‌های بانکی، پیشنهاد شد <strong>پرونده مشارکتی حقیقی</strong> تشکیل شود تا از معافیت‌های مربوط بهره‌مند شود و مالیات منطقی‌تری پرداخت کند. (شرایط هر پرونده متفاوت است.)' ),
			array( 'icon' => 'wallet', 'title' => 'فروش خوب، اما بدون نقدینگی', 'domain' => 'مدیریت مالی',
				'body' => 'کسب‌وکاری با فروش خوب اما مشکل شدید نقدینگی. ابتدا همه‌چیز را <strong>مستند</strong> کردیم، مسئول پیگیری گذاشتیم، با هر مشتری تاریخ پرداخت مشخص گرفتیم و سپس نحوه فروش را تغییر دادیم و بازپرداخت‌ها را کوتاه‌تر کردیم. نتیجه: نقدینگی برگشت.' ),
			array( 'icon' => 'coins', 'title' => 'صندوق ذخیره‌ای که نقدینگی را برگرداند', 'domain' => 'نقدینگی',
				'body' => 'کارفرما پول‌ها را سریع خرج خرید می‌کرد و نمی‌توانست حقوق‌ها را سر وقت بدهد. پیشنهاد شد یک <strong>صندوق ذخیره</strong> بسازد و فقط یک درصد از هر چک را به آن واریز کند. بعد از چند ماه نقدینگی برگشت و حقوق‌ها سر وقت پرداخت شد.' ),
		);
	}

	private static function default_track() {
		return array(
			array( 'period' => '۱۴۰۳', 'asset' => 'طلا', 'entry' => '۳٬۲۰۰', 'exit' => '۸٬۲۰۰ (فروردین ۱۴۰۴)', 'note' => '' ),
			array( 'period' => '۱۴۰۴', 'asset' => 'نقره', 'entry' => '۹۰٬۰۰۰', 'exit' => '۷۰۰٬۰۰۰ (بهمن ۱۴۰۴)', 'note' => '' ),
			array( 'period' => '۱۴۰۵', 'asset' => 'بورس — سیستم', 'entry' => '۴٬۰۰۰', 'exit' => '۵٬۰۰۰', 'note' => '' ),
			array( 'period' => '۱۴۰۵', 'asset' => 'بورس — غگلپا', 'entry' => '', 'exit' => '', 'note' => '۲۰٪ سود' ),
			array( 'period' => '۱۴۰۵', 'asset' => 'بورس — خودرو و بصادر', 'entry' => '۱۹ مرداد ۱۴۰۵', 'exit' => '', 'note' => 'بیش از ۲۵٪ سود' ),
		);
	}

	private static function default_faq() {
		return array(
			array( 'q' => 'مشاوره رایگان چطور است؟', 'a' => '{minutes} دقیقه مشاوره رایگان؛ اگر به نفعت بود ادامه می‌دهیم و اگر نه، هیچ هزینه‌ای نمی‌دهی. با اشتراک، زمان مشاوره بیشتر هم می‌شود.' ),
			array( 'q' => 'اشتراک شامل چه چیزهایی است؟', 'a' => 'دسترسی به آخرین قوانین مالیات و بیمه، تحلیل‌های روزانه بازار، فرم‌ها و قالب‌های کامل، مشاوره مالی بیشتر و دستیار حسابدار.' ),
			array( 'q' => 'آیا پیشنهاد خرید و فروش می‌دهید؟', 'a' => 'خیر. ما صرفاً بازار مناسب را بر اساس داده معرفی می‌کنیم و مسئولیت هر تصمیم سرمایه‌گذاری با خود کاربر است.' ),
			array( 'q' => 'این سایت برای چه کسانی است؟', 'a' => 'برای کسب‌وکارهای خرد و کوچک و نیروهای کار؛ نه شرکت‌های بزرگ.' ),
		);
	}

	private static function default_features() {
		return array(
			array( 'icon' => 'award', 'title' => 'تجربه واقعی', 'desc' => 'ده سال حسابداری، سال‌ها حسابدار تاجرهای بزرگ و از ۱۳۹۹ فعال در بازارهای مالی.' ),
			array( 'icon' => 'users', 'title' => 'کنار کسب‌وکارهای خرد', 'desc' => 'می‌دانیم کسب‌وکار کوچک چه فشارهایی دارد؛ ما کنار حقوق‌بگیرها و کاسب‌های خرد هستیم.' ),
			array( 'icon' => 'bulb', 'title' => 'خودت یاد می‌گیری', 'desc' => 'وابسته نمی‌شوی؛ با برنامه و عدد تصمیم می‌گیری، نه با حدس.' ),
		);
	}
}
