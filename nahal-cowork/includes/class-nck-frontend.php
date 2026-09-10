<?php
defined( 'ABSPATH' ) || exit;

class NCK_Frontend {

	/** @var bool */
	private static $localized = false;

	public static function hooks() {
		add_shortcode( 'nahal_contract', array( __CLASS__, 'shortcode_contract' ) );
		add_shortcode( 'nahal_hall', array( __CLASS__, 'shortcode_hall' ) );
		add_shortcode( 'nahal_admission', array( __CLASS__, 'shortcode_learner' ) );
		add_shortcode( 'nahal_form', array( __CLASS__, 'shortcode_form' ) );
		add_shortcode( 'nahal_portal', array( __CLASS__, 'shortcode_portal' ) );
		add_shortcode( 'nahal_cowork', array( __CLASS__, 'shortcode_both' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
	}

	public static function register_assets() {
		wp_register_style( 'nck-frontend', NCK_URL . 'assets/css/frontend.css', array(), NCK_VERSION );
		wp_register_style( 'nck-print', NCK_URL . 'assets/css/print.css', array(), NCK_VERSION );
		wp_register_script( 'nck-frontend', NCK_URL . 'assets/js/frontend.js', array(), NCK_VERSION, true );
	}

	public static function maybe_enqueue() {
		if ( self::page_has_shortcode() || self::is_elementor_page() ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		if ( ! wp_style_is( 'nck-frontend', 'registered' ) ) {
			self::register_assets();
		}
		$s = NCK_Settings::all();
		wp_enqueue_style( 'nck-frontend' );
		wp_add_inline_style( 'nck-frontend', '.nck-root{--nck-leaf:' . esc_attr( $s['accent'] ) . ';}' );
		wp_enqueue_script( 'nck-frontend' );

		if ( ! self::$localized ) {
			self::$localized = true;
			$now  = NCK_Jalali::now();
			$slot = NCK_Shifts::current_slot( $now, NCK_Settings::hours() );
			wp_localize_script(
				'nck-frontend',
				'NCK',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'nck_front' ),
					'slot'  => $slot,
					'org'   => $s['org_name'],
					'i18n'  => array(
						'signing'  => 'در حال ثبت…',
						'looking'  => 'در حال جستجو…',
						'checking' => 'در حال ثبت حضور…',
						'error'    => 'خطایی رخ داد. دوباره تلاش کنید.',
						'draw'     => 'لطفاً داخل کادر امضا کنید.',
					),
				)
			);
		}
	}

	private static function page_has_shortcode() {
		if ( ! is_singular() ) {
			return false;
		}
		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'nahal_contract' )
			|| has_shortcode( $post->post_content, 'nahal_hall' )
			|| has_shortcode( $post->post_content, 'nahal_admission' )
			|| has_shortcode( $post->post_content, 'nahal_form' )
			|| has_shortcode( $post->post_content, 'nahal_portal' )
			|| has_shortcode( $post->post_content, 'nahal_cowork' );
	}

	private static function is_elementor_page() {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();
		return $post_id && \Elementor\Plugin::$instance->db->is_built_with_elementor( $post_id );
	}

	public static function shortcode_contract( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'nahal_contract' );
		ob_start();
		$view = 'contract';
		include NCK_PATH . 'templates/contract.php';
		return (string) ob_get_clean();
	}

	public static function shortcode_hall( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'nahal_hall' );
		ob_start();
		include NCK_PATH . 'templates/hall.php';
		return (string) ob_get_clean();
	}

	public static function shortcode_learner( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'nahal_admission' );
		ob_start();
		include NCK_PATH . 'templates/learner.php';
		return (string) ob_get_clean();
	}

	public static function shortcode_form( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts(
			array(
				'slug'  => '',
				'id'    => '',
				'title' => '',
			),
			$atts,
			'nahal_form'
		);
		$form = null;
		if ( $atts['id'] !== '' ) {
			$form = NCK_Forms::get( $atts['id'] );
		}
		if ( ! $form && $atts['slug'] !== '' ) {
			$form = NCK_Forms::by_slug( $atts['slug'] );
		}
		if ( ! $form || 'publish' !== $form['status'] ) {
			return '<p class="nck-note">این فرم در دسترس نیست.</p>';
		}
		ob_start();
		include NCK_PATH . 'templates/custom-form.php';
		return (string) ob_get_clean();
	}

	public static function shortcode_portal( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'title' => '' ), $atts, 'nahal_portal' );
		ob_start();
		include NCK_PATH . 'templates/portal.php';
		return (string) ob_get_clean();
	}

	public static function shortcode_both( $atts = array() ) {
		self::enqueue();
		$atts = shortcode_atts( array( 'view' => 'contract' ), $atts, 'nahal_cowork' );
		if ( 'hall' === $atts['view'] ) {
			return self::shortcode_hall( $atts );
		}
		if ( 'admission' === $atts['view'] || 'learner' === $atts['view'] ) {
			return self::shortcode_learner( $atts );
		}
		if ( 'form' === $atts['view'] ) {
			return self::shortcode_form( $atts );
		}
		if ( 'portal' === $atts['view'] ) {
			return self::shortcode_portal( $atts );
		}
		return self::shortcode_contract( $atts ) . self::shortcode_portal( $atts );
	}

	public static function preview_context() {
		$s    = NCK_Settings::all();
		$vars = NCK_Settings::contract_vars();
		$secs = array();
		foreach ( NCK_Settings::parse_sections() as $sec ) {
			$secs[] = array(
				'title' => NCK_Contract::fill_template( $sec['title'], $vars ),
				'body'  => NCK_Contract::fill_template( $sec['body'], $vars ),
			);
		}
		return array(
			's'         => $s,
			'vars'      => $vars,
			'intro'     => NCK_Contract::fill_template( $s['contract_intro'], $vars ),
			'preamble'  => NCK_Contract::fill_template( $s['contract_preamble'], $vars ),
			'sections'  => $secs,
			'notice'    => $s['event_notice'],
		);
	}
}
