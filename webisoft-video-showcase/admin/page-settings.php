<?php
defined( 'ABSPATH' ) || exit;

$settings  = wvs_get_settings();
$items     = wvs_get_items();

?>
<div class="wrap wvs-wrap">

	<div class="wvs-header">
		<div class="wvs-header-title">
			<span class="wvs-logo dashicons dashicons-format-video"></span>
			<div>
				<h1><?php esc_html_e( 'ویترین ویدیویی Webisoft', 'webisoft-video-showcase' ); ?> <small><?php echo esc_html( WVS_VERSION ); ?></small></h1>
				<p><?php esc_html_e( 'گرید ویدیویی سبک اینستاگرام — محتوا و تنظیمات نمایش را از همین‌جا مدیریت کنید.', 'webisoft-video-showcase' ); ?></p>
			</div>
		</div>
	</div>

	<?php if ( isset( $_GET['wvs_saved'] ) ) : ?>
		<div class="wvs-notice">
			<span class="wvs-notice-icon">✓</span>
			<span><?php esc_html_e( 'تنظیمات با موفقیت ذخیره شد.', 'webisoft-video-showcase' ); ?></span>
		</div>
	<?php endif; ?>

	<div class="wvs-card wvs-guide-box">
		<p><strong>📌 <?php esc_html_e( 'راهنما:', 'webisoft-video-showcase' ); ?></strong> <?php esc_html_e( 'هر صفحه/پست یک «پلی‌لیست ویدیو» مجزا دارد: داخل ویرایش همان صفحه، باکس «ویدیوهای این صفحه» را پر کن، بعد ویجت «Webisoft: ویترین ویدیویی» یا شورت‌کد [wvs_showcase] را در همان صفحه بگذار. برای استفاده در چند جا می‌توانی از منوی «پلی‌لیست‌ها» پلی‌لیست مستقل بسازی. تب «ویدیوها» همان لیست سراسری قدیمی است.', 'webisoft-video-showcase' ); ?></p>
	</div>

	<nav class="wvs-tabs">
		<button type="button" class="wvs-tab active" data-tab="videos"><?php esc_html_e( 'ویدیوها', 'webisoft-video-showcase' ); ?></button>
		<button type="button" class="wvs-tab" data-tab="layout"><?php esc_html_e( 'ظاهر گرید', 'webisoft-video-showcase' ); ?></button>
		<button type="button" class="wvs-tab" data-tab="typography"><?php esc_html_e( 'تایپوگرافی و رنگ‌ها', 'webisoft-video-showcase' ); ?></button>
		<button type="button" class="wvs-tab" data-tab="seo"><?php esc_html_e( 'سئو و سرعت', 'webisoft-video-showcase' ); ?></button>
	</nav>

	<form action="" method="post">
		<?php wp_nonce_field( 'wvs_save_settings' ); ?>

		<div class="wvs-panel" data-panel="videos">
			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'پلی‌لیست ویدیو برای کدام نوع‌نوشته‌ها فعال باشد؟', 'webisoft-video-showcase' ); ?></h3>
					<p><?php esc_html_e( 'باکس «ویدیوهای این صفحه» فقط در نوع‌نوشته‌های انتخاب‌شده دیده می‌شود. هیچ‌کدام = همه‌ی نوع‌نوشته‌ها (صفحه، نوشته، CPTهای جت‌انجین و ...).', 'webisoft-video-showcase' ); ?></p>
				</div>
				<div class="wvs-toggle-grid">
					<?php
					$selected_types = (array) $settings['post_types'];
					foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $pt ) {
						if ( in_array( $pt->name, array( 'attachment', 'elementor_library', WVS_CPT ), true ) ) {
							continue;
						}
						printf(
							'<label class="wvs-switch"><span class="wvs-switch-track"><input type="checkbox" name="wvs_settings[post_types][]" value="%1$s" %2$s /><span class="wvs-switch-slider"></span></span><span class="wvs-switch-label">%3$s</span></label>',
							esc_attr( $pt->name ),
							checked( in_array( $pt->name, $selected_types, true ), true, false ),
							esc_html( $pt->labels->name . ' (' . $pt->name . ')' )
						);
					}
					?>
				</div>
			</div>
			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'ویدیوهای سراسری (پیش‌فرض/قدیمی)', 'webisoft-video-showcase' ); ?></h3>
					<p><?php esc_html_e( 'با کشیدن دستگیره ✥ ترتیب نمایش را در گرید تغییر بده.', 'webisoft-video-showcase' ); ?></p>
				</div>
				<?php wvs_render_items_editor( $items ); ?>
			</div>
		</div>

		<div class="wvs-panel" data-panel="layout" hidden>

			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'گرید', 'webisoft-video-showcase' ); ?></h3>
					<p><?php esc_html_e( 'این‌ها پیش‌فرض کل سایت‌اند؛ هر نمونه از ویجت می‌تواند از تب Style المنتور جداگانه بازنویسی شود.', 'webisoft-video-showcase' ); ?></p>
				</div>
				<div class="wvs-field">
					<label><?php esc_html_e( 'تعداد ستون (دسکتاپ / تبلت / موبایل)', 'webisoft-video-showcase' ); ?></label>
					<div class="wvs-suffix-row">
						<input type="number" min="1" max="8" name="wvs_settings[columns_desktop]" value="<?php echo esc_attr( $settings['columns_desktop'] ); ?>" />
						<input type="number" min="1" max="8" name="wvs_settings[columns_tablet]" value="<?php echo esc_attr( $settings['columns_tablet'] ); ?>" />
						<input type="number" min="1" max="8" name="wvs_settings[columns_mobile]" value="<?php echo esc_attr( $settings['columns_mobile'] ); ?>" />
					</div>
				</div>
				<div class="wvs-field">
					<label><?php esc_html_e( 'فاصله بین تایل‌ها px (دسکتاپ / تبلت / موبایل)', 'webisoft-video-showcase' ); ?></label>
					<div class="wvs-suffix-row">
						<input type="number" min="0" name="wvs_settings[gap]" value="<?php echo esc_attr( $settings['gap'] ); ?>" />
						<input type="number" min="0" name="wvs_settings[gap_tablet]" value="<?php echo esc_attr( $settings['gap_tablet'] ); ?>" />
						<input type="number" min="0" name="wvs_settings[gap_mobile]" value="<?php echo esc_attr( $settings['gap_mobile'] ); ?>" />
					</div>
				</div>
				<div class="wvs-field">
					<label><?php esc_html_e( 'گردی گوشه تایل px — دسکتاپ / تبلت / موبایل (هر عددی؛ برای کاملاً گرد عدد بزرگ مثل ۹۹۹ بگذار)', 'webisoft-video-showcase' ); ?></label>
					<div class="wvs-suffix-row">
						<input type="number" min="0" name="wvs_settings[radius]" value="<?php echo esc_attr( $settings['radius'] ); ?>" />
						<input type="number" min="0" name="wvs_settings[radius_tablet]" value="<?php echo esc_attr( $settings['radius_tablet'] ); ?>" />
						<input type="number" min="0" name="wvs_settings[radius_mobile]" value="<?php echo esc_attr( $settings['radius_mobile'] ); ?>" />
					</div>
				</div>
				<div class="wvs-field-grid-2">
					<div class="wvs-field">
						<label><?php esc_html_e( 'نسبت تصویر تایل', 'webisoft-video-showcase' ); ?></label>
						<select name="wvs_settings[aspect_ratio]">
							<option value="1/1" <?php selected( $settings['aspect_ratio'], '1/1' ); ?>><?php esc_html_e( 'مربع (اینستاگرام کلاسیک)', 'webisoft-video-showcase' ); ?></option>
							<option value="4/5" <?php selected( $settings['aspect_ratio'], '4/5' ); ?>><?php esc_html_e( 'پرتره ۴:۵', 'webisoft-video-showcase' ); ?></option>
							<option value="9/16" <?php selected( $settings['aspect_ratio'], '9/16' ); ?>><?php esc_html_e( 'ریلز ۹:۱۶', 'webisoft-video-showcase' ); ?></option>
						</select>
					</div>
					<div class="wvs-field">
						<label><?php esc_html_e( 'افکت هاور', 'webisoft-video-showcase' ); ?></label>
						<select name="wvs_settings[hover_effect]">
							<option value="none" <?php selected( $settings['hover_effect'], 'none' ); ?>><?php esc_html_e( 'بدون افکت', 'webisoft-video-showcase' ); ?></option>
							<option value="zoom" <?php selected( $settings['hover_effect'], 'zoom' ); ?>><?php esc_html_e( 'بزرگ‌نمایی', 'webisoft-video-showcase' ); ?></option>
							<option value="dim" <?php selected( $settings['hover_effect'], 'dim' ); ?>><?php esc_html_e( 'تیره‌شدن', 'webisoft-video-showcase' ); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'بج‌ها و لایت‌باکس', 'webisoft-video-showcase' ); ?></h3>
				</div>
				<div class="wvs-toggle-grid">
					<?php wvs_toggle_field( 'wvs_settings[show_play_icon]', $settings['show_play_icon'], __( 'نمایش آیکون پلی روی تایل', 'webisoft-video-showcase' ) ); ?>
					<?php wvs_toggle_field( 'wvs_settings[show_duration_badge]', $settings['show_duration_badge'], __( 'نمایش بج مدت‌زمان', 'webisoft-video-showcase' ) ); ?>
					<?php wvs_toggle_field( 'wvs_settings[show_views_badge]', $settings['show_views_badge'], __( 'نمایش بج بازدید (دستی)', 'webisoft-video-showcase' ) ); ?>
					<?php wvs_toggle_field( 'wvs_settings[lightbox_autoplay]', $settings['lightbox_autoplay'], __( 'پخش خودکار هنگام باز شدن لایت‌باکس', 'webisoft-video-showcase' ) ); ?>
				</div>
				<div class="wvs-field" style="margin-top:14px;">
					<label><?php esc_html_e( 'رنگ پس‌زمینه لایت‌باکس', 'webisoft-video-showcase' ); ?></label>
					<input type="text" dir="ltr" style="max-width:220px;" name="wvs_settings[lightbox_bg_color]" value="<?php echo esc_attr( $settings['lightbox_bg_color'] ); ?>" />
				</div>
			</div>

		</div>

		<div class="wvs-panel" data-panel="typography" hidden>
			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'فونت', 'webisoft-video-showcase' ); ?></h3>
					<p><?php esc_html_e( 'این پلاگین هیچ فونتی را از قالب یا تنظیمات سراسری المنتور به ارث نمی‌برد؛ همیشه همین مقدار (یا پیش‌فرض خودش) استفاده می‌شود.', 'webisoft-video-showcase' ); ?></p>
				</div>
				<div class="wvs-field">
					<label><?php esc_html_e( 'خانواده فونت (اختیاری)', 'webisoft-video-showcase' ); ?></label>
					<input type="text" dir="ltr" name="wvs_settings[font_family]" value="<?php echo esc_attr( $settings['font_family'] ); ?>" placeholder="'Vazirmatn', Tahoma, sans-serif" />
					<p class="wvs-hint"><?php esc_html_e( 'خالی = فونت پیش‌فرض سیستم (System UI)، مستقل از فونت قالب سایت.', 'webisoft-video-showcase' ); ?></p>
				</div>
			</div>

			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'بج‌ها، آیکون و اسکلتون بارگذاری', 'webisoft-video-showcase' ); ?></h3>
				</div>
				<div class="wvs-field-grid-2">
					<div class="wvs-field">
						<label><?php esc_html_e( 'رنگ پس‌زمینه بج', 'webisoft-video-showcase' ); ?></label>
						<input type="text" dir="ltr" name="wvs_settings[badge_bg_color]" value="<?php echo esc_attr( $settings['badge_bg_color'] ); ?>" />
					</div>
					<div class="wvs-field">
						<label><?php esc_html_e( 'رنگ متن بج', 'webisoft-video-showcase' ); ?></label>
						<input type="text" dir="ltr" name="wvs_settings[badge_text_color]" value="<?php echo esc_attr( $settings['badge_text_color'] ); ?>" />
					</div>
				</div>
				<div class="wvs-field-grid-2">
					<div class="wvs-field">
						<label><?php esc_html_e( 'رنگ آیکون پلی', 'webisoft-video-showcase' ); ?></label>
						<input type="text" dir="ltr" name="wvs_settings[play_icon_color]" value="<?php echo esc_attr( $settings['play_icon_color'] ); ?>" />
					</div>
					<div class="wvs-field">
						<label><?php esc_html_e( 'رنگ پس‌زمینه تایل تا قبل از لود شدن عکس', 'webisoft-video-showcase' ); ?></label>
						<input type="text" dir="ltr" name="wvs_settings[skeleton_color]" value="<?php echo esc_attr( $settings['skeleton_color'] ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<div class="wvs-panel" data-panel="seo" hidden>
			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'سئو', 'webisoft-video-showcase' ); ?></h3>
				</div>
				<div class="wvs-toggle-grid">
					<?php wvs_toggle_field( 'wvs_settings[enable_schema]', $settings['enable_schema'], __( 'خروجی Schema.org (VideoObject) برای نتایج غنی گوگل', 'webisoft-video-showcase' ) ); ?>
					<?php wvs_toggle_field( 'wvs_settings[enable_preconnect]', $settings['enable_preconnect'], __( 'Preconnect خودکار به دامنه لینک‌های خارجی', 'webisoft-video-showcase' ) ); ?>
				</div>
			</div>
			<div class="wvs-card">
				<div class="wvs-card-head">
					<h3><?php esc_html_e( 'سرعت لود', 'webisoft-video-showcase' ); ?></h3>
					<p><?php esc_html_e( 'تایل‌های اول برای امتیاز بهتر LCP بدون تأخیر لود می‌شوند، بقیه lazy می‌مانند.', 'webisoft-video-showcase' ); ?></p>
				</div>
				<div class="wvs-field">
					<label><?php esc_html_e( 'تعداد تایل‌های اول با بارگذاری فوری (eager)', 'webisoft-video-showcase' ); ?></label>
					<input type="number" min="0" style="max-width:100px;" name="wvs_settings[eager_count]" value="<?php echo esc_attr( $settings['eager_count'] ); ?>" />
				</div>
			</div>
		</div>

		<div class="wvs-save-bar">
			<span class="wvs-hint"><?php esc_html_e( 'تغییرات هر تبی که باز باشد، همه با هم ذخیره می‌شوند.', 'webisoft-video-showcase' ); ?></span>
			<button type="submit" name="wvs_save" value="1" class="button button-primary"><?php esc_html_e( 'ذخیره همه تنظیمات', 'webisoft-video-showcase' ); ?></button>
		</div>
	</form>
</div>
