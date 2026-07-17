<?php
defined( 'ABSPATH' ) || exit;
$list = NM_Questions::all();
?>
<div class="nm-grid-2">
	<div class="nm-panel-card">
		<h3>افزودن / ویرایش سوال</h3>
		<form method="post">
			<?php wp_nonce_field( 'nm_question' ); ?>
			<input type="hidden" name="nm_save_question" value="1" />
			<input type="hidden" name="id" value="0" />
			<label>دسته‌بندی<input type="text" name="category" class="widefat" placeholder="اضطراب و استرس" required /></label>
			<label>سوال<input type="text" name="question" class="widefat" required /></label>
			<label>نوع
				<select name="type" class="widefat">
					<option value="text">متنی</option>
					<option value="textarea">چندخطی</option>
					<option value="select">انتخابی</option>
				</select>
			</label>
			<label>گزینه‌ها (هر خط یک گزینه)<textarea name="options_text" class="widefat" rows="4"></textarea></label>
			<label>ترتیب<input type="number" name="sort_order" value="10" class="widefat" /></label>
			<label><input type="checkbox" name="is_required" value="1" checked /> اجباری</label>
			<p><button class="button button-primary">ذخیره سوال</button></p>
		</form>
	</div>
	<div class="nm-panel-card">
		<h3>سوالات فعلی</h3>
		<table class="nm-table">
			<thead><tr><th>دسته</th><th>سوال</th><th>نوع</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $list as $q ) : ?>
				<tr>
					<td><?php echo esc_html( $q->category ); ?></td>
					<td><?php echo esc_html( $q->question ); ?></td>
					<td><?php echo esc_html( $q->type ); ?></td>
					<td><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=nobat-man&tab=questions&nm_delete_question=' . $q->id ), 'nm_del_q_' . $q->id ) ); ?>">حذف</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
