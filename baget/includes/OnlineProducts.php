<?php
namespace WCCP;

defined( 'ABSPATH' ) || exit;

class OnlineProducts {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_cpt' ) );
		add_shortcode( 'baget_pay', array( $this, 'shortcode_pay' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render_link' ) );

		// هندلر پرداخت لینک مستقیم (لاگین / مهمان)
		add_action( 'admin_post_wccp_pay_link', array( $this, 'handle_pay_link' ) );
		add_action( 'admin_post_nopriv_wccp_pay_link', array( $this, 'handle_pay_link' ) );
		add_action( 'admin_post_wccp_pay_verify', array( $this, 'handle_pay_verify' ) );
		add_action( 'admin_post_nopriv_wccp_pay_verify', array( $this, 'handle_pay_verify' ) );
	}

	public static function register_cpt() {
		register_post_type(
			'wccp_product',
			array(
				'labels'       => array(
					'name'          => 'محصولات آنلاین',
					'singular_name' => 'محصول آنلاین',
					'add_new_item'  => 'افزودن محصول آنلاین',
					'edit_item'     => 'ویرایش محصول آنلاین',
				),
				'public'       => true,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'pay' ),
			)
		);
	}

	/** @return string[] */
	public static function product_active_fields( $product_id ) {
		$active = get_post_meta( $product_id, '_wccp_active_fields', true );
		if ( is_array( $active ) && ! empty( $active ) ) {
			return array_values( array_map( 'strval', $active ) );
		}
		$tpl = Templates::product_template_key( $product_id );
		return Templates::fields_for( $tpl );
	}

	public function shortcode_pay( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts );
		$id   = (int) $atts['id'];
		if ( ! $id ) {
			return '';
		}
		return $this->render_product_form( $id );
	}

	public function maybe_render_link() {
		if ( ! is_singular( 'wccp_product' ) ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post ) {
			return;
		}
		$product_id = (int) $post->ID;
		$tpl_key    = Templates::product_template_key( $product_id );
		$css        = Templates::css_for( $tpl_key );
		$error      = isset( $_GET['wccp_err'] ) ? sanitize_text_field( wp_unslash( $_GET['wccp_err'] ) ) : ''; // phpcs:ignore

		status_header( 200 );
		nocache_headers();
		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html( get_the_title( $post ) ) . '</title>';
		echo '<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</head><body class="wccp-tpl-' . esc_attr( $tpl_key ) . '"><div class="card">';
		echo '<h1>' . esc_html( get_the_title( $post ) ) . '</h1>';
		echo wpautop( wp_kses_post( $post->post_content ) );
		if ( $error ) {
			echo '<div class="wccp-pay-error" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 14px;border-radius:12px;margin:0 0 16px">'
				. esc_html( $error ) . '</div>';
		}
		echo $this->render_product_form( $product_id ); // phpcs:ignore
		echo '</div></body></html>';
		exit;
	}

	private function render_product_form( $product_id ) {
		$defs   = CustomFields::merged_with_defaults();
		$active = self::product_active_fields( $product_id );
		$price  = (int) get_post_meta( $product_id, '_wccp_price', true );
		ob_start();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wccp-pay-form">';
		echo '<input type="hidden" name="action" value="wccp_pay_link" />';
		echo '<input type="hidden" name="product_id" value="' . esc_attr( (string) $product_id ) . '" />';
		wp_nonce_field( 'wccp_pay_' . $product_id );
		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			Fields::render_standalone_field( $key, $defs[ $key ] );
		}
		if ( $price > 0 ) {
			echo '<p class="wccp-price">مبلغ: ' . esc_html( number_format_i18n( $price ) ) . ' تومان</p>';
		} else {
			echo '<p class="wccp-price" style="color:#b91c1c">مبلغ تنظیم نشده — در ویرایش لینک پرداخت قیمت را وارد کنید.</p>';
		}
		echo '<button type="submit" class="wccp-pay-btn"' . ( $price > 0 ? '' : ' disabled' ) . '>ادامه و پرداخت</button></form>';
		return ob_get_clean();
	}

	/** پردازش فرم لینک پرداخت */
	public function handle_pay_link() {
		$product_id = (int) ( $_POST['product_id'] ?? 0 ); // phpcs:ignore
		$back       = $product_id ? get_permalink( $product_id ) : home_url( '/' );

		if ( $product_id <= 0 || get_post_type( $product_id ) !== 'wccp_product' ) {
			Payments::die_error( 'لینک پرداخت نامعتبر است.', $back );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wccp_pay_' . $product_id ) ) { // phpcs:ignore
			Payments::die_error( 'نشست منقضی شده — لطفاً فرم را دوباره ارسال کنید.', $back );
		}

		$price = (int) get_post_meta( $product_id, '_wccp_price', true );
		if ( $price < 1000 ) {
			Payments::die_error( 'مبلغ لینک پرداخت باید حداقل ۱٬۰۰۰ تومان باشد. قیمت را در ویرایش لینک تنظیم کنید.', $back );
		}

		$defs   = CustomFields::merged_with_defaults();
		$active = self::product_active_fields( $product_id );
		$values = array();
		$errors = array();

		foreach ( $active as $key ) {
			if ( empty( $defs[ $key ] ) ) {
				continue;
			}
			$type = $defs[ $key ]['type'] ?? 'text';
			if ( in_array( $type, Fields::display_only_types(), true ) ) {
				continue;
			}

			if ( 'checkboxes' === $type ) {
				$raw = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore
				$val = array_values( array_filter( array_map( 'sanitize_text_field', $raw ) ) );
			} else {
				$val = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
			}

			$required = ! empty( $defs[ $key ]['required'] );
			$empty    = is_array( $val ) ? empty( $val ) : ( '' === trim( (string) $val ) );
			if ( $required && $empty ) {
				$errors[] = sprintf( 'فیلد «%s» الزامی است.', $defs[ $key ]['label'] ?? $key );
				continue;
			}
			$values[ $key ] = $val;
		}

		if ( ! empty( $errors ) ) {
			$url = add_query_arg( 'wccp_err', rawurlencode( implode( ' ', $errors ) ), $back );
			wp_safe_redirect( $url );
			exit;
		}

		$phone = (string) ( $values['billing_phone'] ?? '' );
		$email = (string) ( $values['billing_email'] ?? '' );
		$first = (string) ( $values['billing_first_name'] ?? '' );
		$last  = (string) ( $values['billing_last_name'] ?? '' );

		$pending = array(
			'product_id'  => $product_id,
			'amount'      => $price,
			'description' => get_the_title( $product_id ),
			'fields'      => $values,
			'phone'       => $phone,
			'email'       => $email,
			'first_name'  => $first,
			'last_name'   => $last,
			'created'     => time(),
			'status'      => 'pending',
		);
		$token             = Payments::store_pending( $pending );
		$pending['token']  = $token;
		set_transient( 'wccp_pay_' . $token, $pending, DAY_IN_SECONDS );

		// ۱) زرین‌پال مستقیم
		if ( Payments::zarinpal_enabled() ) {
			$url = Payments::create_zarinpal_url( $pending, $token );
			if ( ! is_wp_error( $url ) ) {
				Payments::redirect_external( $url );
			}
			$zarin_err = $url->get_error_message();
		} else {
			$zarin_err = 'مرچنت‌کد زرین‌پال تنظیم نشده است.';
		}

		// ۲) fallback ووکامرس
		$wc_url = Payments::create_wc_pay_url( $pending );
		if ( ! is_wp_error( $wc_url ) ) {
			Payments::redirect_external( $wc_url );
		}

		Payments::die_error(
			$zarin_err . '<br><br>جایگزین ووکامرس: ' . esc_html( $wc_url->get_error_message() ),
			$back
		);
	}

	/** بازگشت از زرین‌پال */
	public function handle_pay_verify() {
		$authority = isset( $_GET['Authority'] ) ? sanitize_text_field( wp_unslash( $_GET['Authority'] ) ) : ''; // phpcs:ignore
		$status    = isset( $_GET['Status'] ) ? sanitize_text_field( wp_unslash( $_GET['Status'] ) ) : ''; // phpcs:ignore

		if ( ! $authority ) {
			Payments::die_error( 'شناسه پرداخت (Authority) نامعتبر است.' );
		}

		$token   = Payments::token_by_authority( $authority );
		$pending = $token ? Payments::get_pending( $token ) : null;
		if ( ! $pending ) {
			Payments::die_error( 'سفارش مرتبط با این پرداخت یافت نشد یا منقضی شده است.' );
		}

		$back = ! empty( $pending['product_id'] ) ? get_permalink( (int) $pending['product_id'] ) : home_url( '/' );

		if ( 'OK' !== strtoupper( $status ) ) {
			Payments::die_error( 'پرداخت لغو شد یا ناموفق بود.', $back );
		}

		$amount = Payments::amount_rial( $pending['amount'] ?? 0 );
		$verify = Payments::zarinpal_api(
			Payments::verify_url(),
			array(
				'merchant_id' => Payments::merchant(),
				'amount'      => $amount,
				'authority'   => $authority,
			)
		);

		$code = (int) ( $verify['data']['code'] ?? 0 );
		if ( 100 !== $code && 101 !== $code ) {
			$msg = $verify['errors']['message'] ?? (string) $code;
			Payments::die_error( 'تأیید پرداخت ناموفق: ' . esc_html( (string) $msg ), $back );
		}

		$ref_id = (string) ( $verify['data']['ref_id'] ?? '' );
		$pending['status']  = 'paid';
		$pending['ref_id']  = $ref_id;
		$pending['paid_at'] = time();
		set_transient( 'wccp_pay_' . $token, $pending, WEEK_IN_SECONDS );
		Payments::clear_authority( $authority );

		/**
		 * بعد از پرداخت موفق لینک مستقیم.
		 *
		 * @param array  $pending
		 * @param string $ref_id
		 */
		do_action( 'wccp_pay_link_paid', $pending, $ref_id );

		Payments::die_success( $pending, $ref_id );
	}
}
