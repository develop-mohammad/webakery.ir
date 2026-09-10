<?php
defined( 'ABSPATH' ) || exit;
?>
<form method="post" class="did-form">
	<?php wp_nonce_field( 'did_settings' ); ?>
	<input type="hidden" name="did_save_settings" value="1" />

	<h2 class="did-h2">بینگ</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="bing_api_key">کلید Azure Bing Search</label></th>
			<td>
				<input id="bing_api_key" type="password" class="large-text" dir="ltr" name="settings[bing_api_key]" value="<?php echo esc_attr( $s['bing_api_key'] ); ?>" autocomplete="new-password" />
				<p class="description">از پورتال Azure، سرویس Bing Search v7. بازار پیش‌فرض: fa-IR (ایران/فارسی).</p>
			</td>
		</tr>
	</table>

	<h2 class="did-h2">گوگل</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="google_serp_provider">ارائه‌دهنده</label></th>
			<td>
				<select id="google_serp_provider" name="settings[google_serp_provider]">
					<option value="none" <?php selected( $s['google_serp_provider'], 'none' ); ?>>غیرفعال</option>
					<option value="serpapi" <?php selected( $s['google_serp_provider'], 'serpapi' ); ?>>SerpAPI (رتبهٔ ارگانیک)</option>
					<option value="dataforseo" <?php selected( $s['google_serp_provider'], 'dataforseo' ); ?>>DataForSEO (رتبهٔ ارگانیک)</option>
					<option value="cse" <?php selected( $s['google_serp_provider'], 'cse' ); ?>>Google CSE (تقریبی — رتبهٔ واقعی نیست)</option>
				</select>
			</td>
		</tr>
		<tr class="did-prov did-prov-serpapi">
			<th><label for="serpapi_key">کلید SerpAPI</label></th>
			<td><input id="serpapi_key" type="password" class="large-text" dir="ltr" name="settings[serpapi_key]" value="<?php echo esc_attr( $s['serpapi_key'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr class="did-prov did-prov-dataforseo">
			<th><label for="dataforseo_login">ورود DataForSEO</label></th>
			<td><input id="dataforseo_login" type="text" class="regular-text" dir="ltr" name="settings[dataforseo_login]" value="<?php echo esc_attr( $s['dataforseo_login'] ); ?>" /></td>
		</tr>
		<tr class="did-prov did-prov-dataforseo">
			<th><label for="dataforseo_password">رمز DataForSEO</label></th>
			<td><input id="dataforseo_password" type="password" class="regular-text" dir="ltr" name="settings[dataforseo_password]" value="<?php echo esc_attr( $s['dataforseo_password'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr class="did-prov did-prov-cse">
			<th><label for="google_cse_key">کلید Google CSE</label></th>
			<td><input id="google_cse_key" type="password" class="large-text" dir="ltr" name="settings[google_cse_key]" value="<?php echo esc_attr( $s['google_cse_key'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr class="did-prov did-prov-cse">
			<th><label for="google_cse_cx">شناسه موتور CSE (cx)</label></th>
			<td><input id="google_cse_cx" type="text" class="regular-text" dir="ltr" name="settings[google_cse_cx]" value="<?php echo esc_attr( $s['google_cse_cx'] ); ?>" /></td>
		</tr>
	</table>

	<h2 class="did-h2">موبایل و شهرهای ایران</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="rank_device">دیوایس نتایج</label></th>
			<td>
				<select id="rank_device" name="settings[rank_device]">
					<option value="mobile" <?php selected( $s['rank_device'], 'mobile' ); ?>>موبایل</option>
					<option value="desktop" <?php selected( $s['rank_device'], 'desktop' ); ?>>دسکتاپ</option>
				</select>
				<p class="description">برای دیدن رشد/افت در گوشی، روی موبایل بگذارید. گوگل با SerpAPI/DataForSEO دقیق است؛ بینگ با موقعیت جغرافیایی Azure.</p>
			</td>
		</tr>
		<tr>
			<th>شهرها</th>
			<td>
				<input type="hidden" name="settings[rank_cities][]" value="" />
				<div class="did-cities">
					<?php
					$selected = DID_Settings::cities();
					foreach ( DID_Geo::cities() as $slug => $info ) :
						?>
						<label class="did-city-check">
							<input type="checkbox" name="settings[rank_cities][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected, true ) ); ?> />
							<?php echo esc_html( $info['fa'] ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description">هر شهر یک درخواست API جدا برای هر کیورد است. شهرهای زیاد یعنی هزینهٔ بیشتر.</p>
			</td>
		</tr>
	</table>

	<h2 class="did-h2">کرول و زمان‌بندی</h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="max_pages">سقف صفحه در هر دامنه</label></th>
			<td><input id="max_pages" type="number" min="5" max="100" name="settings[max_pages]" value="<?php echo (int) $s['max_pages']; ?>" /></td>
		</tr>
		<tr>
			<th><label for="crawl_delay_ms">تأخیر بین درخواست‌ها (میلی‌ثانیه)</label></th>
			<td><input id="crawl_delay_ms" type="number" min="200" max="5000" name="settings[crawl_delay_ms]" value="<?php echo (int) $s['crawl_delay_ms']; ?>" /></td>
		</tr>
		<tr>
			<th><label for="rank_depth">عمق نتایج رتبه</label></th>
			<td><input id="rank_depth" type="number" min="10" max="50" name="settings[rank_depth]" value="<?php echo (int) $s['rank_depth']; ?>" /></td>
		</tr>
		<tr>
			<th><label for="market">بازار بینگ</label></th>
			<td><input id="market" type="text" class="regular-text" dir="ltr" name="settings[market]" value="<?php echo esc_attr( $s['market'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="country">کشور گوگل (gl)</label></th>
			<td><input id="country" type="text" class="small-text" dir="ltr" name="settings[country]" value="<?php echo esc_attr( $s['country'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="schedule">اجرای روزانه</label></th>
			<td>
				<select id="schedule" name="settings[schedule]">
					<option value="off" <?php selected( $s['schedule'], 'off' ); ?>>خاموش</option>
					<option value="daily" <?php selected( $s['schedule'], 'daily' ); ?>>روزانه (WP-Cron)</option>
				</select>
			</td>
		</tr>
	</table>

	<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
</form>
