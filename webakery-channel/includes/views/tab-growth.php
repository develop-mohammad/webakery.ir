<?php
defined( 'ABSPATH' ) || exit;

$s     = WBCN_Settings::get();
$chan  = ! empty( $s['signature'] ) ? WBCN_Settings::channel_username( $s ) : '';
$items = WBCN_Content::recent_items( 7 );
$plan  = WBCN_Ideas::week_plan( $items, $chan );
$log   = (array) get_option( 'wbcn_log', array() );
?>

<div class="wbcn-card">
	<h2>تقویم ۷ روزه از مطالب خودتان</h2>
	<p class="wbcn-hint">
		هر روز یک قالب متفاوت از یک مطلب واقعی سایت. هدف: تعامل شنبه تا جمعه، نه فقط لینک‌انداختن.
		ایده روزانهٔ خودکار را از تب «ربات و کانال» روشن کنید.
	</p>
	<div class="wbcn-week">
		<?php foreach ( $plan as $i => $row ) : ?>
			<article class="wbcn-day">
				<header>
					<strong><?php echo esc_html( $row['day'] ); ?></strong>
					<span class="wbcn-pill"><?php echo esc_html( $row['label'] ); ?></span>
				</header>
				<p class="wbcn-why"><?php echo esc_html( $row['why'] ); ?></p>
				<p class="wbcn-src"><?php echo esc_html( $row['title'] ? $row['title'] : 'نمونه' ); ?></p>
				<pre class="wbcn-mini"><?php echo esc_html( $row['text'] ); ?></pre>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wbcn_send_week' ); ?>
					<input type="hidden" name="action" value="wbcn_send_week">
					<input type="hidden" name="day_index" value="<?php echo (int) $i; ?>">
					<button class="button" <?php disabled( ! WBCN_Plugin::licensed() ); ?>>ارسال این روز</button>
				</form>
			</article>
		<?php endforeach; ?>
	</div>
</div>

<div class="wbcn-card">
	<h2>قواعد رشد کانال (کوتاه)</h2>
	<ol class="wbcn-rules">
		<li>هر مطلب سایت را حداقل ۳ بار در کانال خرج کنید: نکته، چک‌لیست، سؤال.</li>
		<li>لینک سایت را آخر بگذارید؛ ارزش را اول بدهید.</li>
		<li>یک روز در میان سؤال بپرسید تا کانال ساکت نماند.</li>
		<li>پست ذخیره شونده (چک‌لیست) عضو را نگه می‌دارد؛ پست خبری فقط دیده می‌شود.</li>
		<li>شورت‌کد <code>[webakery_channel]</code> را در فوتر یا مقاله بگذارید تا عضو جدید از سایت بیاید.</li>
	</ol>
</div>

<?php if ( $log ) : ?>
<div class="wbcn-card">
	<h2>گزارش ارسال</h2>
	<ul class="wbcn-log">
		<?php foreach ( array_slice( $log, 0, 12 ) as $row ) : ?>
			<li>
				<span><?php echo esc_html( date_i18n( 'Y/m/d H:i', (int) ( $row['t'] ?? 0 ) ) ); ?></span>
				<?php echo esc_html( (string) ( $row['m'] ?? '' ) ); ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>
