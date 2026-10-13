<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helpers: settings, logging, paths, locking, cache flushing.
 */
class MSW_Util {

	const OPT = 'msw_settings';

	public static function defaults() {
		return array(
			// Scan.
			'scan_types'          => array( 'image', 'video', 'audio' ),
			'protect_days'        => 14,
			'protect_patterns'    => '',
			'parent_used'         => 0,
			'include_revisions'   => 0,
			'deep_tables'         => 1,
			'exclude_tables'      => '',
			'aggressive_meta'     => 0,
			'scan_theme_files'    => 1,
			'weak_basename'       => 1,
			'scan_orphans'        => 1,
			'orphan_all_dirs'     => 0,
			'detect_duplicates'   => 0,
			// Quarantine.
			'quarantine_days'     => 30,
			// WebP.
			'webp_engine'         => 'auto',
			'webp_jpeg_quality'   => 82,
			'webp_png_mode'       => 'lossless',
			'webp_png_quality'    => 90,
			'webp_min_saving'     => 5,
			'webp_scope'          => 'used',
			'webp_skip_theme'     => 1,
			'webp_originals'      => 1,
			'webp_retention_days' => 7,
			'webp_on_upload'      => 0,
			// SEO.
			'redirects_enable'    => 1,
			// Automation.
			'auto_scan'           => 'off',
			'notify_email'        => '',
			// Uninstall.
			'remove_data'         => 0,
		);
	}

	public static function all() {
		$saved = get_option( self::OPT, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Validate and store settings. Unknown keys are ignored.
	 */
	public static function save( array $in ) {
		$d   = self::defaults();
		$out = self::all();
		foreach ( $d as $k => $def ) {
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			$v = $in[ $k ];
			switch ( $k ) {
				case 'scan_types':
					$v = array_values( array_intersect( (array) $v, array( 'image', 'video', 'audio', 'doc' ) ) );
					if ( ! $v ) {
						$v = $def;
					}
					break;
				case 'protect_patterns':
				case 'exclude_tables':
					$v = sanitize_textarea_field( (string) $v );
					break;
				case 'notify_email':
					$v = sanitize_email( (string) $v );
					break;
				case 'webp_engine':
					$v = in_array( $v, array( 'auto', 'imagick', 'gd' ), true ) ? $v : 'auto';
					break;
				case 'webp_png_mode':
					$v = ( 'lossy' === $v ) ? 'lossy' : 'lossless';
					break;
				case 'webp_scope':
					$v = ( 'all' === $v ) ? 'all' : 'used';
					break;
				case 'auto_scan':
					$v = in_array( $v, array( 'off', 'weekly', 'monthly' ), true ) ? $v : 'off';
					break;
				case 'protect_days':
				case 'quarantine_days':
				case 'webp_retention_days':
					$v = max( 0, min( 3650, (int) $v ) );
					break;
				case 'webp_jpeg_quality':
				case 'webp_png_quality':
					$v = max( 30, min( 100, (int) $v ) );
					break;
				case 'webp_min_saving':
					$v = max( 0, min( 90, (int) $v ) );
					break;
				default:
					$v = empty( $v ) ? 0 : 1;
			}
			$out[ $k ] = $v;
		}
		update_option( self::OPT, $out, false );
		MSW_Cron::reschedule();
		return $out;
	}

	public static function t( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'msw_' . $name;
	}

	/* ------------------------------------------------------------ logging */

	public static function log( $msg, $level = 'info' ) {
		$log   = get_option( 'msw_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( time(), $level, (string) $msg );
		if ( count( $log ) > 300 ) {
			$log = array_slice( $log, -300 );
		}
		update_option( 'msw_log', $log, false );
	}

	/* -------------------------------------------------------------- paths */

	public static function basedir() {
		$u = wp_upload_dir( null, false );
		return wp_normalize_path( untrailingslashit( $u['basedir'] ) );
	}

	public static function baseurl() {
		$u = wp_upload_dir( null, false );
		return untrailingslashit( $u['baseurl'] );
	}

	public static function abs( $rel ) {
		return self::basedir() . '/' . ltrim( str_replace( '\\', '/', $rel ), '/' );
	}

	public static function url( $rel ) {
		$parts = array_map( 'rawurlencode', explode( '/', ltrim( $rel, '/' ) ) );
		return self::baseurl() . '/' . implode( '/', $parts );
	}

	/**
	 * A relative path is safe when it is non-empty, not absolute and has no "..".
	 */
	public static function safe_rel( $rel ) {
		$rel = str_replace( '\\', '/', (string) $rel );
		if ( '' === $rel || '/' === $rel[0] || preg_match( '~(^|/)\.\.(/|$)~', $rel ) || false !== strpos( $rel, "\0" ) ) {
			return false;
		}
		return true;
	}

	public static function rrmdir( $dir ) {
		$dir = wp_normalize_path( $dir );
		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}
		// Never operate outside uploads.
		if ( 0 !== strpos( $dir, self::basedir() . '/' ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) {
			if ( $f->isDir() && ! $f->isLink() ) {
				@rmdir( $f->getPathname() );
			} else {
				@unlink( $f->getPathname() );
			}
		}
		@rmdir( $dir );
	}

	/** Remove empty parent directories up to (not including) $stop. */
	public static function prune_empty_dirs( $dir, $stop ) {
		$dir  = wp_normalize_path( $dir );
		$stop = wp_normalize_path( $stop );
		while ( 0 === strpos( $dir, $stop . '/' ) && $dir !== $stop ) {
			$left = @scandir( $dir );
			if ( false === $left || count( $left ) > 2 ) {
				break;
			}
			if ( ! @rmdir( $dir ) ) {
				break;
			}
			$dir = wp_normalize_path( dirname( $dir ) );
		}
	}

	/** Create a protected directory (no direct web access, no listing). */
	public static function protected_dir( $abs ) {
		if ( ! wp_mkdir_p( $abs ) ) {
			return false;
		}
		if ( ! file_exists( $abs . '/index.php' ) ) {
			@file_put_contents( $abs . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		if ( ! file_exists( $abs . '/.htaccess' ) ) {
			@file_put_contents( $abs . '/.htaccess', "Order allow,deny\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" );
		}
		return true;
	}

	/* ------------------------------------------------------------ locking */

	public static function lock( $ttl = 90 ) {
		$l = get_option( 'msw_lock' );
		if ( $l && ( time() - (int) $l ) >= $ttl ) {
			delete_option( 'msw_lock' );
		}
		return add_option( 'msw_lock', time(), '', 'no' );
	}

	public static function unlock() {
		delete_option( 'msw_lock' );
	}

	/* ------------------------------------------------------------- caches */

	/** Clear object cache and the common page-cache / builder caches. */
	public static function flush_caches() {
		wp_cache_flush();
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			try {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			} catch ( \Throwable $e ) {
				self::log( 'Elementor cache: ' . $e->getMessage(), 'warn' );
			}
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		do_action( 'litespeed_purge_all' );
		do_action( 'msw_flush_caches' );
	}

	public static function size( $bytes ) {
		$out = size_format( (float) $bytes, 2 );
		return false === $out ? '0 B' : $out;
	}
}
