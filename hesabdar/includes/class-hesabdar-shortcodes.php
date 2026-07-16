<?php
/**
 * Front-end shortcodes.
 *
 * @package Hesabdar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin shortcodes.
 */
class Hesabdar_Shortcodes {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_shortcode( 'hesabdar_products', array( __CLASS__, 'products' ) );
		add_shortcode( 'hesabdar_hours', array( __CLASS__, 'hours' ) );
		add_shortcode( 'hesabdar_order', array( __CLASS__, 'order_form' ) );
		add_shortcode( 'hesabdar_info', array( __CLASS__, 'store_info' ) );
	}

	/**
	 * Ensure assets load when shortcodes render.
	 */
	private static function enqueue() {
		wp_enqueue_style( 'hesabdar' );
		wp_enqueue_script( 'hesabdar' );
	}

	/**
	 * Product grid shortcode.
	 *
	 * Usage: [hesabdar_products limit="8" category="bread" featured="1"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function products( $atts ) {
		self::enqueue();

		$atts = shortcode_atts(
			array(
				'limit'    => 12,
				'category' => '',
				'featured' => '',
				'columns'  => 3,
			),
			$atts,
			'hesabdar_products'
		);

		$args = array(
			'post_type'      => 'hsb_product',
			'posts_per_page' => absint( $atts['limit'] ),
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'hsb_product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		if ( '1' === (string) $atts['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_hsb_featured',
					'value' => '1',
				),
			);
		}

		$query   = new WP_Query( $args );
		$columns = max( 1, min( 4, absint( $atts['columns'] ) ) );

		ob_start();
		?>
		<div class="hsb-products hsb-cols-<?php echo esc_attr( $columns ); ?>" dir="rtl">
			<?php if ( $query->have_posts() ) : ?>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$meta      = Hesabdar_Meta::get_product_meta( get_the_ID() );
					$available = '0' !== (string) $meta['available'];
					?>
					<article class="hsb-product<?php echo $available ? '' : ' is-unavailable'; ?>">
						<div class="hsb-product__media">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large' ); ?>
							<?php else : ?>
								<div class="hsb-product__placeholder" aria-hidden="true"></div>
							<?php endif; ?>
							<?php if ( '1' === (string) $meta['featured'] ) : ?>
								<span class="hsb-product__badge"><?php esc_html_e( 'ویژه', 'hesabdar' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="hsb-product__body">
							<h3 class="hsb-product__title"><?php the_title(); ?></h3>
							<?php if ( has_excerpt() ) : ?>
								<p class="hsb-product__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>
							<div class="hsb-product__meta">
								<span class="hsb-product__price"><?php echo esc_html( Hesabdar_Meta::format_price( $meta['price'] ) ); ?></span>
								<?php if ( ! empty( $meta['unit'] ) ) : ?>
									<span class="hsb-product__unit">/ <?php echo esc_html( $meta['unit'] ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $meta['prep_time'] ) ) : ?>
								<p class="hsb-product__prep"><?php echo esc_html( $meta['prep_time'] ); ?></p>
							<?php endif; ?>
							<p class="hsb-product__status">
								<?php echo $available ? esc_html__( 'موجود', 'hesabdar' ) : esc_html__( 'ناموجود', 'hesabdar' ); ?>
							</p>
						</div>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="hsb-empty"><?php esc_html_e( 'هنوز محصولی ثبت نشده است.', 'hesabdar' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Opening hours shortcode.
	 *
	 * @return string
	 */
	public static function hours() {
		self::enqueue();
		$settings = Hesabdar_Settings::get();

		ob_start();
		?>
		<div class="hsb-hours" dir="rtl">
			<h3 class="hsb-hours__title"><?php esc_html_e( 'ساعات کاری', 'hesabdar' ); ?></h3>
			<ul class="hsb-hours__list">
				<li>
					<span><?php esc_html_e( 'شنبه تا پنج‌شنبه', 'hesabdar' ); ?></span>
					<strong><?php echo esc_html( $settings['hours_weekday'] ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'جمعه', 'hesabdar' ); ?></span>
					<strong><?php echo esc_html( $settings['hours_friday'] ); ?></strong>
				</li>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Store info shortcode.
	 *
	 * @return string
	 */
	public static function store_info() {
		self::enqueue();
		$settings = Hesabdar_Settings::get();

		ob_start();
		?>
		<div class="hsb-info" dir="rtl">
			<p class="hsb-info__brand"><?php echo esc_html( $settings['store_name'] ); ?></p>
			<?php if ( ! empty( $settings['intro'] ) ) : ?>
				<p class="hsb-info__intro"><?php echo esc_html( $settings['intro'] ); ?></p>
			<?php endif; ?>
			<ul class="hsb-info__list">
				<?php if ( ! empty( $settings['phone'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'تلفن', 'hesabdar' ); ?></span>
						<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $settings['phone'] ) ); ?>"><?php echo esc_html( $settings['phone'] ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $settings['whatsapp'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'واتساپ', 'hesabdar' ); ?></span>
						<a href="https://wa.me/<?php echo esc_attr( ltrim( $settings['whatsapp'], '+' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $settings['whatsapp'] ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $settings['address'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'آدرس', 'hesabdar' ); ?></span>
						<strong><?php echo esc_html( $settings['address'] ); ?></strong>
					</li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Order form shortcode.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function order_form( $atts ) {
		self::enqueue();

		$atts = shortcode_atts(
			array(
				'product' => '',
			),
			$atts,
			'hesabdar_order'
		);

		$products = get_posts(
			array(
				'post_type'      => 'hsb_product',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		?>
		<form class="hsb-order" dir="rtl" novalidate>
			<h3 class="hsb-order__title"><?php esc_html_e( 'ثبت سفارش', 'hesabdar' ); ?></h3>
			<p class="hsb-order__hint"><?php esc_html_e( 'سفارش خود را بفرستید؛ تماس می‌گیریم.', 'hesabdar' ); ?></p>

			<label class="hsb-field">
				<span><?php esc_html_e( 'نام', 'hesabdar' ); ?></span>
				<input type="text" name="name" required autocomplete="name" />
			</label>

			<label class="hsb-field">
				<span><?php esc_html_e( 'شماره تماس', 'hesabdar' ); ?></span>
				<input type="tel" name="phone" required autocomplete="tel" dir="ltr" />
			</label>

			<label class="hsb-field">
				<span><?php esc_html_e( 'محصول', 'hesabdar' ); ?></span>
				<select name="product">
					<option value=""><?php esc_html_e( 'انتخاب کنید', 'hesabdar' ); ?></option>
					<?php foreach ( $products as $product ) : ?>
						<option value="<?php echo esc_attr( $product->post_title ); ?>" <?php selected( $atts['product'], $product->post_title ); ?>>
							<?php echo esc_html( $product->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="hsb-field">
				<span><?php esc_html_e( 'تعداد', 'hesabdar' ); ?></span>
				<input type="number" name="qty" min="1" value="1" />
			</label>

			<label class="hsb-field">
				<span><?php esc_html_e( 'توضیحات', 'hesabdar' ); ?></span>
				<textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'ساعت تحویل، توضیح بیشتر…', 'hesabdar' ); ?>"></textarea>
			</label>

			<p class="hsb-order__draft-note" hidden></p>

			<div class="hsb-order__actions">
				<button type="submit" class="hsb-order__submit"><?php esc_html_e( 'ارسال سفارش', 'hesabdar' ); ?></button>
				<button type="button" class="hsb-order__save-local"><?php esc_html_e( 'ذخیره روی این لپ‌تاپ', 'hesabdar' ); ?></button>
				<button type="button" class="hsb-order__download-local"><?php esc_html_e( 'دانلود فایل روی لپ‌تاپ', 'hesabdar' ); ?></button>
				<button type="button" class="hsb-order__clear-local"><?php esc_html_e( 'پاک کردن پیش‌نویس', 'hesabdar' ); ?></button>
			</div>

			<p class="hsb-order__local-hint"><?php esc_html_e( 'پیش‌نویس به‌صورت خودکار روی همین دستگاه ذخیره می‌شود تا اگر صفحه بسته شد، اطلاعات از بین نرود.', 'hesabdar' ); ?></p>
			<p class="hsb-order__feedback" aria-live="polite" hidden></p>
		</form>
		<?php
		return ob_get_clean();
	}
}
