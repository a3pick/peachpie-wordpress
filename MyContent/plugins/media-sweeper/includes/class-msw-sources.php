<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes every place in the database that can hold media references and
 * pages through it in small batches. Used by the scanner (read) and the
 * rewriter (read + write).
 */
class MSW_Sources {

	private static function stage( $id, $label, $table, $pk, $cols, $where = '', $kind = 'generic', $extra = array() ) {
		return array_merge(
			array(
				'id'     => $id,
				'label'  => $label,
				'table'  => $table,
				'from'   => '`' . $table . '`',
				'pk'     => $pk,
				'pkexpr' => '`' . $pk . '`',
				'cols'   => $cols, // name => 'text'|'id'.
				'where'  => $where,
				'kind'   => $kind,
				'paging' => 'pk',
				'limit'  => 100,
			),
			$extra
		);
	}

	/**
	 * @param string $for 'scan' or 'rewrite'.
	 */
	public static function stages( $for ) {
		global $wpdb;
		$s       = MSW_Util::all();
		$scan    = ( 'scan' === $for );
		$stages  = array();
		$skip_pt = array( "'attachment'", "'nav_menu_item'", "'oembed_cache'" );
		if ( ! $s['include_revisions'] ) {
			$skip_pt[] = "'revision'";
		}

		// Posts.
		$where = $scan ? "post_type NOT IN (" . implode( ',', $skip_pt ) . ") AND post_status NOT IN ('trash','auto-draft')" : '';
		$stages[] = self::stage(
			'posts',
			'نوشته‌ها و صفحه‌ها',
			$wpdb->posts,
			'ID',
			array(
				'post_content'          => 'text',
				'post_excerpt'          => 'text',
				'post_content_filtered' => 'text',
			),
			$where,
			'posts',
			array( 'limit' => 40 )
		);

		// Post meta (page builders, WooCommerce, ACF, SEO plugins...).
		if ( $scan ) {
			$stages[] = self::stage(
				'postmeta',
				'متای نوشته‌ها (المنتور، ووکامرس، ACF…)',
				$wpdb->postmeta,
				'meta_id',
				array( 'meta_value' => 'text' ),
				"p.post_type NOT IN (" . implode( ',', $skip_pt ) . ") AND p.post_status NOT IN ('trash','auto-draft') AND t.meta_key NOT IN ('_edit_lock','_edit_last','_wp_old_slug','_wp_page_template')",
				'postmeta',
				array(
					'from'   => '`' . $wpdb->postmeta . '` t INNER JOIN `' . $wpdb->posts . '` p ON p.ID = t.post_id',
					'pkexpr' => 't.meta_id',
					'select' => 't.post_id, t.meta_key, t.meta_value',
					'limit'  => 40,
				)
			);
		} else {
			$stages[] = self::stage( 'postmeta', 'متای نوشته‌ها', $wpdb->postmeta, 'meta_id', array( 'meta_value' => 'text' ), "meta_key NOT LIKE '\\_wp\\_attach%'", 'rw_meta', array( 'keycol' => 'meta_key', 'limit' => 40 ) );
		}

		// Options (widgets, theme mods, site icon, SEO plugin settings...).
		$stages[] = self::stage(
			'options',
			'تنظیمات و ویجت‌ها',
			$wpdb->options,
			'option_id',
			array( 'option_value' => 'text' ),
			"option_name NOT LIKE '%transient%' AND option_name NOT LIKE 'msw%' AND option_name NOT IN ('cron','rewrite_rules','active_plugins','recently_activated')",
			'options',
			array( 'limit' => 60 )
		);

		// Term meta, user meta, comment meta.
		$stages[] = self::stage( 'termmeta', 'متای دسته‌ها و برچسب‌ها', $wpdb->termmeta, 'meta_id', array( 'meta_value' => 'text' ), '', 'meta', array( 'keycol' => 'meta_key', 'idcol' => 'term_id', 'short' => 'tmeta' ) );
		$stages[] = self::stage( 'usermeta', 'متای کاربران (آواتار…)', $wpdb->usermeta, 'umeta_id', array( 'meta_value' => 'text' ), "meta_key REGEXP 'avatar|image|photo|picture|logo|cover|banner|img|thumb'", 'meta', array( 'keycol' => 'meta_key', 'idcol' => 'user_id', 'short' => 'umeta' ) );
		$stages[] = self::stage( 'commentmeta', 'متای دیدگاه‌ها', $wpdb->commentmeta, 'meta_id', array( 'meta_value' => 'text' ), "meta_key REGEXP 'image|photo|picture|img|thumb|attachment'", 'meta', array( 'keycol' => 'meta_key', 'idcol' => 'comment_id', 'short' => 'cmeta' ) );
		$stages[] = self::stage( 'term_taxonomy', 'توضیحات دسته‌ها', $wpdb->term_taxonomy, 'term_taxonomy_id', array( 'description' => 'text' ), '', 'generic' );
		$stages[] = self::stage( 'comments', 'دیدگاه‌ها', $wpdb->comments, 'comment_ID', array( 'comment_content' => 'text' ), '', 'generic' );

		if ( $s['deep_tables'] ) {
			foreach ( self::custom_tables( $for ) as $st ) {
				$stages[] = $st;
			}
		}
		return $stages;
	}

	/** Third-party tables (slider plugins, galleries, form builders...). */
	public static function custom_tables( $for ) {
		global $wpdb;
		$like   = $wpdb->esc_like( $wpdb->prefix ) . '%';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
		$core   = array();
		foreach ( array( 'posts', 'postmeta', 'options', 'termmeta', 'usermeta', 'commentmeta', 'term_taxonomy', 'comments', 'users', 'terms', 'term_relationships', 'links', 'blogs', 'blogmeta', 'site', 'sitemeta', 'signups', 'registration_log', 'blog_versions' ) as $c ) {
			$core[ $wpdb->prefix . $c ] = true;
			$core[ $wpdb->base_prefix . $c ] = true;
		}
		$user_ex = array_filter( array_map( 'trim', preg_split( '~[\s,]+~', (string) MSW_Util::get( 'exclude_tables' ) ) ) );
		$out     = array();

		foreach ( $tables as $table ) {
			if ( isset( $core[ $table ] ) || 0 === strpos( $table, $wpdb->prefix . 'msw_' ) ) {
				continue;
			}
			// Other sites of a multisite network.
			if ( is_multisite() && 1 === (int) get_current_blog_id() && preg_match( '~^' . preg_quote( $wpdb->base_prefix, '~' ) . '\d+_~', $table ) ) {
				continue;
			}
			if ( preg_match( '~actionscheduler|_sessions?$|_logs?$|_log_|_cache$|_transient|_wc_admin|_statistic|_visitors|_404|_failed_jobs|wfhits|wflogs|wfls|relevanssi~i', $table ) ) {
				continue;
			}
			if ( in_array( $table, $user_ex, true ) || in_array( substr( $table, strlen( $wpdb->prefix ) ), $user_ex, true ) ) {
				continue;
			}
			$cols = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table ) . '`', ARRAY_A );
			if ( ! $cols ) {
				continue;
			}
			$pk = array();
			foreach ( $cols as $c ) {
				if ( 'PRI' === $c['Key'] ) {
					$pk[] = $c;
				}
			}
			$use_pk = '';
			if ( 1 === count( $pk ) && preg_match( '~int~i', $pk[0]['Type'] ) ) {
				$use_pk = $pk[0]['Field'];
			}
			if ( '' === $use_pk && 'rewrite' === $for ) {
				MSW_Util::log( "جدول $table کلید اصلی عددی ندارد؛ در اصلاح لینک‌ها نادیده گرفته شد.", 'warn' );
				continue;
			}
			$map = array();
			foreach ( $cols as $c ) {
				if ( $c['Field'] === $use_pk ) {
					continue;
				}
				if ( preg_match( '~char|text|json|blob~i', $c['Type'] ) && ! preg_match( '~^(char|varchar)\((\d+)\)~i', $c['Type'], $mm ) ) {
					$map[ $c['Field'] ] = 'text';
				} elseif ( preg_match( '~^(?:var)?char\((\d+)\)~i', $c['Type'], $mm ) && (int) $mm[1] >= 20 ) {
					$map[ $c['Field'] ] = 'text';
				} elseif ( preg_match( '~int~i', $c['Type'] ) && MSW_Extractor::key_qualifies( $c['Field'] ) && 'rewrite' !== $for ) {
					$map[ $c['Field'] ] = 'id';
				}
			}
			if ( ! $map ) {
				continue;
			}
			$out[] = self::stage(
				'tbl:' . $table,
				'جدول ' . substr( $table, strlen( $wpdb->prefix ) ),
				$table,
				'' !== $use_pk ? $use_pk : 'ctid',
				$map,
				'',
				'custom',
				array(
					'paging' => '' !== $use_pk ? 'pk' : 'offset',
					'limit'  => 100,
				)
			);
		}
		return $out;
	}

	/**
	 * Fetch one batch.
	 *
	 * @return array rows, each with "_pk".
	 */
	public static function fetch( array $st, $cursor, $limit = 0 ) {
		global $wpdb;
		$limit = (int) ( $limit ? $limit : $st['limit'] );
		$cursor = (int) $cursor;
		$cols  = isset( $st['select'] ) ? $st['select'] : implode( ',', array_map( function ( $c ) {
			return '`' . $c . '`';
		}, array_merge( isset( $st['keycol'] ) ? array( $st['keycol'] ) : array(), isset( $st['idcol'] ) ? array( $st['idcol'] ) : array(), array_keys( $st['cols'] ) ) ) );
		if ( 'options' === $st['kind'] ) {
			$cols = '`option_name`,`option_value`';
		}
		if ( 'meta' === $st['kind'] || 'rw_meta' === $st['kind'] ) {
			$cols = '`' . $st['keycol'] . '`,' . ( isset( $st['idcol'] ) ? '`' . $st['idcol'] . '`,' : '' ) . '`meta_value`';
		}
		if ( 'pk' === $st['paging'] ) {
			$sql = "SELECT {$st['pkexpr']} AS _pk, $cols FROM {$st['from']} WHERE {$st['pkexpr']} > $cursor"
				. ( $st['where'] ? " AND ({$st['where']})" : '' )
				. " ORDER BY {$st['pkexpr']} ASC LIMIT $limit";
		} else {
			$sql = "SELECT $cols FROM {$st['from']}" . ( $st['where'] ? " WHERE {$st['where']}" : '' ) . " LIMIT $cursor,$limit";
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( 'offset' === $st['paging'] && $rows ) {
			foreach ( $rows as $i => $r ) {
				$rows[ $i ]['_pk'] = $cursor + $i + 1; // Becomes the next cursor via max().
			}
		}
		return $rows ? $rows : array();
	}

	/** Upper bound used for progress bars. */
	public static function max_cursor( array $st ) {
		global $wpdb;
		if ( 'pk' === $st['paging'] ) {
			return (int) $wpdb->get_var( "SELECT MAX({$st['pkexpr']}) FROM {$st['from']}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$st['from']}" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** Active theme files that may hard-code upload URLs. */
	public static function theme_files() {
		$dirs = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
		$out  = array();
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS ),
				RecursiveIteratorIterator::LEAVES_ONLY,
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
			foreach ( $it as $f ) {
				if ( ! $f->isFile() || $f->getSize() > 2 * MB_IN_BYTES ) {
					continue;
				}
				if ( preg_match( '~\.(?:css|scss|less|php|js|json|html?|twig|svg)$~i', $f->getFilename() ) && false === strpos( $f->getPathname(), '/node_modules/' ) ) {
					$out[] = wp_normalize_path( $f->getPathname() );
				}
				if ( count( $out ) >= 5000 ) {
					break 2;
				}
			}
		}
		return $out;
	}
}
