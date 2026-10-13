<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resumable job runner. A job is a list of phases; every tick() call does
 * work until its time budget is spent, saves state and returns, so it works
 * under strict shared-hosting limits (admin-ajax loop, WP-Cron or WP-CLI).
 */
class MSW_Job {

	const OPT = 'msw_job';

	private static $labels = array(
		'attachments' => 'فهرست‌برداری از کتابخانه رسانه',
		'sources'     => 'جست‌وجوی ارجاع‌ها در دیتابیس',
		'files'       => 'بررسی فایل‌های قالب',
		'orphans'     => 'جست‌وجوی فایل‌های یتیم در uploads',
		'hashes'      => 'محاسبه اثر انگشت فایل‌ها (تکراری‌ها)',
		'evaluate'    => 'نتیجه‌گیری',
		'queue'       => 'آماده‌سازی صف',
		'convert'     => 'تبدیل به WebP',
		'rewrite'     => 'اصلاح لینک‌ها در دیتابیس',
		'finalize'    => 'بررسی نهایی و پاک‌سازی',
	);

	private static $weights = array(
		'attachments' => 15,
		'sources'     => 55,
		'files'       => 3,
		'orphans'     => 12,
		'hashes'      => 10,
		'evaluate'    => 5,
		'queue'       => 2,
		'convert'     => 55,
		'rewrite'     => 38,
		'finalize'    => 5,
	);

	public static function get() {
		$s = get_option( self::OPT );
		return is_array( $s ) ? $s : array( 'status' => 'idle' );
	}

	private static function save( array $s ) {
		$s['updated'] = time();
		update_option( self::OPT, $s, false );
	}

	public static function is_running() {
		$s = self::get();
		return isset( $s['status'] ) && 'running' === $s['status'];
	}

	/* ---------------------------------------------------------------- start */

	/**
	 * @return true|WP_Error
	 */
	public static function start( $type, array $opts = array() ) {
		global $wpdb;
		if ( self::is_running() ) {
			$cur = self::get();
			if ( time() - (int) $cur['updated'] < 600 ) {
				return new WP_Error( 'busy', 'یک عملیات دیگر در حال اجراست.' );
			}
		}
		$state = array(
			'type'    => $type,
			'status'  => 'running',
			'pi'      => 0,
			'cur'     => 0,
			'si'      => 0,
			'smax'    => 0,
			'smax_ok' => 0,
			'fi'      => 0,
			'dq'      => array(),
			'started' => time(),
			'counts'  => array(),
			'opts'    => $opts,
			'msg'     => '',
			'bg'      => ! empty( $opts['bg'] ),
		);

		if ( 'scan' === $type ) {
			$s      = MSW_Util::all();
			$phases = array( 'attachments', 'sources' );
			if ( $s['scan_theme_files'] ) {
				$phases[] = 'files';
			}
			if ( isset( $opts['orphans'] ) ? $opts['orphans'] : $s['scan_orphans'] ) {
				$phases[] = 'orphans';
			}
			if ( isset( $opts['duplicates'] ) ? $opts['duplicates'] : $s['detect_duplicates'] ) {
				$phases[] = 'hashes';
			}
			$phases[]        = 'evaluate';
			$state['phases'] = $phases;
			MSW_DB::truncate( 'refs' );
			MSW_DB::truncate( 'media' );
			$wpdb->query( "DELETE FROM " . MSW_Util::t( 'orphans' ) . " WHERE status = 'orphan'" ); // phpcs:ignore
			MSW_Scanner::prepare_acf();
			$state['stages'] = MSW_Sources::stages( 'scan' );
			delete_option( 'msw_filelist' );
			MSW_Util::log( 'اسکن شروع شد.' );
		} elseif ( 'convert' === $type ) {
			if ( ! MSW_Converter::engine() ) {
				return new WP_Error( 'noengine', 'روی این سرور نه Imagick با WebP فعال است و نه GD با imagewebp.' );
			}
			$scope = isset( $opts['scope'] ) ? $opts['scope'] : MSW_Util::get( 'webp_scope' );
			$n     = MSW_Converter::build_queue( $scope, ! empty( $opts['retry'] ) );
			if ( is_wp_error( $n ) ) {
				return $n;
			}
			if ( ! $n ) {
				return new WP_Error( 'empty', 'تصویری برای تبدیل پیدا نشد.' );
			}
			$state['phases']           = array( 'queue', 'convert', 'rewrite', 'finalize' );
			$state['counts']['total']  = $n;
			$state['retention']        = (int) MSW_Util::get( 'webp_retention_days' );
			MSW_Util::log( "تبدیل WebP شروع شد ($n تصویر)." );
		} else {
			return new WP_Error( 'type', 'نوع عملیات نامعتبر است.' );
		}
		self::save( $state );
		if ( ! empty( $opts['bg'] ) ) {
			MSW_Cron::schedule_tick();
		}
		return true;
	}

	public static function cancel() {
		$s = self::get();
		if ( isset( $s['status'] ) && 'running' === $s['status'] ) {
			$s['status'] = 'cancelled';
			$s['msg']    = 'لغو شد.';
			self::save( $s );
			MSW_Util::log( 'عملیات توسط کاربر لغو شد.', 'warn' );
		}
	}

	/* --------------------------------------------------------------- status */

	public static function public_state() {
		$s = self::get();
		if ( empty( $s['type'] ) ) {
			return array( 'status' => 'idle' );
		}
		$phase = isset( $s['phases'][ $s['pi'] ] ) ? $s['phases'][ $s['pi'] ] : '';
		return array(
			'type'    => $s['type'],
			'status'  => $s['status'],
			'phase'   => $phase,
			'label'   => isset( self::$labels[ $phase ] ) ? self::$labels[ $phase ] : '',
			'detail'  => isset( $s['detail'] ) ? $s['detail'] : '',
			'pct'     => self::pct( $s ),
			'counts'  => isset( $s['counts'] ) ? $s['counts'] : array(),
			'msg'     => isset( $s['msg'] ) ? $s['msg'] : '',
			'started' => isset( $s['started'] ) ? $s['started'] : 0,
			'bg'      => ! empty( $s['bg'] ),
		);
	}

	private static function pct( array $s ) {
		if ( 'done' === $s['status'] ) {
			return 100;
		}
		if ( empty( $s['phases'] ) ) {
			return 0;
		}
		$total = 0;
		foreach ( $s['phases'] as $p ) {
			$total += self::$weights[ $p ];
		}
		$acc = 0;
		for ( $i = 0; $i < $s['pi'] && $i < count( $s['phases'] ); $i++ ) {
			$acc += self::$weights[ $s['phases'][ $i ] ];
		}
		$frac = isset( $s['frac'] ) ? max( 0, min( 1, $s['frac'] ) ) : 0;
		if ( isset( $s['phases'][ $s['pi'] ] ) ) {
			$acc += self::$weights[ $s['phases'][ $s['pi'] ] ] * $frac;
		}
		return (int) min( 99, round( $acc / max( 1, $total ) * 100 ) );
	}

	/* ----------------------------------------------------------------- tick */

	/**
	 * Do work for roughly $budget seconds.
	 */
	public static function tick( $budget = 4 ) {
		$s = self::get();
		if ( empty( $s['status'] ) || 'running' !== $s['status'] ) {
			return self::public_state();
		}
		if ( ! MSW_Util::lock() ) {
			return self::public_state();
		}
		@ignore_user_abort( true );
		$deadline = microtime( true ) + max( 1, $budget );
		try {
			while ( 'running' === $s['status'] && microtime( true ) < $deadline ) {
				$phase  = $s['phases'][ $s['pi'] ];
				$method = 'phase_' . $phase;
				$done   = self::$method( $s, $deadline );
				if ( $done ) {
					$s['pi']++;
					$s['cur'] = $s['si'] = $s['smax'] = $s['smax_ok'] = $s['fi'] = 0;
					$s['frac'] = 0;
					$s['dq']   = array();
					$s['detail'] = '';
					if ( $s['pi'] >= count( $s['phases'] ) ) {
						$s['status']   = 'done';
						$s['finished'] = time();
						self::on_finished( $s );
					}
				}
				// Reflect a cancel issued from another request.
				$fresh = self::get();
				if ( isset( $fresh['status'] ) && 'cancelled' === $fresh['status'] ) {
					$s['status'] = 'cancelled';
				}
			}
		} catch ( \Throwable $e ) {
			$s['status'] = 'error';
			$s['msg']    = $e->getMessage();
			MSW_Util::log( 'خطا در عملیات: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')', 'error' );
		}
		self::save( $s );
		MSW_Util::unlock();
		return self::public_state();
	}

	private static function on_finished( array &$s ) {
		$dur = time() - (int) $s['started'];
		if ( 'scan' === $s['type'] ) {
			$ls             = get_option( 'msw_last_scan', array() );
			$ls['time']     = time();
			$ls['duration'] = $dur;
			update_option( 'msw_last_scan', $ls, false );
			MSW_Util::log( sprintf( 'اسکن پایان یافت: %d بدون استفاده (%s)، %d فایل یتیم. مدت: %d ثانیه.', $ls['unused'], MSW_Util::size( $ls['unused_bytes'] ), $ls['orphans'], $dur ) );
			if ( ! empty( $s['opts']['notify'] ) ) {
				MSW_Cron::send_report( $ls );
			}
		} else {
			$c = $s['counts'];
			MSW_Util::log( sprintf( 'تبدیل WebP پایان یافت: %d تبدیل، %d ردشده، %d ناموفق؛ صرفه‌جویی: %s.', isset( $c['converted'] ) ? $c['converted'] : 0, isset( $c['skipped'] ) ? $c['skipped'] : 0, isset( $c['failed'] ) ? $c['failed'] : 0, MSW_Util::size( isset( $c['saved'] ) ? $c['saved'] : 0 ) ) );
			$st = get_option( 'msw_stats', array() );
			$st['saved'] = (int) ( isset( $st['saved'] ) ? $st['saved'] : 0 ) + (int) ( isset( $c['saved'] ) ? $c['saved'] : 0 );
			update_option( 'msw_stats', $st, false );
		}
	}

	/* ---------------------------------------------------------- scan phases */

	private static function phase_attachments( array &$s, $deadline ) {
		global $wpdb;
		if ( ! $s['smax_ok'] ) {
			$s['smax']    = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type='attachment'" );
			$s['smax_ok'] = 1;
		}
		while ( microtime( true ) < $deadline ) {
			$last = MSW_Scanner::attachments_batch( (int) $s['cur'] );
			if ( false === $last ) {
				return true;
			}
			$s['cur']  = $last;
			$s['frac'] = $s['smax'] ? $s['cur'] / $s['smax'] : 1;
		}
		return false;
	}

	/** Shared loop for scan ("sources") and rewrite. */
	private static function walk_stages( array &$s, $deadline, $mode ) {
		$count = count( $s['stages'] );
		while ( microtime( true ) < $deadline ) {
			if ( $s['si'] >= $count ) {
				MSW_Scanner::flush();
				return true;
			}
			$st = $s['stages'][ $s['si'] ];
			if ( ! $s['smax_ok'] ) {
				$s['smax']    = MSW_Sources::max_cursor( $st );
				$s['smax_ok'] = 1;
				$s['detail']  = $st['label'];
			}
			$rows = MSW_Sources::fetch( $st, $s['cur'] );
			if ( ! $rows ) {
				MSW_Scanner::flush();
				$s['si']++;
				$s['cur']     = 0;
				$s['smax_ok'] = 0;
				continue;
			}
			foreach ( $rows as $row ) {
				if ( 'scan' === $mode ) {
					MSW_Scanner::row( $st, $row );
				} else {
					$changes = MSW_Rewriter::row( $st, $row );
					if ( $changes ) {
						MSW_Rewriter::save( $st, $row, $changes );
						$s['counts']['rewritten'] = ( isset( $s['counts']['rewritten'] ) ? $s['counts']['rewritten'] : 0 ) + 1;
					}
				}
				$s['cur'] = (int) $row['_pk'];
			}
			if ( 'scan' === $mode ) {
				MSW_Scanner::flush();
			}
			$inner     = $s['smax'] ? min( 1, $s['cur'] / $s['smax'] ) : 1;
			$s['frac'] = ( $s['si'] + $inner ) / max( 1, $count );
		}
		return false;
	}

	private static function phase_sources( array &$s, $deadline ) {
		return self::walk_stages( $s, $deadline, 'scan' );
	}

	private static function phase_files( array &$s, $deadline ) {
		$list = get_option( 'msw_filelist' );
		if ( ! is_array( $list ) ) {
			$list = MSW_Sources::theme_files();
			update_option( 'msw_filelist', $list, false );
		}
		$n = count( $list );
		while ( microtime( true ) < $deadline ) {
			if ( $s['fi'] >= $n ) {
				MSW_Scanner::flush();
				delete_option( 'msw_filelist' );
				return true;
			}
			MSW_Scanner::file( $list[ $s['fi'] ] );
			$s['fi']++;
			if ( 0 === $s['fi'] % 25 ) {
				MSW_Scanner::flush();
			}
			$s['frac'] = $n ? $s['fi'] / $n : 1;
		}
		MSW_Scanner::flush();
		return false;
	}

	private static function phase_orphans( array &$s, $deadline ) {
		if ( ! $s['smax_ok'] ) {
			$s['dq']      = array( array( MSW_Util::basedir(), 0 ) );
			$s['smax']    = 0; // directories finished.
			$s['smax_ok'] = 1;
		}
		while ( microtime( true ) < $deadline ) {
			if ( ! $s['dq'] ) {
				return true;
			}
			$job = array_shift( $s['dq'] );
			foreach ( MSW_Scanner::orphan_dir( $job[0], $job[1] ) as $sub ) {
				$s['dq'][] = array( $sub, $job[1] + 1 );
			}
			$s['smax']++;
			$s['frac'] = $s['smax'] / ( $s['smax'] + count( $s['dq'] ) );
			$s['detail'] = basename( $job[0] );
		}
		return false;
	}

	private static function phase_hashes( array &$s, $deadline ) {
		global $wpdb;
		if ( ! $s['smax_ok'] ) {
			$s['smax']    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'media' ) . " WHERE file_hash IS NULL AND missing = 0 AND status <> 'skipped'" ); // phpcs:ignore
			$s['smax_ok'] = 1;
		}
		while ( microtime( true ) < $deadline ) {
			if ( ! MSW_Scanner::hash_batch( 25 ) ) {
				return true;
			}
			$s['cur'] += 25;
			$s['frac'] = $s['smax'] ? min( 1, $s['cur'] / $s['smax'] ) : 1;
		}
		return false;
	}

	private static function phase_evaluate( array &$s, $deadline ) {
		MSW_Scanner::evaluate();
		return true;
	}

	/* ------------------------------------------------------- convert phases */

	private static function phase_queue( array &$s, $deadline ) {
		return true;
	}

	private static function phase_convert( array &$s, $deadline ) {
		$c = $s['counts'];
		foreach ( array( 'converted', 'skipped', 'failed', 'saved', 'processed' ) as $k ) {
			if ( ! isset( $c[ $k ] ) ) {
				$c[ $k ] = 0;
			}
		}
		$s['counts'] = $c;
		while ( microtime( true ) < $deadline ) {
			$r = MSW_Converter::process_next();
			if ( ! $r ) {
				return true;
			}
			$c['processed']++;
			if ( 'converted' === $r['status'] ) {
				$c['converted']++;
				$c['saved'] += max( 0, $r['before'] - $r['after'] );
			} elseif ( 'skipped' === $r['status'] ) {
				$c['skipped']++;
			} else {
				$c['failed']++;
			}
			$s['counts'] = $c;
			$s['frac']   = $c['total'] ? min( 1, $c['processed'] / $c['total'] ) : 1;
			$s['detail'] = '#' . $r['id'];
		}
		return false;
	}

	private static function phase_rewrite( array &$s, $deadline ) {
		global $wpdb;
		if ( ! $s['smax_ok'] && 0 === (int) $s['si'] ) {
			$pending = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'converted' ) . " WHERE status = 'converted'" ); // phpcs:ignore
			if ( ! $pending ) {
				return true;
			}
			$s['stages'] = MSW_Sources::stages( 'rewrite' );
		}
		return self::walk_stages( $s, $deadline, 'rewrite' );
	}

	private static function phase_finalize( array &$s, $deadline ) {
		global $wpdb;
		$ret = isset( $s['retention'] ) ? (int) $s['retention'] : 0;
		while ( microtime( true ) < $deadline ) {
			if ( ! MSW_Converter::finalize( $ret ) ) {
				break;
			}
		}
		$left = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . MSW_Util::t( 'converted' ) . " WHERE status = 'converted'" ); // phpcs:ignore
		if ( $left ) {
			return false;
		}
		MSW_DB::truncate( 'map' );
		MSW_Util::flush_caches();
		return true;
	}
}
