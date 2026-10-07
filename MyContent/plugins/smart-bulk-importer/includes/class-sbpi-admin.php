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
		$job = strtolower( wp_generate_password( 12, false ) );
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
		wp_safe_redirect( add_query_arg( 'job', $job, $back ) );
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

		echo '<div class="wrap sbpi" dir="rtl">';
		echo '<h1>درون‌ریز هوشمند محصولات</h1>';
		echo '<nav class="nav-tab-wrapper">';
		printf( '<a class="nav-tab %s" href="%s">درون‌ریزی</a>', 'history' !== $tab ? 'nav-tab-active' : '', esc_url( $base ) );
		printf( '<a class="nav-tab %s" href="%s">تاریخچه و بازگردانی</a>', 'history' === $tab ? 'nav-tab-active' : '', esc_url( add_query_arg( 'tab', 'history', $base ) ) );
		echo '</nav>';
		if ( $err ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
		}

		if ( 'history' === $tab ) {
			self::render_history();
		} elseif ( $job ) {
			self::render_mapping( $job );
		} else {
			self::render_upload();
		}
		echo '</div>';
	}

	/**
	 * Step 1.
	 */
	private static function render_upload() {
		$seo = SBPI_SEO::seo_plugin();
		?>
		<div class="sbpi-card">
			<h2>۱. آپلود فایل</h2>
			<p>فایل Excel (<code>.xlsx</code>) با چند شیت یا CSV. سطر عنوان، سطر هدر، سطرهای «عنوان بخش» (مثل <code>iPhone 13 — نو</code>) و شیت‌های راهنمای دوستونه به‌صورت خودکار تشخیص داده می‌شوند.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sbpi_upload' ); ?>
				<input type="hidden" name="action" value="sbpi_upload" />
				<input type="file" name="sbpi_file" accept=".xlsx,.csv" required />
				<?php submit_button( 'خواندن فایل و ادامه', 'primary', 'submit', false ); ?>
			</form>
			<p class="description">
				افزونه سئوی شناسایی‌شده:
				<strong><?php echo esc_html( $seo ? $seo : 'هیچ‌کدام (عنوان و توضیحات متا توسط همین افزونه چاپ می‌شود)' ); ?></strong>
				<?php if ( 'aioseo' === $seo ) : ?>
					— متای AIOSEO در جدول اختصاصی آن ذخیره می‌شود و این افزونه آن را پر نمی‌کند.
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Step 2: mapping + global settings + preview/run area.
	 *
	 * @param array $job Job.
	 */
	private static function render_mapping( array $job ) {
		$presets = get_option( 'sbpi_presets', array() );
		$global  = self::default_global();
		$roles   = SBPI_Planner::roles();
		$glossary_count = 0;
		?>
		<form id="sbpi-form" data-job="<?php echo esc_attr( $job['id'] ); ?>">
		<div class="sbpi-card">
			<h2>۲. تنظیمات شیت‌ها و ستون‌ها <small>— فایل: <?php echo esc_html( $job['file'] ); ?></small></h2>
			<p class="description">
				<strong>محصول جدا</strong> = هر مقدار (مثلاً 128 GB / نو / استوک / اکتیو / نات‌اکتیو) یک محصول مستقل با نام و نامک جدا می‌سازد؛ نسخه‌های یک مدل خودکار به هم لینک داخلی می‌دهند.
								<strong>ویژگی متغیر</strong> = کاربر هنگام خرید انتخاب می‌کند (حافظه، رنگ، ریجن، وضعیت) و از ترکیب آن‌ها «تنوع» ساخته می‌شود.
				<strong>ویژگی نمایشی</strong> = فقط در جدول مشخصات و فیلترها می‌آید. همه ویژگی‌ها «سراسری» (pa_) ساخته می‌شوند تا در فیلتر و Schema قابل استفاده باشند.
				سطرهایی که «نام/مدل» یکسان دارند یک محصول متغیر می‌شوند. مقادیر چندتایی با <code>|</code> جدا می‌شوند.
			</p>
		<?php
		foreach ( $job['parsed'] as $si => $sheet ) {
			if ( 'glossary' === $sheet['kind'] ) {
				$glossary_count += count( $sheet['glossary'] );
				printf( '<div class="sbpi-sheet sbpi-muted"><h3>📘 %s</h3><p>شیت راهنما — %d مورد به‌عنوان «نکات پیش از خرید» در توضیحات محصولات مرتبط استفاده می‌شود.</p></div>', esc_html( $sheet['name'] ), count( $sheet['glossary'] ) );
				continue;
			}
			if ( 'products' !== $sheet['kind'] ) {
				printf( '<div class="sbpi-sheet sbpi-muted"><h3>%s</h3><p>داده‌ای شناسایی نشد.</p></div>', esc_html( $sheet['name'] ) );
				continue;
			}
			$conf = SBPI_Planner::default_settings( $sheet );
			$sig  = self::signature( $sheet );
			if ( isset( $presets[ $sig ] ) && is_array( $presets[ $sig ] ) ) {
				// "+" (not array_merge) keeps numeric column keys intact.
				$conf['columns'] = $presets[ $sig ]['columns'] + $conf['columns'];
				$conf['title_tpl'] = $presets[ $sig ]['title_tpl'];
			}
			?>
			<div class="sbpi-sheet" data-sheet="<?php echo esc_attr( $si ); ?>">
				<h3>
					<label><input type="checkbox" data-f="enabled" <?php checked( $conf['enabled'] ); ?> /> 📄 <?php echo esc_html( $sheet['name'] ); ?></label>
					<small><?php echo esc_html( sprintf( '%d سطر داده', count( $sheet['rows'] ) ) ); ?><?php echo isset( $presets[ $sig ] ) ? ' — نگاشت قبلی بازیابی شد ✓' : ''; ?></small>
				</h3>
				<?php if ( $sheet['title'] ) : ?>
					<p class="description"><?php echo esc_html( $sheet['title'] ); ?></p>
				<?php endif; ?>
				<div class="sbpi-grid">
					<label>دسته‌بندی <input type="text" data-f="category" value="<?php echo esc_attr( $conf['category'] ); ?>" placeholder="کنسول بازی > {s1}" /></label>
					<label>برند <input type="text" data-f="brand" value="" placeholder="خالی = تشخیص خودکار (اپل، سونی، …)" /></label>
					<label>الگوی نام محصول <input type="text" data-f="title_tpl" value="<?php echo esc_attr( $conf['title_tpl'] ); ?>" placeholder="{model}" /></label>
				</div>
				<p class="description">متغیرها: <code>{model}</code> <code>{split}</code> (مقادیر ستون‌های «محصول جدا») یا نامک هر ستون مثل <code>{storage}</code> <code>{condition}</code>، <code>{sheet}</code> <code>{s1}</code> <code>{s2}</code> <code>{s3}</code> (بخش‌های سطر عنوان؛ مثلاً در «PLAY STATION 5 — ACCENT — استوک»، s1=PLAY STATION 5). مثال: <code>گوشی موبایل اپل {model} ظرفیت {storage} {condition}</code>.</p>
				<table class="widefat striped sbpi-cols">
					<thead><tr><th>ستون</th><th>نمونه داده</th><th>نقش</th><th>نام ویژگی</th><th>نامک لاتین</th><th>چندمقداری</th></tr></thead>
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
						<tr data-col="<?php echo esc_attr( $ck ); ?>">
							<td><strong><?php echo esc_html( $info[0] ); ?></strong></td>
							<td class="sbpi-sample"><?php echo esc_html( SBPI_Util::truncate( implode( ' ⟩ ', $info[1] ), 90 ) ); ?></td>
							<td><select data-c="role">
								<?php foreach ( $roles as $rk => $rl ) : ?>
									<option value="<?php echo esc_attr( $rk ); ?>" <?php selected( $col['role'], $rk ); ?>><?php echo esc_html( $rl ); ?></option>
								<?php endforeach; ?>
							</select></td>
							<td><input type="text" data-c="name" value="<?php echo esc_attr( $col['name'] ); ?>" /></td>
							<td><input type="text" data-c="slug" value="<?php echo esc_attr( $col['slug'] ); ?>" dir="ltr" placeholder="storage" maxlength="27" /></td>
							<td><input type="checkbox" data-c="split" <?php checked( ! empty( $col['split'] ) ); ?> /></td>
						</tr>
						<?php
					}
					?>
					</tbody>
				</table>
			</div>
			<?php
		}
		?>
		</div>

		<div class="sbpi-card" id="sbpi-global">
			<h2>۳. تنظیمات عمومی و سئو</h2>
			<div class="sbpi-grid">
				<label>وضعیت انتشار محصولات جدید
					<select data-g="status">
						<?php foreach ( array( 'draft' => 'پیش‌نویس (پیشنهادی: اول بررسی کنید)', 'publish' => 'منتشرشده', 'pending' => 'در انتظار بررسی', 'private' => 'خصوصی' ) as $k => $l ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $global['status'], $k ); ?>><?php echo esc_html( $l ); ?></option>
						<?php endforeach; ?>
					</select></label>
				<label>اگر محصول از قبل وجود داشت
					<select data-g="update_mode">
						<option value="update" <?php selected( $global['update_mode'], 'update' ); ?>>به‌روزرسانی (قیمت، موجودی، ویژگی‌ها، تنوع‌های جدید)</option>
						<option value="skip" <?php selected( $global['update_mode'], 'skip' ); ?>>رد شود</option>
					</select></label>
				<label>وضعیت موجودی پیش‌فرض
					<select data-g="stock_status">
						<option value="instock" <?php selected( $global['stock_status'], 'instock' ); ?>>موجود</option>
						<option value="outofstock" <?php selected( $global['stock_status'], 'outofstock' ); ?>>ناموجود</option>
						<option value="onbackorder" <?php selected( $global['stock_status'], 'onbackorder' ); ?>>پیش‌خرید</option>
					</select></label>
				<label>قیمت پیش‌فرض (وقتی ستون قیمت ندارید) <input type="text" data-g="default_price" value="<?php echo esc_attr( $global['default_price'] ); ?>" placeholder="خالی" /></label>
				<label>سقف تنوع برای هر محصول <input type="number" data-g="max_variations" value="<?php echo esc_attr( $global['max_variations'] ); ?>" min="1" max="2000" /></label>
				<label>جداکننده مقادیر چندتایی <input type="text" data-g="separator" value="<?php echo esc_attr( $global['separator'] ); ?>" dir="ltr" /></label>
				<label>پیشوند SKU <input type="text" data-g="sku_prefix" value="<?php echo esc_attr( $global['sku_prefix'] ); ?>" dir="ltr" placeholder="مثلاً SH-" /></label>
				<label>نام فروشگاه در عنوان سئو <input type="text" data-g="store_name" value="<?php echo esc_attr( $global['store_name'] ); ?>" /></label>
				<label>الگوی عنوان سئو <input type="text" data-g="seo_title_tpl" value="<?php echo esc_attr( $global['seo_title_tpl'] ); ?>" /></label>
				<label>الگوی توضیحات متا <input type="text" data-g="seo_desc_tpl" value="<?php echo esc_attr( $global['seo_desc_tpl'] ); ?>" placeholder="خالی = تولید هوشمند از ویژگی‌ها" /></label>
				<label>الگوی کلمه کلیدی کانونی <input type="text" data-g="focus_tpl" value="<?php echo esc_attr( $global['focus_tpl'] ); ?>" /></label>
			</div>
			<p class="description">متغیرهای سئو: <code>{title}</code> <code>{model}</code> <code>{brand}</code> <code>{brand_en}</code> <code>{category}</code> <code>{site}</code> <code>{options}</code> <code>{conditions}</code> <code>{attr:storage}</code> یا <code>{attr:رنگ}</code>. عنوان بالای ۶۵ و توضیحات بالای ۱۵۸ کاراکتر هوشمندانه کوتاه می‌شوند.</p>
			<div class="sbpi-checks">
				<label><input type="checkbox" data-g="gen_description" <?php checked( $global['gen_description'] ); ?> /> تولید توضیحات کامل (معرفی، جدول مشخصات، تفاوت نسخه‌ها، نکات خرید از شیت راهنما<?php echo $glossary_count ? ' — ' . (int) $glossary_count . ' مورد' : ''; ?>)</label>
				<label><input type="checkbox" data-g="auto_sku" <?php checked( $global['auto_sku'] ); ?> /> ساخت خودکار SKU یکتا برای محصول و تنوع‌ها</label>
				<label><input type="checkbox" data-g="attr_archives" <?php checked( $global['attr_archives'] ); ?> /> فعال‌سازی آرشیو برای ویژگی‌های جدید (صفحه مستقل برای هر رنگ/حافظه — فقط اگر برای آن‌ها محتوا دارید)</label>
				<label><input type="checkbox" data-g="update_title" <?php checked( $global['update_title'] ); ?> /> در به‌روزرسانی، نام محصول هم بازنویسی شود</label>
				<label><input type="checkbox" data-g="overwrite_content" <?php checked( $global['overwrite_content'] ); ?> /> توضیحاتی که دستی ویرایش شده‌اند هم بازنویسی شوند (توصیه نمی‌شود)</label>
			</div>
		</div>

		<div class="sbpi-card">
			<h2>۴. پیش‌نمایش و اجرا</h2>
			<p>
				<button type="button" class="button button-secondary" id="sbpi-preview">🔍 پیش‌نمایش (بدون تغییر در سایت)</button>
				<button type="button" class="button button-primary" id="sbpi-run" disabled>🚀 شروع درون‌ریزی</button>
				<button type="button" class="button" id="sbpi-stop" hidden>⏸ توقف</button>
			</p>
			<div id="sbpi-progress" hidden><div class="sbpi-bar"><span></span></div><p class="sbpi-progress-text"></p></div>
			<div id="sbpi-result"></div>
			<pre id="sbpi-log" hidden></pre>
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
		echo '<p class="description">«بازگردانی» فقط محصولاتی را که در آن نوبت <strong>ساخته</strong> شده‌اند (همراه تنوع‌ها) حذف کامل می‌کند؛ محصولات به‌روزرسانی‌شده و تصاویر کتابخانه دست نمی‌خورند. قبل از آن نسخه پشتیبان بگیرید.</p>';
		echo '<table class="widefat striped"><thead><tr><th>تاریخ</th><th>فایل</th><th>ساخته‌شده</th><th>به‌روزشده</th><th>ردشده</th><th>خطا</th><th>وضعیت</th><th></th></tr></thead><tbody>';
		foreach ( array_reverse( $batches, true ) as $id => $b ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td><td>%s</td></tr>',
				esc_html( wp_date( 'Y/m/d H:i', $b['time'] ) ),
				esc_html( $b['file'] ),
				(int) $b['created'],
				(int) $b['updated'],
				(int) $b['skipped'],
				(int) $b['errors'],
				esc_html( $b['status'] ),
				'rolled_back' === $b['status'] ? '' : '<button type="button" class="button sbpi-rollback" data-batch="' . esc_attr( $id ) . '">بازگردانی</button>'
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

		$plan = SBPI_Planner::build( $job['parsed'], $settings );
		update_option( 'sbpi_plan_' . $job['id'], array( 'plan' => $plan, 'global' => $settings['global'] ), false );
		delete_option( 'sbpi_state_' . $job['id'] );
		update_option( 'sbpi_global', $settings['global'], false );

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
				'category'   => implode( ' › ', $p['category'] ),
				'brand'      => $p['brand'] ? $p['brand'][0] : '—',
				'attributes' => implode( '، ', $axes ),
				'seo_title'  => $p['seo']['title'],
				'seo_desc'   => $p['seo']['desc'],
				'focus'      => $p['seo']['focus'],
				'priced'     => $priced,
				'content'    => wp_kses_post( $p['content'] ),
				'lines'      => implode( '، ', array_slice( $p['lines'], 0, 6 ) ) . ( count( $p['lines'] ) > 6 ? ' …' : '' ),
			);
		}
		$warnings = $plan['warnings'];
		if ( $no_price ) {
			$warnings[] = sprintf( '%d محصول قیمت ندارند. ووکامرس تنوع‌های بدون قیمت را در صفحه محصول قابل انتخاب نمی‌کند؛ بعداً قیمت را با همین افزونه (ستون قیمت + حالت به‌روزرسانی) یا ویرایش گروهی وارد کنید.', $no_price );
		}
		wp_send_json_success(
			array(
				'products'   => $rows,
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
			$batches                    = get_option( 'sbpi_batches', array() );
			$batches[ $state['batch'] ] = array(
				'time'   => time(),
				'file'   => $job['file'],
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

		$batches = get_option( 'sbpi_batches', array() );
		if ( isset( $batches[ $state['batch'] ] ) ) {
			$batches[ $state['batch'] ] = array_merge( $batches[ $state['batch'] ], $state['stats'], array( 'status' => $done ? 'done' : 'running' ) );
			update_option( 'sbpi_batches', $batches, false );
		}
		if ( $done ) {
			delete_option( 'sbpi_state_' . $id );
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
		$left = SBPI_Importer::rollback( $batch, self::deadline() );
		if ( 0 === $left ) {
			$batches[ $batch ]['status'] = 'rolled_back';
			update_option( 'sbpi_batches', $batches, false );
		}
		wp_send_json_success( array( 'remaining' => $left ) );
	}
}
