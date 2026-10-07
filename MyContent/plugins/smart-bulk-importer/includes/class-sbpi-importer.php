<?php
/**
 * Writes planned products to WooCommerce using the CRUD API (HPOS-safe, cache-aware).
 *
 * Every step is idempotent: products are matched by `_sbpi_key`, variations by
 * `_sbpi_combo`, so an interrupted run can simply be resumed or re-run.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
final class SBPI_Importer {

	/** Per-request caches. */
	private static $attr_cache = array();
	private static $term_cache = array();
	private static $cat_cache  = array();

	/**
	 * Import (part of) one product. Returns when done or when the time budget runs out.
	 *
	 * @param array $spec     Product spec.
	 * @param array $global   Global settings.
	 * @param array $state    Resume state (by ref): product_id, var_offset, created.
	 * @param float $deadline microtime(true) deadline.
	 * @return array{done:bool, action:string, id:int, message:string}
	 */
	public static function import( array $spec, array $global, array &$state, $deadline ) {
		if ( empty( $state['product_id'] ) ) {
			$existing = self::find_existing( $spec );
			if ( $existing && 'skip' === $global['update_mode'] ) {
				return array( 'done' => true, 'action' => 'skipped', 'id' => $existing, 'message' => 'از قبل وجود داشت؛ رد شد' );
			}
			$state['created'] = ! $existing;
			if ( $existing ) {
				SBPI_Snapshot::take( $existing, $state['batch'] ); // Enables a full undo.
			}
			$state['product_id'] = self::save_parent( $spec, $global, $existing, $state['batch'] );
			$state['var_offset'] = 0;
		}
		$id = (int) $state['product_id'];

		if ( 'variable' === $spec['type'] ) {
			$done = self::save_variations( $id, $spec, $global, $state, $deadline );
			if ( ! $done ) {
				return array(
					'done'    => false,
					'action'  => 'partial',
					'id'      => $id,
					'message' => sprintf( 'تنوع‌ها: %d از %d', $state['var_offset'], count( $spec['variations'] ) ),
				);
			}
			WC_Product_Variable::sync( $id );
		}
		wc_delete_product_transients( $id );

		return array(
			'done'    => true,
			'action'  => $state['created'] ? 'created' : 'updated',
			'id'      => $id,
			'message' => 'variable' === $spec['type'] ? sprintf( 'متغیر با %d تنوع', count( $spec['variations'] ) ) : 'ساده',
		);
	}

	/**
	 * Find a product created earlier from the same key (or by SKU).
	 *
	 * @param array $spec Spec.
	 * @return int
	 */
	private static function find_existing( array $spec ) {
		$ids = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'meta_key'         => '_sbpi_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $spec['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( $ids ) {
			return (int) $ids[0];
		}
		if ( '' !== $spec['sku'] ) {
			$by_sku = wc_get_product_id_by_sku( $spec['sku'] );
			if ( $by_sku && 'product' === get_post_type( $by_sku ) ) {
				return (int) $by_sku;
			}
		}
		return 0;
	}

	/**
	 * Create or update the parent product.
	 *
	 * @param array  $spec     Spec.
	 * @param array  $global   Settings.
	 * @param int    $existing Existing ID or 0.
	 * @param string $batch    Batch ID.
	 * @return int Product ID.
	 * @throws RuntimeException On failure.
	 */
	private static function save_parent( array $spec, array $global, $existing, $batch ) {
		$product = 'variable' === $spec['type'] ? new WC_Product_Variable( $existing ) : new WC_Product_Simple( $existing );

		// Spreadsheet text is untrusted HTML.
		$spec['content'] = wp_kses_post( $spec['content'] );
		$spec['excerpt'] = wp_kses_post( $spec['excerpt'] );

		if ( ! $existing ) {
			$product->set_name( $spec['title'] );
			$product->set_slug( $spec['slug'] );
			$product->set_status( $global['status'] );
			$product->set_catalog_visibility( 'visible' );
			$product->update_meta_data( '_sbpi_created_batch', $batch );
		} elseif ( ! empty( $global['update_title'] ) ) {
			$product->set_name( $spec['title'] );
		}

		// Content: replace only empty or still-untouched generated content.
		$hash = $product->get_meta( '_sbpi_content_hash' );
		$cur  = $product->get_description();
		if ( '' !== $spec['content'] && ( '' === trim( $cur ) || md5( $cur ) === $hash || ! empty( $global['overwrite_content'] ) ) ) {
			$product->set_description( $spec['content'] );
			$product->update_meta_data( '_sbpi_content_hash', md5( $spec['content'] ) );
		}
		$ehash = $product->get_meta( '_sbpi_excerpt_hash' );
		$ecur  = $product->get_short_description();
		if ( '' !== $spec['excerpt'] && ( '' === trim( $ecur ) || md5( $ecur ) === $ehash || ! empty( $global['overwrite_content'] ) ) ) {
			$product->set_short_description( $spec['excerpt'] );
			$product->update_meta_data( '_sbpi_excerpt_hash', md5( $spec['excerpt'] ) );
		}

		// Categories (merged with any the editor added).
		$cat_ids = array();
		if ( $spec['category'] ) {
			$cat_ids[] = self::category_path( $spec['category'] );
		}
		if ( $cat_ids ) {
			$product->set_category_ids( array_values( array_unique( array_merge( $product->get_category_ids(), $cat_ids ) ) ) );
		}

		if ( $spec['tags'] ) {
			$tag_ids = array();
			foreach ( $spec['tags'] as $tag ) {
				$tid = self::term( 'product_tag', $tag );
				if ( $tid ) {
					$tag_ids[] = $tid;
				}
			}
			$product->set_tag_ids( array_values( array_unique( array_merge( $product->get_tag_ids(), $tag_ids ) ) ) );
		}

		// Attributes: ours replace same-taxonomy entries, others are preserved.
		$attributes = $product->get_attributes();
		$position   = 0;
		$specs      = $spec['attributes'];
		if ( $spec['brand'] && ! taxonomy_exists( 'product_brand' ) ) {
			$specs = array(
				'__brand' => array(
					'name'      => 'برند',
					'slug'      => 'brand',
					'values'    => array( $spec['brand'][0] ),
					'variation' => false,
				),
			) + $specs;
		}
		foreach ( $specs as $attr ) {
			$taxonomy = self::attribute_taxonomy( $attr['name'], $attr['slug'], ! empty( $global['attr_archives'] ) );
			$term_ids = array();
			foreach ( $attr['values'] as $order => $value ) {
				$tid = self::term( $taxonomy, $value, $order );
				if ( $tid ) {
					$term_ids[] = $tid;
				}
			}
			$a = new WC_Product_Attribute();
			$a->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
			$a->set_name( $taxonomy );
			$a->set_options( $term_ids );
			$a->set_position( $position++ );
			$a->set_visible( true );
			$a->set_variation( 'variable' === $spec['type'] && $attr['variation'] );
			$attributes[ $taxonomy ] = $a;
		}
		$product->set_attributes( $attributes );

		if ( ! $existing && 'variable' === $spec['type'] && $spec['variations'] ) {
			$defaults = array();
			foreach ( $spec['variations'][0]['attrs'] as $aid => $value ) {
				if ( '' !== $value ) {
					$tax              = self::attribute_taxonomy( $spec['attributes'][ $aid ]['name'], $spec['attributes'][ $aid ]['slug'], false );
					$defaults[ $tax ] = self::term_slug( $tax, $value );
				}
			}
			$product->set_default_attributes( $defaults );
		}

		if ( 'simple' === $spec['type'] ) {
			self::apply_pricing( $product, $spec['price'], $spec['sale'], $spec['stock'], $global );
		}

		$sku = '' !== $spec['sku'] ? $spec['sku'] : ( $product->get_sku() ? '' : self::auto_sku( $spec['slug'], '', $global ) );
		if ( '' !== $sku ) {
			try {
				$product->set_sku( $sku );
			} catch ( WC_Data_Exception $e ) {
				// Duplicate SKU: keep the product, just without this SKU.
				unset( $e );
			}
		}

		$product->update_meta_data( '_sbpi_key', $spec['key'] );
		$product->update_meta_data( '_sbpi_family', $spec['family'] );
		$product->update_meta_data( '_sbpi_model', $spec['model'] );
		$product->update_meta_data( '_sbpi_batch', $batch );
		$product->update_meta_data( '_sbpi_brand', $spec['brand'] ? $spec['brand'][2] : '' );
		$product->update_meta_data( '_sbpi_conditions', $spec['conditions'] );
		$faq = SBPI_SEO::faq_pairs( $spec, $global );
		if ( $faq ) {
			$product->update_meta_data( '_sbpi_faq', $faq );
		} else {
			$product->delete_meta_data( '_sbpi_faq' );
		}

		$id = $product->save();
		if ( ! $id ) {
			throw new RuntimeException( 'ذخیره محصول ناموفق بود.' );
		}

		if ( $spec['brand'] && taxonomy_exists( 'product_brand' ) ) {
			$bid = self::term( 'product_brand', $spec['brand'][0], null, $spec['brand'][1] );
			if ( $bid ) {
				wp_set_object_terms( $id, array( $bid ), 'product_brand', true );
			}
		}

		if ( ! empty( $global['auto_images'] ) ) {
			$imgs    = SBPI_Images::match( $spec );
			$changed = false;
			if ( $imgs['main'] && ! $product->get_image_id() ) {
				SBPI_Images::ensure_alt( $imgs['main'], $spec['title'] );
				$product->set_image_id( $imgs['main'] );
				$changed = true;
			}
			if ( $imgs['gallery'] && ! $product->get_gallery_image_ids() ) {
				foreach ( $imgs['gallery'] as $gid ) {
					SBPI_Images::ensure_alt( $gid, $spec['model'] );
				}
				$product->set_gallery_image_ids( $imgs['gallery'] );
				$changed = true;
			}
			if ( $changed ) {
				$product->save();
			}
		}

		if ( '' !== $spec['image'] && ! $product->get_image_id() ) {
			$img = self::image( $spec['image'], $id, $spec['title'] );
			if ( $img ) {
				$product->set_image_id( $img );
				$product->save();
			}
		}

		SBPI_SEO::apply( $id, $spec['seo'] );
		return $id;
	}

	/**
	 * Create/update variations from the stored offset until done or out of time.
	 *
	 * @param int   $parent_id Parent.
	 * @param array $spec      Spec.
	 * @param array $global    Settings.
	 * @param array $state     State (by ref).
	 * @param float $deadline  Deadline.
	 * @return bool Done.
	 */
	private static function save_variations( $parent_id, array $spec, array $global, array &$state, $deadline ) {
		$existing = array();
		foreach ( wc_get_products(
			array(
				'parent' => $parent_id,
				'type'   => 'variation',
				'limit'  => -1,
				'status' => array( 'publish', 'private' ),
				'return' => 'ids',
			)
		) as $vid ) {
			$combo = get_post_meta( $vid, '_sbpi_combo', true );
			if ( $combo ) {
				$existing[ $combo ] = (int) $vid;
			}
		}

		$total  = count( $spec['variations'] );
		$caid   = SBPI_Images::color_attr( $spec );
		$colors = ! empty( $global['auto_images'] ) && null !== $caid ? SBPI_Images::match( $spec )['colors'] : array();
		$taxes = array();
		foreach ( $spec['attributes'] as $aid => $attr ) {
			if ( $attr['variation'] ) {
				$taxes[ $aid ] = self::attribute_taxonomy( $attr['name'], $attr['slug'], false );
			}
		}

		for ( $i = (int) $state['var_offset']; $i < $total; $i++ ) {
			if ( microtime( true ) > $deadline ) {
				$state['var_offset'] = $i;
				return false;
			}
			$v         = $spec['variations'][ $i ];
			$variation = new WC_Product_Variation( isset( $existing[ $v['key'] ] ) ? $existing[ $v['key'] ] : 0 );
			$variation->set_parent_id( $parent_id );

			$attrs = array();
			foreach ( $v['attrs'] as $aid => $value ) {
				$attrs[ $taxes[ $aid ] ] = '' === $value ? '' : self::term_slug( $taxes[ $aid ], $value );
			}
			$variation->set_attributes( $attrs );
			$variation->set_status( 'publish' );
			if ( '' !== $v['desc'] ) {
				$variation->set_description( $v['desc'] );
			}
			self::apply_pricing( $variation, $v['price'], $v['sale'], $v['stock'], $global );

			if ( ! $variation->get_sku() ) {
				$sku = '' !== $v['sku'] ? $v['sku'] : self::auto_sku( $spec['slug'], $v['key'], $global );
				if ( '' !== $sku ) {
					try {
						$variation->set_sku( $sku );
					} catch ( WC_Data_Exception $e ) {
						unset( $e );
					}
				}
			}
			if ( $colors && ! $variation->get_image_id() && isset( $v['attrs'][ $caid ], $colors[ $v['attrs'][ $caid ] ] ) ) {
				$cid = $colors[ $v['attrs'][ $caid ] ];
				SBPI_Images::ensure_alt( $cid, $spec['model'] . ' رنگ ' . $v['attrs'][ $caid ] );
				$variation->set_image_id( $cid );
			}
			if ( '' !== $v['image'] && ! $variation->get_image_id() ) {
				$img = self::image( $v['image'], $parent_id, $spec['title'] );
				if ( $img ) {
					$variation->set_image_id( $img );
				}
			}
			$variation->update_meta_data( '_sbpi_combo', $v['key'] );
			$is_new = ! $variation->get_id();
			$variation->save();
			if ( $is_new && empty( $state['created'] ) ) {
				SBPI_Snapshot::note_new_variation( $parent_id, $variation->get_id(), $state['batch'] );
			}
		}
		$state['var_offset'] = $total;
		return true;
	}

	/**
	 * Price + stock on a simple product or variation. Empty values keep current data,
	 * so a file without prices never wipes prices set by hand.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $price   Regular price.
	 * @param string     $sale    Sale price.
	 * @param string     $stock   Stock qty.
	 * @param array      $global  Settings.
	 */
	private static function apply_pricing( WC_Product $product, $price, $sale, $stock, array $global ) {
		if ( '' !== (string) $price ) {
			$product->set_regular_price( $price );
		}
		if ( '' !== (string) $sale && ( '' === (string) $price || (float) $sale < (float) $price ) ) {
			$product->set_sale_price( $sale );
		}
		if ( '' !== (string) $stock ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( (int) $stock );
		} elseif ( ! $product->get_manage_stock() ) {
			$product->set_stock_status( $global['stock_status'] );
		}
	}

	/**
	 * "prefix-ps5-slim-disk-1tb[-abc123]" → uppercase SKU, or '' when disabled.
	 *
	 * @param string $slug   Product slug.
	 * @param string $suffix Variation key.
	 * @param array  $global Settings.
	 * @return string
	 */
	private static function auto_sku( $slug, $suffix, array $global ) {
		if ( empty( $global['auto_sku'] ) ) {
			return '';
		}
		$base = preg_match( '/^[a-z0-9-]+$/', $slug ) ? $slug : 'p-' . substr( md5( $slug ), 0, 8 );
		$sku  = trim( $global['sku_prefix'] . $base . ( $suffix ? '-' . substr( $suffix, 0, 6 ) : '' ), '-' );
		return strtoupper( $sku );
	}

	/**
	 * Get or create a global attribute; returns its taxonomy name (pa_xxx).
	 *
	 * @param string $label    Label.
	 * @param string $slug     Preferred latin slug.
	 * @param bool   $archives Enable archives.
	 * @return string
	 * @throws RuntimeException On failure.
	 */
	public static function attribute_taxonomy( $label, $slug, $archives ) {
		$cache_key = SBPI_Util::key( $label ) . '|' . $slug;
		if ( isset( self::$attr_cache[ $cache_key ] ) ) {
			return self::$attr_cache[ $cache_key ];
		}
		$slug = wc_sanitize_taxonomy_name( $slug );

		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			// A slug chosen on the mapping screen is authoritative; label matching only
			// applies when no slug was given, so "create new" is never overridden.
			if ( '' !== $slug ? $tax->attribute_name === $slug : SBPI_Util::key( $tax->attribute_label ) === SBPI_Util::key( $label ) ) {
				return self::$attr_cache[ $cache_key ] = wc_attribute_taxonomy_name( $tax->attribute_name ); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
			}
		}
		if ( '' === $slug ) {
			$latin = sanitize_title( preg_replace( '/[^\x20-\x7E]+/u', ' ', $label ) );
			$slug  = strlen( $latin ) >= 2 ? $latin : 'attr-' . substr( md5( $label ), 0, 6 );
		}
		$slug = substr( $slug, 0, 27 );
		if ( wc_check_if_attribute_name_is_reserved( $slug ) ) {
			$slug = substr( 'x-' . $slug, 0, 27 );
		}
		$id = wc_create_attribute(
			array(
				'name'         => $label,
				'slug'         => $slug,
				'type'         => 'select',
				'order_by'     => 'menu_order',
				'has_archives' => (bool) $archives,
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( 'ساخت ویژگی «' . $label . '» ناموفق: ' . $id->get_error_message() );
		}
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		// The taxonomy is registered on next init; register now so terms can be inserted.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			register_taxonomy(
				$taxonomy,
				array( 'product', 'product_variation' ),
				array(
					'hierarchical' => false,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			);
		}
		return self::$attr_cache[ $cache_key ] = $taxonomy; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
	}

	/**
	 * Get or create a term by name; returns term ID.
	 *
	 * @param string      $taxonomy Taxonomy.
	 * @param string      $name     Name.
	 * @param int|null    $order    Menu order for attribute terms.
	 * @param string|null $slug     Preferred slug.
	 * @param int         $parent   Parent term.
	 * @return int
	 */
	private static function term( $taxonomy, $name, $order = null, $slug = null, $parent = 0 ) {
		$ck = $taxonomy . '|' . $parent . '|' . SBPI_Util::key( $name );
		if ( isset( self::$term_cache[ $ck ] ) ) {
			return self::$term_cache[ $ck ];
		}
		$found = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'name'       => $name,
				'parent'     => $parent,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $found ) && $found ) {
			return self::$term_cache[ $ck ] = (int) $found[0]; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
		}
		$args = array( 'parent' => $parent );
		$args['slug'] = null !== $slug ? $slug : SBPI_Planner::slug( $name );
		$res  = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $res ) ) {
			if ( 'term_exists' === $res->get_error_code() && $res->get_error_data() ) {
				$res = array( 'term_id' => (int) $res->get_error_data() );
			} else {
				unset( $args['slug'] );
				$res = wp_insert_term( $name, $taxonomy, $args );
			}
		}
		if ( is_wp_error( $res ) ) {
			return 0;
		}
		$tid = (int) $res['term_id'];
		if ( null !== $order && 0 === strpos( $taxonomy, 'pa_' ) ) {
			// Keep the spreadsheet's order (128 GB, 256 GB, 512 GB …) in dropdowns.
			update_term_meta( $tid, 'order', (int) $order );
		}
		return self::$term_cache[ $ck ] = $tid; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
	}

	/**
	 * Slug of an attribute term by name (creating it if needed).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @return string
	 */
	private static function term_slug( $taxonomy, $name ) {
		$term = get_term( self::term( $taxonomy, $name ), $taxonomy );
		return $term && ! is_wp_error( $term ) ? $term->slug : '';
	}

	/**
	 * "کنسول بازی > PS5" → leaf category ID (creating the path).
	 *
	 * @param string[] $path Names.
	 * @return int
	 */
	private static function category_path( array $path ) {
		$ck = implode( '>', array_map( array( 'SBPI_Util', 'key' ), $path ) );
		if ( isset( self::$cat_cache[ $ck ] ) ) {
			return self::$cat_cache[ $ck ];
		}
		$parent = 0;
		foreach ( $path as $segment ) {
			// "پلی‌استیشن 5|playstation-5" → name + latin slug (slug is used only when creating).
			$parts  = array_map( 'trim', explode( '|', $segment, 2 ) );
			$slug   = isset( $parts[1] ) && '' !== $parts[1] ? sanitize_title( $parts[1] ) : null;
			$parent = self::term( 'product_cat', $parts[0], null, $slug, $parent );
			if ( ! $parent ) {
				break;
			}
		}
		return self::$cat_cache[ $ck ] = $parent; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
	}

	/**
	 * Sideload an image once (deduplicated by source URL) and set its alt text.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Attach to.
	 * @param string $alt     Alt text.
	 * @return int Attachment ID or 0.
	 */
	private static function image( $url, $post_id, $alt ) {
		$url = esc_url_raw( trim( $url ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return 0;
		}
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_sbpi_src', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $found ) {
			return (int) $found[0];
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $url, $post_id, $alt, 'id' );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_sbpi_src', $url );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		return (int) $id;
	}

	/**
	 * Undo a batch a few products per call: delete the products it created, then restore
	 * the products it updated from their snapshots.
	 *
	 * @param string $batch    Batch ID.
	 * @param float  $deadline Deadline.
	 * @return int Remaining count (0 = finished).
	 */
	public static function rollback( $batch, $deadline ) {
		$query = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'meta_key'       => '_sbpi_created_batch', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $batch, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'posts_per_page' => 20,
		);
		foreach ( get_posts( $query ) as $id ) {
			if ( microtime( true ) > $deadline ) {
				break;
			}
			$product = wc_get_product( $id );
			if ( $product ) {
				$product->delete( true ); // Also deletes variations.
			}
		}
		while ( microtime( true ) < $deadline ) {
			$ids = SBPI_Snapshot::pending( $batch, 10 );
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				try {
					SBPI_Snapshot::restore( $id, $batch );
				} catch ( Throwable $e ) {
					// Never loop forever on one broken product; record and move on.
					SBPI_Admin::log( $batch, array( sprintf( '❌ بازگردانی #%d ناموفق: %s', $id, $e->getMessage() ) ) );
					delete_post_meta( $id, SBPI_Snapshot::key( $batch ) );
				}
			}
		}
		$query['posts_per_page'] = -1;
		return count( get_posts( $query ) ) + count( SBPI_Snapshot::pending( $batch, -1 ) );
	}
}
