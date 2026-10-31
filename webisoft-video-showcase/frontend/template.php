<?php
defined( 'ABSPATH' ) || exit;

/**
 * قالب فرانت‌اند گرید ویدیویی. انتظار می‌رود $tiles، $settings، $uuid و $js_items
 * از Webisoft_Video_Showcase_Plugin::render_frontend() تعریف شده باشند.
 */

$hover       = in_array( $settings['hover_effect'], array( 'none', 'zoom', 'dim' ), true ) ? $settings['hover_effect'] : 'dim';
$eager_count = max( 0, absint( $settings['eager_count'] ) );
?>
<section class="wvs-showcase" id="<?php echo esc_attr( $uuid ); ?>" data-hover="<?php echo esc_attr( $hover ); ?>">

	<div class="wvs-grid" role="list">
		<?php foreach ( $tiles as $tile ) : ?>
			<button
				type="button"
				class="wvs-tile"
				role="listitem"
				data-index="<?php echo (int) $tile['index']; ?>"
				aria-label="<?php echo esc_attr( $tile['title'] ? $tile['title'] : __( 'پخش ویدیو', 'webisoft-video-showcase' ) ); ?>"
			>
				<?php
				$img_attr = array(
					'alt'      => $tile['alt'],
					'loading'  => $tile['index'] < $eager_count ? 'eager' : 'lazy',
					'decoding' => 'async',
					'class'    => 'wvs-tile-img',
				);

				// اولین تایل معمولاً LCP صفحه است؛ چون رندر خارج از فیلتر the_content انجام می‌شود،
				// اولویت‌دهی خودکار وردپرس اعمال نمی‌شود و باید صریح تنظیم شود.
				if ( 0 === $tile['index'] ) {
					$img_attr['fetchpriority'] = 'high';
				}

				echo wp_get_attachment_image( $tile['thumb_id'], 'medium_large', false, $img_attr ); // phpcs:ignore WordPress.Security.EscapeOutput
				?>

				<?php if ( $settings['show_play_icon'] ) : ?>
					<span class="wvs-play-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					</span>
				<?php endif; ?>

				<?php if ( $settings['show_duration_badge'] && $tile['duration_label'] ) : ?>
					<span class="wvs-badge wvs-badge-duration"><?php echo esc_html( $tile['duration_label'] ); ?></span>
				<?php endif; ?>

				<?php if ( $settings['show_views_badge'] && $tile['views_label'] ) : ?>
					<span class="wvs-badge wvs-badge-views">
						<svg viewBox="0 0 24 24" width="12" height="12" fill="currentColor" aria-hidden="true"><path d="M12 5c-7 0-11 7-11 7s4 7 11 7 11-7 11-7-4-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10z"/></svg>
						<?php echo esc_html( $tile['views_label'] ); ?>
					</span>
				<?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>

	<div class="wvs-lightbox" id="<?php echo esc_attr( $uuid ); ?>_lightbox" role="dialog" aria-modal="true" aria-hidden="true">
		<button type="button" class="wvs-lb-close" aria-label="<?php esc_attr_e( 'بستن', 'webisoft-video-showcase' ); ?>">&times;</button>
		<button type="button" class="wvs-lb-nav wvs-lb-prev" aria-label="<?php esc_attr_e( 'ویدیوی قبلی', 'webisoft-video-showcase' ); ?>">&#10094;</button>
		<button type="button" class="wvs-lb-nav wvs-lb-next" aria-label="<?php esc_attr_e( 'ویدیوی بعدی', 'webisoft-video-showcase' ); ?>">&#10095;</button>

		<div class="wvs-lb-inner">
			<div class="wvs-lb-media"></div>

			<div class="wvs-lb-footer">
				<div class="wvs-lb-caption"></div>
				<a href="" class="wvs-lb-link" target="_blank" rel="noopener"></a>
			</div>
		</div>
	</div>

	<?php if ( $settings['enable_schema'] ) : ?>
		<?php
		$schema_items = array();

		foreach ( $tiles as $tile ) {
			/* translators: %d: item number */
			$fallback_name = sprintf( __( 'ویدیوی نمونه‌کار %d', 'webisoft-video-showcase' ), $tile['index'] + 1 );

			$video_object = array(
				'@type'        => 'VideoObject',
				'name'         => $tile['title'] ? $tile['title'] : $fallback_name,
				'thumbnailUrl' => array( wp_get_attachment_image_url( $tile['thumb_id'], 'large' ) ),
			);

			if ( $tile['caption'] ) {
				$video_object['description'] = $tile['caption'];
			}

			if ( 'upload' === $tile['source_type'] && $tile['file_url'] ) {
				$video_object['contentUrl'] = $tile['file_url'];
			} elseif ( $tile['embed_url'] ) {
				$video_object['embedUrl'] = $tile['embed_url'];
			}

			if ( $tile['upload_date'] ) {
				$timestamp = strtotime( $tile['upload_date'] );

				if ( $timestamp ) {
					$video_object['uploadDate'] = gmdate( 'c', $timestamp );
				}
			}

			// duration_label یک متن آزاد (مثلاً «۰:۴۵») است، نه فرمت معتبر ISO 8601 که Schema.org
			// برای duration نیاز داره — عمداً در JSON-LD قرار داده نمی‌شه، فقط به‌صورت بج نمایشی روی تایل می‌مونه.

			$schema_items[] = array(
				'@type'    => 'ListItem',
				'position' => count( $schema_items ) + 1,
				'item'     => $video_object,
			);
		}

		if ( ! empty( $schema_items ) ) {
			$schema = array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'itemListElement' => $schema_items,
			);
			?>
			<script type="application/ld+json"><?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE ); ?></script>
			<?php
		}
		?>
	<?php endif; ?>
</section>
