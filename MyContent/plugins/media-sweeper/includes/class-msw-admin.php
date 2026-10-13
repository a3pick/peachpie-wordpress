<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MSW_Admin {

	const SLUG = 'media-sweeper';

	public static function cap() {
		return apply_filters( 'msw_capability', 'manage_options' );
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_msw', array( __CLASS__, 'ajax' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MSW_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'protect_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'protect_save' ), 10, 2 );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'upload.php?page=' . self::SLUG ) ) . '">' . esc_html( MSW_I18n::tr( 'اسکن رسانه' ) ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_submenu_page( 'upload.php', 'Media Sweeper', 'Media Sweeper', self::cap(), self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function assets( $hook ) {
		if ( 'media_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'msw-admin', MSW_URL . 'assets/admin.css', array(), MSW_VERSION );
		wp_enqueue_script( 'msw-admin', MSW_URL . 'assets/admin.js', array(), MSW_VERSION, true );
		wp_localize_script(
			'msw-admin',
			'MSW',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'msw' ),
				'rtl'      => is_rtl(),
				'settings' => MSW_Util::all(),
				'version'  => MSW_VERSION,
				'qdays'    => (int) MSW_Util::get( 'quarantine_days' ),
				'i18n'     => MSW_I18n::js_catalog(),
				'lang'     => MSW_I18n::active() ? substr( MSW_I18n::locale(), 0, 2 ) : 'fa',
			)
		);
	}

	public static function page() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Access denied.' ) );
		}
		echo '<div class="wrap msw-wrap"><div id="msw-app" class="msw-app"><p>' . esc_html( MSW_I18n::tr( 'در حال بارگذاری…' ) ) . '</p></div>';
		echo '<noscript><p>' . esc_html( MSW_I18n::tr( 'برای استفاده از این صفحه جاوااسکریپت لازم است.' ) ) . '</p></noscript></div>';
	}

	/* --------------------------------------------- "protect" on attachment */

	public static function protect_field( $fields, $post ) {
		$checked = get_post_meta( $post->ID, '_msw_protected', true ) ? 'checked' : '';
		$fields['msw_protect'] = array(
			'label' => 'Media Sweeper',
			'input' => 'html',
			'html'  => '<input type="hidden" name="attachments[' . (int) $post->ID . '][msw_protect_present]" value="1"><label><input type="checkbox" name="attachments[' . (int) $post->ID . '][msw_protect]" value="1" ' . $checked . '> ' . esc_html( MSW_I18n::tr( 'محافظت شود (هرگز حذف/قرنطینه نشود)' ) ) . '</label>',
		);
		return $fields;
	}

	public static function protect_save( $post, $attachment ) {
		if ( isset( $attachment['msw_protect'] ) ) {
			update_post_meta( $post['ID'], '_msw_protected', 1 );
		} elseif ( isset( $post['ID'] ) && current_user_can( 'edit_post', $post['ID'] ) && isset( $attachment['msw_protect_present'] ) ) {
			delete_post_meta( $post['ID'], '_msw_protected' );
		}
		return $post;
	}

	/* ------------------------------------------------------------- AJAX */

	private static function filters() {
		$in = isset( $_REQUEST['f'] ) && is_array( $_REQUEST['f'] ) ? wp_unslash( $_REQUEST['f'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		$out = array();
		foreach ( array( 'status', 'locked', 'type', 'q', 'min_mb', 'max_mb', 'before', 'year', 'orderby', 'order', 'page', 'per_page' ) as $k ) {
			if ( isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $in[ $k ] );
			}
		}
		$out['missing'] = ! empty( $in['missing'] );
		return $out;
	}

	private static function ids() {
		$ids = isset( $_REQUEST['ids'] ) ? (array) wp_unslash( $_REQUEST['ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	private static function hashes() {
		$h = isset( $_REQUEST['ids'] ) ? (array) wp_unslash( $_REQUEST['ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
		return array_values( array_filter( $h, function ( $x ) {
			return is_string( $x ) && preg_match( '~^[a-f0-9]{32}$~', $x );
		} ) );
	}

	public static function ajax() {
		check_ajax_referer( 'msw', 'nonce' );
		if ( ! current_user_can( self::cap() ) ) {
			wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 );
		}
		$do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		$method = 'do_' . $do;
		if ( ! method_exists( __CLASS__, $method ) ) {
			wp_send_json_error( array( 'message' => 'درخواست نامعتبر' ), 400 );
		}
		try {
			self::$method();
		} catch ( \Throwable $e ) {
			MSW_Util::log( 'AJAX ' . $do . ': ' . $e->getMessage(), 'error' );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	private static function do_dashboard() {
		wp_send_json_success( MSW_Query::dashboard() );
	}

	private static function do_job_start() {
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
		$o    = isset( $_POST['opts'] ) && is_array( $_POST['opts'] ) ? wp_unslash( $_POST['opts'] ) : array();
		$opts = array();
		foreach ( array( 'orphans', 'duplicates', 'retry', 'bg', 'drop_original' ) as $k ) {
			if ( isset( $o[ $k ] ) ) {
				$opts[ $k ] = ! empty( $o[ $k ] ) && 'false' !== $o[ $k ] && '0' !== $o[ $k ];
			}
		}
		if ( isset( $o['items'] ) && is_array( $o['items'] ) ) {
			$opts['items'] = array();
			foreach ( $o['items'] as $iid => $name ) {
				$opts['items'][ absint( $iid ) ] = sanitize_text_field( (string) $name );
			}
		}
		if ( isset( $o['pairs'] ) && is_array( $o['pairs'] ) ) {
			$opts['pairs'] = array();
			foreach ( $o['pairs'] as $pp ) {
				if ( is_array( $pp ) ) {
					$opts['pairs'][] = array( 'd' => absint( isset( $pp['d'] ) ? $pp['d'] : 0 ), 'k' => absint( isset( $pp['k'] ) ? $pp['k'] : 0 ) );
				}
			}
		}
		if ( isset( $o['max_px'] ) ) {
			$opts['max_px'] = absint( $o['max_px'] );
		}
		if ( isset( $o['ids'] ) ) {
			$opts['ids'] = array_map( 'absint', (array) $o['ids'] );
		}
		if ( isset( $o['scope'] ) ) {
			$opts['scope'] = ( 'all' === $o['scope'] ) ? 'all' : 'used';
		}
		$r = MSW_Job::start( $type, $opts );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( MSW_Job::tick( 3 ) );
	}

	private static function do_job_tick() {
		wp_send_json_success( MSW_Job::tick( 4 ) );
	}

	private static function do_job_status() {
		wp_send_json_success( MSW_Job::public_state() );
	}

	private static function do_job_cancel() {
		MSW_Job::cancel();
		MSW_Util::unlock();
		wp_send_json_success( MSW_Job::public_state() );
	}

	private static function do_list() {
		$tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : 'unused'; // phpcs:ignore WordPress.Security.NonceVerification
		$f   = self::filters();
		switch ( $tab ) {
			case 'orphans':
				$data = MSW_Query::orphans( $f );
				break;
			case 'duplicates':
				$data = MSW_Query::duplicates( $f );
				break;
			case 'quarantine':
				$data = MSW_Query::quarantine( $f );
				break;
			case 'seo':
				$data = MSW_Query::seo( $f );
				break;
			case 'redirects':
				$data = MSW_Query::redirects( $f );
				break;
			case 'broken':
				$data = MSW_Query::broken( $f );
				break;
			case 'converted':
				$data = MSW_Query::converted( $f );
				break;
			default:
				$data = MSW_Query::media( $f );
				$data['years'] = MSW_Query::years();
		}
		wp_send_json_success( $data );
	}

	private static function do_ids() {
		$tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : 'unused'; // phpcs:ignore WordPress.Security.NonceVerification
		$f   = self::filters();
		if ( 'orphans' === $tab ) {
			wp_send_json_success( array( 'ids' => MSW_Query::orphan_ids( $f ) ) );
		}
		if ( 'quarantine' === $tab ) {
			wp_send_json_success( array( 'ids' => MSW_Query::quarantine_ids() ) );
		}
		wp_send_json_success( array( 'ids' => MSW_Query::media_ids( $f ) ) );
	}

	private static function do_usage() {
		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		wp_send_json_success( array( 'usage' => MSW_Query::live_usage( $id ) ) );
	}

	/** Has content changed since the last scan? Used to guard permanent deletes. */
	private static function stale_reason() {
		global $wpdb;
		$ls = get_option( 'msw_last_scan', array() );
		if ( empty( $ls['time'] ) ) {
			return 'ابتدا یک اسکن کامل انجام دهید.';
		}
		if ( time() - (int) $ls['time'] > 7 * DAY_IN_SECONDS ) {
			return 'اسکن بیش از ۷ روز پیش انجام شده؛ برای حذف دائمی اسکن را تازه کنید.';
		}
		$changed = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision','nav_menu_item') AND post_status NOT IN ('auto-draft') AND post_modified_gmt > %s", gmdate( 'Y-m-d H:i:s', (int) $ls['time'] ) ) );
		if ( (int) $changed > 0 ) {
			return 'محتوای سایت پس از آخرین اسکن تغییر کرده (' . (int) $changed . ' مورد). برای حذف دائمی، اسکن را تازه کنید یا از «قرنطینه» استفاده کنید.';
		}
		return '';
	}

	/** Only items the index says are unused and unlocked may be removed. */
	private static function removable( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$in = implode( ',', array_map( 'intval', $ids ) );
		return array_map( 'intval', (array) $wpdb->get_col( 'SELECT attachment_id FROM ' . MSW_Util::t( 'media' ) . " WHERE attachment_id IN ($in) AND status = 'unused' AND locked = ''" ) ); // phpcs:ignore
	}

	private static function do_act() {
		global $wpdb;
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$res  = array( 'ok' => 0, 'failed' => array(), 'bytes' => 0 );
		$ids  = self::ids();
		$hash = self::hashes();

		$media_ops  = array( 'quarantine', 'delete' );
		$orphan_ops = array( 'orphan_delete', 'orphan_quarantine', 'orphan_restore', 'orphan_purge' );

		if ( in_array( $op, $media_ops, true ) ) {
			if ( 'delete' === $op ) {
				$why = self::stale_reason();
				if ( $why ) {
					wp_send_json_error( array( 'message' => $why ) );
				}
			}
			$allowed = self::removable( $ids );
			foreach ( $ids as $id ) {
				if ( ! in_array( $id, $allowed, true ) ) {
					$res['failed'][] = array( 'id' => $id, 'msg' => 'در فهرست «بدون استفاده» یا محافظت‌نشده نیست؛ رد شد.' );
					continue;
				}
				$bytes = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT bytes FROM ' . MSW_Util::t( 'media' ) . ' WHERE attachment_id = %d', $id ) ); // phpcs:ignore
				$r     = ( 'quarantine' === $op ) ? MSW_Quarantine::quarantine( $id ) : MSW_Quarantine::delete_now( $id );
				if ( true === $r ) {
					$res['ok']++;
					$res['bytes'] += $bytes;
				} else {
					$res['failed'][] = array( 'id' => $id, 'msg' => $r );
				}
			}
			if ( $res['ok'] && 'delete' === $op ) {
				MSW_Util::stat_add( 'deleted', $res['bytes'] );
			}
			if ( $res['ok'] ) {
				MSW_Util::log( sprintf( '%s: %d مورد (%s).', 'quarantine' === $op ? 'قرنطینه' : 'حذف دائمی', $res['ok'], MSW_Util::size( $res['bytes'] ) ) );
				MSW_Scanner::summarize();
			}
		} elseif ( 'restore' === $op || 'purge' === $op ) {
			foreach ( $ids as $id ) {
				$qb = (int) get_post_meta( $id, '_msw_q_bytes', true );
				$r  = ( 'restore' === $op ) ? MSW_Quarantine::restore( $id ) : MSW_Quarantine::purge( $id );
				if ( true === $r ) {
					$res['ok']++;
					if ( 'purge' === $op ) {
						MSW_Util::stat_add( 'deleted', $qb );
					}
				} else {
					$res['failed'][] = array( 'id' => $id, 'msg' => $r );
				}
			}
			if ( $res['ok'] ) {
				MSW_Util::log( sprintf( '%s: %d مورد.', 'restore' === $op ? 'بازگردانی از قرنطینه' : 'حذف دائمی از قرنطینه', $res['ok'] ) );
			}
		} elseif ( 'protect' === $op || 'unprotect' === $op ) {
			foreach ( $ids as $id ) {
				if ( 'protect' === $op ) {
					update_post_meta( $id, '_msw_protected', 1 );
					$wpdb->update( MSW_Util::t( 'media' ), array( 'locked' => 'protected' ), array( 'attachment_id' => $id ) );
				} else {
					delete_post_meta( $id, '_msw_protected' );
					$wpdb->update( MSW_Util::t( 'media' ), array( 'locked' => '' ), array( 'attachment_id' => $id ) );
				}
				$res['ok']++;
			}
			MSW_Scanner::summarize();
		} elseif ( in_array( $op, $orphan_ops, true ) ) {
			$fn = array(
				'orphan_delete'     => 'orphan_delete',
				'orphan_quarantine' => 'orphan_quarantine',
				'orphan_restore'    => 'orphan_restore',
				'orphan_purge'      => 'orphan_purge',
			);
			foreach ( $hash as $h ) {
				$size = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT bytes FROM ' . MSW_Util::t( 'orphans' ) . ' WHERE path_hash = %s', $h ) ); // phpcs:ignore
				$r    = call_user_func( array( 'MSW_Quarantine', $fn[ $op ] ), $h );
				if ( true === $r ) {
					$res['ok']++;
					$res['bytes'] += $size;
				} else {
					$res['failed'][] = array( 'id' => $h, 'msg' => $r );
				}
			}
			if ( $res['ok'] && in_array( $op, array( 'orphan_delete', 'orphan_purge' ), true ) ) {
				MSW_Util::stat_add( 'orphans', $res['bytes'] );
			}
			if ( $res['ok'] ) {
				MSW_Util::log( sprintf( 'فایل یتیم (%s): %d مورد، %s.', $op, $res['ok'], MSW_Util::size( $res['bytes'] ) ) );
				MSW_Scanner::summarize();
			}
		} elseif ( 'set_alt' === $op ) {
			$alts = isset( $_POST['alts'] ) && is_array( $_POST['alts'] ) ? wp_unslash( $_POST['alts'] ) : array();
			foreach ( $alts as $aid => $text ) {
				$aid = absint( $aid );
				if ( $aid && 'attachment' === get_post_type( $aid ) && current_user_can( 'edit_post', $aid ) ) {
					update_post_meta( $aid, '_wp_attachment_image_alt', sanitize_text_field( (string) $text ) );
					$res['ok']++;
				}
			}
			if ( $res['ok'] ) {
				MSW_Util::log( 'Alt متن برای ' . $res['ok'] . ' تصویر ذخیره شد.' );
			}
		} elseif ( 'redirect_delete' === $op ) {
			$olds = isset( $_POST['olds'] ) ? (array) wp_unslash( $_POST['olds'] ) : array();
			foreach ( $olds as $o ) {
				$res['ok'] += (int) $wpdb->delete( MSW_Util::t( 'redirects' ), array( 'old_rel' => sanitize_text_field( (string) $o ) ) );
			}
		} elseif ( 'purge_originals' === $op ) {
			$res['ok'] = MSW_Converter::purge_originals( true );
		} else {
			wp_send_json_error( array( 'message' => 'عملیات نامعتبر.' ) );
		}
		$res['size'] = MSW_Util::size( $res['bytes'] );
		wp_send_json_success( $res );
	}

	private static function do_report() {
		wp_send_json_success( MSW_Query::report() );
	}

	private static function do_sim() {
		wp_send_json_success( MSW_Converter::simulate( 6 ) );
	}

	private static function do_settings_save() {
		$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array();
		wp_send_json_success( MSW_Util::save( $in ) );
	}

	private static function do_log() {
		$log = get_option( 'msw_log', array() );
		$out = array();
		foreach ( array_reverse( (array) $log ) as $l ) {
			$out[] = array( 'time' => wp_date( 'Y/m/d H:i:s', $l[0] ), 'level' => $l[1], 'msg' => $l[2] );
		}
		wp_send_json_success( array( 'items' => $out ) );
	}

	private static function do_log_clear() {
		update_option( 'msw_log', array(), false );
		wp_send_json_success();
	}

	/** CSV download of the current filter (GET). */
	private static function do_export() {
		$tab = isset( $_REQUEST['tab'] ) ? sanitize_key( wp_unslash( $_REQUEST['tab'] ) ) : 'unused'; // phpcs:ignore WordPress.Security.NonceVerification
		$f   = self::filters();
		$f['per_page'] = 200;
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="media-sweeper-' . $tab . '-' . gmdate( 'Ymd-His' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		if ( 'orphans' === $tab ) {
			fputcsv( $out, array( 'path', 'url', 'bytes', 'modified' ) );
			for ( $p = 1; $p <= 500; $p++ ) {
				$f['page'] = $p;
				$d         = MSW_Query::orphans( $f );
				foreach ( $d['items'] as $i ) {
					fputcsv( $out, array( $i['rel'], MSW_Util::url( $i['rel'] ), $i['bytes'], $i['date'] ) );
				}
				if ( $p >= $d['pages'] ) {
					break;
				}
			}
		} else {
			fputcsv( $out, array( 'id', 'title', 'file', 'url', 'type', 'mime', 'bytes', 'uploaded', 'status', 'used_in', 'parent_id', 'edit_link' ) );
			for ( $p = 1; $p <= 500; $p++ ) {
				$f['page'] = $p;
				$d         = MSW_Query::media( $f );
				foreach ( $d['items'] as $i ) {
					fputcsv( $out, array( $i['id'], $i['title'], $i['rel'], $i['url'], $i['type'], $i['mime'], $i['bytes'], $i['date'], $i['status'] . ( $i['locked'] ? '/' . $i['locked'] : '' ), $i['used_in'] ? $i['used_in']['label'] : '', $i['parent'] ? $i['parent']['id'] : '', $i['edit'] ) );
				}
				if ( $p >= $d['pages'] ) {
					break;
				}
			}
		}
		fclose( $out );
		exit;
	}
}
