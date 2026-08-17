<?php
defined( 'ABSPATH' ) || exit;

/**
 * بخش فرانت: نشان کوچک تخفیف روی محصول (لینک به صفحه تخفیف)، شورت‌کد فهرست
 * صفحه‌های تخفیف و ویجت المنتور.
 */
class WDP_Frontend {

	const SHORTCODE = 'webakery_discount_pages';

	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
		add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'loop_badge' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'single_badge' ), 6 );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor' ) );
	}

	public static function assets() {
		wp_enqueue_style( 'wdp-front', WDP_URL . 'assets/front.css', array(), WDP_VERSION );
	}

	protected static function badge_html( $product_id ) {
		$terms = get_the_terms( $product_id, WDP_Taxonomy::TAXONOMY );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$term = $terms[0];
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return '';
		}

		$percent = get_post_meta( $product_id, WDP_Assigner::META_PERCENT, true );
		$label   = '' !== $percent
			? WDP_Util::fa_digits( WDP_Util::trim_zeros( $percent ) ) . '٪ تخفیف'
			: $term->name;

		self::assets();
		return '<a href="' . esc_url( $link ) . '" class="wdp-badge">🏷️ ' . esc_html( $label ) . '</a>';
	}

	public static function loop_badge() {
		global $product;
		if ( $product instanceof WC_Product ) {
			echo self::badge_html( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function single_badge() {
		global $product;
		if ( $product instanceof WC_Product ) {
			echo self::badge_html( $product->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'columns'    => 3,
				'show_empty' => 0,
			),
			$atts,
			self::SHORTCODE
		);

		$terms = get_terms(
			array(
				'taxonomy'   => WDP_Taxonomy::TAXONOMY,
				'hide_empty' => empty( $atts['show_empty'] ),
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return current_user_can( 'manage_woocommerce' )
				? '<p class="wdp-admin-hint">هنوز صفحه تخفیفی ساخته نشده است.</p>'
				: '';
		}

		self::assets();
		$cols = max( 1, min( 6, (int) $atts['columns'] ) );

		ob_start();
		?>
		<div class="wdp-pages" style="--wdp-cols: <?php echo (int) $cols; ?>">
			<?php
			foreach ( $terms as $term ) :
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				?>
				<a href="<?php echo esc_url( $link ); ?>" class="wdp-page-card">
					<span class="wdp-page-title"><?php echo esc_html( $term->name ); ?></span>
					<span class="wdp-page-range"><?php echo esc_html( WDP_Taxonomy::range_label( $term->term_id ) ); ?></span>
					<span class="wdp-page-count"><?php echo esc_html( WDP_Util::fa_digits( (int) $term->count ) ); ?> محصول</span>
				</a>
				<?php
			endforeach;
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function register_elementor( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}
		require_once WDP_PATH . 'includes/class-wdp-elementor-widget.php';
		$widgets_manager->register( new WDP_Elementor_Widget() );
	}
}
