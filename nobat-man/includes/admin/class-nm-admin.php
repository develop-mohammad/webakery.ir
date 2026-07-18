<?php
defined( 'ABSPATH' ) || exit;

class NM_Admin {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'handle_posts' ) );
	}

	public function menu() {
		add_menu_page(
			'نوبت من',
			'نوبت من',
			'manage_options',
			'nobat-man',
			array( $this, 'render' ),
			'dashicons-calendar-alt',
			56
		);
	}

	public function handle_posts() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// اصلاح خودکار تنظیمات برعکس روزهای هفته (یک‌بار)
		if ( isset( $_GET['page'] ) && 'nobat-man' === $_GET['page'] ) {
			if ( NM_Settings::heal_inverted_weekdays() ) {
				add_settings_error(
					'nm',
					'weekdays_healed',
					'تنظیمات روزهای هفته اصلاح شد: قبلاً روزهای کاری اشتباهاً به‌عنوان روز بسته ذخیره شده بود. الان شنبه تا پنج‌شنبه باز است.',
					'updated'
				);
			}
		}

		if ( ! empty( $_POST['nm_save_settings'] ) && check_admin_referer( 'nm_settings' ) ) {
			$raw = isset( $_POST['settings'] ) ? (array) wp_unslash( $_POST['settings'] ) : array();
			$data = array();
			foreach ( $raw as $k => $v ) {
				$key = sanitize_key( $k );
				if ( 'thank_you_text' === $key ) {
					$data[ $key ] = wp_kses_post( $v );
				} elseif ( is_array( $v ) ) {
					$data[ $key ] = array_map( 'sanitize_text_field', $v );
				} else {
					$data[ $key ] = sanitize_text_field( $v );
				}
			}
			// UI جدید: روزهای کاری — به closed_weekdays تبدیل می‌شود
			if ( isset( $raw['working_weekdays'] ) && is_array( $raw['working_weekdays'] ) ) {
				$working = array_map( 'intval', $raw['working_weekdays'] );
				$data['closed_weekdays'] = array_values( array_diff( range( 0, 6 ), $working ) );
			} elseif ( isset( $raw['closed_weekdays'] ) && is_array( $raw['closed_weekdays'] ) ) {
				$data['closed_weekdays'] = NM_Settings::normalize_closed_weekdays( $raw['closed_weekdays'] );
			} else {
				$data['closed_weekdays'] = array( 6 );
			}
			if ( isset( $raw['active_months'] ) && is_array( $raw['active_months'] ) ) {
				$data['active_months'] = array_values( array_unique( array_map( 'intval', $raw['active_months'] ) ) );
			} else {
				$data['active_months'] = array();
			}
			if ( isset( $raw['booking_from'] ) ) { $data['booking_from'] = sanitize_text_field( $raw['booking_from'] ); }
			if ( isset( $raw['booking_until'] ) ) { $data['booking_until'] = sanitize_text_field( $raw['booking_until'] ); }
			if ( isset( $raw['booking_months_ahead'] ) ) { $data['booking_months_ahead'] = max( 1, min( 24, (int) $raw['booking_months_ahead'] ) ); }
			if ( isset( $raw['payment_gateway'] ) ) {
				$gw = sanitize_key( $raw['payment_gateway'] );
				$data['payment_gateway'] = in_array( $gw, array( 'zarinpal', 'zibal', 'woocommerce', 'auto' ), true ) ? $gw : 'auto';
			}
			if ( isset( $raw['zibal_merchant'] ) ) {
				$data['zibal_merchant'] = sanitize_text_field( $raw['zibal_merchant'] );
			}
			if ( isset( $raw['zarinpal_merchant'] ) ) {
				$data['zarinpal_merchant'] = sanitize_text_field( $raw['zarinpal_merchant'] );
			}
			if ( class_exists( 'NM_Payments' ) ) {
				if ( isset( $data['zarinpal_merchant'] ) ) {
					$data['zarinpal_merchant'] = NM_Payments::normalize_zarinpal_merchant( $data['zarinpal_merchant'] );
				}
				// اگر مرچنت UUID در فیلد زیبال بود، به زرین‌پال منتقل کن
				$zibal_in = $data['zibal_merchant'] ?? null;
				$zarin_in = $data['zarinpal_merchant'] ?? null;
				if ( null !== $zibal_in && NM_Payments::looks_like_zarinpal_merchant( $zibal_in ) ) {
					if ( null === $zarin_in || '' === trim( (string) $zarin_in ) ) {
						$data['zarinpal_merchant'] = NM_Payments::normalize_zarinpal_merchant( $zibal_in );
					}
					$data['zibal_merchant'] = '';
					if ( ( $data['payment_gateway'] ?? '' ) === 'zibal' ) {
						$data['payment_gateway'] = 'zarinpal';
					}
				}
			}
			foreach ( array( 'default_price','default_duration','min_duration','max_duration','buffer_minutes','slot_step','block_holidays','enable_voice','enable_photo','max_upload_mb','require_email','require_city','require_gender','pending_ttl_hours','notify_email','notify_sms','enable_installments','installment_count','wc_product_id' ) as $n ) {
				if ( isset( $data[ $n ] ) ) $data[ $n ] = (int) $data[ $n ];
			}
			// checkboxes missing = 0
			foreach ( array( 'block_holidays','enable_voice','enable_photo','require_email','require_city','require_gender','notify_email','notify_sms','enable_installments' ) as $cb ) {
				if ( ! isset( $raw[ $cb ] ) ) $data[ $cb ] = 0;
			}
			NM_Settings::update( $data );
			add_settings_error( 'nm', 'saved', 'تنظیمات ذخیره شد.', 'updated' );
		}

		if ( ! empty( $_POST['nm_save_specialist'] ) && check_admin_referer( 'nm_specialist' ) ) {
			$id = (int) ( $_POST['id'] ?? 0 );
			$res = NM_Specialist::save( wp_unslash( $_POST ), $id );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'nm', 'sp', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'nm', 'sp', 'متخصص ذخیره شد.', 'updated' );
			}
		}

		if ( ! empty( $_GET['nm_delete_specialist'] ) && check_admin_referer( 'nm_del_sp_' . (int) $_GET['nm_delete_specialist'] ) ) {
			NM_Specialist::delete( (int) $_GET['nm_delete_specialist'] );
			add_settings_error( 'nm', 'spdel', 'حذف شد.', 'updated' );
		}

		if ( ! empty( $_POST['nm_save_question'] ) && check_admin_referer( 'nm_question' ) ) {
			$id = (int) ( $_POST['id'] ?? 0 );
			$opts = array_filter( array_map( 'trim', explode( "\n", (string) wp_unslash( $_POST['options_text'] ?? '' ) ) ) );
			$res = NM_Questions::save( array(
				'category'    => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
				'question'    => sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) ),
				'type'        => sanitize_key( $_POST['type'] ?? 'text' ),
				'options'     => $opts,
				'is_required' => ! empty( $_POST['is_required'] ),
				'sort_order'  => (int) ( $_POST['sort_order'] ?? 0 ),
				'is_active'   => 1,
			), $id );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'nm', 'q', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'nm', 'q', 'سوال ذخیره شد.', 'updated' );
			}
		}

		if ( ! empty( $_GET['nm_delete_question'] ) && check_admin_referer( 'nm_del_q_' . (int) $_GET['nm_delete_question'] ) ) {
			NM_Questions::delete( (int) $_GET['nm_delete_question'] );
		}

		if ( ! empty( $_POST['nm_save_schedule'] ) && check_admin_referer( 'nm_schedule' ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'nm_schedules';
			$sp = (int) ( $_POST['specialist_id'] ?? 0 );
			$wpdb->delete( $table, array( 'specialist_id' => $sp ) );
			$days = isset( $_POST['day'] ) ? (array) $_POST['day'] : array();
			foreach ( $days as $wd => $row ) {
				if ( empty( $row['active'] ) ) continue;
				$wpdb->insert( $table, array(
					'specialist_id' => $sp,
					'weekday'       => (int) $wd,
					'start_time'    => sanitize_text_field( $row['start'] ) . ':00',
					'end_time'      => sanitize_text_field( $row['end'] ) . ':00',
					'is_active'     => 1,
				) );
			}
			add_settings_error( 'nm', 'sch', 'برنامه کاری ذخیره شد.', 'updated' );
		}

		if ( ! empty( $_POST['nm_apply_template'] ) && check_admin_referer( 'nm_template' ) ) {
			$res = NM_Templates::apply( sanitize_key( $_POST['template'] ?? '' ) );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'nm', 'tpl', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'nm', 'tpl', 'قالب اعمال شد.', 'updated' );
			}
		}

		if ( ! empty( $_POST['nm_save_business'] ) && check_admin_referer( 'nm_business' ) ) {
			$res = NM_Business::save( wp_unslash( $_POST ), (int) ( $_POST['id'] ?? 0 ) );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'nm', 'biz', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'nm', 'biz', 'بیزینس ذخیره شد.', 'updated' );
			}
		}

		if ( ! empty( $_POST['nm_save_pricing'] ) && check_admin_referer( 'nm_pricing' ) ) {
			$res = NM_Pricing::save_rule( wp_unslash( $_POST ) );
			if ( is_wp_error( $res ) ) {
				add_settings_error( 'nm', 'pr', $res->get_error_message(), 'error' );
			} else {
				add_settings_error( 'nm', 'pr', 'قانون قیمت ذخیره شد.', 'updated' );
			}
		}
	}

	public function render() {
		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );
		$tabs = array(
			'dashboard'    => 'داشبورد',
			'bookings'     => 'رزروها',
			'specialists'  => 'متخصصین',
			'schedule'     => 'ساعات کاری',
			'questions'    => 'سوالات',
			'settings'     => 'تنظیمات',
			'integrations' => 'اتصال‌ها',
			'pro'          => 'نسخه پرو',
			'export'       => 'خروجی',
			'license'      => 'لایسنس',
		);
		include NM_PATH . 'includes/admin/views/layout.php';
	}
}
