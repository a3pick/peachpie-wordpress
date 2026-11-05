<?php
defined( 'ABSPATH' ) || exit;

/**
 * اسکیمای تنظیمات: بخش‌ها، فیلدها و مقادیر پیش‌فرض.
 * همین اسکیما هم برای ساخت فرم ادمین، هم پاک‌سازی ورودی و هم مقادیر پیش‌فرض استفاده می‌شود.
 */
class WSK_Schema {

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
	private static function u( $id, $l, $d = '', $x = array() ) { return self::f( 'url', $id, $l, $d, $x ); }
	private static function img( $id, $l, $d = '', $x = array() ) { return self::f( 'image', $id, $l, $d, $x ); }
	private static function vid( $id, $l, $d = '', $x = array() ) { return self::f( 'video', $id, $l, $d, $x ); }
	private static function n( $id, $l, $d = 0, $x = array() ) { return self::f( 'number', $id, $l, $d, $x ); }
	private static function s( $id, $l, $opts, $d = '', $x = array() ) { return self::f( 'select', $id, $l, $d, array_merge( array( 'options' => $opts ), $x ) ); }
	private static function tg( $id, $l, $d = 1, $x = array() ) { return self::f( 'toggle', $id, $l, $d, $x ); }
	private static function c( $id, $l, $d = '', $x = array() ) { return self::f( 'color', $id, $l, $d, $x ); }
	private static function rep( $id, $l, $fields, $default = array(), $x = array() ) {
		return self::f( 'repeater', $id, $l, $default, array_merge( array( 'fields' => $fields ), $x ) );
	}
	private static function enabled() {
		return self::tg( 'enabled', 'فعال بودن این ماژول', 1, array( 'desc' => 'با غیرفعال کردن، شورت‌کد چیزی نمایش نمی‌دهد.' ) );
	}

	private static function slider_fields( $autoplay = 1, $delay = 4000 ) {
		return array(
			self::tg( 'autoplay', 'پخش خودکار', $autoplay ),
			self::n( 'delay', 'فاصله‌ی جابه‌جایی خودکار (میلی‌ثانیه)', $delay, array( 'min' => 1500, 'max' => 20000 ) ),
		);
	}

	/** انواع مرتب‌سازی قابل‌استفاده در فروشگاه. */
	public static function sort_keys() {
		return array(
			'relevance'    => 'مرتبط‌ترین',
			'most_viewed'  => 'پربازدیدترین',
			'latest'       => 'جدیدترین',
			'best_selling' => 'پرفروش‌ترین',
			'price_low'    => 'ارزان‌ترین',
			'price_high'   => 'گران‌ترین',
			'rating'       => 'بیشترین امتیاز',
		);
	}

	/* ---------- بخش‌ها ---------- */
	public static function groups() {
		return array(
			'base'    => 'تنظیمات پایه',
			'modules' => 'ماژول‌های صفحه',
			'shop'    => 'فروشگاه و قالب',
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
			'group' => 'base', 'label' => 'اطلاعات فروشگاه', 'icon' => 'dashicons-store',
			'desc'  => 'این اطلاعات در همه‌ی ماژول‌ها (استوری، قیمت‌ها، پیش‌فرض تصاویر) استفاده می‌شود.',
			'fields' => array(
				self::t( 'brand_name', 'نام فروشگاه (نمایش در استوری)', 'فروشگاه کالانه' ),
				self::t( 'brand_desc', 'توضیح کوتاه فروشگاه', 'برترین انتخاب برای خرید' ),
				self::img( 'brand_logo', 'تصویر پروفایل (آواتار استوری)', WSK_URL . 'assets/demo/avatar.svg' ),
				self::u( 'instagram', 'لینک اینستاگرام', '' ),
				self::t( 'currency_label', 'برچسب واحد پول (وقتی آیکون ندارید)', 'تومان' ),
				self::img( 'currency_icon', 'آیکون واحد پول (SVG/PNG) — اختیاری', '', array( 'desc' => 'اگر تصویر انتخاب شود به‌جای متن نمایش داده می‌شود. SVG را باید در کتابخانه‌ی رسانه مجاز کرده باشید؛ یا آدرس مستقیم فایل را بنویسید.' ) ),
				self::img( 'placeholder', 'تصویر پیش‌فرض (محصول/مقاله‌ی بدون تصویر)', WSK_URL . 'assets/demo/placeholder.svg' ),
				self::tg( 'persian_digits', 'نمایش ارقام فارسی در قیمت و زمان مطالعه', 1 ),
			),
		);

		$s['appearance'] = array(
			'group' => 'base', 'label' => 'رنگ و ظاهر', 'icon' => 'dashicons-art',
			'desc'  => 'رنگ‌ها و فونت واحد برای همه‌ی ماژول‌ها.',
			'fields' => array(
				self::s( 'font_mode', 'فونت ماژول‌ها', array( 'inherit' => 'ارث‌بری از قالب (پیشنهادی)', 'custom_name' => 'نام فونتِ از قبل بارگذاری‌شده' ), 'inherit' ),
				self::t( 'font_family', 'نام فونت (فقط برای حالت «نام فونت»)', 'Vazirmatn' ),
				self::n( 'radius', 'گردی گوشه کارت‌ها (px)', 12, array( 'min' => 0, 'max' => 40 ) ),
				self::n( 'container_max', 'حداکثر عرض ماژول‌ها (px)', 1300, array( 'min' => 600, 'max' => 2000 ) ),
				self::s( 'direction', 'جهت ماژول‌ها', array( 'auto' => 'مطابق جهت سایت', 'rtl' => 'همیشه راست‌به‌چپ', 'ltr' => 'همیشه چپ‌به‌راست' ), 'rtl' ),
				self::c( 'primary', 'رنگ اصلی برند (هاور، خط عنوان، حلقه استوری)', '#ea7e21' ),
				self::c( 'accent', 'رنگ تأکیدی (تخفیف، برچسب‌ها)', '#ef4056' ),
				self::c( 'ink', 'رنگ عنوان‌ها', '#3f4254' ),
				self::c( 'text', 'رنگ متن', '#444444' ),
				self::c( 'muted', 'رنگ متن کم‌رنگ', '#888888' ),
				self::c( 'price', 'رنگ قیمت', '#111111' ),
				self::c( 'surface', 'پس‌زمینه کارت‌ها', '#ffffff' ),
				self::c( 'surface_alt', 'پس‌زمینه ثانویه (نوار مرتب‌سازی، باکس‌ها)', '#fafafa' ),
				self::c( 'border', 'رنگ حاشیه کارت‌ها', '#eaeaea' ),
				self::c( 'danger', 'رنگ «ناموجود»', '#dc3545' ),
			),
		);

		$s['darkmode'] = array(
			'group' => 'base', 'label' => 'حالت روشن / تاریک', 'icon' => 'dashicons-lightbulb',
			'shortcode' => '[kalane_theme_toggle]',
			'desc'  => 'کاربر با دکمه‌ی تغییر حالت بین روشن و تاریک جابه‌جا می‌شود و انتخابش در مرورگرش می‌ماند. به‌صورت پیش‌فرض فقط ماژول‌های این افزونه تاریک می‌شوند؛ برای کل سایت گزینه‌ی «تاریک‌سازی سراسری» را روشن کنید.',
			'fields' => array(
				self::tg( 'enabled', 'فعال‌سازی حالت تاریک', 0 ),
				self::s( 'default_mode', 'حالت پیش‌فرض بازدیدکننده‌ی جدید', array( 'light' => 'روشن', 'dark' => 'تاریک', 'auto' => 'مطابق تنظیم سیستم‌عامل کاربر' ), 'light' ),
				self::s( 'toggle_position', 'دکمه‌ی شناور تغییر حالت', array( 'none' => 'نمایش نده (فقط شورت‌کد)', 'bottom-start' => 'پایین — ابتدای صفحه (راست در RTL)', 'bottom-end' => 'پایین — انتهای صفحه (چپ در RTL)' ), 'bottom-end' ),
				self::tg( 'global', 'تاریک‌سازی سراسری (بدنه، هدر/فوتر وودمارت، کارت محصول، سایدبار، فرم‌ها)', 0, array( 'desc' => 'روی وودمارت/المنتور تست‌شده نیست؛ ابتدا روی Staging امتحان کنید. بخش‌هایی که رنگ دستی دارند با سلکتورهای پایین هماهنگ می‌شوند.' ) ),
				self::ta( 'surface_selectors', 'سلکتورهایی که در حالت تاریک «پس‌زمینه کارت» بگیرند (هر خط یک سلکتور)', '', array( 'san' => 'selectors', 'rows' => 3, 'class' => 'code', 'desc' => 'مثال: .my-box یا .elementor-element-abc123' ) ),
				self::ta( 'surface_alt_selectors', 'سلکتورهایی که «پس‌زمینه ثانویه» بگیرند', '', array( 'san' => 'selectors', 'rows' => 3, 'class' => 'code' ) ),
				self::c( 'primary', 'رنگ اصلی برند (تاریک)', '#f59a4a' ),
				self::c( 'accent', 'رنگ تأکیدی (تاریک)', '#ff6b7d' ),
				self::c( 'ink', 'رنگ عنوان‌ها (تاریک)', '#f1f2f6' ),
				self::c( 'text', 'رنگ متن (تاریک)', '#c9cdd8' ),
				self::c( 'muted', 'رنگ متن کم‌رنگ (تاریک)', '#8d93a5' ),
				self::c( 'price', 'رنگ قیمت (تاریک)', '#ffffff' ),
				self::c( 'surface', 'پس‌زمینه کارت‌ها (تاریک)', '#1a1d27' ),
				self::c( 'surface_alt', 'پس‌زمینه ثانویه (تاریک)', '#14161e' ),
				self::c( 'border', 'رنگ حاشیه (تاریک)', '#2b3040' ),
				self::c( 'danger', 'رنگ «ناموجود» (تاریک)', '#f87171' ),
				self::c( 'page_bg', 'پس‌زمینه‌ی صفحه (فقط در تاریک‌سازی سراسری)', '#0f1117' ),
			),
		);

		/* ===== ماژول‌ها ===== */
		$s['stories'] = array(
			'group' => 'modules', 'label' => 'استوری‌ها', 'icon' => 'dashicons-video-alt3',
			'shortcode' => '[kalane_stories]',
			'desc'  => 'حلقه‌های استوری مثل اینستاگرام؛ با کلیک، ویدیو در یک پنجره‌ی تمام‌صفحه پخش می‌شود. نام و آواتار از «اطلاعات فروشگاه» می‌آید.',
			'fields' => array(
				self::enabled(),
				self::t( 'ig_label', 'متن دکمه‌ی اینستاگرام داخل استوری', 'اینستاگرام ما' ),
				self::rep(
					'items', 'استوری‌ها',
					array(
						self::img( 'cover', 'عکس کاور' ),
						self::vid( 'video', 'ویدیو (MP4)' ),
						self::t( 'alt', 'متن جایگزین تصویر (دسترس‌پذیری)', '' ),
					),
					array(),
					array( 'title_field' => 'alt' )
				),
			),
		);

		$s['banners'] = array(
			'group' => 'modules', 'label' => 'بنرها', 'icon' => 'dashicons-format-image',
			'shortcode' => '[kalane_banner]',
			'desc'  => 'اگر بیش از یک بنر ثبت شود، به‌صورت اسلایدر نمایش داده می‌شود. شمارش کلیک هر بنر در «آمار» دیده می‌شود.',
			'fields' => array_merge(
				array(
					self::enabled(),
					self::s( 'style', 'سبک بنر', array( 'crescent' => 'هلالی (برش گوشه‌های کناری با ماسک CSS)', 'side_image' => 'تصویر حاشیه‌ی دلخواه (ماسک تصویری)', 'plain' => 'ساده' ), 'crescent' ),
					self::img( 'mask_image', 'تصویر حاشیه (فقط در سبک «تصویر حاشیه»)' ),
					self::s( 'ratio', 'نسبت تصویر بنر', array( '21/6' => '21:6 (عریض)', '16/5' => '16:5', '16/9' => '16:9', 'auto' => 'اصل تصویر' ), '21/6' ),
					self::n( 'max_height', 'حداکثر ارتفاع در دسکتاپ (px) — ۰ = بدون محدودیت', 320, array( 'min' => 0, 'max' => 900 ) ),
				),
				self::slider_fields( 1, 5000 ),
				array(
					self::rep(
						'items', 'بنرها',
						array(
							self::img( 'image', 'تصویر بنر' ),
							self::u( 'link', 'لینک مقصد' ),
							self::t( 'alt', 'متن جایگزین تصویر', '' ),
							self::tg( 'new_tab', 'باز شدن در تب جدید', 0 ),
						),
						self::demo_banners(),
						array( 'title_field' => 'alt' )
					),
				)
			),
		);

		$s['categories'] = array(
			'group' => 'modules', 'label' => 'دسته‌بندی‌های ویژه', 'icon' => 'dashicons-screenoptions',
			'shortcode' => '[kalane_categories]',
			'desc'  => 'ردیف کارت‌های دسته‌بندی. می‌توانید دستی وارد کنید یا مستقیم از دسته‌های ووکامرس (با تصویر شاخص خودشان) بخوانید.',
			'fields' => array(
				self::enabled(),
				self::t( 'title', 'عنوان بخش (خالی = بدون عنوان)', 'برترین دسته‌بندی‌ها' ),
				self::s( 'source', 'منبع داده', array( 'manual' => 'دستی (فهرست پایین)', 'woo' => 'خودکار از دسته‌های ووکامرس' ), 'manual' ),
				self::n( 'woo_limit', 'تعداد دسته (منبع خودکار)', 12, array( 'min' => 1, 'max' => 60 ) ),
				self::tg( 'woo_hide_empty', 'پنهان کردن دسته‌های خالی (منبع خودکار)', 1 ),
				self::s( 'woo_parent', 'کدام دسته‌ها (منبع خودکار)', array( 'top' => 'فقط دسته‌های اصلی', 'all' => 'همه‌ی دسته‌ها' ), 'top' ),
				self::rep(
					'items', 'دسته‌بندی‌ها (منبع دستی)',
					array(
						self::t( 'title', 'عنوان' ),
						self::img( 'image', 'تصویر' ),
						self::u( 'link', 'لینک مقصد' ),
					),
					self::demo_categories(),
					array( 'title_field' => 'title' )
				),
			),
		);

		$s['divider'] = array(
			'group' => 'modules', 'label' => 'جداکننده‌ی عنوان‌دار', 'icon' => 'dashicons-minus',
			'shortcode' => '[kalane_divider text="تخفیف‌های ویژه"]',
			'desc'  => 'خط افقی با متن وسط. متن را می‌توان در خود شورت‌کد (text) هم داد.',
			'fields' => array(
				self::enabled(),
				self::t( 'text', 'متن پیش‌فرض', 'عنوان بخش' ),
			),
		);

		$s['product_slider'] = array(
			'group' => 'modules', 'label' => 'اسلایدر محصولات', 'icon' => 'dashicons-products',
			'shortcode' => '[kalane_product_slider type="best_sellers" limit="10" category="slug1,slug2"]',
			'desc'  => 'نیازمند ووکامرس. type: latest / best_sellers / sale / most_viewed / featured / random. مقادیر زیر پیش‌فرض‌اند و ویژگی‌های شورت‌کد روی آن‌ها اولویت دارند.',
			'fields' => array_merge(
				array(
					self::enabled(),
					self::n( 'limit', 'تعداد پیش‌فرض محصول', 10, array( 'min' => 1, 'max' => 40 ) ),
					self::s( 'type', 'نوع پیش‌فرض', array( 'latest' => 'جدیدترین', 'best_sellers' => 'پرفروش‌ترین', 'sale' => 'تخفیف‌دار', 'most_viewed' => 'پربازدیدترین', 'featured' => 'ویژه', 'random' => 'تصادفی' ), 'latest' ),
					self::tg( 'show_discount', 'نمایش درصد تخفیف و قیمت قبلی', 1 ),
					self::tg( 'in_stock_only', 'فقط محصولات موجود', 0 ),
					self::t( 'empty_text', 'پیام وقتی محصولی نیست', 'محصولی یافت نشد.' ),
					self::t( 'out_of_stock', 'متن ناموجود', 'موجود نیست' ),
				),
				self::slider_fields( 1, 4000 ),
				array(
					self::n( 'cache_minutes', 'کش نتیجه‌ی کوئری (دقیقه) — ۰ = بدون کش', 10, array( 'min' => 0, 'max' => 1440, 'desc' => 'برای سایت‌های پرترافیک مصرف دیتابیس را کم می‌کند.' ) ),
				)
			),
		);

		$s['blog_slider'] = array(
			'group' => 'modules', 'label' => 'اسلایدر مقالات', 'icon' => 'dashicons-admin-post',
			'shortcode' => '[kalane_blog_slider limit="10" category="slug"]',
			'desc'  => 'آخرین مقالات با زمان مطالعه و ماه انتشار.',
			'fields' => array_merge(
				array(
					self::enabled(),
					self::n( 'limit', 'تعداد پیش‌فرض مقاله', 10, array( 'min' => 1, 'max' => 40 ) ),
					self::n( 'chars_per_min', 'سرعت مطالعه (کاراکتر در دقیقه)', 800, array( 'min' => 200, 'max' => 3000 ) ),
					self::t( 'empty_text', 'پیام وقتی مقاله‌ای نیست', 'مقاله‌ای یافت نشد.' ),
				),
				self::slider_fields( 1, 4500 )
			),
		);

		/* ===== فروشگاه و قالب ===== */
		$s['shop'] = array(
			'group' => 'shop', 'label' => 'مرتب‌سازی و ورود اجباری', 'icon' => 'dashicons-filter',
			'desc'  => 'نیازمند ووکامرس. نوار مرتب‌سازی سبک دیجی‌کالا برای صفحه‌های فروشگاه/آرشیو.',
			'fields' => array(
				self::tg( 'sortbar', 'نمایش نوار مرتب‌سازی دیجی‌کالایی', 1 ),
				self::tg( 'hide_default_ordering', 'پنهان کردن لیست کشویی مرتب‌سازی قالب', 1 ),
				self::s( 'default_sort', 'مرتب‌سازی پیش‌فرض نوار', array_merge( array( '' => 'پیش‌فرض ووکامرس' ), self::sort_keys() ), '' ),
				self::rep(
					'sort_items', 'گزینه‌های مرتب‌سازی (به‌ترتیب نمایش)',
					array(
						self::s( 'key', 'نوع', self::sort_keys(), 'latest' ),
						self::t( 'label', 'برچسب نمایشی' ),
					),
					self::default_sort_items(),
					array( 'title_field' => 'label' )
				),
				self::tg( 'force_login', 'ورود اجباری قبل از تسویه‌حساب (پس از ورود، برگشت به تسویه‌حساب)', 0, array( 'desc' => 'کاربر مهمان در صفحه‌ی تسویه‌حساب به «حساب من» هدایت می‌شود. اگر فروش مهمان می‌خواهید خاموش بگذارید.' ) ),
			),
		);

		$s['theme'] = array(
			'group' => 'shop', 'label' => 'اصلاحات وودمارت و المنتور', 'icon' => 'dashicons-admin-appearance',
			'desc'  => 'همه‌ی CSS/JS که قبلاً در Code Snippets بود (فقط با قالب Woodmart معنا دارد). هر کدام جداگانه قابل‌خاموش‌شدن است.',
			'fields' => array(
				self::tg( 'woodmart_css', 'استایل توضیحات دسته/محصول، سایدبار فیلتر، جدول‌ها و نوار ابزار فروشگاه', 1 ),
				self::tg( 'hide_timer_days', 'مخفی کردن «روز» در تایمر تخفیف وودمارت', 1 ),
				self::tg( 'category_align', 'چینش مرکزی دسته‌ها (کلاس CSS: category-align روی ظرف المنتور)', 1 ),
				self::tg( 'hide_stories_nav', 'مخفی کردن دکمه‌های ناوبری استوری قالب (webisoft-stories-nav-btn)', 1 ),
				self::tg( 'logo_shimmer', 'افکت درخشش لوگو (کلاس‌های site-logo و logo-webisoft)', 0 ),
				self::tg( 'hover_tabs', 'تب‌های المنتور با هاور (کلاس CSS: hoverTabs روی ویجت Tabs)', 1 ),
				self::tg( 'convert_titles', 'تبدیل H3 عنوان محصول به span در ظرف‌های دارای کلاس convert-titles-group (بهبود سلسله‌مراتب هدینگ سئو)', 1 ),
			),
		);

		/* ===== سیستم ===== */
		$s['system'] = array(
			'group' => 'system', 'label' => 'سیستم و آمار', 'icon' => 'dashicons-admin-generic',
			'desc'  => '',
			'fields' => array(
				self::tg( 'assets_everywhere', 'بارگذاری CSS/JS در همه‌ی صفحه‌ها', 1, array( 'desc' => 'خاموش: فقط وقتی شورت‌کدی در صفحه اجرا شود بارگذاری می‌شود (برای صفحه‌های المنتور که شورت‌کد را بعد از head رندر می‌کنند، ممکن است CSS در فوتر بیاید).' ) ),
				self::tg( 'track_stats', 'ثبت آمار کلیک بنر/دسته و نمایش استوری و بازدید محصول', 1 ),
				self::n( 'retention_days', 'نگهداری آمار (روز)', 180, array( 'min' => 7, 'max' => 3650 ) ),
				self::ta( 'custom_css', 'CSS سفارشی', '', array( 'rows' => 6, 'class' => 'code', 'san' => 'css' ) ),
				self::tg( 'delete_on_uninstall', 'حذف همه‌ی داده‌ها (تنظیمات + جدول آمار) هنگام حذف افزونه', 0 ),
			),
		);

		self::$sections = $s;
		return $s;
	}

	/** محتوای نمونه (تصویر SVG همراه افزونه) تا ظاهر از همان ابتدا شکل بگیرد؛ از پنل جایگزین می‌شود. */
	private static function demo_banners() {
		$out = array();
		foreach ( array( 1 => 'تخفیف‌های ویژه', 2 => 'جدیدترین محصولات', 3 => 'ارسال سریع' ) as $i => $alt ) {
			$out[] = array( 'image' => WSK_URL . 'assets/demo/banner-' . $i . '.svg', 'link' => '', 'alt' => $alt, 'new_tab' => 0 );
		}
		return $out;
	}

	private static function demo_categories() {
		$out = array();
		$map = array( 'mobile' => 'موبایل', 'laptop' => 'لپ‌تاپ', 'fashion' => 'پوشاک', 'home' => 'خانه و آشپزخانه', 'watch' => 'ساعت', 'book' => 'کتاب', 'gift' => 'هدیه', 'bag' => 'کیف و کفش' );
		foreach ( $map as $k => $title ) {
			$out[] = array( 'title' => $title, 'image' => WSK_URL . 'assets/demo/cat-' . $k . '.svg', 'link' => '' );
		}
		return $out;
	}

	private static function default_sort_items() {
		$out = array();
		foreach ( array( 'relevance', 'most_viewed', 'latest', 'best_selling', 'price_low', 'price_high' ) as $k ) {
			$out[] = array( 'key' => $k, 'label' => self::sort_keys()[ $k ] );
		}
		return $out;
	}

	public static function defaults() {
		$out = array();
		foreach ( self::sections() as $sid => $sec ) {
			$out[ $sid ] = self::field_defaults( $sec['fields'] );
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
}
