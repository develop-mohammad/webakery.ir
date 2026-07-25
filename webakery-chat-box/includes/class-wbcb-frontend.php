<?php
defined( 'ABSPATH' ) || exit;

class WBCB_Frontend {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ), 50 );
		add_action( 'wp_footer', array( $this, 'debug_comment' ), 51 );
		add_action( 'admin_bar_menu', array( $this, 'frontend_admin_bar_hint' ), 120 );
	}

	public function assets() {
		if ( ! WBCB_Settings::should_show_widget() ) {
			return;
		}
		wp_enqueue_style( 'wbcb-widget', WBCB_URL . 'assets/css/widget.css', array(), WBCB_VERSION );
		wp_enqueue_script( 'wbcb-widget', WBCB_URL . 'assets/js/widget.js', array(), WBCB_VERSION, true );

		$s       = WBCB_Settings::get();
		$context = self::current_page_context();
		wp_localize_script(
			'wbcb-widget',
			'WBCB',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wbcb_visitor' ),
				'online'   => WBCB_Settings::is_online(),
				'context'  => $context,
				'settings' => array(
					'title'       => $s['title'],
					'subtitle'    => $s['subtitle'],
					'placeholder' => $s['placeholder'],
					'askName'     => ! empty( $s['ask_name'] ),
					'askEmail'    => ! empty( $s['ask_email'] ),
					'primary'     => $s['primary'],
					'position'    => $s['position'],
					'offlineNote' => $s['offline_note'],
					'whatsapp'    => $s['whatsapp'],
					'telegram'    => $s['telegram'],
				),
				'i18n'     => array(
					'send'    => 'ارسال',
					'close'   => 'بستن',
					'typing'  => 'در حال نوشتن…',
					'error'   => 'خطا — دوباره تلاش کنید',
					'name'    => 'نام شما',
					'email'   => 'ایمیل (اختیاری)',
					'start'   => 'شروع گفتگو',
					'product' => 'محصول فعلی',
				),
			)
		);
	}

	/**
	 * زمینه صفحه فعلی — مخصوص محصول ووکامرس.
	 *
	 * @return array{page_url:string,page_title:string,product_id:int,product_name:string,product_url:string}
	 */
	public static function current_page_context() {
		$page_url   = '';
		$page_title = '';
		if ( function_exists( 'wp_get_canonical_url' ) ) {
			$canon = wp_get_canonical_url();
			if ( $canon ) {
				$page_url = $canon;
			}
		}
		if ( ! $page_url ) {
			$page_url = home_url( add_query_arg( array() ) );
		}
		$page_title = wp_get_document_title();

		$product_id    = 0;
		$product_name  = '';
		$product_url   = '';
		$product_image = '';

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product_id = (int) get_queried_object_id();
			if ( $product_id <= 0 && function_exists( 'wc_get_product' ) ) {
				global $product;
				if ( $product && is_object( $product ) && method_exists( $product, 'get_id' ) ) {
					$product_id = (int) $product->get_id();
				}
			}
			if ( $product_id > 0 ) {
				$product_name = get_the_title( $product_id );
				$product_url  = get_permalink( $product_id ) ?: $page_url;
				$page_title   = $product_name ?: $page_title;
				$page_url     = $product_url ?: $page_url;
				if ( function_exists( 'wc_get_product' ) ) {
					$p = wc_get_product( $product_id );
					if ( $p ) {
						$product_name = $p->get_name();
						$img_id       = (int) $p->get_image_id();
						if ( $img_id ) {
							$product_image = (string) wp_get_attachment_image_url( $img_id, 'medium' );
							if ( ! $product_image ) {
								$product_image = (string) wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' );
							}
						}
					}
				}
				if ( ! $product_image ) {
					$product_image = (string) get_the_post_thumbnail_url( $product_id, 'medium' );
				}
			}
		}

		return array(
			'page_url'       => $page_url,
			'page_title'     => $page_title,
			'product_id'     => $product_id,
			'product_name'   => $product_name,
			'product_url'    => $product_url,
			'product_image'  => $product_image,
		);
	}

	/** توضیح برای مدیر وقتی ویجت مخفی است */
	public function frontend_admin_bar_hint( $bar ) {
		if ( is_admin() || ! current_user_can( 'manage_options' ) || ! is_object( $bar ) ) {
			return;
		}
		$reason = WBCB_Settings::widget_hide_reason();
		if ( $reason === '' ) {
			return;
		}
		$map = array(
			'disabled'        => 'چت‌باکس خاموش است — تنظیمات را روشن کنید',
			'license'         => 'لایسنس/آزمایشی منقضی — فعال‌سازی لازم است',
			'admin_logged_in' => 'ویجت برای مدیر مخفی است — تیک را بردارید یا خارج شوید',
			'show_on_shop'    => 'فقط صفحات فروشگاه — صفحه فعلی شامل نیست',
			'show_on_front'   => 'فقط صفحه اصلی — صفحه فعلی شامل نیست',
		);
		$title = $map[ $reason ] ?? ( 'چت‌باکس مخفی: ' . $reason );
		$href  = ( 'license' === $reason )
			? admin_url( 'admin.php?page=webakery-chat-box-license' )
			: admin_url( 'admin.php?page=webakery-chat-box-settings' );
		$bar->add_node(
			array(
				'id'    => 'wbcb-hidden-hint',
				'title' => '⚠ ' . $title,
				'href'  => $href,
				'meta'  => array( 'class' => 'wbcb-ab-hidden' ),
			)
		);
	}

	/** کامنت HTML برای دیباگ در View Source */
	public function debug_comment() {
		if ( WBCB_Settings::should_show_widget() ) {
			return;
		}
		$reason = WBCB_Settings::widget_hide_reason();
		echo "\n<!-- webakery-chat-box: hidden reason=" . esc_html( $reason ) . " -->\n";
	}

	public function render_widget() {
		if ( ! WBCB_Settings::should_show_widget() ) {
			return;
		}
		$s       = WBCB_Settings::get();
		$ctx     = self::current_page_context();
		$pos     = ( 'right' === $s['position'] ) ? 'is-right' : 'is-left';
		$prim    = esc_attr( $s['primary'] );
		$has_prod = ! empty( $ctx['product_id'] ) && ! empty( $ctx['product_name'] );
		?>
		<div id="wbcb-root" class="wbcb-root <?php echo esc_attr( $pos ); ?>" style="--wbcb-primary:<?php echo $prim; ?>;" dir="rtl" lang="fa">
			<button type="button" class="wbcb-launcher" id="wbcb-launcher" aria-expanded="false" aria-controls="wbcb-panel">
				<span class="wbcb-launcher-icon" aria-hidden="true">💬</span>
				<span class="wbcb-launcher-label"><?php echo esc_html( $s['title'] ); ?></span>
			</button>
			<div class="wbcb-panel" id="wbcb-panel" hidden>
				<header class="wbcb-head">
					<div>
						<strong class="wbcb-head-title"><?php echo esc_html( $s['title'] ); ?></strong>
						<span class="wbcb-head-sub"><?php echo esc_html( $s['subtitle'] ); ?></span>
					</div>
					<span class="wbcb-status-dot" data-online="<?php echo WBCB_Settings::is_online() ? '1' : '0'; ?>"></span>
					<button type="button" class="wbcb-icon-btn" id="wbcb-close" aria-label="بستن">×</button>
				</header>
				<?php if ( $has_prod ) : ?>
					<div class="wbcb-product-chip" id="wbcb-product-chip">
						<?php if ( ! empty( $ctx['product_image'] ) ) : ?>
							<img class="wbcb-product-chip-img" src="<?php echo esc_url( $ctx['product_image'] ); ?>" alt="" width="44" height="44" loading="lazy" />
						<?php else : ?>
							<span class="wbcb-product-chip-ic" aria-hidden="true">🛒</span>
						<?php endif; ?>
						<div class="wbcb-product-chip-text">
							<small>در حال مشاهده محصول</small>
							<strong><?php echo esc_html( $ctx['product_name'] ); ?></strong>
						</div>
					</div>
				<?php else : ?>
					<div class="wbcb-product-chip" id="wbcb-product-chip" hidden></div>
				<?php endif; ?>
				<div class="wbcb-intro" id="wbcb-intro" hidden>
					<label class="wbcb-field">
						<span><?php esc_html_e( 'نام', 'webakery-chat-box' ); ?></span>
						<input type="text" id="wbcb-name" autocomplete="name" />
					</label>
					<label class="wbcb-field" id="wbcb-email-wrap" hidden>
						<span><?php esc_html_e( 'ایمیل', 'webakery-chat-box' ); ?></span>
						<input type="email" id="wbcb-email" autocomplete="email" />
					</label>
					<button type="button" class="wbcb-btn-primary" id="wbcb-start"><?php esc_html_e( 'شروع گفتگو', 'webakery-chat-box' ); ?></button>
				</div>
				<div class="wbcb-messages" id="wbcb-messages" role="log" aria-live="polite"></div>
				<footer class="wbcb-foot">
					<form id="wbcb-form" class="wbcb-form">
						<textarea id="wbcb-input" rows="1" placeholder="<?php echo esc_attr( $s['placeholder'] ); ?>"></textarea>
						<button type="submit" class="wbcb-send" id="wbcb-send" aria-label="ارسال">➤</button>
					</form>
					<div class="wbcb-links" id="wbcb-links"></div>
				</footer>
			</div>
		</div>
		<?php
	}
}
