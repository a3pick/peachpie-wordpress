<?php
defined( 'ABSPATH' ) || exit;

/**
 * یک ردیف آیتم ویدیویی در ریپیتر ادمین.
 * انتظار می‌رود $index و $item (با کلیدهای پیش‌فرض) از فایل والد تعریف شده باشند.
 */

$name        = 'wvs_items[' . $index . ']';
$source_type = ( 'external' === ( $item['source_type'] ?? 'upload' ) ) ? 'external' : 'upload';
$orientation = in_array( $item['orientation'] ?? '', array( 'landscape', 'portrait', 'square' ), true ) ? $item['orientation'] : 'landscape';
$has_thumb   = ! empty( $item['thumbnail_preview'] );
?>
<div class="wvs-item-row" data-source="<?php echo esc_attr( $source_type ); ?>">
	<div class="wvs-item-row-head">
		<span class="wvs-drag-handle dashicons dashicons-move" title="<?php esc_attr_e( 'جابجایی', 'webisoft-video-showcase' ); ?>"></span>
		<div class="wvs-item-row-thumb<?php echo $has_thumb ? '' : ' is-empty'; ?>">
			<img src="<?php echo esc_url( $item['thumbnail_preview'] ?? '' ); ?>" alt="" />
		</div>
		<strong class="wvs-item-row-title"><?php echo esc_html( $item['title'] ? $item['title'] : sprintf( /* translators: %d: item number */ __( 'ویدیو %d', 'webisoft-video-showcase' ), (int) $index + 1 ) ); ?></strong>
		<span class="wvs-pill <?php echo 'external' === $source_type ? 'wvs-pill-muted' : 'wvs-pill-success'; ?>">
			<?php echo 'external' === $source_type ? esc_html__( 'لینک خارجی', 'webisoft-video-showcase' ) : esc_html__( 'آپلودی', 'webisoft-video-showcase' ); ?>
		</span>
		<button type="button" class="button-link wvs-remove-item"><?php esc_html_e( 'حذف', 'webisoft-video-showcase' ); ?></button>
	</div>

	<input type="hidden" class="wvs-field-id" name="<?php echo esc_attr( $name ); ?>[id]" value="<?php echo esc_attr( $item['id'] ?? '' ); ?>" />

	<div class="wvs-item-row-body">

		<div class="wvs-field wvs-field-thumb">
			<label><?php esc_html_e( 'تصویر کاور (الزامی)', 'webisoft-video-showcase' ); ?></label>
			<div class="wvs-media-picker">
				<img class="wvs-thumb-preview" src="<?php echo esc_url( $item['thumbnail_preview'] ?? '' ); ?>" style="<?php echo $has_thumb ? '' : 'display:none;'; ?>" alt="" />
				<input type="hidden" class="wvs-field-thumb-id" name="<?php echo esc_attr( $name ); ?>[thumbnail_id]" value="<?php echo esc_attr( $item['thumbnail_id'] ?? '' ); ?>" />
				<button type="button" class="button wvs-pick-thumbnail"><?php esc_html_e( 'انتخاب تصویر', 'webisoft-video-showcase' ); ?></button>
			</div>
			<p class="wvs-hint"><?php esc_html_e( 'تولید خودکار تامبنیل از ویدیو پشتیبانی نمی‌شود؛ یک اسکرین‌شات مناسب از ویدیو آپلود کنید.', 'webisoft-video-showcase' ); ?></p>
		</div>

		<div class="wvs-field wvs-field-wide">
			<label><?php esc_html_e( 'منبع ویدیو', 'webisoft-video-showcase' ); ?></label>

			<div class="wvs-source-switcher">
				<label class="wvs-radio-pill">
					<input type="radio" class="wvs-source-radio" name="<?php echo esc_attr( $name ); ?>[source_type]" value="upload" <?php checked( $source_type, 'upload' ); ?> />
					<span><?php esc_html_e( 'آپلود فایل ویدیو', 'webisoft-video-showcase' ); ?></span>
				</label>
				<label class="wvs-radio-pill">
					<input type="radio" class="wvs-source-radio" name="<?php echo esc_attr( $name ); ?>[source_type]" value="external" <?php checked( $source_type, 'external' ); ?> />
					<span><?php esc_html_e( 'لینک خارجی', 'webisoft-video-showcase' ); ?></span>
				</label>
			</div>

			<div class="wvs-source-upload" <?php echo 'upload' !== $source_type ? 'style="display:none;"' : ''; ?>>
				<div class="wvs-media-picker">
					<span class="wvs-file-name"><?php echo esc_html( $item['video_file_name'] ?? '' ); ?></span>
					<input type="hidden" class="wvs-field-video-id" name="<?php echo esc_attr( $name ); ?>[video_file_id]" value="<?php echo esc_attr( $item['video_file_id'] ?? '' ); ?>" />
					<button type="button" class="button wvs-pick-video"><?php esc_html_e( 'انتخاب فایل ویدیو', 'webisoft-video-showcase' ); ?></button>
				</div>
				<p class="wvs-hint"><?php esc_html_e( 'ویدیو مستقیماً از هاست شما پخش می‌شود؛ جهت نمایش در لایت‌باکس خودکار از روی خود فایل تشخیص داده می‌شود.', 'webisoft-video-showcase' ); ?></p>
			</div>

			<div class="wvs-source-external" <?php echo 'external' !== $source_type ? 'style="display:none;"' : ''; ?>>
				<input type="url" class="regular-text" dir="ltr" placeholder="https://..." name="<?php echo esc_attr( $name ); ?>[video_external_url]" value="<?php echo esc_attr( $item['video_external_url'] ?? '' ); ?>" />
				<p class="wvs-hint"><?php esc_html_e( 'لینک یوتیوب، آپارات، ویمیو یا هر لینک قابل جاسازی دیگر.', 'webisoft-video-showcase' ); ?></p>

				<div class="wvs-field" style="margin-top:10px;">
					<label><?php esc_html_e( 'جهت ویدیو (برای اندازه درست پخش‌کننده در لایت‌باکس)', 'webisoft-video-showcase' ); ?></label>
					<select name="<?php echo esc_attr( $name ); ?>[orientation]">
						<option value="landscape" <?php selected( $orientation, 'landscape' ); ?>><?php esc_html_e( 'افقی (۱۶:۹ — رایج‌ترین حالت)', 'webisoft-video-showcase' ); ?></option>
						<option value="portrait" <?php selected( $orientation, 'portrait' ); ?>><?php esc_html_e( 'عمودی (۹:۱۶ — ریلز/استوری)', 'webisoft-video-showcase' ); ?></option>
						<option value="square" <?php selected( $orientation, 'square' ); ?>><?php esc_html_e( 'مربعی (۱:۱)', 'webisoft-video-showcase' ); ?></option>
					</select>
					<p class="wvs-hint"><?php esc_html_e( 'چون ابعاد واقعی لینک‌های خارجی از قبل مشخص نیست، خودتان جهت آن را انتخاب کنید تا در لایت‌باکس ریز/سیاه‌حاشیه دیده نشود.', 'webisoft-video-showcase' ); ?></p>
				</div>
			</div>
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'عنوان', 'webisoft-video-showcase' ); ?></label>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" />
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'متن جایگزین تصویر (Alt، برای سئو)', 'webisoft-video-showcase' ); ?></label>
			<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[alt_text]" value="<?php echo esc_attr( $item['alt_text'] ?? '' ); ?>" />
		</div>

		<div class="wvs-field wvs-field-wide">
			<label><?php esc_html_e( 'توضیح/کپشن (در لایت‌باکس نمایش داده می‌شود)', 'webisoft-video-showcase' ); ?></label>
			<textarea rows="2" class="large-text" name="<?php echo esc_attr( $name ); ?>[caption]"><?php echo esc_textarea( $item['caption'] ?? '' ); ?></textarea>
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'لینک دکمه (اختیاری)', 'webisoft-video-showcase' ); ?></label>
			<input type="url" class="regular-text" dir="ltr" name="<?php echo esc_attr( $name ); ?>[link]" value="<?php echo esc_attr( $item['link'] ?? '' ); ?>" />
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'برچسب مدت زمان (اختیاری، مثلاً ۰:۴۵)', 'webisoft-video-showcase' ); ?></label>
			<input type="text" class="small-text" dir="ltr" name="<?php echo esc_attr( $name ); ?>[duration_label]" value="<?php echo esc_attr( $item['duration_label'] ?? '' ); ?>" />
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'برچسب بازدید (اختیاری، دستی)', 'webisoft-video-showcase' ); ?></label>
			<input type="text" class="small-text" dir="ltr" name="<?php echo esc_attr( $name ); ?>[views_label]" value="<?php echo esc_attr( $item['views_label'] ?? '' ); ?>" />
		</div>

		<div class="wvs-field">
			<label><?php esc_html_e( 'تاریخ انتشار (اختیاری، برای سئو)', 'webisoft-video-showcase' ); ?></label>
			<input type="date" name="<?php echo esc_attr( $name ); ?>[upload_date]" value="<?php echo esc_attr( $item['upload_date'] ?? '' ); ?>" />
		</div>

	</div>
</div>
