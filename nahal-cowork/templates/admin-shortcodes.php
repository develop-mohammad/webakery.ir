<?php
defined( 'ABSPATH' ) || exit;
$guide = NCK_Admin::shortcode_guide();
?>
<div class="nck-panel">
	<h2>شورت‌کد چیست؟</h2>
	<p>شورت‌کد یک خط کوتاه داخل کروشه است. آن را در محتوای برگه وردپرس می‌گذارید تا فرم نهال روی همان صفحه ظاهر شود. لازم نیست کد برنامه‌نویسی بنویسید.</p>
	<ol class="nck-steps">
		<li>پیشخوان → برگه‌ها → افزودن برگه</li>
		<li>برای برگه یک عنوان فارسی بگذارید (مثلاً «اجاره سالن»)</li>
		<li>در ویرایشگر، فقط همان یک شورت‌کد را بچسبانید</li>
		<li>انتشار → آدرس برگه را در منوی سایت بگذارید</li>
	</ol>
	<p class="description">هر فرم را روی برگهٔ خودش بگذارید. همه شورت‌کدها را در یک صفحه نچینید. اگر از المنتور استفاده می‌کنید، به‌جای شورت‌کد می‌توانید ویجت‌های «نهال» را از فهرست ویجت‌ها بکشید.</p>
</div>

<table class="widefat striped nck-table nck-sc-table">
	<thead>
		<tr>
			<th>شورت‌کد</th>
			<th>چه فرمی نشان می‌دهد</th>
			<th>برای چه کسی</th>
			<th>پرداخت</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $guide as $row ) : ?>
			<tr>
				<td><code dir="ltr"><?php echo esc_html( $row['code'] ); ?></code></td>
				<td>
					<strong><?php echo esc_html( $row['title'] ); ?></strong>
					<p class="description"><?php echo esc_html( $row['page'] ); ?></p>
				</td>
				<td><?php echo esc_html( $row['who'] ); ?></td>
				<td><?php echo esc_html( $row['pay'] ); ?></td>
				<td>
					<button type="button" class="button" data-nck-copy="<?php echo esc_attr( $row['code'] ); ?>">کپی</button>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<div class="nck-panel">
	<h2>فرم سفارشی</h2>
	<p>شورت‌کد <code dir="ltr">[nahal_form]</code> به‌تنهایی کار نمی‌کند؛ باید شناسه انگلیسی فرم را بدهید.</p>
	<p>مثال: اگر در تب «فرم‌ها» فرمی با شناسه <code dir="ltr">workshop</code> ساخته‌اید:</p>
	<p><code dir="ltr">[nahal_form slug="workshop"]</code>
		<button type="button" class="button" data-nck-copy='[nahal_form slug="workshop"]'>کپی نمونه</button>
	</p>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . NCK_MENU . '&tab=forms' ) ); ?>">رفتن به فرم‌ساز</a>
	</p>
</div>
