<?php
defined( 'ABSPATH' ) || exit;

/**
 * قیمت‌گذاری بر اساس نقش
 */
class WRPM_Pricing {

	const SETTINGS_OPTION = 'wrpm_settings';
	const META_KEY        = '_wrpm_role_prices';

	/** @var self|null */
	private static $instance = null;

	/** @var bool جلوگیری از حلقهٔ بازگشتی فیلتر قیمت */
	private $filtering = false;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// قیمت محصول ساده و متغیر
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'filter_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'filter_sale_price' ), 99, 2 );

		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'filter_regular_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'filter_sale_price' ), 99, 2 );

		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price_hash' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_regular_price', array( $this, 'filter_variation_regular_hash' ), 99, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( $this, 'filter_variation_sale_hash' ), 99, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_prices_hash' ), 99, 1 );

		// نمایش قیمت HTML (مخفی‌سازی)
		add_filter( 'woocommerce_get_price_html', array( $this, 'filter_price_html' ), 99, 2 );

		// فیلدهای متا در ویرایش محصول
		add_action( 'woocommerce_product_options_pricing', array( $this, 'render_product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );

		// ورییشن
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
	}

	/**
	 * @return array
	 */
	public static function settings() {
		$defaults = array(
			'global_discounts'   => array(),
			'hide_price_roles'   => array(),
			'hide_price_guests'  => 0,
			'hide_price_message' => 'برای مشاهده قیمت وارد شوید.',
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( $defaults, $saved );
	}

	/**
	 * @param array $settings
	 * @return array
	 */
	public static function save_settings( array $settings ) {
		$clean = array(
			'global_discounts'   => array(),
			'hide_price_roles'   => array(),
			'hide_price_guests'  => ! empty( $settings['hide_price_guests'] ) ? 1 : 0,
			'hide_price_message' => sanitize_text_field( $settings['hide_price_message'] ?? 'برای مشاهده قیمت وارد شوید.' ),
		);

		if ( ! empty( $settings['global_discounts'] ) && is_array( $settings['global_discounts'] ) ) {
			foreach ( $settings['global_discounts'] as $role => $pct ) {
				$role = sanitize_key( $role );
				$pct  = floatval( $pct );
				if ( $role && $pct > 0 && $pct <= 100 ) {
					$clean['global_discounts'][ $role ] = $pct;
				}
			}
		}

		if ( ! empty( $settings['hide_price_roles'] ) && is_array( $settings['hide_price_roles'] ) ) {
			$clean['hide_price_roles'] = array_values( array_unique( array_map( 'sanitize_key', $settings['hide_price_roles'] ) ) );
		}

		update_option( self::SETTINGS_OPTION, $clean, false );
		return $clean;
	}

	/**
	 * نقش فعلی کاربر برای قیمت‌گذاری
	 *
	 * @return string|null
	 */
	public static function current_role() {
		if ( ! is_user_logged_in() ) {
			return null;
		}
		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return null;
		}
		// اولین نقش غیرمدیر (اگر همه مدیر بودند، همان)
		foreach ( (array) $user->roles as $role ) {
			if ( 'administrator' !== $role ) {
				return $role;
			}
		}
		return $user->roles[0];
	}

	/**
	 * قیمت‌های نقش ذخیره‌شده روی محصول
	 *
	 * @param int $product_id
	 * @return array<string,array{regular:string,sale:string}>
	 */
	public static function get_product_role_prices( $product_id ) {
		$raw = get_post_meta( (int) $product_id, self::META_KEY, true );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param int   $product_id
	 * @param array $prices
	 */
	public static function save_product_role_prices( $product_id, array $prices ) {
		$clean = array();
		foreach ( $prices as $role => $row ) {
			$role = sanitize_key( $role );
			if ( ! $role ) {
				continue;
			}
			$regular = isset( $row['regular'] ) ? wc_format_decimal( $row['regular'] ) : '';
			$sale    = isset( $row['sale'] ) ? wc_format_decimal( $row['sale'] ) : '';
			if ( '' === $regular && '' === $sale ) {
				continue;
			}
			$clean[ $role ] = array(
				'regular' => $regular,
				'sale'    => $sale,
			);
		}
		if ( empty( $clean ) ) {
			delete_post_meta( (int) $product_id, self::META_KEY );
		} else {
			update_post_meta( (int) $product_id, self::META_KEY, $clean );
		}
	}

	/**
	 * آیا قیمت باید مخفی شود؟
	 *
	 * @return bool
	 */
	public function should_hide_price() {
		if ( ! WC_Role_Price_Manager::licensed() ) {
			return false;
		}
		$settings = self::settings();
		if ( ! is_user_logged_in() ) {
			return ! empty( $settings['hide_price_guests'] );
		}
		$role = self::current_role();
		return $role && in_array( $role, (array) $settings['hide_price_roles'], true );
	}

	/**
	 * محاسبه قیمت نهایی برای نقش فعلی
	 *
	 * @param WC_Product $product
	 * @param string     $which price|regular|sale
	 * @return string|null null = بدون تغییر
	 */
	private function resolve_role_price( $product, $which = 'price' ) {
		if ( ! WC_Role_Price_Manager::licensed() || $this->filtering ) {
			return null;
		}
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$role = self::current_role();
		if ( ! $role || 'administrator' === $role ) {
			return null;
		}

		$product_id = $product->get_id();
		// برای ورییشن، اول متای خود ورییشن را بخوان
		$role_prices = self::get_product_role_prices( $product_id );

		$regular = null;
		$sale    = null;

		if ( isset( $role_prices[ $role ] ) ) {
			$row = $role_prices[ $role ];
			if ( isset( $row['regular'] ) && '' !== $row['regular'] ) {
				$regular = (string) $row['regular'];
			}
			if ( isset( $row['sale'] ) && '' !== $row['sale'] ) {
				$sale = (string) $row['sale'];
			}
		}

		// اگر قیمت نقش نبود، تخفیف درصدی سراسری
		if ( null === $regular && null === $sale ) {
			$settings  = self::settings();
			$discounts = $settings['global_discounts'] ?? array();
			if ( empty( $discounts[ $role ] ) ) {
				return null;
			}
			$pct = floatval( $discounts[ $role ] );
			if ( $pct <= 0 ) {
				return null;
			}

			$this->filtering = true;
			$base_regular    = $product->get_regular_price( 'edit' );
			$base_sale       = $product->get_sale_price( 'edit' );
			$base_price      = $product->get_price( 'edit' );
			$this->filtering = false;

			$source = ( '' !== (string) $base_sale ) ? $base_sale : ( ( '' !== (string) $base_regular ) ? $base_regular : $base_price );
			if ( '' === (string) $source || null === $source ) {
				return null;
			}
			$discounted = (float) $source * ( 1 - ( $pct / 100 ) );
			$discounted = wc_format_decimal( $discounted );

			if ( 'regular' === $which ) {
				return (string) ( '' !== (string) $base_regular ? $base_regular : $source );
			}
			if ( 'sale' === $which ) {
				return (string) $discounted;
			}
			return (string) $discounted;
		}

		if ( 'regular' === $which ) {
			return null !== $regular ? $regular : null;
		}
		if ( 'sale' === $which ) {
			return null !== $sale ? $sale : ( null !== $regular ? '' : null );
		}

		// which = price
		if ( null !== $sale && '' !== $sale ) {
			return $sale;
		}
		if ( null !== $regular && '' !== $regular ) {
			return $regular;
		}
		return null;
	}

	public function filter_price( $price, $product ) {
		$resolved = $this->resolve_role_price( $product, 'price' );
		return null !== $resolved ? $resolved : $price;
	}

	public function filter_regular_price( $price, $product ) {
		$resolved = $this->resolve_role_price( $product, 'regular' );
		return null !== $resolved ? $resolved : $price;
	}

	public function filter_sale_price( $price, $product ) {
		$resolved = $this->resolve_role_price( $product, 'sale' );
		return null !== $resolved ? $resolved : $price;
	}

	public function filter_variation_price_hash( $price, $variation, $product ) {
		return $this->filter_price( $price, $variation );
	}

	public function filter_variation_regular_hash( $price, $variation, $product ) {
		return $this->filter_regular_price( $price, $variation );
	}

	public function filter_variation_sale_hash( $price, $variation, $product ) {
		return $this->filter_sale_price( $price, $variation );
	}

	public function variation_prices_hash( $hash ) {
		$role = self::current_role();
		$hash[] = 'wrpm_' . ( $role ? $role : 'guest' );
		return $hash;
	}

	public function filter_price_html( $html, $product ) {
		if ( ! $this->should_hide_price() ) {
			return $html;
		}
		$settings = self::settings();
		$msg      = $settings['hide_price_message'] ?: 'برای مشاهده قیمت وارد شوید.';
		return '<span class="wrpm-hidden-price">' . esc_html( $msg ) . '</span>';
	}

	public function render_product_fields() {
		global $post;
		if ( ! $post ) {
			return;
		}
		$roles  = WRPM_Roles::priceable_roles();
		$prices = self::get_product_role_prices( $post->ID );
		echo '<div class="options_group wrpm-role-prices">';
		echo '<p class="form-field"><strong>' . esc_html__( 'قیمت بر اساس نقش (نقش‌قیمت)', 'wc-role-price-manager' ) . '</strong></p>';
		foreach ( $roles as $slug => $label ) {
			$regular = $prices[ $slug ]['regular'] ?? '';
			$sale    = $prices[ $slug ]['sale'] ?? '';
			woocommerce_wp_text_input(
				array(
					'id'                => 'wrpm_regular_' . $slug,
					'name'              => 'wrpm_role_prices[' . $slug . '][regular]',
					'label'             => sprintf( /* translators: %s role name */ __( 'قیمت عادی — %s', 'wc-role-price-manager' ), $label ),
					'value'             => $regular,
					'data_type'         => 'price',
					'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'                => 'wrpm_sale_' . $slug,
					'name'              => 'wrpm_role_prices[' . $slug . '][sale]',
					'label'             => sprintf( /* translators: %s role name */ __( 'قیمت ویژه — %s', 'wc-role-price-manager' ), $label ),
					'value'             => $sale,
					'data_type'         => 'price',
					'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
				)
			);
		}
		echo '</div>';
	}

	public function save_product_fields( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['wrpm_role_prices'] ) ? (array) wp_unslash( $_POST['wrpm_role_prices'] ) : array(); // phpcs:ignore
		self::save_product_role_prices( $post_id, $raw );
	}

	/**
	 * @param int     $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) {
		$roles  = WRPM_Roles::priceable_roles();
		$prices = self::get_product_role_prices( $variation->ID );
		echo '<div class="wrpm-variation-prices" style="width:100%;clear:both;padding:8px 0">';
		echo '<p><strong>' . esc_html__( 'قیمت نقش‌ها', 'wc-role-price-manager' ) . '</strong></p>';
		foreach ( $roles as $slug => $label ) {
			$regular = $prices[ $slug ]['regular'] ?? '';
			$sale    = $prices[ $slug ]['sale'] ?? '';
			?>
			<p class="form-row form-row-first">
				<label><?php echo esc_html( sprintf( __( 'عادی — %s', 'wc-role-price-manager' ), $label ) ); ?></label>
				<input type="text" class="short wc_input_price"
					name="wrpm_var_role_prices[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $slug ); ?>][regular]"
					value="<?php echo esc_attr( $regular ); ?>" placeholder="" />
			</p>
			<p class="form-row form-row-last">
				<label><?php echo esc_html( sprintf( __( 'ویژه — %s', 'wc-role-price-manager' ), $label ) ); ?></label>
				<input type="text" class="short wc_input_price"
					name="wrpm_var_role_prices[<?php echo esc_attr( $loop ); ?>][<?php echo esc_attr( $slug ); ?>][sale]"
					value="<?php echo esc_attr( $sale ); ?>" placeholder="" />
			</p>
			<?php
		}
		echo '</div>';
	}

	public function save_variation_fields( $variation_id, $loop ) {
		$raw = isset( $_POST['wrpm_var_role_prices'][ $loop ] ) ? (array) wp_unslash( $_POST['wrpm_var_role_prices'][ $loop ] ) : array(); // phpcs:ignore
		self::save_product_role_prices( $variation_id, $raw );
	}
}
