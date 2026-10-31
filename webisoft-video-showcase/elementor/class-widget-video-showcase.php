<?php
defined( 'ABSPATH' ) || exit;

class Webisoft_Widget_Video_Showcase extends \Elementor\Widget_Base {

	public function get_name() {
		return 'webisoft-video-showcase';
	}

	public function get_title() {
		return __( 'Webisoft: ویترین ویدیویی', 'webisoft-video-showcase' );
	}

	public function get_icon() {
		return 'eicon-posts-masonry';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'instagram', 'video', 'ویدیو', 'اینستاگرام', 'گرید', 'showcase' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'محتوا', 'webisoft-video-showcase' ),
			)
		);

		$this->add_control(
			'content_note',
			array(
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'raw'  => sprintf(
					/* translators: %s: settings page URL */
					__( 'ویدیوها از باکس «ویدیوهای این صفحه» در ویرایش همین صفحه/پست (یا از منوی «پلی‌لیست‌ها») مدیریت می‌شن. <a href="%s" target="_blank">تنظیمات سراسری</a>', 'webisoft-video-showcase' ),
					esc_url( admin_url( 'admin.php?page=webisoft-video-showcase' ) )
				),
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);

		$this->add_control(
			'source_mode',
			array(
				'label'   => __( 'منبع ویدیوها', 'webisoft-video-showcase' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'     => __( 'خودکار: ویدیوهای همین صفحه/پست (اگر نبود، لیست سراسری)', 'webisoft-video-showcase' ),
					'current'  => __( 'فقط همین صفحه/پست (در لیستینگ جت‌انجین: هر آیتم)', 'webisoft-video-showcase' ),
					'playlist' => __( 'یک پلی‌لیست مستقل', 'webisoft-video-showcase' ),
					'post_id'  => __( 'شناسه (ID) دلخواه / داینامیک', 'webisoft-video-showcase' ),
					'global'   => __( 'لیست سراسری (پنل ویترین ویدیویی)', 'webisoft-video-showcase' ),
				),
			)
		);

		$this->add_control(
			'playlist_id',
			array(
				'label'     => __( 'پلی‌لیست', 'webisoft-video-showcase' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => $this->get_playlist_options(),
				'condition' => array( 'source_mode' => 'playlist' ),
			)
		);

		$this->add_control(
			'post_id',
			array(
				'label'       => __( 'شناسه پست/صفحه/پلی‌لیست', 'webisoft-video-showcase' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'dynamic'     => array( 'active' => true ),
				'description' => __( 'ID همان پست که ویدیوها را در باکس «ویدیوهای این صفحه» پر کرده‌ای.', 'webisoft-video-showcase' ),
				'condition'   => array( 'source_mode' => 'post_id' ),
			)
		);

		$this->add_control(
			'override_display',
			array(
				'label'        => __( 'بازنویسی تنظیمات نمایش برای همین نمونه', 'webisoft-video-showcase' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'aspect_ratio',
			array(
				'label'     => __( 'نسبت تصویر تایل', 'webisoft-video-showcase' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''      => __( '(پیش‌فرض تنظیمات عمومی)', 'webisoft-video-showcase' ),
					'1/1'   => __( 'مربع (اینستاگرام کلاسیک)', 'webisoft-video-showcase' ),
					'4/5'   => __( 'پرتره ۴:۵', 'webisoft-video-showcase' ),
					'9/16'  => __( 'ریلز ۹:۱۶', 'webisoft-video-showcase' ),
				),
				'condition' => array( 'override_display' => 'yes' ),
			)
		);

		$this->add_control(
			'hover_effect',
			array(
				'label'     => __( 'افکت هاور', 'webisoft-video-showcase' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '',
				'options'   => array(
					''      => __( '(پیش‌فرض تنظیمات عمومی)', 'webisoft-video-showcase' ),
					'none'  => __( 'بدون افکت', 'webisoft-video-showcase' ),
					'zoom'  => __( 'بزرگ‌نمایی', 'webisoft-video-showcase' ),
					'dim'   => __( 'تیره‌شدن', 'webisoft-video-showcase' ),
				),
				'condition' => array( 'override_display' => 'yes' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'ظاهر گرید', 'webisoft-video-showcase' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'      => __( 'تعداد ستون', 'webisoft-video-showcase' ),
				'type'       => \Elementor\Controls_Manager::NUMBER,
				'min'        => 1,
				'max'        => 8,
				'selectors'  => array(
					'{{WRAPPER}}' => '--wvs-cols: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'gap',
			array(
				'label'      => __( 'فاصله بین تایل‌ها (px)', 'webisoft-video-showcase' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 40 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--wvs-gap: {{SIZE}}px;',
				),
			)
		);

		$this->add_control(
			'radius',
			array(
				'label'      => __( 'گردی گوشه تایل', 'webisoft-video-showcase' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 300 ),
					'%'  => array( 'min' => 0, 'max' => 50 ),
				),
				'description' => __( 'برای کاملاً گرد (دایره/حبه‌ای) روی ٪ بزن و مقدار را ۵۰٪ بگذار.', 'webisoft-video-showcase' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--wvs-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'lightbox_bg_color',
			array(
				'label'     => __( 'رنگ پس‌زمینه لایت‌باکس', 'webisoft-video-showcase' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}}' => '--wvs-lightbox-bg: {{VALUE}} !important;',
				),
			)
		);

		$this->end_controls_section();
	}

	private function get_playlist_options() {
		$options = array( '' => __( '— انتخاب کنید —', 'webisoft-video-showcase' ) );

		$posts = get_posts(
			array(
				'post_type'        => WVS_CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post ) {
			$options[ $post->ID ] = sprintf( '%s (#%d)', $post->post_title ? $post->post_title : __( '(بدون عنوان)', 'webisoft-video-showcase' ), $post->ID );
		}

		return $options;
	}

	protected function render() {
		$plugin = Webisoft_Video_Showcase_Plugin::instance();
		$s      = $this->get_settings_for_display();

		$overrides = array();

		if ( 'yes' === ( $s['override_display'] ?? '' ) ) {
			$overrides['aspect_ratio'] = $s['aspect_ratio'] ?? '';
			$overrides['hover_effect'] = $s['hover_effect'] ?? '';
		}

		$mode   = $s['source_mode'] ?? 'auto';
		$source = array( 'mode' => 'auto' );

		if ( 'playlist' === $mode ) {
			$source = array( 'id' => absint( $s['playlist_id'] ?? 0 ) );
		} elseif ( 'post_id' === $mode ) {
			$source = array( 'id' => absint( $s['post_id'] ?? 0 ) );
		} elseif ( in_array( $mode, array( 'current', 'global' ), true ) ) {
			$source = array( 'mode' => $mode );
		}

		// پلی‌لیست/ID خالی نباید بی‌صدا به صفحه‌ی جاری برگردد.
		if ( in_array( $mode, array( 'playlist', 'post_id' ), true ) && empty( $source['id'] ) ) {
			$source = array( 'mode' => 'post', 'id' => 0 );
		}

		echo $plugin->render_frontend( $overrides, $source ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
