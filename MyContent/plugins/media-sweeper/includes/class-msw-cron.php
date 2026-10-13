<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSW_Cron {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
		add_action( 'msw_daily', array( __CLASS__, 'daily' ) );
		add_action( 'msw_scheduled_scan', array( __CLASS__, 'scheduled_scan' ) );
		add_action( 'msw_job_tick', array( __CLASS__, 'job_tick' ) );
	}

	public static function schedules( $s ) {
		$s['msw_weekly']  = array( 'interval' => WEEK_IN_SECONDS, 'display' => 'Weekly (Media Sweeper)' );
		$s['msw_monthly'] = array( 'interval' => 30 * DAY_IN_SECONDS, 'display' => 'Monthly (Media Sweeper)' );
		return $s;
	}

	public static function activate() {
		self::reschedule();
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'msw_daily' );
		wp_clear_scheduled_hook( 'msw_scheduled_scan' );
		wp_clear_scheduled_hook( 'msw_job_tick' );
	}

	public static function reschedule() {
		if ( ! wp_next_scheduled( 'msw_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'msw_daily' );
		}
		wp_clear_scheduled_hook( 'msw_scheduled_scan' );
		$mode = MSW_Util::get( 'auto_scan' );
		if ( 'weekly' === $mode || 'monthly' === $mode ) {
			// Night-time start (03:00 site time next day).
			$when = strtotime( 'tomorrow 03:00', current_time( 'timestamp' ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
			wp_schedule_event( max( time() + 300, $when ), 'weekly' === $mode ? 'msw_weekly' : 'msw_monthly', 'msw_scheduled_scan' );
		}
	}

	public static function daily() {
		MSW_Quarantine::purge_expired();
		MSW_Converter::purge_originals( false );
		MSW_Renamer::purge_undo( false );
		MSW_Optimizer::purge_backups( false );
	}

	public static function scheduled_scan() {
		if ( MSW_Job::is_running() ) {
			return;
		}
		$r = MSW_Job::start( 'scan', array( 'bg' => true, 'notify' => true ) );
		if ( is_wp_error( $r ) ) {
			MSW_Util::log( 'اسکن زمان‌بندی‌شده: ' . $r->get_error_message(), 'warn' );
		}
	}

	public static function schedule_tick() {
		if ( ! wp_next_scheduled( 'msw_job_tick' ) ) {
			wp_schedule_single_event( time() + 30, 'msw_job_tick' );
		}
	}

	public static function job_tick() {
		$s = MSW_Job::get();
		if ( empty( $s['status'] ) || 'running' !== $s['status'] || empty( $s['bg'] ) ) {
			return;
		}
		$r = MSW_Job::tick( 20 );
		if ( 'running' === $r['status'] ) {
			self::schedule_tick();
		}
	}

	public static function send_report( array $ls ) {
		$to = MSW_Util::get( 'notify_email' );
		if ( ! $to ) {
			return;
		}
		$body = sprintf(
			MSW_I18n::tr( "گزارش Media Sweeper — %s\n\nکل رسانه‌ها: %d\nبدون استفاده: %d (%s)\nفایل‌های یتیم: %d (%s)\nمفقود: %d\n\nبرای مشاهده و پاک‌سازی: %s" ),
			home_url(),
			$ls['total'],
			$ls['unused'],
			MSW_Util::size( $ls['unused_bytes'] ),
			$ls['orphans'],
			MSW_Util::size( $ls['orphan_bytes'] ),
			$ls['missing'],
			admin_url( 'upload.php?page=media-sweeper' )
		);
		wp_mail( $to, MSW_I18n::tr( 'گزارش اسکن رسانه — ' ) . wp_parse_url( home_url(), PHP_URL_HOST ), $body );
	}
}
