<?php
defined( 'ABSPATH' ) || exit;

/**
 * تکسونومی «صفحه تخفیف»: هر ترم یک صفحه با URL اختصاصی و یک بازه تخفیف
 * (درصدی یا مبلغ ثابت) است. محصولاتی که تخفیف فعلی‌شان داخل این بازه باشد،
 * توسط WDP_Assigner خودکار به این صفحه اضافه می‌شوند.
 */
class WDP_Taxonomy {

	const TAXONOMY = 'wdp_discount_page';

	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 5 );

		add_action( self::TAXONOMY . '_add_form_fields', array( __CLASS__, 'add_form_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( __CLASS__, 'edit_form_fields' ) );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'save_meta' ) );
		add_action( 'edited_' . self::TAXONOMY, array( __CLASS__, 'save_meta' ) );

		add_filter( 'manage_edit-' . self::TAXONOMY . '_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'manage_' . self::TAXONOMY . '_custom_column', array( __CLASS__, 'column_content' ), 10, 3 );
	}

	/** پیشوند آدرس صفحه‌های تخفیف؛ قابل تغییر از تب تنظیمات */
	public static function url_base() {
		$settings = get_option( 'wdp_settings', array() );
		$base     = isset( $settings['url_base'] ) ? trim( (string) $settings['url_base'] ) : '';
		$base     = $base ? sanitize_title( $base ) : 'discount';
		return $base ? $base : 'discount';
	}

	public static function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			array( 'product' ),
			array(
				'labels'            => array(
					'name'          => 'صفحه‌های تخفیف',
					'singular_name' => 'صفحه تخفیف',
					'search_items'  => 'جستجوی صفحه تخفیف',
					'all_items'     => 'همه صفحه‌های تخفیف',
					'edit_item'     => 'ویرایش صفحه تخفیف',
					'update_item'   => 'به‌روزرسانی صفحه تخفیف',
					'add_new_item'  => 'افزودن صفحه تخفیف جدید',
					'new_item_name' => 'نام صفحه تخفیف جدید',
					'not_found'     => 'صفحه تخفیفی یافت نشد',
					'menu_name'     => 'صفحه‌های تخفیف',
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_in_menu'      => false, // دسترسی از منوی اختصاصی افزونه.
				'show_in_nav_menus' => true,
				'show_admin_column' => false,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'         => self::url_base(),
					'with_front'   => false,
					'hierarchical' => false,
				),
			)
		);
	}

	/* ─── فیلدهای فرم افزودن/ویرایش ترم ────────────────────────── */

	public static function add_form_fields() {
		?>
		<div class="form-field wdp-term-field">
			<label>نوع تخفیف</label>
			<p>
				<label style="margin-left:16px"><input type="radio" name="wdp_type" value="percent" checked> درصدی (٪)</label>
				<label><input type="radio" name="wdp_type" value="fixed"> مبلغ ثابت</label>
			</p>
		</div>
		<div class="form-field wdp-term-field">
			<label for="wdp_min">حداقل بازه</label>
			<input type="text" name="wdp_min" id="wdp_min" value="0" inputmode="decimal" dir="ltr">
			<p>مثلاً برای «۲۰ تا ۳۰ درصد تخفیف» عدد ۲۰ را وارد کنید.</p>
		</div>
		<div class="form-field wdp-term-field">
			<label for="wdp_max">حداکثر بازه</label>
			<input type="text" name="wdp_max" id="wdp_max" value="100" inputmode="decimal" dir="ltr">
			<p>مثلاً برای «۲۰ تا ۳۰ درصد تخفیف» عدد ۳۰ را وارد کنید.</p>
		</div>
		<div class="form-field wdp-term-field">
			<label for="wdp_priority">اولویت (اختیاری)</label>
			<input type="number" name="wdp_priority" id="wdp_priority" value="10" min="0" max="999">
			<p>اگر بازه این صفحه با صفحه دیگری هم‌پوشانی داشت، محصول به صفحه‌ای با اولویت بیشتر می‌رود.</p>
		</div>
		<div class="form-field wdp-term-field">
			<label>محدود به دسته‌بندی محصول (اختیاری)</label>
			<?php self::render_category_checklist( array() ); ?>
			<p>اگر هیچ‌کدام را تیک نزنید، این صفحه برای محصولات همه دسته‌بندی‌ها باز است.
			اگر تیک بزنید، فقط محصولاتی از همان دسته‌بندی(ها) — با همین بازه تخفیف — در این صفحه قرار می‌گیرند.</p>
		</div>
		<?php
	}

	public static function edit_form_fields( $term ) {
		$type     = self::type( $term->term_id );
		$min      = self::min( $term->term_id );
		$max      = self::max( $term->term_id );
		$priority = self::priority( $term->term_id );
		$link     = get_term_link( $term );
		?>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label>نوع تخفیف</label></th>
			<td>
				<label style="margin-left:16px"><input type="radio" name="wdp_type" value="percent" <?php checked( 'percent', $type ); ?>> درصدی (٪)</label>
				<label><input type="radio" name="wdp_type" value="fixed" <?php checked( 'fixed', $type ); ?>> مبلغ ثابت</label>
			</td>
		</tr>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label for="wdp_min">حداقل بازه</label></th>
			<td><input type="text" name="wdp_min" id="wdp_min" value="<?php echo esc_attr( WDP_Util::trim_zeros( $min ) ); ?>" inputmode="decimal" dir="ltr"></td>
		</tr>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label for="wdp_max">حداکثر بازه</label></th>
			<td><input type="text" name="wdp_max" id="wdp_max" value="<?php echo esc_attr( WDP_Util::trim_zeros( $max ) ); ?>" inputmode="decimal" dir="ltr"></td>
		</tr>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label for="wdp_priority">اولویت (اختیاری)</label></th>
			<td>
				<input type="number" name="wdp_priority" id="wdp_priority" value="<?php echo (int) $priority; ?>" min="0" max="999">
				<p class="description">اگر بازه این صفحه با صفحه دیگری هم‌پوشانی داشت، محصول به صفحه‌ای با اولویت بیشتر می‌رود.</p>
			</td>
		</tr>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label>محدود به دسته‌بندی محصول (اختیاری)</label></th>
			<td>
				<?php self::render_category_checklist( self::categories( $term->term_id ) ); ?>
				<p class="description">
					اگر هیچ‌کدام را تیک نزنید، این صفحه برای محصولات همه دسته‌بندی‌ها باز است.
					اگر تیک بزنید، فقط محصولاتی از همان دسته‌بندی(ها) — با همین بازه تخفیف — در این صفحه قرار می‌گیرند
					(مثلاً می‌توانید «۲۰ تا ۳۰٪ لوازم خانگی» را جدا از «۲۰ تا ۳۰٪ پوشاک» بسازید).
				</p>
			</td>
		</tr>
		<?php if ( ! is_wp_error( $link ) ) : ?>
		<tr class="form-field wdp-term-field">
			<th scope="row"><label>لینک صفحه</label></th>
			<td><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link ); ?></a></td>
		</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * چک‌باکس‌های دسته‌بندی محصول (سلسله‌مراتبی)؛ هم برای محدود کردن یک صفحه
	 * تخفیف و هم برای فرم «اعمال گروهی تخفیف» استفاده می‌شود.
	 *
	 * @param int[]  $selected شناسه دسته‌بندی‌های تیک‌خورده
	 * @param string $name     نام فیلد فرم (مثلاً wdp_categories[] یا bulk_categories[])
	 */
	public static function render_category_checklist( array $selected, $name = 'wdp_categories[]' ) {
		$tree = self::category_tree();
		if ( ! $tree ) {
			echo '<p class="wdp-muted">دسته‌بندی محصولی یافت نشد.</p>';
			return;
		}
		echo '<div class="wdp-cat-list">';
		foreach ( $tree as $row ) {
			printf(
				'<label class="wdp-cat" style="padding-right:%dpx"><input type="checkbox" name="%s" value="%d" %s> %s <em>(%d)</em></label><br>',
				(int) $row['depth'] * 16,
				esc_attr( $name ),
				(int) $row['id'],
				checked( in_array( $row['id'], $selected, true ), true, false ),
				esc_html( $row['name'] ),
				(int) $row['count']
			);
		}
		echo '</div>';
	}

	/**
	 * فهرست دسته‌بندی‌های محصولات به‌صورت سلسله‌مراتبی (برای چک‌باکس‌ها).
	 *
	 * @return array<int,array{id:int,name:string,depth:int,count:int}>
	 */
	public static function category_tree() {
		$cached = get_transient( 'wdp_product_cat_tree' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$flat = array();
		$walk = function ( $parent, $depth ) use ( &$walk, &$flat, $by_parent ) {
			if ( empty( $by_parent[ $parent ] ) ) {
				return;
			}
			foreach ( $by_parent[ $parent ] as $term ) {
				$flat[] = array(
					'id'    => (int) $term->term_id,
					'name'  => $term->name,
					'depth' => $depth,
					'count' => (int) $term->count,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		set_transient( 'wdp_product_cat_tree', $flat, 5 * MINUTE_IN_SECONDS );
		return $flat;
	}

	public static function save_meta( $term_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$type = ( isset( $_POST['wdp_type'] ) && 'fixed' === $_POST['wdp_type'] ) ? 'fixed' : 'percent';

		$min = isset( $_POST['wdp_min'] ) ? WDP_Util::to_number( wp_unslash( $_POST['wdp_min'] ) ) : 0;
		$max = isset( $_POST['wdp_max'] ) ? WDP_Util::to_number( wp_unslash( $_POST['wdp_max'] ) ) : 0;
		if ( $max < $min ) {
			list( $min, $max ) = array( $max, $min );
		}
		if ( 'percent' === $type ) {
			$min = min( 100, max( 0, $min ) );
			$max = min( 100, max( 0, $max ) );
		} else {
			$min = max( 0, $min );
			$max = max( 0, $max );
		}

		$priority = isset( $_POST['wdp_priority'] ) ? (int) WDP_Util::to_number( wp_unslash( $_POST['wdp_priority'] ) ) : 10;
		$priority = max( 0, min( 999, $priority ) );

		$categories = array();
		if ( ! empty( $_POST['wdp_categories'] ) && is_array( $_POST['wdp_categories'] ) ) {
			foreach ( wp_unslash( $_POST['wdp_categories'] ) as $cat_id ) {
				$cat_id = (int) $cat_id;
				if ( $cat_id > 0 ) {
					$categories[] = $cat_id;
				}
			}
			$categories = array_values( array_unique( $categories ) );
		}

		update_term_meta( $term_id, '_wdp_type', $type );
		update_term_meta( $term_id, '_wdp_min', $min );
		update_term_meta( $term_id, '_wdp_max', $max );
		update_term_meta( $term_id, '_wdp_priority', $priority );
		update_term_meta( $term_id, '_wdp_categories', $categories );

		if ( class_exists( 'WDP_Assigner' ) ) {
			WDP_Assigner::clear_rules_cache();
			// بازبینی سراسری در پس‌زمینه تا ذخیره صفحه تخفیف قفل نشود.
			WDP_Assigner::schedule_recalculate();
		}
	}

	/* ─── دسترسی به متادیتای ترم ────────────────────────────────── */

	public static function type( $term_id ) {
		$type = get_term_meta( $term_id, '_wdp_type', true );
		return 'fixed' === $type ? 'fixed' : 'percent';
	}

	public static function min( $term_id ) {
		return (float) get_term_meta( $term_id, '_wdp_min', true );
	}

	public static function max( $term_id ) {
		$max = get_term_meta( $term_id, '_wdp_max', true );
		return '' === $max ? 100.0 : (float) $max;
	}

	public static function priority( $term_id ) {
		$p = get_term_meta( $term_id, '_wdp_priority', true );
		return '' === $p ? 10 : (int) $p;
	}

	/** شناسه دسته‌بندی‌های محصولی که این صفحه به آن‌ها محدود شده؛ آرایه خالی یعنی بدون محدودیت */
	public static function categories( $term_id ) {
		$categories = get_term_meta( $term_id, '_wdp_categories', true );
		return is_array( $categories ) ? array_map( 'intval', $categories ) : array();
	}

	/** نام دسته‌بندی‌های محدودیت این صفحه، برای نمایش */
	public static function category_names( $term_id ) {
		$names = array();
		foreach ( self::categories( $term_id ) as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	public static function currency() {
		return function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'تومان';
	}

	public static function range_label( $term_id ) {
		return WDP_Util::range_label( self::type( $term_id ), self::min( $term_id ), self::max( $term_id ), self::currency() );
	}

	/**
	 * همه صفحه‌های تخفیف به‌همراه متادیتا؛ ورودی موتور تشخیص WDP_Assigner.
	 *
	 * @return array<int,array{term_id:int,type:string,min:float,max:float,priority:int,categories:int[]}>
	 */
	public static function all_rules() {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		$rules = array();
		foreach ( $terms as $term ) {
			$rules[] = array(
				'term_id'    => (int) $term->term_id,
				'type'       => self::type( $term->term_id ),
				'min'        => self::min( $term->term_id ),
				'max'        => self::max( $term->term_id ),
				'priority'   => self::priority( $term->term_id ),
				'categories' => self::categories( $term->term_id ),
			);
		}
		return $rules;
	}

	/* ─── ستون‌های سفارشی جدول ترم‌ها ───────────────────────────── */

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'description' === $key ) {
				$new['wdp_range']      = 'بازه تخفیف';
				$new['wdp_categories'] = 'دسته‌بندی محصول';
				$new['wdp_link']       = 'لینک صفحه';
			}
		}
		if ( ! isset( $new['wdp_range'] ) ) {
			$new['wdp_range']      = 'بازه تخفیف';
			$new['wdp_categories'] = 'دسته‌بندی محصول';
			$new['wdp_link']       = 'لینک صفحه';
		}
		return $new;
	}

	public static function column_content( $content, $column, $term_id ) {
		if ( 'wdp_range' === $column ) {
			$type  = self::type( $term_id );
			$badge = 'percent' === $type ? 'درصدی' : 'مبلغ ثابت';
			return '<strong>' . esc_html( self::range_label( $term_id ) ) . '</strong><br><span class="wdp-muted">' . esc_html( $badge ) . '</span>';
		}
		if ( 'wdp_categories' === $column ) {
			$names = self::category_names( $term_id );
			return $names ? esc_html( implode( '، ', $names ) ) : '<span class="wdp-muted">همه دسته‌بندی‌ها</span>';
		}
		if ( 'wdp_link' === $column ) {
			$link = get_term_link( (int) $term_id, self::TAXONOMY );
			if ( is_wp_error( $link ) ) {
				return '—';
			}
			return '<input type="text" class="wdp-link-copy" readonly dir="ltr" onclick="this.select()" value="' . esc_attr( $link ) . '" style="width:100%;font-size:11px">';
		}
		return $content;
	}
}
