<?php
defined( 'ABSPATH' ) || exit;

class NCK_Admin {

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_csv' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NCK_FILE ), array( __CLASS__, 'links' ) );
	}

	public static function menu() {
		add_menu_page(
			'قرارداد نهال',
			'نهال',
			'edit_posts',
			NCK_MENU,
			array( __CLASS__, 'render' ),
			'dashicons-welcome-learn-more',
			56
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, NCK_MENU ) ) {
			return;
		}
		wp_enqueue_style( 'nck-admin', NCK_URL . 'assets/css/admin.css', array(), NCK_VERSION );
		wp_enqueue_script( 'nck-admin', NCK_URL . 'assets/js/admin.js', array(), NCK_VERSION, true );
		wp_localize_script(
			'nck-admin',
			'NCKAdmin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'nck_admin' ),
				'types' => NCK_Forms::field_types(),
				'roles' => NCK_Forms::roles(),
			)
		);
	}

	public static function handle_save() {
		if ( empty( $_POST['nck_save_settings'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'nck_settings' );
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore
		NCK_Settings::save( $input );
		wp_safe_redirect( add_query_arg( array( 'page' => NCK_MENU, 'tab' => 'settings', 'saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_form() {
		if ( empty( $_POST['nck_save_form'] ) && empty( $_GET['nck_delete_form'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! empty( $_GET['nck_delete_form'] ) ) { // phpcs:ignore
			check_admin_referer( 'nck_delete_form' );
			$id = isset( $_GET['nck_delete_form'] ) ? sanitize_text_field( wp_unslash( $_GET['nck_delete_form'] ) ) : ''; // phpcs:ignore
			NCK_Forms::delete( $id );
			wp_safe_redirect( add_query_arg( array( 'page' => NCK_MENU, 'tab' => 'forms', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		check_admin_referer( 'nck_save_form' );
		$raw = isset( $_POST['nck_form_json'] ) ? wp_unslash( $_POST['nck_form_json'] ) : ''; // phpcs:ignore
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => NCK_MENU, 'tab' => 'forms', 'form_error' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		$form = NCK_Forms::save( $data );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => NCK_MENU,
					'tab'      => 'forms',
					'form'     => $form['id'],
					'form_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public static function handle_csv() {
		if ( empty( $_GET['nck_export'] ) || empty( $_GET['page'] ) || NCK_MENU !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		check_admin_referer( 'nck_export' );
		$type = sanitize_key( wp_unslash( $_GET['nck_export'] ) ); // phpcs:ignore
		$t    = NCK_Jalali::today();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="nahal-' . $type . '.csv"' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' );
		if ( 'members' === $type ) {
			fputcsv( $out, array( 'id', 'name', 'phone', 'status', 'created_at' ) );
			foreach ( NCK_Members::search( '', 500, 0 ) as $row ) {
				fputcsv( $out, array( $row['id'], $row['full_name'], $row['phone'], $row['status'], $row['created_at'] ) );
			}
		} else {
			fputcsv( $out, array( 'name', 'phone', 'shift', 'used', 'year', 'month' ) );
			foreach ( NCK_Attendance::month_report( $t['y'], $t['m'] ) as $row ) {
				fputcsv( $out, array( $row['full_name'], $row['phone'], $row['shift_type'], $row['used'], $t['y'], $t['m'] ) );
			}
		}
		fclose( $out );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore
		$tabs = array(
			'dashboard'  => 'خانه',
			'members'    => 'اعضا',
			'contracts'  => 'قراردادها',
			'forms'      => 'فرم‌ها',
			'attendance' => 'حضور',
			'shortcodes' => 'شورت‌کدها',
			'settings'   => 'تنظیمات',
			'license'    => 'لایسنس',
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		if ( in_array( $tab, array( 'settings', 'license', 'forms' ), true ) && ! current_user_can( 'manage_options' ) ) {
			$tab = 'dashboard';
		}
		include NCK_PATH . 'templates/admin-layout.php';
	}

	public static function links( $links ) {
		$url  = admin_url( 'admin.php?page=' . NCK_MENU );
		$help = admin_url( 'admin.php?page=' . NCK_MENU . '&tab=shortcodes' );
		array_unshift( $links, '<a href="' . esc_url( $help ) . '">شورت‌کدها</a>' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">نهال</a>' );
		return $links;
	}

	/**
	 * راهنمای شورت‌کدهای فرانت برای برگه و المنتور.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function shortcode_guide() {
		return array(
			array(
				'code'  => '[nahal_contract]',
				'title' => 'قرارداد فضای کار',
				'who'   => 'کسی که می‌خواهد عضو فضای کار اشتراکی شود و قرارداد ببندد.',
				'page'  => 'یک برگه جدا، مثلاً «قرارداد فضای کار».',
				'pay'   => 'مرحله آخر فرم، پرداخت است. اگر مبلغ وارد شود سفارش ووکامرس ساخته می‌شود.',
			),
			array(
				'code'  => '[nahal_hall]',
				'title' => 'اجاره سالن',
				'who'   => 'کسی که سالن را برای مراسم یا برنامه اجاره می‌کند.',
				'page'  => 'یک برگه جدا، مثلاً «اجاره سالن».',
				'pay'   => 'مرحله آخر فرم، پرداخت است. مبلغ اجاره سفارش ووکامرس می‌سازد.',
			),
			array(
				'code'  => '[nahal_admission]',
				'title' => 'پذیرش فراگیر',
				'who'   => 'ثبت‌نام فراگیر در دوره‌های آموزشی نهال.',
				'page'  => 'یک برگه جدا، مثلاً «پذیرش فراگیر».',
				'pay'   => 'اگر مبلغ شهریه روی فرم یا تنظیمات باشد، سفارش ساخته می‌شود.',
			),
			array(
				'code'  => '[nahal_form slug="workshop"]',
				'title' => 'فرم سفارشی',
				'who'   => 'هر فرمی که خودتان در تب «فرم‌ها» می‌سازید (کارگاه، اردو، …).',
				'page'  => 'برگه جدا برای همان فرم. به‌جای workshop همان شناسه انگلیسی فرم را بگذارید.',
				'pay'   => 'اگر در همان فرم پرداخت را روشن کرده باشید.',
			),
			array(
				'code'  => '[nahal_portal]',
				'title' => 'پورتال عضو',
				'who'   => 'عضو فعلی که می‌خواهد باقی‌مانده شیفت را ببیند یا حضور ثبت کند.',
				'page'  => 'یک برگه جدا، مثلاً «پورتال اعضا». این فرم قرارداد نیست.',
				'pay'   => 'پرداخت ندارد.',
			),
		);
	}

	public static function notice() {
		if ( ! empty( $_GET['saved'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>تنظیمات ذخیره شد.</p></div>';
		}
		if ( ! empty( $_GET['form_saved'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>فرم ذخیره شد. شورت‌کد را در برگه یا المنتور بگذارید.</p></div>';
		}
		if ( ! empty( $_GET['deleted'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-success is-dismissible"><p>فرم حذف شد.</p></div>';
		}
		if ( ! empty( $_GET['form_error'] ) ) { // phpcs:ignore
			echo '<div class="notice notice-error is-dismissible"><p>ذخیره فرم انجام نشد. ساختار را بررسی کنید.</p></div>';
		}
	}
}
