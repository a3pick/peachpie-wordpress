<?php
/**
 * Environment check and an end-to-end self-test that runs on the real site:
 * imports a tiny built-in sample, verifies every result, undoes it and cleans up.
 *
 * Everything it creates uses "SBPI Selftest" names and sbpi-t-* attribute slugs,
 * and is removed at the end even when a check fails.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Self-test.
 */
final class SBPI_Selftest {

	/** @var array[] Results: [status, label, detail]. */
	private static $results = array();

	/**
	 * Server / site requirements.
	 *
	 * @return array[] [status ok|warn|bad, label, detail]
	 */
	public static function environment() {
		global $wp_version;
		$out = array();
		$mem = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		$met = (int) ini_get( 'max_execution_time' );
		$up  = min( wp_convert_hr_to_bytes( (string) ini_get( 'upload_max_filesize' ) ), wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) );
		$wc  = defined( 'WC_VERSION' ) ? WC_VERSION : '';

		$out[] = array( version_compare( PHP_VERSION, '8.0', '>=' ) ? 'ok' : ( version_compare( PHP_VERSION, '7.4', '>=' ) ? 'warn' : 'bad' ), 'PHP', PHP_VERSION . ( version_compare( PHP_VERSION, '8.0', '<' ) ? ' — به 8.1 یا بالاتر ارتقا دهید' : '' ) );
		$out[] = array( version_compare( $wp_version, '6.2', '>=' ) ? 'ok' : 'bad', 'وردپرس', $wp_version );
		$out[] = array( $wc && version_compare( $wc, '7.0', '>=' ) ? 'ok' : 'bad', 'ووکامرس', $wc ? $wc : 'فعال نیست' );
		$out[] = array( class_exists( 'ZipArchive' ) ? 'ok' : 'bad', 'اکستنشن zip', class_exists( 'ZipArchive' ) ? 'فعال' : 'غیرفعال — خواندن/ساخت XLSX ممکن نیست (CSV کار می‌کند)' );
		$out[] = array( function_exists( 'mb_strlen' ) ? 'ok' : 'bad', 'اکستنشن mbstring', function_exists( 'mb_strlen' ) ? 'فعال' : 'غیرفعال — متن فارسی درست پردازش نمی‌شود' );
		$out[] = array( $mem < 0 || $mem >= 256 * MB_IN_BYTES ? 'ok' : ( $mem >= 128 * MB_IN_BYTES ? 'warn' : 'bad' ), 'حافظه PHP', ( $mem < 0 ? 'نامحدود' : ini_get( 'memory_limit' ) ) . ( $mem >= 0 && $mem < 256 * MB_IN_BYTES ? ' — برای فایل‌های بزرگ 256M پیشنهاد می‌شود' : '' ) );
		$out[] = array( 0 === $met || $met >= 30 ? 'ok' : 'warn', 'زمان اجرا', ( 0 === $met ? 'نامحدود' : $met . ' ثانیه' ) . ' — درون‌ریزی مرحله‌ای است و با هر مقداری کار می‌کند' );
		$out[] = array( $up >= 8 * MB_IN_BYTES ? 'ok' : 'warn', 'حداکثر آپلود', size_format( $up ) );
		$hpos = class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$out[] = array( 'ok', 'HPOS', $hpos ? 'فعال (سازگار)' : 'غیرفعال (سازگار)' );
		$seo   = SBPI_SEO::seo_plugin();
		$out[] = array( 'aioseo' === $seo ? 'warn' : 'ok', 'افزونه سئو', $seo ? ( 'aioseo' === $seo ? 'AIOSEO — متا پر نمی‌شود' : $seo ) : 'ندارد — متا توسط همین افزونه چاپ می‌شود' );
		$out[] = array( 'ok', 'برند ووکامرس', taxonomy_exists( 'product_brand' ) ? 'طبقه‌بندی product_brand' : 'ویژگی «برند» (ووکامرس قدیمی‌تر از 9.6)' );
		$out[] = array( wp_using_ext_object_cache() ? 'ok' : 'warn', 'کش آبجکت', wp_using_ext_object_cache() ? 'فعال' : 'غیرفعال — برای سایت‌های بزرگ Redis/Memcached پیشنهاد می‌شود' );
		return $out;
	}

	/**
	 * Record a check.
	 *
	 * @param bool   $ok     Passed.
	 * @param string $label  Label.
	 * @param string $detail Detail on failure/success.
	 */
	private static function check( $ok, $label, $detail = '' ) {
		self::$results[] = array( $ok ? 'ok' : 'bad', $label, $detail );
		return $ok;
	}

	/**
	 * Run the full self-test.
	 *
	 * @return array[] Results.
	 */
	public static function run() {
		self::$results = array();
		$tag           = substr( md5( uniqid( '', true ) ), 0, 6 );
		$b1            = 'selftest-' . $tag . '-a';
		$b2            = 'selftest-' . $tag . '-b';
		$deadline      = microtime( true ) + 120;
		$ids           = array();
		$had_brand     = (bool) wc_attribute_taxonomy_id_by_name( 'brand' );

		try {
			$global                    = SBPI_Admin::default_global();
			$global['status']          = 'draft';
			$global['update_mode']     = 'update';
			$global['auto_images']     = 0;
			$global['faq']             = 1;
			$global['gen_description'] = 1;
			$global['auto_sku']        = 1;
			$global['sku_prefix']      = 'SBPIT-';
			$global['cond_texts']      = array();

			$plan = SBPI_Planner::build( self::sample( '1000000' ), self::settings() + array( 'global' => $global ) );
			self::check( 2 === count( $plan['products'] ), 'ساخت برنامه از فایل نمونه', count( $plan['products'] ) . ' محصول (انتظار: ۲)' );
			$by_type = array();
			foreach ( $plan['products'] as $p ) {
				$by_type[ $p['type'] ] = $p;
			}
			self::check( isset( $by_type['variable'], $by_type['simple'] ), 'تشخیص محصول متغیر و ساده', implode( '، ', array_keys( $by_type ) ) );

			// 1. First import.
			foreach ( $plan['products'] as $spec ) {
				$state = array( 'batch' => $b1 );
				$res   = SBPI_Importer::import( $spec, $global, $state, $deadline );
				self::check( $res['done'] && 'created' === $res['action'], 'ساخت «' . $spec['title'] . '»', $res['message'] );
				$ids[ $spec['type'] ] = (int) $res['id'];
			}

			$var = isset( $ids['variable'] ) ? wc_get_product( $ids['variable'] ) : null;
			$sim = isset( $ids['simple'] ) ? wc_get_product( $ids['simple'] ) : null;
			if ( self::check( $var && $var->is_type( 'variable' ) && $sim && $sim->is_type( 'simple' ), 'نوع محصولات در ووکامرس' ) ) {
				self::check( 'draft' === $var->get_status(), 'وضعیت پیش‌نویس', $var->get_status() );
				$children = $var->get_children();
				self::check( 2 === count( $children ), 'تعداد تنوع‌ها', count( $children ) . ' (انتظار: ۲)' );
				$prices = array();
				$skus   = array();
				foreach ( $children as $vid ) {
					$v        = wc_get_product( $vid );
					$prices[] = $v->get_regular_price();
					$skus[]   = $v->get_sku();
				}
				self::check( array( '1000000', '1000000' ) === $prices, 'قیمت تنوع‌ها', implode( '، ', $prices ) );
				self::check( count( array_filter( array_unique( $skus ) ) ) === 2, 'SKU یکتای تنوع‌ها', implode( '، ', $skus ) );
				self::check( '700000' === $sim->get_regular_price(), 'قیمت محصول ساده', $sim->get_regular_price() );

				$attrs = $var->get_attributes();
				self::check( isset( $attrs['pa_sbpi-t-color'] ) && $attrs['pa_sbpi-t-color']->get_variation(), 'ویژگی سراسری متغیر (رنگ)', implode( '، ', array_keys( $attrs ) ) );
				$terms = wc_get_product_terms( $var->get_id(), 'pa_sbpi-t-color', array( 'fields' => 'names' ) );
				sort( $terms );
				self::check( array( 'Blue', 'Red' ) === $terms, 'مقادیر ویژگی', implode( '، ', $terms ) );

				$cats = wc_get_product_terms( $var->get_id(), 'product_cat', array( 'fields' => 'all' ) );
				$leaf = $cats ? $cats[0] : null;
				self::check( $leaf && 'SBPI Selftest Sub' === $leaf->name && $leaf->parent, 'دسته‌بندی چندسطحی', $leaf ? $leaf->name : '—' );

				self::check( '' !== (string) $var->get_meta( '_sbpi_key' ), 'کلید شناسایی محصول' );
				$seo_key = array( 'yoast' => '_yoast_wpseo_title', 'rankmath' => 'rank_math_title' );
				$plugin  = SBPI_SEO::seo_plugin();
				$tkey    = isset( $seo_key[ $plugin ] ) ? $seo_key[ $plugin ] : SBPI_SEO::META_TITLE;
				self::check( '' !== (string) get_post_meta( $var->get_id(), $tkey, true ), 'عنوان سئو در ' . ( $plugin ? $plugin : 'متای داخلی' ), get_post_meta( $var->get_id(), $tkey, true ) );
				self::check( false !== strpos( $var->get_description(), '<h2>' ), 'توضیحات تولیدشده' );
				self::check( is_array( $sim->get_meta( '_sbpi_faq' ) ) && $sim->get_meta( '_sbpi_faq' ), 'سؤالات متداول از واژه‌نامه' );

				$markup = apply_filters( 'woocommerce_structured_data_product', array( 'offers' => array( array( '@type' => 'Offer' ) ) ), $sim );
				self::check( isset( $markup['brand']['name'] ) && 'SBPI Test Brand' === $markup['brand']['name'], 'Schema: برند', isset( $markup['brand']['name'] ) ? $markup['brand']['name'] : '—' );
				self::check( isset( $markup['offers'][0]['itemCondition'] ) && false !== strpos( $markup['offers'][0]['itemCondition'], 'UsedCondition' ), 'Schema: وضعیت کالا (استوک ← Used)', isset( $markup['offers'][0]['itemCondition'] ) ? $markup['offers'][0]['itemCondition'] : '—' );
			}

			// 2. Re-import with a new price: same products, no duplicates, snapshot taken.
			$plan2 = SBPI_Planner::build( self::sample( '1100000' ), self::settings() + array( 'global' => $global ) );
			foreach ( $plan2['products'] as $spec ) {
				$state = array( 'batch' => $b2 );
				$res   = SBPI_Importer::import( $spec, $global, $state, $deadline );
				self::check( 'updated' === $res['action'] && isset( $ids[ $spec['type'] ] ) && $ids[ $spec['type'] ] === (int) $res['id'], 'اجرای دوباره بدون محصول تکراری (' . $spec['type'] . ')', $res['action'] . ' #' . $res['id'] );
			}
			if ( $var ) {
				$var = wc_get_product( $ids['variable'] );
				self::check( 2 === count( $var->get_children() ), 'بدون تنوع تکراری', count( $var->get_children() ) . ' تنوع' );
				$v1 = wc_get_product( $var->get_children()[0] );
				self::check( '1100000' === $v1->get_regular_price(), 'به‌روزرسانی قیمت', $v1->get_regular_price() );
				self::check( metadata_exists( 'post', $var->get_id(), SBPI_Snapshot::key( $b2 ) ), 'ذخیره وضعیت قبلی برای بازگردانی' );

				// 3. Undo the update: price must return to the original.
				SBPI_Importer::rollback( $b2, microtime( true ) + 60 );
				$v1 = wc_get_product( $var->get_children()[0] );
				self::check( '1000000' === $v1->get_regular_price(), 'بازگردانی به‌روزرسانی (قیمت قبلی)', $v1->get_regular_price() );
				self::check( ! metadata_exists( 'post', $var->get_id(), SBPI_Snapshot::key( $b2 ) ), 'پاک شدن snapshot پس از بازگردانی' );
			}

			// 4. Undo the creation: products and variations must disappear.
			$children = $var ? $var->get_children() : array();
			SBPI_Importer::rollback( $b1, microtime( true ) + 60 );
			$left = array_filter( array_merge( array_values( $ids ), $children ), 'get_post' );
			self::check( ! $left, 'بازگردانی ساخت (حذف محصولات و تنوع‌ها)', $left ? 'باقی‌مانده: ' . implode( '، ', $left ) : '' );
		} catch ( Throwable $e ) {
			self::check( false, 'خطای غیرمنتظره', $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		} finally {
			self::cleanup( $ids, array( $b1, $b2 ), $had_brand );
		}
		return self::$results;
	}

	/**
	 * Built-in sample (same shape as a parsed supplier file).
	 *
	 * @param string $price Price of the "new" row.
	 * @return array Parsed sheets.
	 */
	private static function sample( $price ) {
		return array(
			array(
				'name'     => 'SBPI-SELFTEST',
				'kind'     => 'products',
				'title'    => '',
				'headers'  => array( 'MODEL', 'حافظه', 'رنگ', 'GRADE', 'قیمت' ),
				'rows'     => array(
					array( 'line' => 2, 'cells' => array( 'SBPI Selftest Phone', '128 GB', 'Red | Blue', 'نو', $price ), 'section' => array() ),
					array( 'line' => 3, 'cells' => array( 'SBPI Selftest Phone', '128 GB', 'Red', 'استوک', '700000' ), 'section' => array() ),
				),
				'notes'    => array(),
				'glossary' => array(),
				'samples'  => array(),
			),
			array(
				'name'     => 'SBPI-SELFTEST-GLOSSARY',
				'kind'     => 'glossary',
				'title'    => '',
				'headers'  => array(),
				'rows'     => array(),
				'notes'    => array(),
				'glossary' => array( array( 'استوک', 'متن آزمایشی خودآزمایی.' ) ),
				'samples'  => array(),
			),
		);
	}

	/**
	 * Mapping for the sample (test-only attribute slugs).
	 *
	 * @return array
	 */
	private static function settings() {
		return array(
			'sheets' => array(
				0 => array(
					'enabled'   => true,
					'category'  => 'SBPI Selftest > SBPI Selftest Sub',
					'brand'     => 'SBPI Test Brand',
					'title_tpl' => '{model} {split}',
					'columns'   => array(
						'0' => array( 'role' => 'model', 'name' => '', 'slug' => '', 'split' => false ),
						'1' => array( 'role' => 'split_attr', 'name' => 'SBPI تست حافظه', 'slug' => 'sbpi-t-storage', 'split' => false ),
						'2' => array( 'role' => 'var_attr', 'name' => 'SBPI تست رنگ', 'slug' => 'sbpi-t-color', 'split' => true ),
						'3' => array( 'role' => 'split_attr', 'name' => 'SBPI تست وضعیت', 'slug' => 'sbpi-t-cond', 'split' => false ),
						'4' => array( 'role' => 'price', 'name' => '', 'slug' => '', 'split' => false ),
					),
				),
			),
		);
	}

	/**
	 * Remove everything the test created.
	 *
	 * @param int[]    $ids     Product IDs.
	 * @param string[] $batches   Batch IDs.
	 * @param bool     $had_brand Site already had a pa_brand attribute.
	 */
	private static function cleanup( array $ids, array $batches, $had_brand ) {
		foreach ( $batches as $b ) {
			SBPI_Importer::rollback( $b, microtime( true ) + 30 );
			delete_option( 'sbpi_log_' . $b );
		}
		foreach ( $ids as $id ) {
			$p = wc_get_product( $id );
			if ( $p ) {
				$p->delete( true );
			}
		}
		foreach ( array( 'sbpi-t-storage', 'sbpi-t-color', 'sbpi-t-cond', 'brand' ) as $slug ) {
			$tax = wc_attribute_taxonomy_name( $slug );
			if ( 'brand' === $slug ) {
				// Only the test term, never the site's own brand attribute.
				$t = taxonomy_exists( $tax ) ? get_term_by( 'name', 'SBPI Test Brand', $tax ) : false;
				if ( $t ) {
					wp_delete_term( $t->term_id, $tax );
				}
				$aid = wc_attribute_taxonomy_id_by_name( 'brand' );
				if ( ! $had_brand && $aid ) {
					wc_delete_attribute( $aid ); // Created by this test only.
				}
				continue;
			}
			if ( taxonomy_exists( $tax ) ) {
				foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
					wp_delete_term( $tid, $tax );
				}
			}
			$aid = wc_attribute_taxonomy_id_by_name( $slug );
			if ( $aid ) {
				wc_delete_attribute( $aid );
			}
		}
		foreach ( array( 'SBPI Selftest Sub', 'SBPI Selftest' ) as $name ) {
			$t = get_term_by( 'name', $name, 'product_cat' );
			if ( $t ) {
				wp_delete_term( $t->term_id, 'product_cat' );
			}
		}
		if ( taxonomy_exists( 'product_brand' ) ) {
			$t = get_term_by( 'name', 'SBPI Test Brand', 'product_brand' );
			if ( $t ) {
				wp_delete_term( $t->term_id, 'product_brand' );
			}
		}
		$left = get_posts( array( 'post_type' => array( 'product', 'product_variation' ), 'post_status' => 'any', 's' => 'SBPI Selftest', 'fields' => 'ids', 'posts_per_page' => 20 ) );
		self::check( ! $left && ! wc_attribute_taxonomy_id_by_name( 'sbpi-t-color' ), 'پاک‌سازی کامل داده‌های آزمایشی', $left ? 'باقی‌مانده: ' . implode( '، ', $left ) : '' );
	}
}
