<?php
defined( 'ABSPATH' ) || exit;

/**
 * تنظیمات پیش‌فرض نمایش (اگر گزینه هنوز ذخیره نشده یا فیلدی جدید اضافه شده).
 */
function wvs_default_settings() {
	return array(
		'columns_desktop'      => 3,
		'columns_tablet'       => 3,
		'columns_mobile'       => 3,
		'gap'                  => 4, // دسکتاپ
		'gap_tablet'           => 4,
		'gap_mobile'           => 4,
		'aspect_ratio'         => '1/1',
		'radius'               => 4, // دسکتاپ
		'radius_tablet'        => 4,
		'radius_mobile'        => 4,
		'hover_effect'         => 'dim',
		'show_play_icon'       => 1,
		'show_duration_badge'  => 1,
		'show_views_badge'     => 0,
		'eager_count'          => 3,
		'lightbox_bg_color'    => 'rgba(0,0,0,0.92)',
		'lightbox_autoplay'    => 1,
		'enable_schema'        => 1,
		'enable_preconnect'    => 1,
		// تایپوگرافی و رنگ — عمداً مقدار پیش‌فرض واقعی (نه خالی)، تا هیچ‌وقت بی‌صدا به فونت/رنگ
		// پیش‌فرض قالب یا تنظیمات سراسری المنتور گره نخورد؛ همه از همین پنل قابل تغییرند.
		'font_family'          => '',
		'badge_bg_color'       => 'rgba(0,0,0,0.55)',
		'badge_text_color'     => '#ffffff',
		'play_icon_color'      => '#ffffff',
		'skeleton_color'       => '#e9e9e9',
		// نوع‌نوشته‌هایی که باکس «پلی‌لیست ویدیو» دارند. خالی = همه‌ی نوع‌نوشته‌های دارای رابط ادمین.
		'post_types'           => array(),
	);
}

function wvs_get_settings() {
	$saved = (array) get_option( WVS_OPTION_SETTINGS, array() );
	return wp_parse_args( $saved, wvs_default_settings() );
}

function wvs_get_items() {
	return (array) get_option( WVS_OPTION_ITEMS, array() );
}

/**
 * تبدیل لینک خارجی (یوتیوب/آپارات/ویمیو) به آدرس embed مناسب برای iframe.
 * اگر با هیچ‌کدوم از الگوهای شناخته‌شده مطابقت نداشت، همون URL خام (پاک‌سازی‌شده)
 * برگردونده می‌شه تا هر لینک embed دیگه‌ای هم که کاربر مستقیم بده کار کنه.
 */
function wvs_normalize_embed_url( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	// یوتیوب: watch?v=, youtu.be/, /shorts/
	if ( preg_match( '#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/shorts/)([A-Za-z0-9_-]{6,})#i', $url, $m ) ) {
		return 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $m[1] );
	}

	// آپارات: aparat.com/v/{hash}
	if ( preg_match( '#aparat\.com/v/([A-Za-z0-9]+)#i', $url, $m ) ) {
		return 'https://www.aparat.com/video/video/embed/videohash/' . rawurlencode( $m[1] ) . '/vt/frame';
	}

	// ویمیو: vimeo.com/{id}
	if ( preg_match( '#vimeo\.com/(?:video/)?(\d+)#i', $url, $m ) ) {
		return 'https://player.vimeo.com/video/' . rawurlencode( $m[1] );
	}

	return esc_url_raw( $url );
}

/**
 * دامنه‌ی پرووایدر یک لینک embed، برای رندر preconnect هدفمند (نه سراسری).
 */
function wvs_get_embed_preconnect_domain( $embed_url ) {
	$host = wp_parse_url( $embed_url, PHP_URL_HOST );

	if ( ! $host ) {
		return '';
	}

	return 'https://' . $host;
}

/**
 * چاپ یک سوییچ toggle در صفحه تنظیمات. ساختار عمداً «label > span.track (position:relative) + span.label»ست،
 * نه یک <label> تودرتوی دیگر — تگ label نمی‌تواند تگ label دیگری را در خود جای دهد (HTML نامعتبر)، و چون
 * track خودش یک جعبه مستقل با ابعاد ثابته، در RTL هم هیچ‌وقت زیر متن لیبل گم نمی‌شود.
 */
function wvs_toggle_field( $name, $checked, $label ) {
	printf(
		'<label class="wvs-switch"><span class="wvs-switch-track"><input type="checkbox" name="%1$s" value="1" %2$s /><span class="wvs-switch-slider"></span></span><span class="wvs-switch-label">%3$s</span></label>',
		esc_attr( $name ),
		checked( $checked, 1, false ),
		esc_html( $label )
	);
}


/* ----------------------------------------------------------------
 * پلی‌لیست‌های مجزا (ویدیوها روی متای هر پست/صفحه/پلی‌لیست ذخیره می‌شوند)
 * ---------------------------------------------------------------- */

/**
 * نوع‌نوشته‌هایی که باکس ویدیو دارند. پلی‌لیست اختصاصی همیشه هست.
 */
function wvs_get_enabled_post_types() {
	$saved = (array) wvs_get_settings()['post_types'];
	$saved = array_filter( array_map( 'sanitize_key', $saved ), 'post_type_exists' );

	if ( empty( $saved ) ) {
		$exclude = array( 'attachment', 'elementor_library', 'e-floating-buttons', 'e-landing-page', 'jet-engine', 'jet-form-builder', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' );
		$saved   = array_diff( array_keys( get_post_types( array( 'show_ui' => true ) ) ), $exclude );
	}

	$saved[] = WVS_CPT;

	return array_values( array_unique( $saved ) );
}

/**
 * ویدیوهای ذخیره‌شده روی یک پست (آرایه‌ی خالی اگر چیزی نباشد).
 */
function wvs_get_items_for_post( $post_id ) {
	$items = get_post_meta( (int) $post_id, WVS_META_ITEMS, true );

	return is_array( $items ) ? $items : array();
}

/**
 * آیا همین بازدیدکننده اجازه دیدن ویدیوهای این پست را دارد؟ (جلوگیری از لو رفتن پلی‌لیست پیش‌نویس/خصوصی/رمزدار)
 */
function wvs_can_view_post_items( $post_id ) {
	$post = get_post( (int) $post_id );

	if ( ! $post ) {
		return false;
	}

	if ( 'publish' !== $post->post_status && ! current_user_can( 'edit_post', $post->ID ) ) {
		return false;
	}

	return ! post_password_required( $post );
}

/**
 * تعیین اینکه کدام لیست ویدیو نمایش داده شود.
 *
 * $source['mode']: auto (پیش‌فرض) | current | post | global
 *   - auto:    ویدیوهای پست/صفحه‌ی جاری؛ اگر نداشت، لیست سراسری قدیمی (سازگاری با نصب‌های قبلی).
 *   - current: فقط پست/صفحه‌ی جاری (داخل لیستینگ جت‌انجین = آیتم همان تکرار).
 *   - post:    پست/پلی‌لیست مشخص با $source['id'].
 *   - global:  لیست سراسری پنل «ویترین ویدیویی».
 * اگر $source['id'] پر باشد، mode نادیده گرفته می‌شود و همان پست استفاده می‌شود.
 *
 * @return array{items:array,post_id:int}
 */
function wvs_resolve_items( $source = array() ) {
	$mode = isset( $source['mode'] ) ? (string) $source['mode'] : 'auto';
	$id   = isset( $source['id'] ) ? absint( $source['id'] ) : 0;

	if ( $id ) {
		$mode = 'post';
	} elseif ( in_array( $mode, array( 'auto', 'current' ), true ) ) {
		$id = (int) get_the_ID();

		// داخل قالب‌های تکرارشونده (Theme Builder/جت‌انجین) گاهی ID خودِ قالب برمی‌گردد، نه پستِ در حال نمایش.
		if ( ! $id || in_array( get_post_type( $id ), array( 'elementor_library', 'jet-engine', 'jet-theme-core' ), true ) ) {
			$id = (int) get_queried_object_id();
		}
	}

	if ( 'global' === $mode ) {
		return array( 'items' => wvs_get_items(), 'post_id' => 0 );
	}

	$items = ( $id && wvs_can_view_post_items( $id ) ) ? wvs_get_items_for_post( $id ) : array();

	if ( empty( $items ) && 'auto' === $mode ) {
		return array( 'items' => wvs_get_items(), 'post_id' => 0 );
	}

	return array( 'items' => $items, 'post_id' => $id );
}

/**
 * افزودن پیش‌نمایش تصویر و نام فایل برای نمایش در ادمین.
 */
function wvs_prepare_items_for_admin( $items ) {
	return array_map(
		function ( $item ) {
			$item = wp_parse_args( (array) $item, wvs_empty_item() );

			$item['thumbnail_preview'] = $item['thumbnail_id'] ? wp_get_attachment_image_url( (int) $item['thumbnail_id'], 'thumbnail' ) : '';
			$item['video_file_name']   = ( 'upload' === $item['source_type'] && $item['video_file_id'] ) ? basename( (string) wp_get_attachment_url( (int) $item['video_file_id'] ) ) : '';

			return $item;
		},
		array_values( (array) $items )
	);
}

function wvs_empty_item() {
	return array(
		'id'                 => '',
		'source_type'        => 'upload',
		'video_file_id'      => '',
		'video_file_name'    => '',
		'video_external_url' => '',
		'orientation'        => 'landscape',
		'thumbnail_id'       => '',
		'thumbnail_preview'  => '',
		'title'              => '',
		'alt_text'           => '',
		'caption'            => '',
		'link'               => '',
		'duration_label'     => '',
		'views_label'        => '',
		'upload_date'        => '',
	);
}

/**
 * ریپیتر ویدیوها (لیست ردیف‌ها + دکمه افزودن + تمپلیت JS). هم در صفحه تنظیمات و هم در متاباکس پست استفاده می‌شود.
 */
function wvs_render_items_editor( $items ) {
	$items = wvs_prepare_items_for_admin( $items );
	?>
	<div id="wvs-items-wrapper">
		<?php foreach ( $items as $index => $item ) : ?>
			<?php require WVS_DIR . 'admin/partials/item-row.php'; ?>
		<?php endforeach; ?>
	</div>
	<button type="button" id="wvs-add-item" class="button button-primary button-large">+ <?php esc_html_e( 'افزودن ویدیوی جدید', 'webisoft-video-showcase' ); ?></button>

	<script type="text/template" id="wvs-item-template">
		<?php
		$index = '__INDEX__';
		$item  = wvs_empty_item();
		require WVS_DIR . 'admin/partials/item-row.php';
		?>
	</script>
	<?php
}

/**
 * پاک‌سازی آرایه‌ی خام ویدیوها از فرم (مشترک بین تنظیمات سراسری و متاباکس پست).
 */
function wvs_sanitize_items( $raw ) {
	$clean = array();

	foreach ( (array) $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$thumbnail_id = absint( $item['thumbnail_id'] ?? 0 );

		if ( ! $thumbnail_id ) {
			continue; // بدون تامبنیل، آیتم نامعتبره.
		}

		$source_type = ( 'external' === ( $item['source_type'] ?? '' ) ) ? 'external' : 'upload';

		$video_file_id      = absint( $item['video_file_id'] ?? 0 );
		$video_external_url = esc_url_raw( $item['video_external_url'] ?? '' );

		if ( 'upload' === $source_type && ! $video_file_id ) {
			continue; // فایل ویدیو انتخاب نشده.
		}

		if ( 'external' === $source_type && '' === $video_external_url ) {
			continue; // لینک خارجی خالیه.
		}

		$orientation = sanitize_key( $item['orientation'] ?? 'landscape' );

		if ( ! in_array( $orientation, array( 'landscape', 'portrait', 'square' ), true ) ) {
			$orientation = 'landscape';
		}

		$clean[] = array(
			'id'                 => sanitize_key( $item['id'] ?? '' ) ?: uniqid( 'wvs_' ),
			'source_type'        => $source_type,
			'video_file_id'      => $video_file_id,
			'video_external_url' => $video_external_url,
			'orientation'        => $orientation,
			'thumbnail_id'       => $thumbnail_id,
			'title'              => sanitize_text_field( $item['title'] ?? '' ),
			'alt_text'           => sanitize_text_field( $item['alt_text'] ?? '' ),
			'caption'            => sanitize_textarea_field( $item['caption'] ?? '' ),
			'link'               => esc_url_raw( $item['link'] ?? '' ),
			'duration_label'     => sanitize_text_field( $item['duration_label'] ?? '' ),
			'views_label'        => sanitize_text_field( $item['views_label'] ?? '' ),
			'upload_date'        => sanitize_text_field( $item['upload_date'] ?? '' ),
		);
	}

	return $clean;
}
