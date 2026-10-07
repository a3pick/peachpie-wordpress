<?php
/**
 * Admin UI: upload → mapping → preview → chunked import → history/rollback.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
final class SBPI_Admin {

	const CAP  = 'manage_woocommerce';
	const SLUG = 'sbpi-importer';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sbpi_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'wp_ajax_sbpi_preview', array( __CLASS__, 'ajax_preview' ) );
		add_action( 'wp_ajax_sbpi_run', array( __CLASS__, 'ajax_run' ) );
		add_action( 'wp_ajax_sbpi_rollback', array( __CLASS__, 'ajax_rollback' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_sbpi_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_sbpi_template', array( __CLASS__, 'handle_template' ) );
		add_action( 'admin_post_sbpi_save_content', array( __CLASS__, 'handle_save_content' ) );
		add_action( 'wp_ajax_sbpi_price_apply', array( __CLASS__, 'ajax_price_apply' ) );
		add_action( 'wp_ajax_sbpi_selftest', array( __CLASS__, 'ajax_selftest' ) );
		add_action( 'admin_post_sbpi_log', array( __CLASS__, 'handle_log' ) );
	}

	/**
	 * Menu under Products.
	 */
	public static function menu() {
		add_submenu_page( 'edit.php?post_type=product', 'درون‌ریز هوشمند محصولات', 'درون‌ریز هوشمند', self::CAP, self::SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( 'product_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'sbpi-admin', SBPI_URL . 'assets/admin.css', array(), SBPI_VERSION );
		wp_enqueue_script( 'sbpi-admin', SBPI_URL . 'assets/admin.js', array(), SBPI_VERSION, true );
		wp_localize_script(
			'sbpi-admin',
			'SBPI',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'sbpi' ),
			)
		);
	}

	/**
	 * Attributes already defined on the site: slug => label.
	 *
	 * @return array
	 */
	public static function existing_attributes() {
		$out = array();
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$out[ $tax->attribute_name ] = $tax->attribute_label;
		}
		return $out;
	}

	/** Synonyms used to recognise an existing attribute under another name. */
	const SYNONYMS = array(
		'color'     => array( 'رنگ', 'رنگبندی', 'رنگ بندی', 'رنگ‌بندی', 'colour', 'color', 'رنگ های موجود' ),
		'storage'   => array( 'حافظه', 'حافظه داخلی', 'ظرفیت', 'ظرفیت حافظه', 'storage', 'capacity', 'internal storage' ),
		'ram'       => array( 'رم', 'حافظه رم', 'ram', 'memory', 'مقدار رم' ),
		'condition' => array( 'وضعیت', 'وضعیت کالا', 'وضعیت محصول', 'گرید', 'grade', 'condition', 'کیفیت' ),
		'region'    => array( 'ریجن', 'منطقه', 'سری منطقه‌ای', 'پارت نامبر', 'region', 'part number' ),
		'brand'     => array( 'برند', 'سازنده', 'brand', 'manufacturer' ),
		'warranty'  => array( 'گارانتی', 'warranty' ),
	);

	/**
	 * Find the site's attribute matching a column: slug, label, then synonyms.
	 *
	 * @param string $name     Suggested label.
	 * @param string $slug     Suggested slug.
	 * @param array  $existing slug => label.
	 * @return string Existing slug or ''.
	 */
	public static function match_existing( $name, $slug, array $existing ) {
		if ( '' !== $slug && isset( $existing[ $slug ] ) ) {
			return $slug;
		}
		$norm = static function ( $t ) {
			return str_replace( array( "\xE2\x80\x8C", ' ', '-', '_' ), '', SBPI_Util::key( SBPI_Util::header_label( $t ) ) );
		};
		$key = $norm( $name );
		foreach ( $existing as $es => $label ) {
			if ( $norm( $label ) === $key || $norm( $es ) === $key ) {
				return $es;
			}
		}
		$group = isset( self::SYNONYMS[ $slug ] ) ? self::SYNONYMS[ $slug ] : array();
		foreach ( self::SYNONYMS as $words ) {
			foreach ( $words as $w ) {
				if ( $norm( $w ) === $key ) {
					$group = array_merge( $group, $words );
				}
			}
		}
		$group = array_map( $norm, $group );
		foreach ( $existing as $es => $label ) {
			if ( in_array( $norm( $label ), $group, true ) || in_array( $norm( $es ), $group, true ) ) {
				return $es;
			}
		}
		return '';
	}

	/**
	 * Global defaults.
	 *
	 * @return array
	 */
	public static function default_global() {
		$saved = get_option( 'sbpi_global', array() );
		return wp_parse_args(
			is_array( $saved ) ? $saved : array(),
			array(
				'status'            => 'draft',
				'update_mode'       => 'update',
				'update_title'      => 0,
				'overwrite_content' => 0,
				'gen_description'   => 1,
				'auto_sku'          => 1,
				'sku_prefix'        => '',
				'default_price'     => '',
				'stock_status'      => 'instock',
				'max_variations'    => 400,
				'separator'         => '|',
				'attr_archives'     => 0,
				'store_name'        => get_bloginfo( 'name' ),
				'seo_title_tpl'     => 'قیمت و خرید {title} | {site}',
				'seo_desc_tpl'      => '',
				'focus_tpl'         => 'خرید {title}',
				'auto_images'       => 1,
				'faq'               => 1,
			)
		);
	}

	/**
	 * Sanitize global settings from the browser.
	 *
	 * @param array $in Raw.
	 * @return array
	 */
	private static function sanitize_global( array $in ) {
		$d   = self::default_global();
		$out = array();
		foreach ( $d as $k => $v ) {
			$raw = isset( $in[ $k ] ) ? $in[ $k ] : $v;
			if ( is_int( $v ) ) {
				$out[ $k ] = absint( $raw );
			} else {
				$out[ $k ] = sanitize_text_field( wp_unslash( (string) $raw ) );
			}
		}
		$out['status']         = in_array( $out['status'], array( 'draft', 'publish', 'pending', 'private' ), true ) ? $out['status'] : 'draft';
		$out['update_mode']    = in_array( $out['update_mode'], array( 'update', 'skip' ), true ) ? $out['update_mode'] : 'update';
		$out['stock_status']   = in_array( $out['stock_status'], array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $out['stock_status'] : 'instock';
		$out['max_variations'] = min( 2000, max( 1, $out['max_variations'] ) );
		$out['default_price']  = SBPI_Util::to_number( $out['default_price'] );
		$out['sku_prefix']     = preg_replace( '/[^A-Za-z0-9_-]/', '', $out['sku_prefix'] );
		$out['separator']      = '' === $out['separator'] ? '|' : mb_substr( $out['separator'], 0, 3 );
		return $out;
	}

	/**
	 * Sanitize per-sheet settings.
	 *
	 * @param array $in     Raw sheets settings.
	 * @param array $parsed Parsed sheets.
	 * @return array
	 */
	private static function sanitize_sheets( array $in, array $parsed ) {
		$roles = array_keys( SBPI_Planner::roles() );
		$out   = array();
		foreach ( $parsed as $si => $sheet ) {
			if ( 'products' !== $sheet['kind'] || ! isset( $in[ $si ] ) || ! is_array( $in[ $si ] ) ) {
				continue;
			}
			$raw     = $in[ $si ];
			$allowed = array_merge( array_map( 'strval', array_keys( $sheet['headers'] ) ), array_keys( SBPI_Parser::VIRTUAL ) );
			$columns = array();
			foreach ( $allowed as $ck ) {
				$c              = isset( $raw['columns'][ $ck ] ) && is_array( $raw['columns'][ $ck ] ) ? $raw['columns'][ $ck ] : array();
				$role           = isset( $c['role'] ) ? (string) $c['role'] : 'ignore';
				$columns[ $ck ] = array(
					'role'  => in_array( $role, $roles, true ) ? $role : 'ignore',
					'name'  => sanitize_text_field( wp_unslash( isset( $c['name'] ) ? (string) $c['name'] : '' ) ),
					'slug'  => substr( sanitize_key( isset( $c['slug'] ) ? (string) $c['slug'] : '' ), 0, 27 ),
					'split' => ! empty( $c['split'] ),
				);
			}
			$out[ $si ] = array(
				'enabled'   => ! empty( $raw['enabled'] ),
				'category'  => sanitize_text_field( wp_unslash( isset( $raw['category'] ) ? (string) $raw['category'] : '' ) ),
				'acc_category' => sanitize_text_field( wp_unslash( isset( $raw['acc_category'] ) ? (string) $raw['acc_category'] : '' ) ),
				'brand'     => sanitize_text_field( wp_unslash( isset( $raw['brand'] ) ? (string) $raw['brand'] : '' ) ),
				'title_tpl' => sanitize_text_field( wp_unslash( isset( $raw['title_tpl'] ) ? (string) $raw['title_tpl'] : '{model}' ) ),
				'columns'   => $columns,
			);
		}
		return $out;
	}

	/**
	 * Header signature for remembering mappings across files.
	 *
	 * @param array $sheet Parsed sheet.
	 * @return string
	 */
	private static function signature( array $sheet ) {
		return md5( implode( '|', array_map( array( 'SBPI_Util', 'key' ), $sheet['headers'] ) ) );
	}

	/**
	 * Upload handler: read + parse, store as job, redirect to mapping.
	 */
	public static function handle_upload() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'دسترسی غیرمجاز.', 'sbpi' ), 403 );
		}
		check_admin_referer( 'sbpi_upload' );

		$back = admin_url( 'edit.php?post_type=product&page=' . self::SLUG );
		if ( empty( $_FILES['sbpi_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['sbpi_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_safe_redirect( add_query_arg( 'sbpi_error', rawurlencode( 'فایلی انتخاب نشده است.' ), $back ) );
			exit;
		}
		$name = sanitize_file_name( wp_unslash( $_FILES['sbpi_file']['name'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$size = (int) $_FILES['sbpi_file']['size'];
		if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) || $size > 20 * MB_IN_BYTES ) {
			wp_safe_redirect( add_query_arg( 'sbpi_error', rawurlencode( 'فقط فایل XLSX یا CSV تا ۲۰ مگابایت.' ), $back ) );
			exit;
		}

		try {
			$parsed = SBPI_Parser::parse( SBPI_Reader::read( $_FILES['sbpi_file']['tmp_name'], $ext ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} catch ( Exception $e ) {
			wp_safe_redirect( add_query_arg( 'sbpi_error', rawurlencode( $e->getMessage() ), $back ) );
			exit;
		}

		self::cleanup_jobs();
		$job    = strtolower( wp_generate_password( 12, false ) );
		$prices = SBPI_Prices::detect( $parsed );
		update_option(
			'sbpi_job_' . $job,
			array(
				'file'    => $name,
				'time'    => time(),
				'user'    => get_current_user_id(),
				'parsed'  => $parsed,
			),
			false
		);
		wp_safe_redirect( add_query_arg( $prices ? array( 'job' => $job, 'tab' => 'prices' ) : array( 'job' => $job ), $back ) );
		exit;
	}

	/**
	 * Remove job data older than 3 days.
	 */
	private static function cleanup_jobs() {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sbpi\\_job\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $names as $option ) {
			$job = get_option( $option );
			if ( ! is_array( $job ) || empty( $job['time'] ) || $job['time'] < time() - 3 * DAY_IN_SECONDS ) {
				$id = substr( $option, strlen( 'sbpi_job_' ) );
				delete_option( $option );
				delete_option( 'sbpi_plan_' . $id );
				delete_option( 'sbpi_state_' . $id );
				delete_option( 'sbpi_pchanges_' . $id );
			}
		}
	}

	/**
	 * Load a job from the request.
	 *
	 * @param string $job Job id.
	 * @return array|null
	 */
	private static function job( $job ) {
		$job = preg_replace( '/[^a-z0-9]/', '', (string) $job );
		if ( '' === $job ) {
			return null;
		}
		$data = get_option( 'sbpi_job_' . $job );
		return is_array( $data ) ? $data + array( 'id' => $job ) : null;
	}

	/**
	 * Page router.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		$job = isset( $_GET['job'] ) ? self::job( sanitize_key( $_GET['job'] ) ) : null;
		$err = isset( $_GET['sbpi_error'] ) ? sanitize_text_field( wp_unslash( $_GET['sbpi_error'] ) ) : '';
		// phpcs:enable
		$base = admin_url( 'edit.php?post_type=product&page=' . self::SLUG );

		$step = in_array( $tab, array( 'history', 'prices', 'content', 'health', 'system' ), true ) ? 0 : ( $job ? 2 : 1 );
		echo '<div class="wrap sbpi" dir="rtl">';
		echo '<div class="sbpi-head"><h1><span class="dashicons dashicons-database-import"></span> درون‌ریز هوشمند محصولات <span class="sbpi-ver">v' . esc_html( SBPI_VERSION ) . '</span></h1>';
		$seo = SBPI_SEO::seo_plugin();
		$seo_names = array( 'yoast' => 'Yoast SEO', 'rankmath' => 'Rank Math', 'aioseo' => 'AIOSEO' );
		printf( '<span class="sbpi-chip %s" title="%s">%s</span>', $seo && 'aioseo' !== $seo ? 'ok' : 'warn', esc_attr( 'aioseo' === $seo ? 'متای AIOSEO در جدول اختصاصی آن ذخیره می‌شود و این افزونه آن را پر نمی‌کند.' : 'متای سئو در این افزونه ذخیره می‌شود.' ), esc_html( 'سئو: ' . ( $seo ? $seo_names[ $seo ] : 'داخلی (بدون افزونه سئو)' ) ) );
		echo '</div>';
		$tabs = array(
			''        => array( 'درون‌ریزی', 'upload' ),
			'prices'  => array( 'قیمت‌ها', 'money-alt' ),
			'content' => array( 'متن وضعیت‌ها', 'edit-page' ),
			'health'  => array( 'سلامت سئو', 'heart' ),
			'system'  => array( 'بررسی سیستم', 'shield' ),
			'history' => array( 'تاریخچه', 'backup' ),
		);
		echo '<nav class="nav-tab-wrapper sbpi-tabs">';
		foreach ( $tabs as $tk => $tl ) {
			printf( '<a class="nav-tab %s" href="%s"><span class="dashicons dashicons-%s"></span> %s</a>', $tk === $tab ? 'nav-tab-active' : '', esc_url( $tk ? add_query_arg( 'tab', $tk, $base ) : $base ), esc_attr( $tl[1] ), esc_html( $tl[0] ) );
		}
		echo '</nav>';
		if ( $step ) {
			$steps = array( 1 => 'آپلود فایل', 2 => 'تنظیم ستون‌ها', 3 => 'پیش‌نمایش', 4 => 'درون‌ریزی' );
			echo '<ol class="sbpi-steps" id="sbpi-steps">';
			foreach ( $steps as $n => $label ) {
				printf( '<li class="%s" data-step="%d"><span>%s</span>%s</li>', $n < $step ? 'done' : ( $n === $step ? 'current' : '' ), $n, esc_html( number_format_i18n( $n ) ), esc_html( $label ) );
			}
			echo '</ol>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>ذخیره شد.</p></div>';
		}
		if ( $err ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
		}

		if ( 'history' === $tab ) {
			self::render_history();
		} elseif ( 'prices' === $tab ) {
			self::render_prices( $job );
		} elseif ( 'content' === $tab ) {
			self::render_content();
		} elseif ( 'health' === $tab ) {
			self::render_health();
		} elseif ( 'system' === $tab ) {
			self::render_system();
		} elseif ( $job ) {
			self::render_mapping( $job );
		} else {
			self::render_upload();
		}
		echo '<div class="sbpi-toasts" aria-live="polite"></div>';
		echo '</div>';
	}

	/**
	 * Step 1: upload.
	 */
	private static function render_upload() {
		$batches = get_option( 'sbpi_batches', array() );
		$last    = $batches ? end( $batches ) : null;
		?>
		<div class="sbpi-layout">
			<div class="sbpi-card sbpi-main">
				<h2>فایل محصولات را انتخاب کنید</h2>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sbpi-upload">
					<?php wp_nonce_field( 'sbpi_upload' ); ?>
					<input type="hidden" name="action" value="sbpi_upload" />
					<label class="sbpi-drop" for="sbpi-file">
						<span class="dashicons dashicons-media-spreadsheet"></span>
						<strong class="sbpi-drop-title">فایل را اینجا رها کنید یا کلیک کنید</strong>
						<span class="sbpi-drop-hint">Excel (.xlsx) با هر تعداد شیت، یا CSV — حداکثر ۲۰ مگابایت</span>
						<span class="sbpi-drop-file" hidden></span>
						<input type="file" id="sbpi-file" name="sbpi_file" accept=".xlsx,.csv" required />
					</label>
					<p class="sbpi-actions-inline">
						<button type="submit" class="button button-primary button-hero" disabled>خواندن فایل و ادامه ←</button>
					</p>
					<p class="description">با آپلود، فقط فایل خوانده می‌شود؛ هیچ تغییری در سایت ایجاد نمی‌شود تا خودتان «شروع درون‌ریزی» را بزنید.</p>
				</form>
			</div>
			<aside class="sbpi-side">
				<div class="sbpi-card">
					<h3><span class="dashicons dashicons-download"></span> فایل نمونه استاندارد</h3>
					<p>برای کارفرما بفرستید تا فایل‌ها همیشه یک‌شکل باشند. ستون‌هایش خودکار شناخته می‌شوند.</p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sbpi_template' ), 'sbpi_template' ) ); ?>">دانلود فایل نمونه</a>
				</div>
				<div class="sbpi-card">
					<h3><span class="dashicons dashicons-lightbulb"></span> خودکار تشخیص داده می‌شود</h3>
					<ul class="sbpi-list">
						<li>سطر عنوان و سطر هدر</li>
						<li>سطرهای بخش مثل <code>iPhone 13 — نو</code></li>
						<li>مقادیر چندتایی با <code>|</code></li>
						<li>شیت راهنما ← سؤالات متداول</li>
						<li>ویژگی‌های موجود سایت</li>
					</ul>
				</div>
				<?php
				$bad = array_filter(
					SBPI_Selftest::environment(),
					static function ( $c ) {
						return 'ok' !== $c[0];
					}
				);
				?>
				<div class="sbpi-card">
					<h3><span class="dashicons dashicons-shield"></span> وضعیت سیستم</h3>
					<?php if ( $bad ) : ?>
						<ul class="sbpi-env-mini">
							<?php foreach ( $bad as $c ) : ?>
								<li class="<?php echo esc_attr( $c[0] ); ?>"><strong><?php echo esc_html( $c[1] ); ?>:</strong> <?php echo esc_html( $c[2] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="sbpi-ok-text">✓ همه پیش‌نیازها برقرار است.</p>
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=' . self::SLUG . '&tab=system' ) ); ?>">بررسی کامل و خودآزمایی ←</a>
				</div>
				<?php if ( $last ) : ?>
				<div class="sbpi-card">
					<h3><span class="dashicons dashicons-backup"></span> آخرین درون‌ریزی</h3>
					<p><?php echo esc_html( $last['file'] ); ?><br><small><?php echo esc_html( wp_date( 'Y/m/d H:i', $last['time'] ) ); ?> — ساخته: <?php echo (int) $last['created']; ?>، به‌روز: <?php echo (int) $last['updated']; ?></small></p>
				</div>
				<?php endif; ?>
			</aside>
		</div>
		<?php
	}

	/**
	 * Step 2: mapping + global settings + preview/run area.
	 *
	 * @param array $job Job.
	 */
	private static function render_mapping( array $job ) {
		$presets        = get_option( 'sbpi_presets', array() );
		$global         = self::default_global();
		$roles          = SBPI_Planner::roles();
		$glossary_count = 0;
		$existing       = self::existing_attributes();
		$sample_title   = '';
		$role_help      = array(
			'split_attr' => 'هر مقدار یک محصول جدا با نام و نامک مستقل',
			'var_attr'   => 'مشتری هنگام خرید انتخاب می‌کند (تنوع)',
			'info_attr'  => 'فقط در جدول مشخصات و فیلترها',
			'model'      => 'سطرهای هم‌نام در یک گروه قرار می‌گیرند',
			'ignore'     => 'استفاده نمی‌شود',
		);
		?>
		<form id="sbpi-form" data-job="<?php echo esc_attr( $job['id'] ); ?>">

		<div class="sbpi-card">
			<div class="sbpi-card-head">
				<h2><span class="dashicons dashicons-media-spreadsheet"></span> <?php echo esc_html( $job['file'] ); ?></h2>
				<div class="sbpi-head-actions">
					<button type="button" class="button button-small" data-sheets="all">انتخاب همه شیت‌ها</button>
					<button type="button" class="button button-small" data-sheets="none">هیچ‌کدام</button>
					<a class="button button-small" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=' . self::SLUG ) ); ?>">فایل دیگر</a>
				</div>
			</div>
			<div class="sbpi-legend">
				<?php foreach ( $role_help as $rk => $rh ) : ?>
					<span class="sbpi-role-chip role-<?php echo esc_attr( $rk ); ?>" title="<?php echo esc_attr( $rh ); ?>"><?php echo esc_html( $roles[ $rk ] ); ?><small><?php echo esc_html( $rh ); ?></small></span>
				<?php endforeach; ?>
			</div>
		<?php
		foreach ( $job['parsed'] as $si => $sheet ) {
			if ( 'ignored' === $sheet['kind'] || 'glossary' === $sheet['kind'] || 'products' !== $sheet['kind'] ) {
				if ( 'glossary' === $sheet['kind'] ) {
					$glossary_count += count( $sheet['glossary'] );
				}
				$msg = 'glossary' === $sheet['kind']
					? sprintf( 'واژه‌نامه — %s مورد در «سؤالات متداول / نکات خرید» محصولات مرتبط استفاده می‌شود.', number_format_i18n( count( $sheet['glossary'] ) ) )
					: ( 'ignored' === $sheet['kind'] ? 'شیت راهنما — درون‌ریزی نمی‌شود.' : 'داده‌ای شناسایی نشد.' );
				printf(
					'<div class="sbpi-sheet-mini"><span class="dashicons dashicons-%s"></span> <strong>%s</strong> <span>%s</span></div>',
					'glossary' === $sheet['kind'] ? 'book-alt' : 'hidden',
					esc_html( $sheet['name'] ),
					esc_html( $msg )
				);
				continue;
			}
			$conf = SBPI_Planner::default_settings( $sheet );
			// Reuse the site's own attributes (e.g. laptop "رنگ‌بندی" / pa_rang) when they match.
			foreach ( $conf['columns'] as $ck => $col ) {
				if ( in_array( $col['role'], array( 'var_attr', 'info_attr', 'split_attr' ), true ) ) {
					$hit = self::match_existing( $col['name'], $col['slug'], $existing );
					if ( '' !== $hit ) {
						$conf['columns'][ $ck ]['slug'] = $hit;
						$conf['columns'][ $ck ]['name'] = $existing[ $hit ];
					}
				}
			}
			$sig      = self::signature( $sheet );
			$restored = isset( $presets[ $sig ] ) && is_array( $presets[ $sig ] );
			if ( $restored ) {
				// "+" (not array_merge) keeps numeric column keys intact.
				$conf['columns']   = $presets[ $sig ]['columns'] + $conf['columns'];
				$conf['title_tpl'] = $presets[ $sig ]['title_tpl'];
			}
			if ( '' === $sample_title && $sheet['rows'] ) {
				foreach ( $conf['columns'] as $ck => $col ) {
					if ( 'model' === $col['role'] && is_numeric( $ck ) ) {
						$sample_title = $sheet['rows'][0]['cells'][ (int) $ck ];
						break;
					}
				}
			}
			?>
			<details class="sbpi-sheet" data-sheet="<?php echo esc_attr( $si ); ?>" <?php echo $conf['enabled'] ? 'open' : ''; ?>>
				<summary>
					<label class="sbpi-switch" onclick="event.stopPropagation()">
						<input type="checkbox" data-f="enabled" <?php checked( $conf['enabled'] ); ?> />
						<span></span>
					</label>
					<strong><?php echo esc_html( $sheet['name'] ); ?></strong>
					<span class="sbpi-chip"><?php echo esc_html( sprintf( '%s سطر', number_format_i18n( count( $sheet['rows'] ) ) ) ); ?></span>
					<?php if ( $restored ) : ?><span class="sbpi-chip ok">نگاشت قبلی بازیابی شد</span><?php endif; ?>
					<?php if ( $sheet['title'] ) : ?><span class="sbpi-sheet-title"><?php echo esc_html( SBPI_Util::truncate( $sheet['title'], 70 ) ); ?></span><?php endif; ?>
				</summary>
				<div class="sbpi-sheet-body">
					<div class="sbpi-grid">
						<label>دسته‌بندی
							<input type="text" data-f="category" value="<?php echo esc_attr( $conf['category'] ); ?>" placeholder="موبایل > گوشی اپل" />
							<small>زیردسته با <code>&gt;</code>، نامک لاتین با <code>|</code> — مثل <code dir="ltr">موبایل|mobile &gt; {model}</code>. <code>{model}</code> = یک صفحه دسته برای هر مدل</small>
						</label>
						<label>دسته لوازم جانبی
							<input type="text" data-f="acc_category" value="<?php echo esc_attr( isset( $conf['acc_category'] ) ? $conf['acc_category'] : '' ); ?>" placeholder="خالی = همان دسته اصلی" />
							<small>برای سطرهای بخش ACCESSORIES یا مدل‌هایی مثل کیف، کابل، دسته</small>
						</label>
						<label>برند
							<input type="text" data-f="brand" value="" placeholder="خودکار (اپل، سونی، …)" />
							<small>خالی = تشخیص از نام مدل</small>
						</label>
						<label>الگوی نام محصول
							<input type="text" data-f="title_tpl" value="<?php echo esc_attr( $conf['title_tpl'] ); ?>" placeholder="{model} {split}" />
							<small class="sbpi-tokens" data-target="title_tpl">
								<?php foreach ( array( '{model}', '{split}', '{storage}', '{condition}', '{s1}' ) as $tok ) : ?>
									<button type="button" class="sbpi-token"><?php echo esc_html( $tok ); ?></button>
								<?php endforeach; ?>
							</small>
						</label>
					</div>
					<div class="sbpi-table-wrap">
					<table class="widefat sbpi-cols">
						<thead><tr><th>ستون فایل</th><th>نمونه داده</th><th>نقش</th><th>نام ویژگی</th><th>ویژگی سایت / نامک</th><th title="مقادیر با | جدا شده‌اند">چندمقداری</th></tr></thead>
						<tbody>
						<?php
						$all = array();
						foreach ( $sheet['headers'] as $c => $h ) {
							$all[ (string) $c ] = array( $h, isset( $sheet['samples'][ $c ] ) ? $sheet['samples'][ $c ] : array() );
						}
						foreach ( SBPI_Parser::VIRTUAL as $vk => $vl ) {
							$samples = array();
							foreach ( $sheet['rows'] as $r ) {
								$idx = array( '__s1' => 0, '__s2' => 1, '__s3' => 2 );
								$val = '__sheet' === $vk ? $sheet['name'] : ( isset( $r['section'][ $idx[ $vk ] ] ) ? $r['section'][ $idx[ $vk ] ] : '' );
								if ( '' !== $val && ! in_array( $val, $samples, true ) ) {
									$samples[] = $val;
								}
								if ( count( $samples ) >= 3 ) {
									break;
								}
							}
							if ( $samples ) {
								$all[ $vk ] = array( $vl, $samples );
							}
						}
						foreach ( $all as $ck => $info ) {
							$col = isset( $conf['columns'][ $ck ] ) ? $conf['columns'][ $ck ] : array( 'role' => 'ignore', 'name' => '', 'slug' => '', 'split' => false );
							?>
							<tr data-col="<?php echo esc_attr( $ck ); ?>" data-role="<?php echo esc_attr( $col['role'] ); ?>"<?php echo 0 === strpos( (string) $ck, '__' ) && 'ignore' === $col['role'] ? ' class="sbpi-virtual" hidden' : ''; ?>>
								<td><strong><?php echo esc_html( $info[0] ); ?></strong><?php echo 0 === strpos( (string) $ck, '__' ) ? ' <span class="sbpi-chip">مجازی</span>' : ''; ?></td>
								<td class="sbpi-sample">
									<?php foreach ( array_slice( $info[1], 0, 3 ) as $smp ) : ?>
										<span><?php echo esc_html( SBPI_Util::truncate( $smp, 40 ) ); ?></span>
									<?php endforeach; ?>
								</td>
								<td><select data-c="role">
									<?php foreach ( $roles as $rk => $rl ) : ?>
										<option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $col['role'], $rk ); ?>><?php echo esc_html( $rl ); ?></option>
									<?php endforeach; ?>
								</select></td>
								<td><input type="text" data-c="name" value="<?php echo esc_attr( $col['name'] ); ?>" /></td>
								<td>
									<select data-c="pick">
										<option value="">➕ ساخت ویژگی جدید</option>
										<?php foreach ( $existing as $es => $el ) : ?>
											<option value="<?php echo esc_attr( $es ); ?>" data-label="<?php echo esc_attr( $el ); ?>" <?php selected( $col['slug'], $es ); ?>>✓ <?php echo esc_html( $el . ' (' . $es . ')' ); ?></option>
										<?php endforeach; ?>
									</select>
									<input type="text" data-c="slug" value="<?php echo esc_attr( $col['slug'] ); ?>" dir="ltr" placeholder="نامک لاتین، مثلاً storage" maxlength="27" />
								</td>
								<td class="sbpi-center"><input type="checkbox" data-c="split" <?php checked( ! empty( $col['split'] ) ); ?> /></td>
							</tr>
							<?php
						}
						?>
						</tbody>
					</table>
					</div>
					<button type="button" class="button-link sbpi-virtual-toggle">نمایش ستون‌های مجازی (از سطرهای عنوان بخش)</button>
				</div>
			</details>
			<?php
		}
		?>
		</div>

		<div class="sbpi-card" id="sbpi-global">
			<h2><span class="dashicons dashicons-admin-settings"></span> تنظیمات</h2>
			<div class="sbpi-groups">
				<fieldset>
					<legend>انتشار و موجودی</legend>
					<label>وضعیت محصولات جدید
						<select data-g="status">
							<?php foreach ( array( 'draft' => 'پیش‌نویس (پیشنهادی)', 'publish' => 'منتشرشده', 'pending' => 'در انتظار بررسی', 'private' => 'خصوصی' ) as $k => $l ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $global['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
							<?php endforeach; ?>
						</select></label>
					<label>اگر محصول از قبل وجود داشت
						<select data-g="update_mode">
							<option value="update" <?php selected( $global['update_mode'], 'update' ); ?>>به‌روزرسانی شود</option>
							<option value="skip" <?php selected( $global['update_mode'], 'skip' ); ?>>رد شود</option>
						</select></label>
					<label>وضعیت موجودی پیش‌فرض
						<select data-g="stock_status">
							<option value="instock" <?php selected( $global['stock_status'], 'instock' ); ?>>موجود</option>
							<option value="outofstock" <?php selected( $global['stock_status'], 'outofstock' ); ?>>ناموجود</option>
							<option value="onbackorder" <?php selected( $global['stock_status'], 'onbackorder' ); ?>>پیش‌خرید</option>
						</select></label>
					<label>قیمت پیش‌فرض <input type="text" data-g="default_price" value="<?php echo esc_attr( $global['default_price'] ); ?>" placeholder="خالی" inputmode="numeric" /><small>وقتی فایل ستون قیمت ندارد</small></label>
				</fieldset>

				<fieldset class="sbpi-seo-set">
					<legend>سئو</legend>
					<label>نام فروشگاه <input type="text" data-g="store_name" value="<?php echo esc_attr( $global['store_name'] ); ?>" /></label>
					<label>الگوی عنوان سئو <input type="text" data-g="seo_title_tpl" value="<?php echo esc_attr( $global['seo_title_tpl'] ); ?>" /></label>
					<label>الگوی توضیحات متا <input type="text" data-g="seo_desc_tpl" value="<?php echo esc_attr( $global['seo_desc_tpl'] ); ?>" placeholder="خالی = تولید هوشمند از ویژگی‌ها" /></label>
					<label>کلمه کلیدی کانونی <input type="text" data-g="focus_tpl" value="<?php echo esc_attr( $global['focus_tpl'] ); ?>" /></label>
					<div class="sbpi-serp-live" data-sample="<?php echo esc_attr( $sample_title ? $sample_title : 'iPhone 13 128 GB نو' ); ?>">
						<small>پیش‌نمایش در گوگل (نمونه)</small>
						<div class="u" dir="ltr"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?> › product</div>
						<div class="t"></div>
						<div class="m"><span class="sbpi-len"></span></div>
					</div>
					<small>متغیرها: <code>{title}</code> <code>{model}</code> <code>{brand}</code> <code>{category}</code> <code>{site}</code> <code>{options}</code> <code>{conditions}</code> <code>{attr:storage}</code></small>
				</fieldset>

				<fieldset>
					<legend>محتوا و تصاویر</legend>
					<label class="sbpi-check"><input type="checkbox" data-g="gen_description" <?php checked( $global['gen_description'] ); ?> /> تولید توضیحات کامل <small>معرفی، جدول مشخصات، تفاوت نسخه‌ها، متن وضعیت</small></label>
					<label class="sbpi-check"><input type="checkbox" data-g="faq" <?php checked( $global['faq'] ); ?> /> سؤالات متداول + Schema <small><?php echo $glossary_count ? esc_html( sprintf( 'از %s مورد واژه‌نامه', number_format_i18n( $glossary_count ) ) ) : 'این فایل واژه‌نامه ندارد'; ?></small></label>
					<label class="sbpi-check"><input type="checkbox" data-g="auto_images" <?php checked( $global['auto_images'] ); ?> /> تصویر خودکار از کتابخانه رسانه <small dir="ltr">iphone-13-blue.jpg · iphone-13-128-gb-used.jpg · …_2.jpg</small></label>
					<label class="sbpi-check"><input type="checkbox" data-g="auto_sku" <?php checked( $global['auto_sku'] ); ?> /> ساخت SKU یکتا</label>
					<p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=' . self::SLUG . '&tab=content' ) ); ?>" target="_blank">ویرایش متن وضعیت‌ها (نو/اکتیو/استوک) ↗</a></p>
				</fieldset>

				<details class="sbpi-advanced">
					<summary>تنظیمات پیشرفته</summary>
					<div class="sbpi-grid">
						<label>سقف تنوع هر محصول <input type="number" data-g="max_variations" value="<?php echo esc_attr( $global['max_variations'] ); ?>" min="1" max="2000" /></label>
						<label>جداکننده مقادیر <input type="text" data-g="separator" value="<?php echo esc_attr( $global['separator'] ); ?>" dir="ltr" /></label>
						<label>پیشوند SKU <input type="text" data-g="sku_prefix" value="<?php echo esc_attr( $global['sku_prefix'] ); ?>" dir="ltr" placeholder="SH-" /></label>
					</div>
					<label class="sbpi-check"><input type="checkbox" data-g="attr_archives" <?php checked( $global['attr_archives'] ); ?> /> آرشیو برای ویژگی‌های جدید <small>فقط اگر برای صفحه هر رنگ/حافظه محتوا دارید</small></label>
					<label class="sbpi-check"><input type="checkbox" data-g="update_title" <?php checked( $global['update_title'] ); ?> /> بازنویسی نام محصول در به‌روزرسانی</label>
					<label class="sbpi-check"><input type="checkbox" data-g="overwrite_content" <?php checked( $global['overwrite_content'] ); ?> /> بازنویسی توضیحاتی که دستی ویرایش شده‌اند <small class="sbpi-warn">توصیه نمی‌شود</small></label>
				</details>
			</div>
		</div>

		<div class="sbpi-card" id="sbpi-output" hidden>
			<div id="sbpi-progress" hidden>
				<div class="sbpi-progress-head"><strong class="sbpi-pct">۰٪</strong><span class="sbpi-progress-text" aria-live="polite"></span><span class="sbpi-eta"></span></div>
				<div class="sbpi-bar"><span></span></div>
			</div>
			<div id="sbpi-result"></div>
			<details id="sbpi-log-wrap" hidden><summary>گزارش لحظه‌ای</summary><pre id="sbpi-log"></pre></details>
		</div>

		<div class="sbpi-actionbar">
			<span class="sbpi-actionbar-status" id="sbpi-status">تنظیمات را بررسی کنید و «پیش‌نمایش» را بزنید.</span>
			<button type="button" class="button button-large" id="sbpi-preview"><span class="dashicons dashicons-visibility"></span> پیش‌نمایش</button>
			<button type="button" class="button button-primary button-large" id="sbpi-run" disabled><span class="dashicons dashicons-controls-play"></span> شروع درون‌ریزی</button>
			<button type="button" class="button button-large" id="sbpi-stop" hidden><span class="dashicons dashicons-controls-pause"></span> توقف</button>
		</div>
		</form>
		<?php
	}

	/**
	 * History tab.
	 */
	private static function render_history() {
		$batches = get_option( 'sbpi_batches', array() );
		echo '<div class="sbpi-card"><h2>درون‌ریزی‌های قبلی</h2>';
		if ( ! $batches ) {
			echo '<p>هنوز درون‌ریزی انجام نشده است.</p></div>';
			return;
		}
		echo '<p class="description">«بازگردانی» کل یک نوبت را برمی‌گرداند: محصولات <strong>ساخته‌شده</strong> (همراه تنوع‌ها) حذف می‌شوند و محصولات <strong>به‌روزشده</strong> به وضعیت دقیق قبل از آن نوبت (نام، توضیحات، قیمت، موجودی، ویژگی‌ها، دسته، تصویر، متای سئو و تنوع‌ها) برمی‌گردند؛ تنوع‌هایی که آن نوبت اضافه کرده حذف می‌شوند. برای به‌روزرسانی قیمت، قیمت‌ها و موجودی‌های قبلی برمی‌گردند. تصاویر کتابخانه، ویژگی‌ها و دسته‌های ساخته‌شده حذف نمی‌شوند. نوبت‌های جدیدتر را اول بازگردانی کنید.</p>';
		echo '<table class="widefat striped"><thead><tr><th>تاریخ</th><th>نوع</th><th>فایل</th><th>ساخته‌شده</th><th>به‌روزشده</th><th>ردشده</th><th>خطا</th><th>وضعیت</th><th></th></tr></thead><tbody>';
		$labels = array( 'running' => 'نیمه‌کاره', 'done' => 'انجام شد', 'rolled_back' => 'بازگردانی شد' );
		foreach ( array_reverse( $batches, true ) as $id => $b ) {
			$log_url = wp_nonce_url( admin_url( 'admin-post.php?action=sbpi_log&batch=' . rawurlencode( $id ) ), 'sbpi_log' );
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td><td>%s %s</td></tr>',
				esc_html( wp_date( 'Y/m/d H:i', $b['time'] ) ),
				esc_html( isset( $b['type'] ) && 'prices' === $b['type'] ? 'قیمت' : 'درون‌ریزی' ),
				esc_html( $b['file'] ),
				(int) $b['created'],
				(int) $b['updated'],
				(int) $b['skipped'],
				(int) $b['errors'],
				esc_html( isset( $labels[ $b['status'] ] ) ? $labels[ $b['status'] ] : $b['status'] ),
				get_option( 'sbpi_log_' . $id ) ? '<a class="button button-small" href="' . esc_url( $log_url ) . '">گزارش</a>' : '',
				'rolled_back' === $b['status'] ? '' : '<button type="button" class="button button-small sbpi-rollback" data-batch="' . esc_attr( $id ) . '">بازگردانی</button>'
			);
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Common AJAX guard.
	 */
	private static function guard() {
		check_ajax_referer( 'sbpi', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی غیرمجاز.' ), 403 );
		}
	}

	/**
	 * Append lines to a batch's persistent log (capped).
	 *
	 * @param string   $batch Batch ID.
	 * @param string[] $lines Lines.
	 */
	public static function log( $batch, array $lines ) {
		if ( ! $lines ) {
			return;
		}
		$log   = get_option( 'sbpi_log_' . $batch, array() );
		$stamp = wp_date( 'H:i:s' );
		foreach ( $lines as $l ) {
			$log[] = $stamp . '  ' . $l;
		}
		update_option( 'sbpi_log_' . $batch, array_slice( $log, -5000 ), false );
	}

	/**
	 * Single-run lock: only one import / price update / undo at a time.
	 * Expires on its own (2 min without activity) if a browser tab is closed.
	 *
	 * @param string $owner Job or batch ID.
	 */
	private static function lock( $owner ) {
		$lock = get_transient( 'sbpi_lock' );
		if ( is_array( $lock ) && $lock['owner'] !== $owner && time() - $lock['time'] < 120 ) {
			$user = get_userdata( $lock['user'] );
			wp_send_json_error(
				array(
					'message' => sprintf(
						'عملیات دیگری توسط %s در حال اجراست (%s). صبر کنید تا تمام شود؛ اگر صفحه آن بسته شده، حداکثر ۲ دقیقه بعد دوباره تلاش کنید.',
						$user ? $user->display_name : 'کاربر دیگر',
						$lock['what']
					),
				)
			);
		}
		$names = array(
			'wp_ajax_sbpi_run'         => 'درون‌ریزی',
			'wp_ajax_sbpi_price_apply' => 'به‌روزرسانی قیمت',
			'wp_ajax_sbpi_rollback'    => 'بازگردانی',
			'wp_ajax_sbpi_selftest'    => 'خودآزمایی',
		);
		$what = isset( $names[ current_action() ] ) ? $names[ current_action() ] : 'عملیات';
		set_transient( 'sbpi_lock', array( 'owner' => $owner, 'user' => get_current_user_id(), 'time' => time(), 'what' => $what ), 300 );
	}

	/**
	 * Release the lock if we own it.
	 *
	 * @param string $owner Owner.
	 */
	private static function unlock( $owner ) {
		$lock = get_transient( 'sbpi_lock' );
		if ( is_array( $lock ) && $lock['owner'] === $owner ) {
			delete_transient( 'sbpi_lock' );
		}
	}

	/**
	 * Download a batch log as text.
	 */
	public static function handle_log() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز.', 403 );
		}
		check_admin_referer( 'sbpi_log' );
		$batch = isset( $_GET['batch'] ) ? preg_replace( '/[^A-Za-z0-9-]/', '', wp_unslash( $_GET['batch'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$log   = get_option( 'sbpi_log_' . $batch, array() );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sbpi-log-' . $batch . '.txt"' );
		echo "\xEF\xBB\xBF" . implode( "\r\n", array_map( 'wp_strip_all_tags', (array) $log ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text download.
		exit;
	}

	/**
	 * Seconds of work per request.
	 *
	 * @return float Deadline timestamp.
	 */
	private static function deadline() {
		$max    = (int) ini_get( 'max_execution_time' );
		$budget = $max > 0 ? min( 20, max( 5, $max * 0.5 ) ) : 20;
		return microtime( true ) + $budget;
	}

	/**
	 * Build + store plan, return a summary.
	 */
	public static function ajax_preview() {
		self::guard();
		$job = self::job( isset( $_POST['job'] ) ? sanitize_key( $_POST['job'] ) : '' );
		if ( ! $job ) {
			wp_send_json_error( array( 'message' => 'نشست درون‌ریزی منقضی شده؛ فایل را دوباره آپلود کنید.' ) );
		}
		$raw = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => 'تنظیمات نامعتبر.' ) );
		}
		$settings = array(
			'global' => self::sanitize_global( isset( $raw['global'] ) && is_array( $raw['global'] ) ? $raw['global'] : array() ),
			'sheets' => self::sanitize_sheets( isset( $raw['sheets'] ) && is_array( $raw['sheets'] ) ? $raw['sheets'] : array(), $job['parsed'] ),
		);

		update_option( 'sbpi_global', $settings['global'], false );
		$settings['global']['cond_texts'] = self::cond_texts();
		$plan = SBPI_Planner::build( $job['parsed'], $settings );
		update_option( 'sbpi_plan_' . $job['id'], array( 'plan' => $plan, 'global' => $settings['global'] ), false );
		delete_option( 'sbpi_state_' . $job['id'] );
		$diff = self::import_diff( $plan['products'] );

		$presets = get_option( 'sbpi_presets', array() );
		foreach ( $settings['sheets'] as $si => $conf ) {
			$presets[ self::signature( $job['parsed'][ $si ] ) ] = array(
				'columns'   => $conf['columns'],
				'title_tpl' => $conf['title_tpl'],
			);
		}
		update_option( 'sbpi_presets', array_slice( $presets, -50, null, true ), false );

		$rows      = array();
		$total_var = 0;
		$no_price  = 0;
		foreach ( $plan['products'] as $p ) {
			$d          = $diff['products'][ $p['key'] ];
			$total_var += count( $p['variations'] );
			$priced     = 'simple' === $p['type'] ? '' !== $p['price'] : (bool) array_filter( wp_list_pluck( $p['variations'], 'price' ), 'strlen' );
			if ( ! $priced ) {
				$no_price++;
			}
			$axes = array();
			foreach ( $p['attributes'] as $a ) {
				$axes[] = ( $a['variation'] ? '★ ' : '' ) . $a['name'] . ' (' . count( $a['values'] ) . ')';
			}
			$rows[] = array(
				'title'      => $p['title'],
				'slug'       => $p['slug'],
				'type'       => $p['type'],
				'variations' => count( $p['variations'] ),
				'category'   => implode( ' › ', array_map( array( 'SBPI_Category', 'name' ), $p['category'] ) ),
				'brand'      => $p['brand'] ? $p['brand'][0] : '—',
				'attributes' => implode( '، ', $axes ),
				'seo_title'  => $p['seo']['title'],
				'seo_desc'   => $p['seo']['desc'],
				'focus'      => $p['seo']['focus'],
				'priced'     => $priced,
				'content'    => wp_kses_post( $p['content'] ),
				'status'     => $d['exists'] ? ( 'skip' === $settings['global']['update_mode'] ? 'skip' : 'update' ) : 'new',
				'new_vars'   => $d['new_vars'],
				'lines'      => implode( '، ', array_slice( $p['lines'], 0, 6 ) ) . ( count( $p['lines'] ) > 6 ? ' …' : '' ),
			);
		}
		$warnings = $plan['warnings'];
		$existing = self::existing_attributes();
		$new      = array();
		foreach ( $plan['products'] as $p ) {
			foreach ( $p['attributes'] as $a ) {
				$found = isset( $existing[ $a['slug'] ] );
				foreach ( $existing as $el ) {
					$found = $found || SBPI_Util::key( $el ) === SBPI_Util::key( $a['name'] );
				}
				if ( ! $found ) {
					$new[ $a['name'] . ( $a['slug'] ? ' (' . $a['slug'] . ')' : '' ) ] = true;
				}
			}
		}
		if ( $new ) {
			$warnings[] = 'این ویژگی‌ها در سایت وجود ندارند و جدید ساخته می‌شوند: ' . implode( '، ', array_keys( $new ) ) . '. اگر معادلشان را از قبل دارید (مثلاً برای لپ‌تاپ)، در ستون «ویژگی سایت» همان را انتخاب کنید تا ویژگی تکراری ساخته نشود.';
		}
		$drafts = array();
		foreach ( $settings['global']['cond_texts'] as $c => $t ) {
			if ( preg_match( '/\[[^\]]{3,}\]/u', $t ) ) {
				$drafts[] = $c;
			}
		}
		if ( $drafts ) {
			$warnings[] = 'متن وضعیت‌های ' . implode( '، ', $drafts ) . ' هنوز بخش [داخل کروشه] دارد و تا تکمیل نشود در توضیحات درج نمی‌شود (تب «متن وضعیت‌ها»).';
		} elseif ( ! $settings['global']['cond_texts'] ) {
			$warnings[] = 'متن اختصاصی وضعیت‌ها (نو/اکتیو/استوک) هنوز تنظیم نشده؛ از تب «متن وضعیت‌ها» تکمیل کنید تا صفحه‌ها کمتر شبیه هم باشند.';
		}
		if ( $no_price ) {
			$warnings[] = sprintf( '%d محصول قیمت ندارند. ووکامرس تنوع‌های بدون قیمت را در صفحه محصول قابل انتخاب نمی‌کند؛ بعداً قیمت را با همین افزونه (ستون قیمت + حالت به‌روزرسانی) یا ویرایش گروهی وارد کنید.', $no_price );
		}
		wp_send_json_success(
			array(
				'products'   => $rows,
				'diff'       => array(
					'new'     => $diff['new'],
					'update'  => $diff['existing'],
					'prices'  => array_slice( $diff['prices'], 0, 200 ),
					'pcount'  => count( $diff['prices'] ),
					'mode'    => $settings['global']['update_mode'],
				),
				'count'      => count( $rows ),
				'variations' => $total_var,
				'warnings'   => $warnings,
				'seo_plugin' => SBPI_SEO::seo_plugin(),
			)
		);
	}

	/**
	 * Process products until the time budget is used; the browser calls again.
	 */
	public static function ajax_run() {
		self::guard();
		$job = self::job( isset( $_POST['job'] ) ? sanitize_key( $_POST['job'] ) : '' );
		$id  = $job ? $job['id'] : '';
		$stored = $id ? get_option( 'sbpi_plan_' . $id ) : null;
		if ( ! $job || ! is_array( $stored ) ) {
			wp_send_json_error( array( 'message' => 'ابتدا پیش‌نمایش بگیرید.' ) );
		}
		self::lock( $id );
		$products = $stored['plan']['products'];
		$global   = $stored['global'];
		$state    = get_option( 'sbpi_state_' . $id );
		if ( ! is_array( $state ) ) {
			$state = array(
				'batch'   => gmdate( 'Ymd-His' ) . '-' . $id,
				'cursor'  => 0,
				'product' => array(),
				'stats'   => array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0 ),
			);
			$batches = get_option( 'sbpi_batches', array() );
			while ( count( $batches ) >= 50 ) {
				// Keep 50 batches; drop the oldest and its log (its products stay untouched).
				$old = key( $batches );
				unset( $batches[ $old ] );
				delete_option( 'sbpi_log_' . $old );
				delete_option( 'sbpi_pundo_' . $old );
			}
			$batches[ $state['batch'] ] = array(
				'time'   => time(),
				'file'   => $job['file'],
				'type'   => 'import',
				'status' => 'running',
			) + $state['stats'];
			update_option( 'sbpi_batches', $batches, false );
		}

		$deadline = self::deadline();
		$log      = array();
		$total    = count( $products );

		wp_defer_term_counting( true );
		wc_set_time_limit( 0 );
		while ( $state['cursor'] < $total && microtime( true ) < $deadline ) {
			$spec  = $products[ $state['cursor'] ];
			$pstate = $state['product'] + array( 'batch' => $state['batch'] );
			try {
				$res              = SBPI_Importer::import( $spec, $global, $pstate, $deadline );
				$state['product'] = $pstate;
				if ( ! $res['done'] ) {
					$log[] = sprintf( '⏳ %s — %s', $spec['title'], $res['message'] );
					break;
				}
				$state['stats'][ $res['action'] ]++;
				$icon  = array( 'created' => '✅', 'updated' => '🔄', 'skipped' => '⏭' );
				$log[] = sprintf( '%s %s (#%d) — %s', $icon[ $res['action'] ], $spec['title'], $res['id'], $res['message'] );
			} catch ( Throwable $e ) {
				$state['stats']['errors']++;
				$log[] = sprintf( '❌ %s — %s', $spec['title'], $e->getMessage() );
			}
			$state['product'] = array();
			$state['cursor']++;
		}
		wp_defer_term_counting( false );

		$done = $state['cursor'] >= $total;
		update_option( 'sbpi_state_' . $id, $state, false );
		self::log( $state['batch'], $log );

		$batches = get_option( 'sbpi_batches', array() );
		if ( isset( $batches[ $state['batch'] ] ) ) {
			$batches[ $state['batch'] ] = array_merge( $batches[ $state['batch'] ], $state['stats'], array( 'status' => $done ? 'done' : 'running' ) );
			update_option( 'sbpi_batches', $batches, false );
		}
		if ( $done ) {
			// Model-family hub pages (only categories with an empty description).
			$cats = SBPI_Category::describe( $products );
			if ( $cats ) {
				$batches = get_option( 'sbpi_batches', array() );
				$batches[ $state['batch'] ]['cats'] = $cats;
				update_option( 'sbpi_batches', $batches, false );
				self::log( $state['batch'], array( sprintf( '🗂 توضیح و جدول مقایسه برای %d صفحه دسته نوشته شد.', count( $cats ) ) ) );
				$log[] = sprintf( '🗂 صفحه دسته (هاب مدل) برای %d دسته ساخته شد.', count( $cats ) );
			}
			delete_option( 'sbpi_state_' . $id );
			self::unlock( $id );
		}

		wp_send_json_success(
			array(
				'done'   => $done,
				'cursor' => $state['cursor'],
				'total'  => $total,
				'stats'  => $state['stats'],
				'log'    => $log,
				'list'   => admin_url( 'edit.php?post_type=product' ),
			)
		);
	}

	/**
	 * Roll back a batch in chunks.
	 */
	public static function ajax_rollback() {
		self::guard();
		$batch   = isset( $_POST['batch'] ) ? preg_replace( '/[^A-Za-z0-9-]/', '', wp_unslash( $_POST['batch'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$batches = get_option( 'sbpi_batches', array() );
		if ( ! isset( $batches[ $batch ] ) ) {
			wp_send_json_error( array( 'message' => 'نوبت یافت نشد.' ) );
		}
		self::lock( 'undo-' . $batch );
		if ( isset( $batches[ $batch ]['type'] ) && 'prices' === $batches[ $batch ]['type'] ) {
			$changes = get_option( 'sbpi_pundo_' . $batch, array() );
			$offset  = isset( $batches[ $batch ]['undo_offset'] ) ? (int) $batches[ $batch ]['undo_offset'] : 0;
			$next    = SBPI_Prices::undo( $changes, $offset, self::deadline() );
			$left    = max( 0, count( $changes ) - $next );
			$batches[ $batch ]['undo_offset'] = $next;
		} else {
			$left = SBPI_Importer::rollback( $batch, self::deadline() );
		}
		if ( 0 === $left ) {
			if ( ! empty( $batches[ $batch ]['cats'] ) ) {
				SBPI_Category::undo( $batches[ $batch ]['cats'] );
			}
			$batches[ $batch ]['status'] = 'rolled_back';
			delete_option( 'sbpi_pundo_' . $batch );
			self::log( $batch, array( '↩ بازگردانی کامل شد.' ) );
			self::unlock( 'undo-' . $batch );
		}
		update_option( 'sbpi_batches', $batches, false );
		wp_send_json_success( array( 'remaining' => $left ) );
	}

	/**
	 * Compare a plan with what is already on the site (one query per 500 products).
	 *
	 * @param array $products Plan products.
	 * @return array
	 */
	private static function import_diff( array $products ) {
		global $wpdb;
		$keys = wp_list_pluck( $products, 'key' );
		$map  = array();
		foreach ( array_chunk( $keys, 500 ) as $chunk ) {
			$in   = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					"SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id AND p.post_type = 'product' AND p.post_status <> 'trash' WHERE m.meta_key = '_sbpi_key' AND m.meta_value IN ($in)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$chunk
				)
			);
			foreach ( $rows as $r ) {
				$map[ $r->meta_value ] = (int) $r->post_id;
			}
		}

		// Existing prices: simple products and variations (by combo key).
		$prices = array();
		$combos = array();
		foreach ( array_chunk( array_values( $map ), 500 ) as $chunk ) {
			$in   = implode( ',', array_map( 'intval', $chunk ) );
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT p.ID, p.post_parent, c.meta_value AS combo, r.meta_value AS reg
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = '_sbpi_combo'
				 LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_regular_price'
				 WHERE p.ID IN ($in) OR (p.post_parent IN ($in) AND p.post_type = 'product_variation')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			foreach ( $rows as $r ) {
				if ( $r->post_parent && $r->combo ) {
					$combos[ $r->post_parent ][ $r->combo ] = (string) $r->reg;
				} elseif ( ! $r->post_parent ) {
					$prices[ $r->ID ] = (string) $r->reg;
				}
			}
		}

		$out = array(
			'products' => array(),
			'new'      => 0,
			'existing' => 0,
			'prices'   => array(),
		);
		foreach ( $products as $p ) {
			$id   = isset( $map[ $p['key'] ] ) ? $map[ $p['key'] ] : 0;
			$item = array( 'exists' => (bool) $id, 'new_vars' => 0 );
			$id ? $out['existing']++ : $out['new']++;
			if ( $id ) {
				if ( 'simple' === $p['type'] ) {
					$old = isset( $prices[ $id ] ) ? $prices[ $id ] : '';
					if ( '' !== $p['price'] && (float) $old !== (float) $p['price'] ) {
						$out['prices'][] = array( $p['title'], '', $old, $p['price'] );
					}
				} else {
					foreach ( $p['variations'] as $v ) {
						if ( ! isset( $combos[ $id ][ $v['key'] ] ) ) {
							$item['new_vars']++;
							continue;
						}
						$old = $combos[ $id ][ $v['key'] ];
						if ( '' !== $v['price'] && (float) $old !== (float) $v['price'] ) {
							$out['prices'][] = array( $p['title'], implode( ' / ', array_filter( $v['attrs'], 'strlen' ) ), $old, $v['price'] );
						}
					}
				}
			}
			$out['products'][ $p['key'] ] = $item;
		}
		return $out;
	}

	/**
	 * Saved per-condition texts (falls back to editable drafts).
	 *
	 * @return array
	 */
	public static function cond_texts() {
		$saved = get_option( 'sbpi_cond_texts', null );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Tab: per-condition content.
	 */
	private static function render_content() {
		$texts = self::cond_texts();
		if ( ! $texts ) {
			$texts = SBPI_SEO::default_cond_texts(); // First visit: editable drafts.
		}
		// Always offer the standard conditions plus any custom ones already saved.
		$texts = $texts + array_fill_keys( array_keys( SBPI_SEO::default_cond_texts() ), '' );
		?>
		<div class="sbpi-card">
			<h2>متن اختصاصی هر وضعیت</h2>
			<p class="description">این متن زیر عنوان «شرایط و وضعیت …» در توضیحات هر محصولی با همان وضعیت درج می‌شود و باعث می‌شود صفحه‌های نو/اکتیو/استوک محتوای متفاوت و واقعاً مفیدی داشته باشند. متغیرها: <code>{title}</code> <code>{model}</code>. متن پیش‌فرض فقط پیش‌نویس است — بخش‌های [داخل کروشه] را با سیاست واقعی فروشگاه جایگزین کنید (اطلاعات ساختگی درباره گارانتی منتشر نکنید). خالی = این بخش درج نمی‌شود. روی محصولات قبلی با درون‌ریزی دوباره اعمال می‌شود (اگر توضیحاتشان دستی ویرایش نشده باشد).</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbpi_save_content' ); ?>
				<input type="hidden" name="action" value="sbpi_save_content" />
				<?php foreach ( $texts as $cond => $html ) : ?>
					<div class="sbpi-cond">
						<label><strong>وضعیت:</strong> <input type="text" name="cond[]" value="<?php echo esc_attr( $cond ); ?>" /></label>
						<textarea name="text[]" rows="4" class="large-text"><?php echo esc_textarea( $html ); ?></textarea>
					</div>
				<?php endforeach; ?>
				<div class="sbpi-cond">
					<label><strong>وضعیت جدید:</strong> <input type="text" name="cond[]" value="" placeholder="مثلاً ریفربیش" /></label>
					<textarea name="text[]" rows="3" class="large-text"></textarea>
				</div>
				<?php submit_button( 'ذخیره متن‌ها' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save per-condition texts.
	 */
	public static function handle_save_content() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز.', 403 );
		}
		check_admin_referer( 'sbpi_save_content' );
		$conds = isset( $_POST['cond'] ) ? (array) wp_unslash( $_POST['cond'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$texts = isset( $_POST['text'] ) ? (array) wp_unslash( $_POST['text'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$out   = array();
		foreach ( $conds as $i => $c ) {
			$c = sanitize_text_field( $c );
			if ( '' !== $c ) {
				$out[ $c ] = wp_kses_post( isset( $texts[ $i ] ) ? $texts[ $i ] : '' );
			}
		}
		update_option( 'sbpi_cond_texts', $out, false );
		wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=' . self::SLUG . '&tab=content&saved=1' ) );
		exit;
	}

	/**
	 * Tab: export / price update.
	 *
	 * @param array|null $job Uploaded price job.
	 */
	private static function render_prices( $job ) {
		if ( $job && ( $sheet = SBPI_Prices::detect( $job['parsed'] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition, WordPress.CodeAnalysis.AssignmentInCondition
			$diff = SBPI_Prices::diff( $sheet );
			update_option( 'sbpi_pchanges_' . $job['id'], $diff['changes'], false );
			$labels = array( 'regular_price' => 'قیمت عادی', 'sale_price' => 'قیمت ویژه', 'stock' => 'موجودی', 'stock_status' => 'وضعیت موجودی' );
			?>
			<div class="sbpi-card" id="sbpi-prices" data-job="<?php echo esc_attr( $job['id'] ); ?>">
				<h2>پیش‌نمایش تغییرات — <?php echo esc_html( $job['file'] ); ?></h2>
				<p class="sbpi-summary"><strong><?php echo count( $diff['changes'] ); ?></strong> ردیف تغییر می‌کند، <strong><?php echo (int) $diff['unchanged']; ?></strong> بدون تغییر<?php echo $diff['missing'] ? '، <strong>' . count( $diff['missing'] ) . '</strong> پیدا نشد' : ''; ?>.</p>
				<?php if ( $diff['missing'] ) : ?>
					<div class="notice notice-warning inline"><p>پیدا نشد (حذف شده یا شناسه/SKU عوض شده): <?php echo esc_html( implode( '، ', array_slice( $diff['missing'], 0, 30 ) ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( $diff['changes'] ) : ?>
					<table class="widefat striped"><thead><tr><th>محصول</th><th>فیلد</th><th>قبلی</th><th>جدید</th></tr></thead><tbody>
					<?php
					$shown = 0;
					foreach ( $diff['changes'] as $ch ) {
						foreach ( $ch['set'] as $f => $pair ) {
							if ( ++$shown > 500 ) {
								break 2;
							}
							$up = is_numeric( $pair[0] ) && is_numeric( $pair[1] ) ? ( (float) $pair[1] > (float) $pair[0] ? ' ▲' : ' ▼' ) : '';
							printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td><strong>%s</strong>%s</td></tr>', esc_html( $ch['name'] ), esc_html( $labels[ $f ] ), esc_html( '' === (string) $pair[0] ? '—' : self::fmt( $pair[0] ) ), esc_html( '' === (string) $pair[1] ? '(حذف)' : self::fmt( $pair[1] ) ), esc_html( $up ) );
						}
					}
					?>
					</tbody></table>
					<p>
						<button type="button" class="button button-primary" id="sbpi-price-apply">✅ اعمال <?php echo count( $diff['changes'] ); ?> تغییر</button>
						<span class="sbpi-progress-text"></span>
					</p>
				<?php endif; ?>
			</div>
			<?php
			return;
		}
		$cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		?>
		<div class="sbpi-card">
			<h2>۱. خروجی Excel از محصولات</h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sbpi_export" />
				<?php wp_nonce_field( 'sbpi_export', '_wpnonce', false ); ?>
				<select name="scope">
					<option value="sbpi">فقط محصولات ساخته‌شده با این افزونه</option>
					<option value="all">همه محصولات ساده و متغیر</option>
				</select>
				<select name="cat">
					<option value="0">همه دسته‌ها</option>
					<?php foreach ( is_array( $cats ) ? $cats : array() as $c ) : ?>
						<option value="<?php echo (int) $c->term_id; ?>"><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( '📤 دانلود فایل قیمت‌ها', 'primary', 'submit', false ); ?>
			</form>
			<p class="description">فایل شامل هر محصول و همه تنوع‌هایش با SKU، قیمت عادی، قیمت ویژه و موجودی است. فقط همین ستون‌ها را در Excel ویرایش کنید.</p>
		</div>
		<div class="sbpi-card">
			<h2>۲. آپلود فایل ویرایش‌شده</h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbpi_upload' ); ?>
				<input type="hidden" name="action" value="sbpi_upload" />
				<input type="file" name="sbpi_file" accept=".xlsx,.csv" required />
				<?php submit_button( 'نمایش تغییرات', 'primary', 'submit', false ); ?>
			</form>
			<p class="description">قبل از اعمال، فهرست دقیق «قبلی ← جدید» را می‌بینید. فقط قیمت و موجودی تغییر می‌کند؛ نام، محتوا و سئو دست نمی‌خورد.</p>
		</div>
		<?php
	}

	/**
	 * "45000000" → "45,000,000".
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function fmt( $v ) {
		return is_numeric( $v ) ? number_format( (float) $v, ( floor( (float) $v ) == $v ) ? 0 : 2 ) : (string) $v; // phpcs:ignore Universal.Operators.StrictComparisons
	}

	/**
	 * Export download.
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز.', 403 );
		}
		check_admin_referer( 'sbpi_export' );
		wc_set_time_limit( 0 );
		$scope = isset( $_GET['scope'] ) && 'all' === $_GET['scope'] ? 'all' : 'sbpi';
		$cat   = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
		try {
			SBPI_Prices::export( $scope, $cat );
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Template download.
	 */
	public static function handle_template() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی غیرمجاز.', 403 );
		}
		check_admin_referer( 'sbpi_template' );
		try {
			SBPI_Prices::template();
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Apply price changes in chunks.
	 */
	public static function ajax_price_apply() {
		self::guard();
		$job     = self::job( isset( $_POST['job'] ) ? sanitize_key( $_POST['job'] ) : '' );
		$changes = $job ? get_option( 'sbpi_pchanges_' . $job['id'] ) : null;
		if ( ! is_array( $changes ) ) {
			wp_send_json_error( array( 'message' => 'تغییری برای اعمال پیدا نشد؛ فایل را دوباره آپلود کنید.' ) );
		}
		self::lock( $job['id'] );
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$batch  = 'p' . gmdate( 'Ymd-His', (int) $job['time'] ) . '-' . $job['id'];
		if ( 0 === $offset ) {
			// Register the batch first so the change can be undone from History.
			$batches           = get_option( 'sbpi_batches', array() );
			$batches[ $batch ] = array(
				'time'    => time(),
				'file'    => $job['file'],
				'type'    => 'prices',
				'status'  => 'running',
				'created' => 0,
				'updated' => count( $changes ),
				'skipped' => 0,
				'errors'  => 0,
			);
			update_option( 'sbpi_batches', $batches, false );
			update_option( 'sbpi_pundo_' . $batch, $changes, false );
		}
		$next = SBPI_Prices::apply( $changes, $offset, self::deadline() );
		$done = $next >= count( $changes );
		if ( $done ) {
			delete_option( 'sbpi_pchanges_' . $job['id'] );
			$batches = get_option( 'sbpi_batches', array() );
			if ( isset( $batches[ $batch ] ) ) {
				$batches[ $batch ]['status'] = 'done';
				update_option( 'sbpi_batches', $batches, false );
			}
			$lines = array();
			foreach ( $changes as $ch ) {
				foreach ( $ch['set'] as $f => $pair ) {
					$lines[] = sprintf( '#%d %s — %s: %s → %s', $ch['id'], $ch['name'], $f, '' === (string) $pair[0] ? '—' : $pair[0], '' === (string) $pair[1] ? '(حذف)' : $pair[1] );
				}
			}
			self::log( $batch, $lines );
			self::unlock( $job['id'] );
		}
		wp_send_json_success( array( 'offset' => $next, 'total' => count( $changes ), 'done' => $done ) );
	}

	/**
	 * Tab: environment + self-test.
	 */
	private static function render_system() {
		$labels = array( 'ok' => '✓', 'warn' => '!', 'bad' => '✕' );
		?>
		<div class="sbpi-layout">
			<div class="sbpi-card sbpi-main">
				<h2><span class="dashicons dashicons-yes-alt"></span> خودآزمایی روی همین سایت</h2>
				<p>یک فایل نمونه کوچک داخلی (۱ محصول متغیر + ۱ ساده) درون‌ریزی می‌شود و همه چیز بررسی می‌شود: ویژگی‌ها، تنوع‌ها، قیمت، SKU، دسته، متای سئو، توضیحات، FAQ، Schema، اجرای دوباره بدون تکرار، بازگردانی به‌روزرسانی و بازگردانی ساخت. در پایان <strong>همه داده‌های آزمایشی پاک می‌شوند</strong> (حتی اگر بررسی‌ای شکست بخورد).</p>
				<p class="description">محصولات آزمایشی «پیش‌نویس» و با نام «SBPI Selftest» ساخته می‌شوند و در سایت دیده نمی‌شوند. بهتر است ابتدا روی Staging اجرا شود.</p>
				<p><button type="button" class="button button-primary button-large" id="sbpi-selftest"><span class="dashicons dashicons-controls-play"></span> اجرای خودآزمایی</button></p>
				<div id="sbpi-selftest-out"></div>
			</div>
			<aside class="sbpi-side">
				<div class="sbpi-card">
					<h3><span class="dashicons dashicons-admin-tools"></span> پیش‌نیازهای سرور</h3>
					<table class="sbpi-env"><tbody>
					<?php foreach ( SBPI_Selftest::environment() as $c ) : ?>
						<tr class="<?php echo esc_attr( $c[0] ); ?>"><td class="i"><?php echo esc_html( $labels[ $c[0] ] ); ?></td><th><?php echo esc_html( $c[1] ); ?></th><td><?php echo esc_html( $c[2] ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			</aside>
		</div>
		<?php
	}

	/**
	 * Run the self-test.
	 */
	public static function ajax_selftest() {
		self::guard();
		self::lock( 'selftest' );
		wc_set_time_limit( 0 );
		$results = SBPI_Selftest::run();
		self::unlock( 'selftest' );
		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Tab: SEO health.
	 */
	private static function render_health() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$ours  = ! isset( $_GET['all'] );
		$issue = isset( $_GET['issue'] ) ? sanitize_key( $_GET['issue'] ) : '';
		// phpcs:enable
		$report = SBPI_Health::run( $ours );
		$base   = admin_url( 'edit.php?post_type=product&page=' . self::SLUG . '&tab=health' . ( $ours ? '' : '&all=1' ) );
		?>
		<div class="sbpi-card">
			<h2>سلامت سئو محصولات</h2>
			<p>
				<?php echo $ours ? 'محصولات ساخته‌شده با این افزونه' : 'همه محصولات'; ?> — <?php echo (int) $report['total']; ?> محصول بررسی شد.
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=' . self::SLUG . '&tab=health' . ( $ours ? '&all=1' : '' ) ) ); ?>"><?php echo $ours ? 'نمایش همه محصولات' : 'فقط محصولات این افزونه'; ?></a>
			</p>
			<div class="sbpi-badges">
				<a class="sbpi-badge <?php echo '' === $issue ? 'on' : ''; ?>" href="<?php echo esc_url( $base ); ?>">همه مشکلات</a>
				<?php foreach ( SBPI_Health::ISSUES as $k => $l ) : ?>
					<a class="sbpi-badge <?php echo $issue === $k ? 'on' : ''; ?> <?php echo $report['counts'][ $k ] ? 'bad' : 'ok'; ?>" href="<?php echo esc_url( add_query_arg( 'issue', $k, $base ) ); ?>"><?php echo esc_html( $l ); ?>: <?php echo (int) $report['counts'][ $k ]; ?></a>
				<?php endforeach; ?>
			</div>
			<table class="widefat striped"><thead><tr><th>محصول</th><th>عنوان سئو</th><th>کلمات</th><th>مشکلات</th></tr></thead><tbody>
			<?php
			$n = 0;
			foreach ( $report['rows'] as $row ) {
				if ( $issue && ! in_array( $issue, $row['issues'], true ) ) {
					continue;
				}
				if ( ++$n > 500 ) {
					echo '<tr><td colspan="4">… فقط ۵۰۰ مورد اول نمایش داده شد؛ با فیلتر بالا محدود کنید.</td></tr>';
					break;
				}
				$labels = array();
				foreach ( $row['issues'] as $i ) {
					$labels[] = '<span class="sbpi-len bad">' . esc_html( SBPI_Health::ISSUES[ $i ] ) . '</span>';
				}
				printf(
					'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%d</td><td>%s</td></tr>',
					esc_url( get_edit_post_link( $row['id'] ) ),
					esc_html( $row['title'] ),
					esc_html( $row['seo'] ),
					(int) $row['words'],
					implode( ' ', $labels ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
				);
			}
			if ( 0 === $n ) {
				echo '<tr><td colspan="4">مشکلی پیدا نشد 🎉</td></tr>';
			}
			?>
			</tbody></table>
			<p class="description">«توضیحات کوتاه» تقریبی است (شمارش کلمات متن بدون HTML). «عنوان سئوی تکراری» با عنوان ذخیره‌شده در افزونه سئو یا نام محصول مقایسه می‌شود.</p>
		</div>
		<?php
	}
}
