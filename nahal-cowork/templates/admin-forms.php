<?php
defined( 'ABSPATH' ) || exit;
$edit_id = isset( $_GET['form'] ) ? sanitize_text_field( wp_unslash( $_GET['form'] ) ) : ''; // phpcs:ignore
$is_new  = ( 'new' === $edit_id );
if ( '' === $edit_id ) {
	NCK_Forms::sync_all_products();
}
$form = $is_new ? NCK_Forms::blank() : ( $edit_id ? NCK_Forms::get( $edit_id ) : null );
$list = NCK_Forms::all();

if ( $form || $is_new ) :
	$json = wp_json_encode( $form, JSON_UNESCAPED_UNICODE );
	?>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=forms' ) ); ?>">بازگشت به فهرست فرم‌ها</a>
	</p>
	<form method="post" class="nck-form-editor" data-nck-form-editor>
		<?php wp_nonce_field( 'nck_save_form' ); ?>
		<input type="hidden" name="nck_save_form" value="1" />
		<input type="hidden" name="nck_form_json" id="nck-form-json" value="<?php echo esc_attr( $json ); ?>" />

		<div class="nck-panel">
			<h2><?php echo $is_new ? 'افزودن فرم جدید' : 'ویرایش فرم'; ?></h2>
			<p class="description">برای نمایش در سایت، شورت‌کد را در برگه یا ویجت المنتور بگذارید. با ذخیره، یک محصول ووکامرس با همین عنوان ساخته می‌شود و اگر پرداخت روشن باشد سفارش در حسابدار هم دیده می‌شود.</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="nck-form-title">عنوان</label></th>
					<td><input id="nck-form-title" class="regular-text" type="text" data-nck-meta="title" value="<?php echo esc_attr( $form['title'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="nck-form-slug">شناسه انگلیسی (slug)</label></th>
					<td>
						<input id="nck-form-slug" class="regular-text" type="text" dir="ltr" data-nck-meta="slug" value="<?php echo esc_attr( $form['slug'] ); ?>" placeholder="workshop" />
						<p class="description">شورت‌کد: <code>[nahal_form slug="…"]</code></p>
					</td>
				</tr>
				<tr>
					<th><label for="nck-form-kicker">بالانویس</label></th>
					<td><input id="nck-form-kicker" class="regular-text" type="text" data-nck-meta="kicker" value="<?php echo esc_attr( $form['kicker'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="nck-form-tagline">توضیح کوتاه</label></th>
					<td><input id="nck-form-tagline" class="regular-text" type="text" data-nck-meta="tagline" value="<?php echo esc_attr( $form['tagline'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="nck-form-slogan">شعار پایین فرم</label></th>
					<td><input id="nck-form-slogan" class="regular-text" type="text" data-nck-meta="slogan" value="<?php echo esc_attr( $form['slogan'] ); ?>" /></td>
				</tr>
				<tr>
					<th>وضعیت</th>
					<td>
						<label><input type="radio" name="nck_status_ui" data-nck-meta="status" value="publish" <?php checked( $form['status'], 'publish' ); ?> /> منتشر</label>
						<label style="margin-right:12px"><input type="radio" name="nck_status_ui" data-nck-meta="status" value="draft" <?php checked( $form['status'], 'draft' ); ?> /> پیش‌نویس</label>
					</td>
				</tr>
				<tr>
					<th>امضا</th>
					<td><label><input type="checkbox" data-nck-meta="require_signature" <?php checked( ! empty( $form['require_signature'] ) ); ?> /> مرحله امضا در پایان فرم</label></td>
				</tr>
				<tr>
					<th>محصول ووکامرس</th>
					<td>
						<?php if ( ! empty( $form['product_id'] ) && function_exists( 'get_edit_post_link' ) ) : ?>
							<p>
								<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( (int) $form['product_id'] ) ); ?>" target="_blank" rel="noopener">
									ویرایش محصول <?php echo esc_html( (string) (int) $form['product_id'] ); ?>
								</a>
							</p>
							<p class="description">با هر ذخیره، عنوان و مبلغ این محصول با فرم هماهنگ می‌شود.</p>
						<?php elseif ( class_exists( 'NCK_Pay' ) && ! NCK_Pay::wc_ready() ) : ?>
							<p class="description">ووکامرس فعال نیست. بعد از فعال‌سازی، با ذخیره فرم یک محصول در فهرست محصولات ساخته می‌شود.</p>
						<?php else : ?>
							<p class="description">با ذخیره این فرم، یک محصول مجازی با همین عنوان در ووکامرس ساخته می‌شود.</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<div class="nck-panel">
			<h2>پرداخت و حسابدار</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th>فعال‌سازی پرداخت</th>
					<td><label><input type="checkbox" data-nck-pay="enabled" <?php checked( ! empty( $form['payment']['enabled'] ) ); ?> /> این فرم به پرداخت می‌رسد و در ووکامرس / حسابدار ثبت شود</label></td>
				</tr>
				<tr>
					<th><label for="nck-form-amount">مبلغ ثابت (تومان)</label></th>
					<td>
						<input id="nck-form-amount" type="text" dir="ltr" data-nck-pay="amount" value="<?php echo esc_attr( (string) $form['payment']['amount'] ); ?>" />
						<p class="description">اگر صفر باشد، مبلغ از فیلد «مبلغ» فرم خوانده می‌شود.</p>
					</td>
				</tr>
				<tr>
					<th><label for="nck-form-item">عنوان ردیف سفارش</label></th>
					<td><input id="nck-form-item" class="regular-text" type="text" data-nck-pay="item_name" value="<?php echo esc_attr( $form['payment']['item_name'] ); ?>" placeholder="همان عنوان فرم" /></td>
				</tr>
				<tr>
					<th>روش پرداخت</th>
					<td>
						<p class="description">پرداخت کاربر فقط از درگاه بانک است؛ کارت‌به‌کارت و پرداخت در محل نمایش داده نمی‌شود.</p>
					</td>
				</tr>
			</table>
		</div>

		<div class="nck-panel">
			<h2>مراحل و فیلدها</h2>
			<p class="description">هر مرحله یک صفحه ویزارد است. برای اتصال به سفارش، حداقل یک فیلد با نقش «نام» و یک فیلد با نقش «موبایل» بگذارید.</p>
			<div id="nck-form-builder"></div>
			<p>
				<button type="button" class="button" data-nck-add-step>افزودن مرحله</button>
			</p>
		</div>

		<?php submit_button( 'ذخیره فرم' ); ?>
	</form>
<?php else : ?>
	<div class="nck-panel">
		<p>فرم‌های آماده افزونه را با شورت‌کد جدا روی برگه می‌گذارید: فضای کار <code dir="ltr">[nahal_contract]</code>، سالن <code dir="ltr">[nahal_hall]</code>، پذیرش <code dir="ltr">[nahal_admission]</code>.</p>
		<p>اینجا فرم‌های خودتان را می‌سازید — کارگاه، اردو، ثبت‌نام دوره. بعد از ذخیره، ستون «شورت‌کد» را در برگه بچسبانید.</p>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=shortcodes' ) ); ?>">توضیح کامل شورت‌کدها</a></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=forms&form=new' ) ); ?>">افزودن فرم جدید</a>
		</p>
	</div>

	<table class="widefat striped nck-table">
		<thead>
			<tr>
				<th>عنوان</th>
				<th>شورت‌کد</th>
				<th>محصول</th>
				<th>پرداخت</th>
				<th>وضعیت</th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $list ) : ?>
				<tr><td colspan="6">هنوز فرم سفارشی ندارید.</td></tr>
			<?php else : ?>
				<?php foreach ( $list as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['title'] ); ?></td>
						<td><code><?php echo esc_html( NCK_Forms::shortcode( $row ) ); ?></code></td>
						<td>
							<?php if ( ! empty( $row['product_id'] ) && function_exists( 'get_edit_post_link' ) ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $row['product_id'] ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) (int) $row['product_id'] ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo ! empty( $row['payment']['enabled'] ) ? 'فعال' : 'بدون پرداخت'; ?></td>
						<td><?php echo 'publish' === $row['status'] ? 'منتشر' : 'پیش‌نویس'; ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=forms&form=' . rawurlencode( $row['id'] ) ) ); ?>">ویرایش</a>
							<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=forms&nck_delete_form=' . rawurlencode( $row['id'] ) ), 'nck_delete_form' ) ); ?>" onclick="return confirm('این فرم حذف شود؟');">حذف</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
<?php endif; ?>
