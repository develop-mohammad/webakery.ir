<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$list = $wpdb->get_results( 'SELECT * FROM ' . NM_Specialist::table() . ' ORDER BY id DESC' );
$edit = ! empty( $_GET['edit'] ) ? NM_Specialist::get( (int) $_GET['edit'] ) : null;
?>
<div class="nm-grid-2">
	<div class="nm-panel-card">
		<h3><?php echo $edit ? 'ویرایش متخصص' : 'افزودن متخصص'; ?></h3>
		<?php if ( ! NM_Pro::is_active() && count( $list ) >= 1 && ! $edit ) : ?>
			<div class="notice notice-warning"><p>نسخه رایگان فقط ۱ متخصص دارد. برای نامحدود، پرو را فعال کنید.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'nm_specialist' ); ?>
			<input type="hidden" name="nm_save_specialist" value="1" />
			<input type="hidden" name="id" value="<?php echo $edit ? (int) $edit->id : 0; ?>" />
			<label>نام<input type="text" name="name" value="<?php echo esc_attr( $edit->name ?? '' ); ?>" required class="widefat" /></label>
			<label>مهارت‌ها<input type="text" name="skills" value="<?php echo esc_attr( $edit->skills ?? '' ); ?>" class="widefat" placeholder="روانشناسی مثبت، کوچینگ، ..." /></label>
			<label>بیو<textarea name="bio" class="widefat" rows="3"><?php echo esc_textarea( $edit->bio ?? '' ); ?></textarea></label>
			<label>قیمت (تومان)<input type="number" name="price" value="<?php echo esc_attr( $edit->price ?? NM_Settings::get( 'default_price' ) ); ?>" class="widefat" /></label>
			<label>مدت جلسه (دقیقه ۵–۳۰۰)<input type="number" name="duration" min="5" max="300" value="<?php echo esc_attr( $edit->duration ?? 60 ); ?>" class="widefat" /></label>
			<label>وقفه بین رزروها (دقیقه)<input type="number" name="buffer_minutes" value="<?php echo esc_attr( $edit->buffer_minutes ?? 10 ); ?>" class="widefat" /></label>
			<label>Google Calendar ID<input type="text" name="google_calendar_id" value="<?php echo esc_attr( $edit->google_calendar_id ?? '' ); ?>" class="widefat" placeholder="primary" /></label>
			<label><input type="checkbox" name="is_active" value="1" <?php checked( empty( $edit ) || ! empty( $edit->is_active ) ); ?> /> فعال</label>
			<p><button class="button button-primary">ذخیره</button></p>
		</form>
	</div>
	<div class="nm-panel-card">
		<h3>لیست متخصصین</h3>
		<table class="nm-table">
			<thead><tr><th>نام</th><th>قیمت</th><th>مدت</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $list as $sp ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $sp->name ); ?></strong><br><small><?php echo esc_html( $sp->skills ); ?></small></td>
					<td><?php echo esc_html( NM_Settings::format_price( $sp->price ) ); ?></td>
					<td><?php echo (int) $sp->duration; ?>′</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=nobat-man&tab=specialists&edit=' . $sp->id ) ); ?>">ویرایش</a>
						|
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=nobat-man&tab=specialists&nm_delete_specialist=' . $sp->id ), 'nm_del_sp_' . $sp->id ) ); ?>" onclick="return confirm('حذف شود؟')">حذف</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
