<?php
/**
 * Snapshots of existing products before an import changes them, so a batch can be
 * fully undone (not only the products it created).
 *
 * Stored as post meta `_sbpi_snap_{batch}` on the parent product.
 *
 * @package SBPI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot / restore.
 */
final class SBPI_Snapshot {

	/** Meta keys this plugin may write on a product (restored verbatim). */
	const META_KEYS = array(
		'_sbpi_key', '_sbpi_batch', '_sbpi_brand', '_sbpi_conditions', '_sbpi_family', '_sbpi_model', '_sbpi_faq',
		'_sbpi_content_hash', '_sbpi_excerpt_hash', '_sbpi_seo_written', '_sbpi_seo_title', '_sbpi_seo_desc',
		'_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw',
		'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword',
	);

	/**
	 * Meta key for a batch.
	 *
	 * @param string $batch Batch ID.
	 * @return string
	 */
	public static function key( $batch ) {
		return '_sbpi_snap_' . $batch;
	}

	/**
	 * Save the current state of a product (once per batch).
	 *
	 * @param int    $id    Product ID.
	 * @param string $batch Batch ID.
	 */
	public static function take( $id, $batch ) {
		if ( metadata_exists( 'post', $id, self::key( $batch ) ) ) {
			return; // Resumed run: keep the original snapshot.
		}
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return;
		}
		$attrs = array();
		foreach ( $product->get_attributes() as $tax => $a ) {
			if ( $a instanceof WC_Product_Attribute ) {
				$attrs[ $tax ] = array(
					'id'        => $a->get_id(),
					'name'      => $a->get_name(),
					'options'   => $a->get_options(),
					'position'  => $a->get_position(),
					'visible'   => $a->get_visible(),
					'variation' => $a->get_variation(),
				);
			}
		}
		$meta = array();
		foreach ( self::META_KEYS as $k ) {
			$meta[ $k ] = metadata_exists( 'post', $id, $k ) ? get_post_meta( $id, $k, true ) : null;
		}
		$data = array(
			'type'       => $product->get_type(),
			'fields'     => self::fields( $product ),
			'attributes' => $attrs,
			'defaults'   => $product->get_default_attributes(),
			'categories' => $product->get_category_ids(),
			'tags'       => $product->get_tag_ids(),
			'gallery'    => $product->get_gallery_image_ids(),
			'brands'     => taxonomy_exists( 'product_brand' ) ? wp_get_object_terms( $id, 'product_brand', array( 'fields' => 'ids' ) ) : array(),
			'meta'       => $meta,
			'variations' => array(),
			'new_vars'   => array(),
		);
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v ) {
					$data['variations'][ $vid ] = self::fields( $v ) + array( 'status' => $v->get_status() );
				}
			}
		}
		update_post_meta( $id, self::key( $batch ), $data );
	}

	/**
	 * Remember a variation created while updating an existing product.
	 *
	 * @param int    $parent Parent ID.
	 * @param int    $vid    New variation ID.
	 * @param string $batch  Batch ID.
	 */
	public static function note_new_variation( $parent, $vid, $batch ) {
		$data = get_post_meta( $parent, self::key( $batch ), true );
		if ( is_array( $data ) ) {
			$data['new_vars'][] = (int) $vid;
			update_post_meta( $parent, self::key( $batch ), $data );
		}
	}

	/**
	 * Fields shared by products and variations.
	 *
	 * @param WC_Product $p Product.
	 * @return array
	 */
	private static function fields( WC_Product $p ) {
		return array(
			'name'         => $p->get_name( 'edit' ),
			'description'  => $p->get_description( 'edit' ),
			'short'        => $p->get_short_description( 'edit' ),
			'sku'          => $p->get_sku( 'edit' ),
			'regular'      => $p->get_regular_price( 'edit' ),
			'sale'         => $p->get_sale_price( 'edit' ),
			'manage_stock' => $p->get_manage_stock( 'edit' ),
			'stock'        => $p->get_stock_quantity( 'edit' ),
			'stock_status' => $p->get_stock_status( 'edit' ),
			'image'        => $p->get_image_id( 'edit' ),
		);
	}

	/**
	 * Apply saved fields.
	 *
	 * @param WC_Product $p Product.
	 * @param array      $f Fields.
	 */
	private static function apply_fields( WC_Product $p, array $f ) {
		$p->set_name( $f['name'] );
		$p->set_description( $f['description'] );
		$p->set_short_description( $f['short'] );
		try {
			$p->set_sku( $f['sku'] );
		} catch ( WC_Data_Exception $e ) {
			unset( $e );
		}
		if ( ! $p->is_type( 'variable' ) ) {
			$p->set_regular_price( $f['regular'] );
			$p->set_sale_price( $f['sale'] );
			$p->set_manage_stock( $f['manage_stock'] );
			$p->set_stock_quantity( $f['stock'] );
			$p->set_stock_status( $f['stock_status'] );
		}
		$p->set_image_id( $f['image'] );
	}

	/**
	 * Restore one product and delete its snapshot.
	 *
	 * @param int    $id    Product ID.
	 * @param string $batch Batch ID.
	 */
	public static function restore( $id, $batch ) {
		$data = get_post_meta( $id, self::key( $batch ), true );
		if ( ! is_array( $data ) ) {
			delete_post_meta( $id, self::key( $batch ) );
			return;
		}

		foreach ( $data['new_vars'] as $vid ) {
			$v = wc_get_product( $vid );
			if ( $v ) {
				$v->delete( true );
			}
		}

		$current = wc_get_product( $id );
		if ( $current && $current->get_type() !== $data['type'] ) {
			wp_set_object_terms( $id, $data['type'], 'product_type' );
			wc_delete_product_transients( $id );
		}
		$classname = WC_Product_Factory::get_product_classname( $id, $data['type'] );
		$product   = new $classname( $id );

		self::apply_fields( $product, $data['fields'] );
		$attrs = array();
		foreach ( $data['attributes'] as $tax => $a ) {
			$obj = new WC_Product_Attribute();
			$obj->set_id( $a['id'] );
			$obj->set_name( $a['name'] );
			$obj->set_options( $a['options'] );
			$obj->set_position( $a['position'] );
			$obj->set_visible( $a['visible'] );
			$obj->set_variation( $a['variation'] );
			$attrs[ $tax ] = $obj;
		}
		$product->set_attributes( $attrs );
		$product->set_default_attributes( $data['defaults'] );
		$product->set_category_ids( $data['categories'] );
		$product->set_tag_ids( $data['tags'] );
		$product->set_gallery_image_ids( $data['gallery'] );
		$product->save();

		foreach ( $data['meta'] as $k => $v ) {
			if ( null === $v ) {
				delete_post_meta( $id, $k );
			} else {
				update_post_meta( $id, $k, $v );
			}
		}
		if ( taxonomy_exists( 'product_brand' ) ) {
			wp_set_object_terms( $id, array_map( 'intval', $data['brands'] ), 'product_brand' );
		}

		foreach ( $data['variations'] as $vid => $f ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) {
				continue;
			}
			self::apply_fields( $v, $f );
			$v->set_status( $f['status'] );
			$v->save();
		}
		if ( 'variable' === $data['type'] ) {
			WC_Product_Variable::sync( $id );
		}
		wc_delete_product_transients( $id );
		delete_post_meta( $id, self::key( $batch ) );
	}

	/**
	 * IDs of products with a snapshot in this batch.
	 *
	 * @param string $batch Batch ID.
	 * @param int    $limit Max.
	 * @return int[]
	 */
	public static function pending( $batch, $limit ) {
		return get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'meta_key'       => self::key( $batch ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'   => 'EXISTS',
				'fields'         => 'ids',
				'posts_per_page' => $limit,
			)
		);
	}
}
