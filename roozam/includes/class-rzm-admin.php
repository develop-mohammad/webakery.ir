<?php
defined( 'ABSPATH' ) || exit;

class RZM_Admin {

	public static function hooks() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		add_menu_page(
			'روزم',
			'روزم',
			'manage_options',
			'roozam',
			array( __CLASS__, 'render' ),
			'dashicons-calendar-alt',
			58
		);
	}

	public static function register() {
		register_setting(
			'rzm_settings_group',
			RZM_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => RZM_Settings::defaults(),
			)
		);
	}

	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$defaults = RZM_Settings::defaults();
		return array(
			'wake_time'     => RZM_Settings::sanitize_time( isset( $input['wake_time'] ) ? $input['wake_time'] : $defaults['wake_time'], $defaults['wake_time'] ),
			'sleep_time'    => RZM_Settings::sanitize_time( isset( $input['sleep_time'] ) ? $input['sleep_time'] : $defaults['sleep_time'], $defaults['sleep_time'] ),
			'break_minutes' => max( 0, min( 60, absint( isset( $input['break_minutes'] ) ? $input['break_minutes'] : $defaults['break_minutes'] ) ) ),
			'page_title'    => sanitize_text_field( isset( $input['page_title'] ) ? $input['page_title'] : $defaults['page_title'] ),
		);
	}

	public static function assets( $hook ) {
		if ( strpos( (string) $hook, 'roozam' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'rzm-admin',
			RZM_URL . 'assets/css/admin.css',
			array(),
			RZM_VERSION
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$opts = RZM_Settings::get();
		?>
		<div class="wrap rzm-admin">
			<h1>روزم | برنامه‌ریز روزانه</h1>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=roozam&tab=settings' ) ); ?>">تنظیمات</a>
				<a class="nav-tab <?php echo $tab === 'help' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=roozam&tab=help' ) ); ?>">راهنما</a>
				<a class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=roozam&tab=license' ) ); ?>">لایسنس</a>
			</nav>

			<?php if ( $tab === 'license' ) : ?>
				<div class="rzm-admin-card">
					<p>فعال‌سازی لایسنس از منوی «لایسنس افزونه‌ها» یا فرم زیر:</p>
					<?php
					if ( class_exists( 'WB_License' ) ) {
						WB_License::render_box( RZM_PRODUCT );
					}
					?>
				</div>
			<?php elseif ( $tab === 'help' ) : ?>
				<div class="rzm-admin-card">
					<h2>نصب سریع</h2>
					<ol>
						<li>یک برگه بسازید و شورت‌کد <code>[roozam]</code> را قرار دهید.</li>
						<li>کاربران واردشده برنامه را روی حساب خود ذخیره می‌کنند.</li>
						<li>دکمه «برنامه‌ریزی امروز» عادت‌ها و کارها را در ساعات بیداری می‌چیند.</li>
					</ol>
					<p>سازنده: <a href="https://webakery.ir" target="_blank" rel="noopener">webakery.ir</a></p>
				</div>
			<?php else : ?>
				<form method="post" action="options.php" class="rzm-admin-card">
					<?php settings_fields( 'rzm_settings_group' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="rzm_page_title">عنوان اپ</label></th>
							<td><input name="<?php echo esc_attr( RZM_Settings::OPTION ); ?>[page_title]" id="rzm_page_title" type="text" class="regular-text" value="<?php echo esc_attr( $opts['page_title'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="rzm_wake">ساعت بیداری پیش‌فرض</label></th>
							<td><input name="<?php echo esc_attr( RZM_Settings::OPTION ); ?>[wake_time]" id="rzm_wake" type="time" value="<?php echo esc_attr( $opts['wake_time'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="rzm_sleep">ساعت خواب پیش‌فرض</label></th>
							<td><input name="<?php echo esc_attr( RZM_Settings::OPTION ); ?>[sleep_time]" id="rzm_sleep" type="time" value="<?php echo esc_attr( $opts['sleep_time'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="rzm_break">استراحت بین کارها</label></th>
							<td>
								<input name="<?php echo esc_attr( RZM_Settings::OPTION ); ?>[break_minutes]" id="rzm_break" type="number" min="0" max="60" value="<?php echo esc_attr( (string) $opts['break_minutes'] ); ?>" />
								<span class="description">دقیقه</span>
							</td>
						</tr>
					</table>
					<?php submit_button( 'ذخیره تنظیمات' ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
