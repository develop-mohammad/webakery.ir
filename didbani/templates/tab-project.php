<?php
defined( 'ABSPATH' ) || exit;
$is_new = ! empty( $_GET['new'] ); // phpcs:ignore
$form_id = $is_new ? 0 : (int) $pid;
$own     = $is_new ? '' : DID_Admin::own_host( $domains );
$comp    = $is_new ? '' : DID_Admin::competitor_text( $domains );
$kws     = $is_new ? '' : DID_Admin::keyword_text( $keywords );
$name    = ( $is_new || ! $project ) ? '' : $project['name'];
?>
<p>
	<?php if ( $pid && ! $is_new ) : ?>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=didbani&tab=project&new=1' ) ); ?>">پروژه جدید</a>
	<?php elseif ( $pid ) : ?>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=didbani&tab=project&project=' . (int) $pid ) ); ?>">بازگشت به پروژه جاری</a>
	<?php endif; ?>
</p>
<form method="post" class="did-form">
	<?php wp_nonce_field( 'did_project' ); ?>
	<input type="hidden" name="did_save_project" value="1" />
	<input type="hidden" name="project_id" value="<?php echo (int) $form_id; ?>" />

	<table class="form-table" role="presentation">
		<tr>
			<th><label for="project_name">نام پروژه</label></th>
			<td>
				<input id="project_name" class="regular-text" type="text" name="project_name" value="<?php echo esc_attr( $name ); ?>" placeholder="مثلاً فروشگاه خودم" />
			</td>
		</tr>
		<tr>
			<th><label for="own_domain">سایت خودم</label></th>
			<td>
				<input id="own_domain" class="regular-text" dir="ltr" type="text" name="own_domain" value="<?php echo esc_attr( $own ); ?>" placeholder="example.ir" />
				<p class="description">دامنه بدون http هم قبول است. www حذف می‌شود.</p>
			</td>
		</tr>
		<tr>
			<th><label for="competitors">دامنهٔ رقبا</label></th>
			<td>
				<textarea id="competitors" name="competitors" class="large-text" rows="6" dir="ltr" placeholder="competitor1.ir"><?php echo esc_textarea( $comp ); ?></textarea>
				<p class="description">هر خط یک دامنه. فقط صفحات عمومی همین دامنه‌ها کرول می‌شوند.</p>
			</td>
		</tr>
		<tr>
			<th><label for="keywords">کلیدواژه‌ها</label></th>
			<td>
				<textarea id="keywords" name="keywords" class="large-text" rows="8" placeholder="خرید فرش"><?php echo esc_textarea( $kws ); ?></textarea>
				<p class="description">هر خط یک کلیدواژه. ارقام فارسی به لاتین تبدیل می‌شوند.</p>
			</td>
		</tr>
	</table>

	<p>
		<button type="submit" class="button button-primary" <?php disabled( ! $usable ); ?>><?php echo $form_id ? 'ذخیره تغییرات' : 'ساخت پروژه'; ?></button>
		<?php if ( $form_id ) : ?>
			<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=didbani&tab=project&did_delete=1&project=' . (int) $form_id ), 'did_delete' ) ); ?>" onclick="return confirm('این پروژه و نتایج کرول/رتبه حذف شود؟');">حذف پروژه</a>
		<?php endif; ?>
	</p>
</form>
