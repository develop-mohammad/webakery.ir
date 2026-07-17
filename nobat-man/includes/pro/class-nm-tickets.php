<?php
defined( 'ABSPATH' ) || exit;

class NM_Tickets {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'nobat_man_ticket', array( __CLASS__, 'shortcode' ) );
	}

	public static function create( array $data ) {
		if ( ! NM_Pro::is_active() ) return NM_Pro::require_pro();
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( $wpdb->prefix . 'nm_tickets', array(
			'booking_id'     => (int) ( $data['booking_id'] ?? 0 ) ?: null,
			'specialist_id'  => (int) ( $data['specialist_id'] ?? 0 ) ?: null,
			'customer_name'  => sanitize_text_field( $data['customer_name'] ?? '' ),
			'customer_email' => sanitize_email( $data['customer_email'] ?? '' ),
			'customer_phone' => sanitize_text_field( $data['customer_phone'] ?? '' ),
			'subject'        => sanitize_text_field( $data['subject'] ?? '' ),
			'status'         => 'open',
			'created_at'     => $now,
			'updated_at'     => $now,
		) );
		$tid = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'nm_ticket_replies', array(
			'ticket_id'   => $tid,
			'sender_type' => 'customer',
			'sender_id'   => null,
			'message'     => sanitize_textarea_field( $data['message'] ?? '' ),
			'created_at'  => $now,
		) );
		return $tid;
	}

	public static function shortcode() {
		if ( ! NM_Pro::is_active() ) {
			return '<div class="nm-card">تیکت در نسخه پرو فعال است.</div>';
		}
		ob_start();
		?>
		<form method="post" class="nm-card" style="padding:20px">
			<?php wp_nonce_field( 'nm_ticket' ); ?>
			<input type="hidden" name="nm_ticket_submit" value="1" />
			<p><input type="text" name="customer_name" placeholder="نام" required style="width:100%" /></p>
			<p><input type="tel" name="customer_phone" placeholder="تلفن" required style="width:100%" /></p>
			<p><input type="text" name="subject" placeholder="موضوع" required style="width:100%" /></p>
			<p><textarea name="message" placeholder="پیام" required style="width:100%" rows="5"></textarea></p>
			<button class="nm-btn nm-btn-primary">ارسال تیکت</button>
		</form>
		<?php
		if ( ! empty( $_POST['nm_ticket_submit'] ) && check_admin_referer( 'nm_ticket' ) ) {
			$id = self::create( wp_unslash( $_POST ) );
			if ( ! is_wp_error( $id ) ) {
				echo '<p style="color:green">تیکت ثبت شد. #' . (int) $id . '</p>';
			}
		}
		return ob_get_clean();
	}
}
