<?php
defined( 'ABSPATH' ) || exit;

/**
 * لایه‌ی دیتابیس: جدول آمار روزانه (کلیک بنر/دسته، نمایش استوری، بازدید محصول)
 * و شمارنده‌ی بازدید محصول (متای _wsk_views) برای مرتب‌سازی «پربازدیدترین».
 */
class WSK_DB {

	const VIEWS_KEY = '_wsk_views';
	const CRON      = 'wsk_prune_stats';
	const TYPES     = array( 'banner', 'category', 'story', 'product' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wsk_stats';
	}

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 20 );
		add_action( self::CRON, array( __CLASS__, 'prune' ) );
		add_action( 'wp_insert_post', array( __CLASS__, 'default_views' ), 10, 2 );
	}

	public static function activate() {
		self::install();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( 'wsk_db_version' ) !== WSK_DB_VERSION ) {
			self::install();
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		// day + نوع + کلید یکتا؛ هر کلیک یک UPSERT اتمی است (بدون خواندن-نوشتن جداگانه).
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			obj_type varchar(20) NOT NULL,
			obj_key varchar(100) NOT NULL,
			label varchar(255) NOT NULL DEFAULT '',
			day date NOT NULL,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq_hit (obj_type,obj_key,day),
			KEY day_idx (day)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'wsk_db_version', WSK_DB_VERSION, false );
	}

	/* ---------- ثبت آمار ---------- */
	public static function hit( $type, $key, $label = '' ) {
		global $wpdb;
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return false;
		}
		$table = self::table();
		$day   = current_time( 'Y-m-d' );
		$key   = substr( (string) $key, 0, 100 );
		$label = mb_substr( wp_strip_all_tags( (string) $label ), 0, 250 );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام جدول از prefix وردپرس است.
		$sql = $wpdb->prepare(
			"INSERT INTO {$table} (obj_type,obj_key,label,day,hits) VALUES (%s,%s,%s,%s,1) ON DUPLICATE KEY UPDATE hits = hits + 1, label = VALUES(label)",
			$type,
			$key,
			$label,
			$day
		);
		return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * مجموع هر آیتم در N روز اخیر.
	 *
	 * @return array[] هر ردیف: obj_type, obj_key, label, hits
	 */
	public static function report( $type, $days = 30, $limit = 50 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d', strtotime( '-' . max( 1, (int) $days ) . ' days', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT obj_type, obj_key, MAX(label) AS label, SUM(hits) AS hits FROM {$table} WHERE obj_type = %s AND day >= %s GROUP BY obj_type, obj_key ORDER BY hits DESC LIMIT %d",
				$type,
				$since,
				(int) $limit
			),
			ARRAY_A
		);
		// phpcs:enable
	}

	public static function totals_per_day( $days = 30 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d', strtotime( '-' . max( 1, (int) $days ) . ' days', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT day, obj_type, SUM(hits) AS hits FROM {$table} WHERE day >= %s GROUP BY day, obj_type ORDER BY day ASC", $since ),
			ARRAY_A
		);
		// phpcs:enable
	}

	public static function prune() {
		global $wpdb;
		$days  = max( 7, (int) WSK_Options::get( 'system', 'retention_days', 180 ) );
		$table = self::table();
		$limit = gmdate( 'Y-m-d', strtotime( '-' . $days . ' days', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", $limit ) );
	}

	public static function reset_stats() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB
	}

	/* ---------- بازدید محصول ---------- */
	/** افزایش اتمی شمارنده؛ اگر متا نبود ساخته می‌شود. */
	public static function bump_views( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s", $post_id, self::VIEWS_KEY )
		);
		if ( ! $updated ) {
			add_post_meta( $post_id, self::VIEWS_KEY, 1, true );
		}
	}

	public static function default_views( $post_id, $post ) {
		if ( $post && 'product' === $post->post_type && '' === get_post_meta( $post_id, self::VIEWS_KEY, true ) ) {
			add_post_meta( $post_id, self::VIEWS_KEY, 0, true );
		}
	}

	/** ساخت متای صفر برای محصولات قدیمی (یک کوئری). */
	public static function backfill_views() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$n = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT p.ID, %s, '0' FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_type = 'product' AND m.meta_id IS NULL",
				self::VIEWS_KEY,
				self::VIEWS_KEY
			)
		);
		// phpcs:enable
		return (int) $n;
	}

	/**
	 * آرگومان‌های WP_Query برای مرتب‌سازی نزولی بر اساس یک متای عددی؛
	 * محصولات بدون آن متا حذف نمی‌شوند (برخلاف meta_key + meta_value_num).
	 */
	public static function meta_order_args( $key, $meta_query = array() ) {
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'relation'  => 'OR',
			'wsk_num'   => array(
				'key'     => $key,
				'type'    => 'NUMERIC',
				'compare' => 'EXISTS',
			),
			'wsk_nonum' => array(
				'key'     => $key,
				'compare' => 'NOT EXISTS',
			),
		);
		return array(
			'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'    => array( 'wsk_num' => 'DESC', 'date' => 'DESC' ),
		);
	}

	public static function views_query_args( $meta_query = array() ) {
		return self::meta_order_args( self::VIEWS_KEY, $meta_query );
	}
}
