<?php
defined( 'ABSPATH' ) || exit;

/**
 * پلی‌لیست‌های مجزا: ویدیوهای هر صفحه/پست روی متای همان پست ذخیره می‌شوند (WVS_META_ITEMS) و
 * یک نوع‌نوشته‌ی اختصاصی «پلی‌لیست» هم برای لیست‌های مستقل و قابل استفاده در چند جا وجود دارد.
 */
final class Webisoft_Video_Showcase_Playlists {

	const NONCE_ACTION = 'wvs_save_post_items';
	const NONCE_FIELD  = 'wvs_post_items_nonce';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );

		add_filter( 'manage_' . WVS_CPT . '_posts_columns', array( $this, 'list_columns' ) );
		add_action( 'manage_' . WVS_CPT . '_posts_custom_column', array( $this, 'list_column_content' ), 10, 2 );
	}

	public function register_post_type() {
		register_post_type(
			WVS_CPT,
			array(
				'labels'              => array(
					'name'          => __( 'پلی‌لیست‌های ویدیو', 'webisoft-video-showcase' ),
					'singular_name' => __( 'پلی‌لیست ویدیو', 'webisoft-video-showcase' ),
					'menu_name'     => __( 'پلی‌لیست‌ها', 'webisoft-video-showcase' ),
					'add_new'       => __( 'افزودن پلی‌لیست', 'webisoft-video-showcase' ),
					'add_new_item'  => __( 'افزودن پلی‌لیست جدید', 'webisoft-video-showcase' ),
					'edit_item'     => __( 'ویرایش پلی‌لیست', 'webisoft-video-showcase' ),
					'all_items'     => __( 'همه‌ی پلی‌لیست‌ها', 'webisoft-video-showcase' ),
					'not_found'     => __( 'هنوز پلی‌لیستی ساخته نشده است.', 'webisoft-video-showcase' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => Webisoft_Video_Showcase_Admin::MENU_SLUG,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'supports'            => array( 'title' ),
			)
		);
	}

	public function register_meta_box() {
		foreach ( wvs_get_enabled_post_types() as $post_type ) {
			add_meta_box(
				'wvs_post_items',
				__( 'ویدیوهای این صفحه (Webisoft Video Showcase)', 'webisoft-video-showcase' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="wvs-wrap wvs-metabox">
			<div class="wvs-guide-box">
				<p>
					<strong><?php esc_html_e( 'شناسه (ID):', 'webisoft-video-showcase' ); ?></strong>
					<code><?php echo (int) $post->ID; ?></code>
					&nbsp;|&nbsp;
					<strong><?php esc_html_e( 'شورت‌کد:', 'webisoft-video-showcase' ); ?></strong>
					<code dir="ltr">[wvs_showcase id="<?php echo (int) $post->ID; ?>"]</code>
				</p>
				<p class="wvs-hint">
					<?php esc_html_e( 'ویجت المنتور یا شورت‌کد بدون id، به‌صورت خودکار ویدیوهای همین صفحه را نشان می‌دهد (داخل لیستینگ جت‌انجین = ویدیوهای هر آیتم). برای نمایش این لیست در صفحه‌ای دیگر، شورت‌کد بالا را با id بگذار.', 'webisoft-video-showcase' ); ?>
				</p>
			</div>
			<?php wvs_render_items_editor( wvs_get_items_for_post( $post->ID ) ); ?>
		</div>
		<?php
	}

	public function save_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, wvs_get_enabled_post_types(), true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw   = isset( $_POST['wvs_items'] ) ? wp_unslash( $_POST['wvs_items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$items = wvs_sanitize_items( $raw );

		if ( empty( $items ) ) {
			delete_post_meta( $post_id, WVS_META_ITEMS );
			return;
		}

		update_post_meta( $post_id, WVS_META_ITEMS, wp_slash( $items ) );
	}

	public function list_columns( $columns ) {
		$columns['wvs_id']        = __( 'شناسه', 'webisoft-video-showcase' );
		$columns['wvs_count']     = __( 'تعداد ویدیو', 'webisoft-video-showcase' );
		$columns['wvs_shortcode'] = __( 'شورت‌کد', 'webisoft-video-showcase' );

		return $columns;
	}

	public function list_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'wvs_id':
				echo (int) $post_id;
				break;
			case 'wvs_count':
				echo count( wvs_get_items_for_post( $post_id ) );
				break;
			case 'wvs_shortcode':
				echo '<code dir="ltr">[wvs_showcase id="' . (int) $post_id . '"]</code>';
				break;
		}
	}
}
