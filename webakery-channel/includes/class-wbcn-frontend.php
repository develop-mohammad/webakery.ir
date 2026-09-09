<?php
defined( 'ABSPATH' ) || exit;

class WBCN_Frontend {

	public static function register() {
		add_shortcode( 'webakery_channel', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'wbcn-front', WBCN_URL . 'assets/front.css', array(), WBCN_VERSION );
	}

	/**
	 * [webakery_channel]
	 *
	 * @param array<string,string>|string $atts
	 */
	public static function shortcode( $atts ) {
		$s = WBCN_Settings::get();
		$user = WBCN_Settings::channel_username( $s );
		if ( $user === '' ) {
			return '';
		}
		wp_enqueue_style( 'wbcn-front' );
		$label = $s['join_label'] ? $s['join_label'] : 'عضویت در کانال';
		$url   = 'https://t.me/' . rawurlencode( $user );
		ob_start();
		?>
		<div class="wbcn-join" dir="rtl">
			<p class="wbcn-join-text">نکته‌های کوتاه و کاربردی مطالب سایت را در تلگرام بگیرید.</p>
			<a class="wbcn-join-btn" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
				<?php echo esc_html( $label ); ?>
			</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
