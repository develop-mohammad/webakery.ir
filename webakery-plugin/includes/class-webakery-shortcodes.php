<?php
/**
 * Front-end shortcodes.
 *
 * @package Webakery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers plugin shortcodes.
 */
class Webakery_Shortcodes {

	/**
	 * Hook registrations.
	 */
	public static function init() {
		add_shortcode( 'webakery_products', array( __CLASS__, 'products' ) );
		add_shortcode( 'webakery_hours', array( __CLASS__, 'hours' ) );
		add_shortcode( 'webakery_order', array( __CLASS__, 'order_form' ) );
		add_shortcode( 'webakery_info', array( __CLASS__, 'store_info' ) );
	}

	/**
	 * Ensure assets load when shortcodes render.
	 */
	private static function enqueue() {
		wp_enqueue_style( 'webakery' );
		wp_enqueue_script( 'webakery' );
	}

	/**
	 * Product grid shortcode.
	 *
	 * Usage: [webakery_products limit="8" category="bread" featured="1"]
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
			'webakery_products'
		);

		$args = array(
			'post_type'      => 'wbk_product',
			'posts_per_page' => absint( $atts['limit'] ),
			'post_status'    => 'publish',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		);

		if ( ! empty( $atts['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'wbk_product_cat',
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['category'] ),
				),
			);
		}

		if ( '1' === (string) $atts['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_wbk_featured',
					'value' => '1',
				),
			);
		}

		$query   = new WP_Query( $args );
		$columns = max( 1, min( 4, absint( $atts['columns'] ) ) );

		ob_start();
		?>
		<div class="wbk-products wbk-cols-<?php echo esc_attr( $columns ); ?>" dir="rtl">
			<?php if ( $query->have_posts() ) : ?>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$meta      = Webakery_Meta::get_product_meta( get_the_ID() );
					$available = '0' !== (string) $meta['available'];
					?>
					<article class="wbk-product<?php echo $available ? '' : ' is-unavailable'; ?>">
						<div class="wbk-product__media">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium_large' ); ?>
							<?php else : ?>
								<div class="wbk-product__placeholder" aria-hidden="true"></div>
							<?php endif; ?>
							<?php if ( '1' === (string) $meta['featured'] ) : ?>
								<span class="wbk-product__badge"><?php esc_html_e( 'ویژه', 'webakery' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="wbk-product__body">
							<h3 class="wbk-product__title"><?php the_title(); ?></h3>
							<?php if ( has_excerpt() ) : ?>
								<p class="wbk-product__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php endif; ?>
							<div class="wbk-product__meta">
								<span class="wbk-product__price"><?php echo esc_html( Webakery_Meta::format_price( $meta['price'] ) ); ?></span>
								<?php if ( ! empty( $meta['unit'] ) ) : ?>
									<span class="wbk-product__unit">/ <?php echo esc_html( $meta['unit'] ); ?></span>
								<?php endif; ?>
							</div>
							<?php if ( ! empty( $meta['prep_time'] ) ) : ?>
								<p class="wbk-product__prep"><?php echo esc_html( $meta['prep_time'] ); ?></p>
							<?php endif; ?>
							<p class="wbk-product__status">
								<?php echo $available ? esc_html__( 'موجود', 'webakery' ) : esc_html__( 'ناموجود', 'webakery' ); ?>
							</p>
						</div>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="wbk-empty"><?php esc_html_e( 'هنوز محصولی ثبت نشده است.', 'webakery' ); ?></p>
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
		$settings = Webakery_Settings::get();

		ob_start();
		?>
		<div class="wbk-hours" dir="rtl">
			<h3 class="wbk-hours__title"><?php esc_html_e( 'ساعات کاری', 'webakery' ); ?></h3>
			<ul class="wbk-hours__list">
				<li>
					<span><?php esc_html_e( 'شنبه تا پنج‌شنبه', 'webakery' ); ?></span>
					<strong><?php echo esc_html( $settings['hours_weekday'] ); ?></strong>
				</li>
				<li>
					<span><?php esc_html_e( 'جمعه', 'webakery' ); ?></span>
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
		$settings = Webakery_Settings::get();

		ob_start();
		?>
		<div class="wbk-info" dir="rtl">
			<p class="wbk-info__brand"><?php echo esc_html( $settings['store_name'] ); ?></p>
			<?php if ( ! empty( $settings['intro'] ) ) : ?>
				<p class="wbk-info__intro"><?php echo esc_html( $settings['intro'] ); ?></p>
			<?php endif; ?>
			<ul class="wbk-info__list">
				<?php if ( ! empty( $settings['phone'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'تلفن', 'webakery' ); ?></span>
						<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $settings['phone'] ) ); ?>"><?php echo esc_html( $settings['phone'] ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $settings['whatsapp'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'واتساپ', 'webakery' ); ?></span>
						<a href="https://wa.me/<?php echo esc_attr( ltrim( $settings['whatsapp'], '+' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $settings['whatsapp'] ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( ! empty( $settings['address'] ) ) : ?>
					<li>
						<span><?php esc_html_e( 'آدرس', 'webakery' ); ?></span>
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
			'webakery_order'
		);

		$products = get_posts(
			array(
				'post_type'      => 'wbk_product',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		ob_start();
		?>
		<form class="wbk-order" dir="rtl" novalidate>
			<h3 class="wbk-order__title"><?php esc_html_e( 'ثبت سفارش', 'webakery' ); ?></h3>
			<p class="wbk-order__hint"><?php esc_html_e( 'سفارش خود را بفرستید؛ تماس می‌گیریم.', 'webakery' ); ?></p>

			<label class="wbk-field">
				<span><?php esc_html_e( 'نام', 'webakery' ); ?></span>
				<input type="text" name="name" required autocomplete="name" />
			</label>

			<label class="wbk-field">
				<span><?php esc_html_e( 'شماره تماس', 'webakery' ); ?></span>
				<input type="tel" name="phone" required autocomplete="tel" dir="ltr" />
			</label>

			<label class="wbk-field">
				<span><?php esc_html_e( 'محصول', 'webakery' ); ?></span>
				<select name="product">
					<option value=""><?php esc_html_e( 'انتخاب کنید', 'webakery' ); ?></option>
					<?php foreach ( $products as $product ) : ?>
						<option value="<?php echo esc_attr( $product->post_title ); ?>" <?php selected( $atts['product'], $product->post_title ); ?>>
							<?php echo esc_html( $product->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label class="wbk-field">
				<span><?php esc_html_e( 'تعداد', 'webakery' ); ?></span>
				<input type="number" name="qty" min="1" value="1" />
			</label>

			<label class="wbk-field">
				<span><?php esc_html_e( 'توضیحات', 'webakery' ); ?></span>
				<textarea name="message" rows="3" placeholder="<?php esc_attr_e( 'ساعت تحویل، توضیح بیشتر…', 'webakery' ); ?>"></textarea>
			</label>

			<p class="wbk-order__draft-note" hidden></p>

			<div class="wbk-order__actions">
				<button type="submit" class="wbk-order__submit"><?php esc_html_e( 'ارسال سفارش', 'webakery' ); ?></button>
				<button type="button" class="wbk-order__save-local"><?php esc_html_e( 'ذخیره روی این لپ‌تاپ', 'webakery' ); ?></button>
				<button type="button" class="wbk-order__download-local"><?php esc_html_e( 'دانلود فایل روی لپ‌تاپ', 'webakery' ); ?></button>
				<button type="button" class="wbk-order__clear-local"><?php esc_html_e( 'پاک کردن پیش‌نویس', 'webakery' ); ?></button>
			</div>

			<p class="wbk-order__local-hint"><?php esc_html_e( 'پیش‌نویس به‌صورت خودکار روی همین دستگاه ذخیره می‌شود تا اگر صفحه بسته شد، اطلاعات از بین نرود.', 'webakery' ); ?></p>
			<p class="wbk-order__feedback" aria-live="polite" hidden></p>
		</form>
		<?php
		return ob_get_clean();
	}
}
