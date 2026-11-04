<?php
defined( 'ABSPATH' ) || exit;

/**
 * آواتار اختصاصی، آپلود فونت و موارد جانبی.
 */
class WSF_Extras {

	const META_AVATAR = 'wsf_custom_avatar_id'; // همان کلید قدیمی؛ آواتارهای قبلی حفظ می‌شود.

	public static function init() {
		if ( WSF_Options::get( 'system', 'avatar', 1 ) ) {
			add_filter( 'pre_get_avatar_data', array( __CLASS__, 'avatar_data' ), 99, 2 );
			add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
			add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
			add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
			add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'profile_assets' ) );
		}
		if ( WSF_Options::get( 'system', 'font_uploads', 1 ) ) {
			add_filter( 'upload_mimes', array( __CLASS__, 'font_mimes' ) );
			add_filter( 'wp_check_filetype_and_ext', array( __CLASS__, 'font_filetype' ), 10, 5 );
		}
	}

	/* ---------- فونت ---------- */
	private static function fonts() {
		return array(
			'ttf'   => 'font/ttf',
			'otf'   => 'font/otf',
			'woff'  => 'font/woff',
			'woff2' => 'font/woff2',
		);
	}

	public static function font_mimes( $mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes = array_merge( $mimes, self::fonts() );
		}
		return $mimes;
	}

	public static function font_filetype( $data, $file, $filename, $mimes, $real_mime = '' ) {
		if ( ! empty( $data['ext'] ) || ! current_user_can( 'manage_options' ) ) {
			return $data;
		}
		$ext   = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$fonts = self::fonts();
		if ( isset( $fonts[ $ext ] ) ) {
			$data['ext']  = $ext;
			$data['type'] = $fonts[ $ext ];
		}
		return $data;
	}

	/* ---------- آواتار ---------- */
	private static function user_from( $id_or_email ) {
		if ( is_numeric( $id_or_email ) ) {
			return get_user_by( 'id', absint( $id_or_email ) );
		}
		if ( $id_or_email instanceof WP_User ) {
			return $id_or_email;
		}
		if ( $id_or_email instanceof WP_Post && ! empty( $id_or_email->post_author ) ) {
			return get_user_by( 'id', (int) $id_or_email->post_author );
		}
		if ( $id_or_email instanceof WP_Comment && ! empty( $id_or_email->user_id ) ) {
			return get_user_by( 'id', (int) $id_or_email->user_id );
		}
		if ( is_object( $id_or_email ) && ! empty( $id_or_email->user_id ) ) {
			return get_user_by( 'id', absint( $id_or_email->user_id ) );
		}
		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			return get_user_by( 'email', $id_or_email );
		}
		return false;
	}

	public static function avatar_data( $args, $id_or_email ) {
		$user = self::user_from( $id_or_email );
		if ( ! $user ) {
			return $args;
		}
		$aid = absint( get_user_meta( $user->ID, self::META_AVATAR, true ) );
		if ( ! $aid ) {
			return $args;
		}
		$size = ! empty( $args['size'] ) ? absint( $args['size'] ) : 96;
		$url  = wp_get_attachment_image_url( $aid, array( $size, $size ) );
		if ( ! $url ) {
			$url = wp_get_attachment_url( $aid );
		}
		if ( ! $url ) {
			return $args;
		}
		$args['url']          = $url;
		$args['found_avatar'] = true;
		return $args;
	}

	public static function profile_fields( $user ) {
		$aid = absint( get_user_meta( $user->ID, self::META_AVATAR, true ) );
		$url = $aid ? wp_get_attachment_image_url( $aid, 'thumbnail' ) : '';
		wp_nonce_field( 'wsf_avatar_' . $user->ID, 'wsf_avatar_nonce' );
		?>
		<h2>آواتار سفارشی</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="wsf_avatar_id">تصویر پروفایل</label></th>
				<td>
					<div id="wsf-avatar-preview" class="wsf-avatar-preview">
						<?php if ( $url ) : ?>
							<img src="<?php echo esc_url( $url ); ?>" alt="">
						<?php else : ?>
							<span>بدون تصویر</span>
						<?php endif; ?>
					</div>
					<input type="hidden" id="wsf_avatar_id" name="wsf_avatar_id" value="<?php echo esc_attr( $aid ); ?>">
					<button type="button" class="button" id="wsf-avatar-select">انتخاب یا آپلود تصویر</button>
					<button type="button" class="button button-link-delete" id="wsf-avatar-remove"<?php echo $aid ? '' : ' style="display:none"'; ?>>حذف تصویر</button>
					<p class="description">این تصویر از کتابخانه رسانه خوانده می‌شود و جای Gravatar را می‌گیرد.</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['wsf_avatar_nonce'], $_POST['wsf_avatar_id'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsf_avatar_nonce'] ) ), 'wsf_avatar_' . $user_id ) ) {
			return;
		}
		$aid = absint( wp_unslash( $_POST['wsf_avatar_id'] ) );
		if ( $aid && wp_attachment_is_image( $aid ) ) {
			update_user_meta( $user_id, self::META_AVATAR, $aid );
		} else {
			delete_user_meta( $user_id, self::META_AVATAR );
		}
	}

	public static function profile_assets( $hook ) {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wsf-admin', WSF_URL . 'assets/css/wsf-admin.css', array(), WSF_VERSION );
		wp_enqueue_script( 'wsf-admin-profile', WSF_URL . 'assets/js/wsf-admin-profile.js', array( 'jquery' ), WSF_VERSION, true );
		wp_localize_script( 'wsf-admin-profile', 'WSFProfile', array( 'title' => 'انتخاب تصویر آواتار', 'button' => 'استفاده از این تصویر', 'none' => 'بدون تصویر' ) );
	}
}
