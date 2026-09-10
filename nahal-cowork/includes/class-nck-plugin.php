<?php
defined( 'ABSPATH' ) || exit;

class NCK_Plugin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->license();
		NCK_Install::maybe_upgrade();
		NCK_Ajax::hooks();
		NCK_Print::hooks();
		NCK_Frontend::hooks();
		NCK_Admin::hooks();

		if ( did_action( 'elementor/loaded' ) ) {
			$this->load_elementor();
		} else {
			add_action( 'elementor/loaded', array( $this, 'load_elementor' ) );
		}

		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
	}

	public function load_elementor() {
		require_once NCK_PATH . 'includes/class-nck-elementor.php';
		NCK_Elementor::hooks();
	}

	private function license() {
		if ( ! class_exists( 'WB_License' ) ) {
			require_once NCK_PATH . 'includes/class-wb-license.php';
		}
		WB_License::init(
			array(
				'product'       => NCK_PRODUCT,
				'name'          => 'قرارداد نهال | فضای کار، سالن و پذیرش',
				'price'         => '۳۹۹,۰۰۰ تومان',
				'file'          => NCK_FILE,
				'version'       => NCK_VERSION,
				'trial_days'    => 7,
				'server'        => 'https://webakery.ir/license-server',
				'register_menu' => true,
				'page'          => 'admin.php?page=' . NCK_MENU . '&tab=license',
				'features'      => array(
					'قرارداد فضای کار اشتراکی با امضا و چاپ',
					'قرارداد اجاره سالن (کد ملی، مبلغ، زمان)',
					'فرم پذیرش فراگیر مرحله‌ای',
					'سهمیه ۲۶ شیفت در ماه و ثبت حضور',
					'شورت‌کد و ویجت المنتور',
					'به‌روزرسانی خودکار از webakery.ir',
				),
			)
		);
	}

	public static function is_usable() {
		if ( ! class_exists( 'WB_License' ) ) {
			return true;
		}
		return WB_License::is_active( NCK_PRODUCT );
	}

	public function row_meta( $links, $file ) {
		if ( plugin_basename( NCK_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=license' ) ) . '">فعال‌سازی لایسنس</a>';
		$links[] = '<a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a>';
		return $links;
	}
}
