<?php
defined( 'ABSPATH' ) || exit;

final class Webisoft_Video_Showcase_Plugin {

	private static $instance = null;

	public $admin;

	public $playlists;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->admin     = new Webisoft_Video_Showcase_Admin();
		$this->playlists = new Webisoft_Video_Showcase_Playlists();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_elementor_notice' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
		add_shortcode( 'wvs_showcase', array( $this, 'shortcode' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'webisoft-video-showcase', false, dirname( WVS_BASENAME ) . '/languages' );
	}

	public function maybe_elementor_notice() {
		if ( did_action( 'elementor/loaded' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'پلاگین «ویترین ویدیویی Webisoft» برای استفاده از ویجت نیازمند المنتور (Elementor) است؛ بدون المنتور فقط شورت‌کد [wvs_showcase] کار می‌کند.', 'webisoft-video-showcase' )
		);
	}

	/**
	 * [wvs_showcase] یا [wvs_showcase id="123"] یا [wvs_showcase source="global"]
	 * بدون id: ویدیوهای پست/صفحه‌ی جاری. با id: ویدیوهای همان پست یا پلی‌لیست.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'           => '',
				'playlist'     => '',
				'source'       => 'auto',
				'aspect_ratio' => '',
				'hover_effect' => '',
			),
			$atts,
			'wvs_showcase'
		);

		$id   = absint( $atts['id'] ? $atts['id'] : $atts['playlist'] );
		$mode = in_array( $atts['source'], array( 'auto', 'current', 'global' ), true ) ? $atts['source'] : 'auto';

		return $this->render_frontend(
			array(
				'aspect_ratio' => in_array( $atts['aspect_ratio'], array( '1/1', '4/5', '9/16' ), true ) ? $atts['aspect_ratio'] : '',
				'hover_effect' => in_array( $atts['hover_effect'], array( 'none', 'zoom', 'dim' ), true ) ? $atts['hover_effect'] : '',
			),
			array(
				'mode' => $mode,
				'id'   => $id,
			)
		);
	}

	public function register_widget( $widgets_manager ) {
		require_once WVS_DIR . 'elementor/class-widget-video-showcase.php';
		$widgets_manager->register( new Webisoft_Widget_Video_Showcase() );
	}

	/* ----------------------------------------------------------------
	 * فرانت‌اند
	 * ---------------------------------------------------------------- */

	public function register_frontend_assets() {
		wp_register_style( 'wvs-frontend', WVS_URL . 'assets/css/frontend.css', array(), WVS_VERSION );

		wp_register_script(
			'wvs-frontend',
			WVS_URL . 'assets/js/frontend.js',
			array(),
			WVS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * همه‌ی متغیرها روی :root نشسته‌اند (لایه پیش‌فرض سراسری از پنل ادمین). --wvs-cols یک متغیر
	 * واحده که مقدارش در بریک‌پوینت‌های مختلف عوض می‌شه (نه سه متغیر جدا) — تا کنترل ریسپانسیو
	 * per-instance المنتور (که خودش @media تولید می‌کنه) روی همین یک نام سوار بشه.
	 * نکته مهم: هیچ‌کدوم از این‌ها نباید هیچ‌وقت مستقیم روی .wvs-showcase (خودِ المنت ویجت) هم
	 * ست بشن — چون رشته وراثت از :root/از {{WRAPPER}} المنتور دقیقاً همون‌جا قطع می‌شه؛ فقط
	 * frontend.css با var(--x, fallback) باید مصرفش کنه. باگ واقعی که یک بار رخ داد و رفع شد.
	 */
	private function build_dynamic_css( $settings ) {
		$font_family = str_replace( array( '}', ';' ), '', $settings['font_family'] );

		$css = sprintf(
			':root{--wvs-gap:%1$dpx;--wvs-radius:%2$dpx;--wvs-aspect:%3$s;--wvs-lightbox-bg:%4$s;--wvs-cols:%5$d;--wvs-badge-bg:%6$s;--wvs-badge-color:%7$s;--wvs-play-color:%8$s;--wvs-skeleton:%9$s;',
			absint( $settings['gap'] ),
			absint( $settings['radius'] ),
			esc_attr( $settings['aspect_ratio'] ),
			esc_attr( $settings['lightbox_bg_color'] ),
			absint( $settings['columns_desktop'] ),
			esc_attr( $settings['badge_bg_color'] ),
			esc_attr( $settings['badge_text_color'] ),
			esc_attr( $settings['play_icon_color'] ),
			esc_attr( $settings['skeleton_color'] )
		);

		if ( $font_family ) {
			$css .= '--wvs-font-family:' . esc_attr( $font_family ) . ';';
		}

		$css .= '}';
		$css .= sprintf(
			'@media (max-width:1024px){:root{--wvs-cols:%1$d;--wvs-gap:%2$dpx;--wvs-radius:%3$dpx;}}',
			absint( $settings['columns_tablet'] ),
			absint( $settings['gap_tablet'] ),
			absint( $settings['radius_tablet'] )
		);
		$css .= sprintf(
			'@media (max-width:767px){:root{--wvs-cols:%1$d;--wvs-gap:%2$dpx;--wvs-radius:%3$dpx;}}',
			absint( $settings['columns_mobile'] ),
			absint( $settings['gap_mobile'] ),
			absint( $settings['radius_mobile'] )
		);

		return $css;
	}

	/**
	 * رندر بخش گرید ویدیویی. توسط ویجت المنتور صدا زده می‌شه.
	 */
	public function render_frontend( $instance_overrides = array(), $source = array() ) {
		$resolved = wvs_resolve_items( $source );
		$items    = $resolved['items'];

		if ( empty( $items ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return '';
			}

			$hint = $resolved['post_id']
				? sprintf(
					/* translators: %d: post ID */
					__( 'برای شناسه‌ی %d هنوز ویدیویی اضافه نشده است. از باکس «ویدیوهای این صفحه» در ویرایش همان پست/پلی‌لیست اضافه کن.', 'webisoft-video-showcase' ),
					(int) $resolved['post_id']
				)
				: __( 'هنوز هیچ ویدیویی اضافه نشده است (ویرایش همین صفحه یا منوی «ویترین ویدیویی»).', 'webisoft-video-showcase' );

			return '<p style="padding:16px;background:#fff3cd;border:1px solid #ffe08a;border-radius:6px;">' . esc_html( $hint ) . '</p>';
		}

		wp_enqueue_style( 'wvs-frontend' );
		wp_enqueue_script( 'wvs-frontend' );

		$settings = wp_parse_args( array_filter( $instance_overrides, function ( $v ) {
			return '' !== $v && null !== $v;
		} ), wvs_get_settings() );

		// چند ویجت در یک صفحه: هر CSS یکسان فقط یک بار چاپ می‌شود.
		static $printed_css = array();
		$dynamic_css        = $this->build_dynamic_css( $settings );
		$css_key            = md5( $dynamic_css );

		if ( ! isset( $printed_css[ $css_key ] ) ) {
			$printed_css[ $css_key ] = true;
			wp_add_inline_style( 'wvs-frontend', $dynamic_css );
		}

		$uuid = 'wvs_' . wp_unique_id();

		$tiles     = array();
		$js_items  = array();
		$preconnect_domains = array();

		foreach ( $items as $item ) {
			$thumb_id = (int) $item['thumbnail_id'];

			if ( $thumb_id && ! wp_attachment_is_image( $thumb_id ) ) {
				$thumb_id = 0; // کاور حذف‌شده/نامعتبر = مثل بدون کاور.
			}

			$embed_url = '';
			$file_url  = '';

			if ( 'upload' === $item['source_type'] ) {
				$file_url = (string) wp_get_attachment_url( (int) $item['video_file_id'] );

				if ( ! $file_url ) {
					continue;
				}
			} else {
				$embed_url = wvs_normalize_embed_url( $item['video_external_url'] );

				if ( ! $embed_url ) {
					continue;
				}

				$domain = wvs_get_embed_preconnect_domain( $embed_url );

				if ( $domain && $settings['enable_preconnect'] ) {
					$preconnect_domains[ $domain ] = true;
				}
			}

			$alt = $item['alt_text'] ? $item['alt_text'] : $item['title'];

			// بدون کاور: یوتیوب → تامبنیل رسمی، آپلودی → اولین فریم خودِ ویدیو، بقیه → placeholder.
			$thumb_url = $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
			$yt_id     = $embed_url ? wvs_get_youtube_id( $embed_url ) : '';

			if ( ! $thumb_id && $yt_id ) {
				$thumb_url = 'https://i.ytimg.com/vi/' . rawurlencode( $yt_id ) . '/hqdefault.jpg';
			}

			$fallback = '';

			if ( ! $thumb_id && ! $thumb_url ) {
				$fallback = $file_url ? 'video' : 'placeholder';
			}

			$js_items[] = array(
				'id'          => $item['id'],
				'sourceType'  => $item['source_type'],
				'fileUrl'     => $file_url,
				'embedUrl'    => $embed_url,
				'orientation' => $item['orientation'],
				'title'       => $item['title'],
				'caption'     => $item['caption'],
				'link'        => $item['link'],
				'thumbAlt'    => $alt,
				'thumbUrl'    => $thumb_url,
			);

			$tiles[] = array(
				'index'          => count( $js_items ) - 1,
				'thumb_id'       => $thumb_id,
				'thumb_url'      => $thumb_url,
				'fallback'       => $fallback,
				'alt'            => $alt,
				'title'          => $item['title'],
				'caption'        => $item['caption'],
				'link'           => $item['link'],
				'duration_label' => $item['duration_label'],
				'views_label'    => $item['views_label'],
				'source_type'    => $item['source_type'],
				'file_url'       => $file_url,
				'embed_url'      => $embed_url,
				'upload_date'    => $item['upload_date'],
			);
		}

		if ( empty( $tiles ) ) {
			return '';
		}

		wp_localize_script(
			'wvs-frontend',
			'WebisoftVideoShowcaseConfig_' . $uuid,
			array(
				'items'      => $js_items,
				'autoplay'   => (bool) $settings['lightbox_autoplay'],
				'preconnect' => array_map( 'esc_url_raw', array_keys( $preconnect_domains ) ),
			)
		);

		ob_start();
		require WVS_DIR . 'frontend/template.php';
		return ob_get_clean();
	}
}
