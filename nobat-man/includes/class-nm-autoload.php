<?php
defined( 'ABSPATH' ) || exit;

/**
 * Autoloader سبک — فقط کلاس‌های NM_* و WB_License.
 */
class NM_Autoload {

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	public static function load( $class ) {
		if ( 'WB_License' === $class ) {
			$file = NM_PATH . 'includes/class-wb-license.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}

		if ( 0 !== strpos( $class, 'NM_' ) ) {
			return;
		}

		$map = array(
			'NM_Plugin'            => 'includes/class-nm-plugin.php',
			'NM_Activator'         => 'includes/class-nm-activator.php',
			'NM_Install'           => 'includes/class-nm-install.php',
			'NM_Jalali'            => 'includes/class-nm-jalali.php',
			'NM_Holidays'          => 'includes/class-nm-holidays.php',
			'NM_Settings'          => 'includes/class-nm-settings.php',
			'NM_Availability'      => 'includes/class-nm-availability.php',
			'NM_Booking'           => 'includes/class-nm-booking.php',
			'NM_Specialist'        => 'includes/class-nm-specialist.php',
			'NM_Questions'         => 'includes/class-nm-questions.php',
			'NM_Ajax'              => 'includes/class-nm-ajax.php',
			'NM_Assets'            => 'includes/class-nm-assets.php',
			'NM_Shortcodes'        => 'includes/frontend/class-nm-shortcodes.php',
			'NM_Frontend'          => 'includes/frontend/class-nm-frontend.php',
			'NM_Admin'             => 'includes/admin/class-nm-admin.php',
			'NM_Admin_Bookings'    => 'includes/admin/class-nm-admin-bookings.php',
			'NM_Admin_Settings'    => 'includes/admin/class-nm-admin-settings.php',
			'NM_Admin_Specialists' => 'includes/admin/class-nm-admin-specialists.php',
			'NM_Admin_Questions'   => 'includes/admin/class-nm-admin-questions.php',
			'NM_Admin_Export'      => 'includes/admin/class-nm-admin-export.php',
			'NM_WooCommerce'       => 'includes/integrations/class-nm-woocommerce.php',
			'NM_Zibal'             => 'includes/integrations/class-nm-zibal.php',
			'NM_Hesabdar'          => 'includes/integrations/class-nm-hesabdar.php',
			'NM_Google_Calendar'   => 'includes/integrations/class-nm-google-calendar.php',
			'NM_SMS'               => 'includes/integrations/class-nm-sms.php',
			'NM_Notifications'     => 'includes/integrations/class-nm-notifications.php',
			'NM_Invoice'           => 'includes/integrations/class-nm-invoice.php',
			'NM_Pro'               => 'includes/pro/class-nm-pro.php',
			'NM_Tickets'           => 'includes/pro/class-nm-tickets.php',
			'NM_Subscriptions'     => 'includes/pro/class-nm-subscriptions.php',
			'NM_Installments'      => 'includes/pro/class-nm-installments.php',
			'NM_Templates'         => 'includes/pro/class-nm-templates.php',
			'NM_Business'          => 'includes/pro/class-nm-business.php',
			'NM_Pricing'           => 'includes/pro/class-nm-pricing.php',
		);

		if ( empty( $map[ $class ] ) ) {
			return;
		}

		$file = NM_PATH . $map[ $class ];
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
