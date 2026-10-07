<?php
/**
 * Price round-trip: export products/variations to Excel, re-upload, diff, apply.
 * Also builds the standard input template for suppliers.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Price export / update.
 */
final class SBPI_Prices {

	/** Export columns (also used to recognise a re-uploaded export). */
	const HEADERS = array( 'شناسه (ID)', 'شناسه والد', 'نوع', 'SKU', 'نام محصول', 'ویژگی‌های تنوع', 'قیمت عادی', 'قیمت ویژه', 'موجودی', 'وضعیت موجودی', 'وضعیت انتشار' );

	/** Stock status labels. */
	const STOCK = array(
		'instock'     => 'موجود',
		'outofstock'  => 'ناموجود',
		'onbackorder' => 'پیش‌خرید',
	);

	/**
	 * Build the export workbook and stream it.
	 *
	 * @param string $scope "sbpi" (made by this plugin) or "all".
	 * @param int    $cat   Product category ID (0 = all).
	 */
	public static function export( $scope, $cat ) {
		$rows   = array( self::HEADERS );
		$page   = 1;
		$status = array( 'publish', 'draft', 'pending', 'private' );
		do {
			$args = array(
				'status'   => $status,
				'type'     => array( 'simple', 'variable' ),
				'limit'    => 200,
				'page'     => $page,
				'orderby'  => 'title',
				'order'    => 'ASC',
				'return'   => 'objects',
			);
			if ( 'sbpi' === $scope ) {
				$args['meta_key']     = '_sbpi_key'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['meta_compare'] = 'EXISTS';
			}
			if ( $cat ) {
				$term = get_term( $cat, 'product_cat' );
				if ( $term && ! is_wp_error( $term ) ) {
					$args['category'] = array( $term->slug );
				}
			}
			$products = wc_get_products( $args );
			foreach ( $products as $product ) {
				$is_var = $product->is_type( 'variable' );
				$rows[] = array(
					$product->get_id(),
					'',
					$is_var ? 'متغیر' : 'ساده',
					$product->get_sku(),
					$product->get_name(),
					'',
					$is_var ? '' : self::num( $product->get_regular_price() ),
					$is_var ? '' : self::num( $product->get_sale_price() ),
					! $is_var && $product->get_manage_stock() ? (int) $product->get_stock_quantity() : '',
					$is_var ? '' : self::STOCK[ $product->get_stock_status() ] ?? $product->get_stock_status(),
					get_post_status_object( $product->get_status() ) ? get_post_status_object( $product->get_status() )->label : $product->get_status(),
				);
				if ( $is_var ) {
					$children = $product->get_children();
					_prime_post_caches( $children );
					foreach ( $children as $vid ) {
						$v = wc_get_product( $vid );
						if ( ! $v ) {
							continue;
						}
						$rows[] = array(
							$vid,
							$product->get_id(),
							'تنوع',
							$v->get_sku(),
							$product->get_name(),
							wc_get_formatted_variation( $v, true, true, false ),
							self::num( $v->get_regular_price() ),
							self::num( $v->get_sale_price() ),
							$v->get_manage_stock() ? (int) $v->get_stock_quantity() : '',
							self::STOCK[ $v->get_stock_status() ] ?? $v->get_stock_status(),
							'',
						);
					}
				}
			}
			$page++;
		} while ( count( $products ) === 200 );

		$help = array(
			array( 'راهنما', '' ),
			array( 'ستون‌های قابل ویرایش', 'قیمت عادی، قیمت ویژه، موجودی، وضعیت موجودی. بقیه ستون‌ها فقط برای شناسایی‌اند؛ شناسه (ID) را تغییر ندهید.' ),
			array( 'قیمت عادی خالی', 'یعنی بدون تغییر (قیمت حذف نمی‌شود).' ),
			array( 'قیمت ویژه خالی', 'اگر الان تخفیف دارد، تخفیف حذف می‌شود.' ),
			array( 'موجودی', 'عدد = مدیریت موجودی فعال می‌شود. خالی = بدون تغییر.' ),
			array( 'وضعیت موجودی', 'موجود / ناموجود / پیش‌خرید' ),
			array( 'سطرهای «متغیر»', 'قیمت ندارند؛ قیمت را روی سطرهای «تنوع» زیر آن وارد کنید.' ),
			array( 'آپلود', 'محصولات › درون‌ریز هوشمند › تب «قیمت‌ها» ← آپلود فایل ویرایش‌شده ← پیش‌نمایش تغییرات ← اعمال.' ),
		);

		$path = wp_tempnam( 'sbpi-export.xlsx' );
		SBPI_Writer::write(
			$path,
			array(
				array( 'name' => 'قیمت‌ها', 'rows' => $rows, 'widths' => array( 10, 10, 8, 22, 40, 45, 14, 14, 10, 12, 12 ), 'header' => true ),
				array( 'name' => 'راهنمای فایل (نادیده)', 'rows' => $help, 'widths' => array( 22, 90 ) ),
			)
		);
		SBPI_Writer::send( $path, 'products-prices-' . wp_date( 'Y-m-d-Hi' ) . '.xlsx' );
	}

	/**
	 * Standard supplier template, recognised automatically by the mapping screen.
	 */
	public static function template() {
		$head = array( 'N', 'MODEL', 'حافظه (Storage)', 'رم (RAM)', 'رنگ (Color)', 'ریجن (Region)', 'GRADE', 'قیمت', 'قیمت ویژه', 'موجودی', 'تصویر (URL)', 'SKU', 'توضیحات' );
		$rows = array(
			array( array( 'v' => 'لیست محصولات — عنوان دلخواه (این سطر نادیده گرفته می‌شود)', 's' => 'section' ) ),
			$head,
			array( array( 'v' => 'iPhone 13 — نو', 's' => 'section' ) ),
			array( 1, 'iPhone 13', '128 GB', '', 'Midnight | Starlight | Blue', 'ZAA/LL (آمریکا) | CH (چین)', 'نو', '', '', '', '', '', '' ),
			array( 2, 'iPhone 13', '256 GB', '', 'Midnight | Starlight | Blue', 'ZAA/LL (آمریکا) | CH (چین)', 'نو', '', '', '', '', '', '' ),
			array( array( 'v' => 'iPhone 13 — استوک', 's' => 'section' ) ),
			array( 3, 'iPhone 13', '128 GB', '', 'Midnight | Blue', 'ZAA/LL (آمریکا)', 'استوک', '', '', '', '', '', '' ),
		);
		// Header row styled bold.
		foreach ( $rows[1] as $i => $h ) {
			$rows[1][ $i ] = array( 'v' => $h, 's' => 'bold' );
		}
		$help = array(
			array( 'ستون', 'توضیح', 'مثال' ),
			array( 'سطر عنوان بخش', 'یک سطر با فقط یک خانه پر، بالای گروهی از سطرها. با «—» بخش‌ها را جدا کنید.', 'iPhone 13 — نو  یا  PS5 — ACCENT (اکانتی) — استوک' ),
			array( 'MODEL', 'نام مدل؛ همه حافظه‌ها/وضعیت‌های یک مدل باید دقیقاً همین نام را داشته باشند.', 'iPhone 13' ),
			array( 'حافظه / رم / GRADE', 'هر مقدار یک محصول جدا می‌سازد. وضعیت‌ها: نو، آکبند، اکتیو، نات‌اکتیو، استوک.', '128 GB' ),
			array( 'رنگ / ریجن', 'چند مقدار را با | جدا کنید؛ داخل هر محصول به‌صورت انتخاب (تنوع) می‌آیند.', 'Blue | Pink' ),
			array( 'قیمت / قیمت ویژه', 'عدد به تومان یا ریال (مطابق واحد پول سایت)، بدون حروف. ارقام فارسی و جداکننده هزارگان مجاز است.', '45000000' ),
			array( 'موجودی', 'عدد؛ خالی یعنی بدون مدیریت موجودی.', '5' ),
			array( 'تصویر (URL)', 'اختیاری. یا تصاویر را با نام استاندارد در کتابخانه رسانه آپلود کنید (iphone-13-blue.jpg).', 'https://…/a.jpg' ),
			array( 'خانه خالی', 'خالی، «—» یا «ندارد» = بدون مقدار.', '—' ),
			array( 'واژه‌نامه', 'در شیت «واژه‌نامه» (دو ستون: اصطلاح | توضیح) اصطلاحات را توضیح دهید؛ در توضیحات/سؤالات متداول محصولات مرتبط می‌آیند.', 'استوک | کارکرده، …' ),
			array( 'این شیت', 'نام شیتی که «(نادیده)» دارد درون‌ریزی نمی‌شود.', '' ),
		);
		$path = wp_tempnam( 'sbpi-template.xlsx' );
		SBPI_Writer::write(
			$path,
			array(
				array( 'name' => 'محصولات', 'rows' => $rows, 'widths' => array( 5, 22, 14, 10, 34, 38, 10, 14, 14, 9, 26, 16, 30 ) ),
				array( 'name' => 'واژه‌نامه', 'rows' => array( array( 'اصطلاح', 'توضیح' ) ), 'widths' => array( 24, 80 ), 'header' => true ),
				array( 'name' => 'راهنمای فایل (نادیده)', 'rows' => $help, 'widths' => array( 22, 80, 40 ), 'header' => true ),
			)
		);
		SBPI_Writer::send( $path, 'product-import-template.xlsx' );
	}

	/**
	 * Is a parsed workbook a re-uploaded price export?
	 *
	 * @param array $parsed Parsed sheets.
	 * @return array|null The price sheet.
	 */
	public static function detect( array $parsed ) {
		foreach ( $parsed as $sheet ) {
			if ( 'products' === $sheet['kind'] && isset( $sheet['headers'][0] ) && SBPI_Util::key( $sheet['headers'][0] ) === SBPI_Util::key( self::HEADERS[0] ) ) {
				return $sheet;
			}
		}
		return null;
	}

	/**
	 * Compare the uploaded file with current data.
	 *
	 * @param array $sheet Price sheet.
	 * @return array{changes: array, unchanged: int, missing: string[]}
	 */
	public static function diff( array $sheet ) {
		$ids = array();
		foreach ( $sheet['rows'] as $row ) {
			$id = (int) SBPI_Util::latin_digits( $row['cells'][0] );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		_prime_post_caches( $ids );

		$labels    = array_flip( self::STOCK );
		$changes   = array();
		$unchanged = 0;
		$missing   = array();
		foreach ( $sheet['rows'] as $row ) {
			$c       = $row['cells'];
			$id      = (int) SBPI_Util::latin_digits( $c[0] );
			$product = $id ? wc_get_product( $id ) : null;
			if ( ! $product && '' !== $c[3] ) {
				$by_sku  = wc_get_product_id_by_sku( $c[3] );
				$product = $by_sku ? wc_get_product( $by_sku ) : null;
			}
			if ( ! $product ) {
				$missing[] = sprintf( 'سطر %d (%s)', $row['line'], $c[4] );
				continue;
			}
			if ( $product->is_type( 'variable' ) ) {
				continue;
			}
			$name = $product->get_name() . ( '' !== $c[5] ? ' — ' . $c[5] : '' );
			$set  = array();

			$reg = SBPI_Util::to_number( $c[6] );
			if ( '' !== $reg && (float) $reg !== (float) $product->get_regular_price() ) {
				$set['regular_price'] = array( $product->get_regular_price(), $reg );
			}
			$sale = SBPI_Util::to_number( $c[7] );
			if ( (string) $sale !== (string) self::num( $product->get_sale_price() ) && ! ( '' === $sale && '' === $product->get_sale_price() ) ) {
				$set['sale_price'] = array( $product->get_sale_price(), $sale );
			}
			$qty = SBPI_Util::to_number( $c[8] );
			if ( '' !== $qty && ( ! $product->get_manage_stock() || (int) $qty !== (int) $product->get_stock_quantity() ) ) {
				$set['stock'] = array( $product->get_manage_stock() ? (int) $product->get_stock_quantity() : '—', (int) $qty );
			}
			$st = isset( $labels[ $c[9] ] ) ? $labels[ $c[9] ] : ( isset( self::STOCK[ $c[9] ] ) ? $c[9] : '' );
			if ( '' !== $st && '' === $qty && $st !== $product->get_stock_status() ) {
				$set['stock_status'] = array( self::STOCK[ $product->get_stock_status() ] ?? $product->get_stock_status(), self::STOCK[ $st ] );
			}
			if ( $set ) {
				$changes[] = array(
					'id'   => $product->get_id(),
					'name' => $name,
					'set'  => $set,
				);
			} else {
				$unchanged++;
			}
		}
		return array(
			'changes'   => $changes,
			'unchanged' => $unchanged,
			'missing'   => $missing,
		);
	}

	/**
	 * Apply changes from $offset until the deadline.
	 *
	 * @param array $changes  Output of diff()['changes'].
	 * @param int   $offset   Start.
	 * @param float $deadline Deadline.
	 * @return int New offset.
	 */
	public static function apply( array $changes, $offset, $deadline ) {
		$labels  = array_flip( self::STOCK );
		$parents = array();
		$total   = count( $changes );
		for ( $i = $offset; $i < $total && microtime( true ) < $deadline; $i++ ) {
			$ch      = $changes[ $i ];
			$product = wc_get_product( $ch['id'] );
			if ( ! $product ) {
				continue;
			}
			foreach ( $ch['set'] as $field => $pair ) {
				$new = $pair[1];
				switch ( $field ) {
					case 'regular_price':
						$product->set_regular_price( $new );
						break;
					case 'sale_price':
						$product->set_sale_price( $new );
						break;
					case 'stock':
						$product->set_manage_stock( true );
						$product->set_stock_quantity( (int) $new );
						break;
					case 'stock_status':
						$product->set_stock_status( $labels[ $new ] );
						break;
				}
			}
			$product->save();
			if ( $product->get_parent_id() ) {
				$parents[ $product->get_parent_id() ] = true;
			}
		}
		foreach ( array_keys( $parents ) as $pid ) {
			WC_Product_Variable::sync( $pid );
			wc_delete_product_transients( $pid );
		}
		return $i;
	}

	/**
	 * Numeric string without trailing zeros ("45000000.00" → "45000000").
	 *
	 * @param string $v Value.
	 * @return string|int|float
	 */
	private static function num( $v ) {
		if ( '' === (string) $v ) {
			return '';
		}
		return 0 + $v;
	}
}
