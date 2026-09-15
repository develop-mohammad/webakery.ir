<?php
defined( 'ABSPATH' ) || exit;
?>
<form method="post" class="did-form">
	<?php wp_nonce_field( 'did_settings' ); ?>
	<input type="hidden" name="did_save_settings" value="1" />

	<table class="form-table" role="presentation">
		<tr>
			<th><label for="bing_api_key">کلید Bing</label></th>
			<td>
				<input id="bing_api_key" type="password" class="large-text" dir="ltr" name="settings[bing_api_key]" value="<?php echo esc_attr( $s['bing_api_key'] ); ?>" autocomplete="new-password" />
				<p class="description">Azure Bing Search v7 · بازار: fa-IR</p>
			</td>
		</tr>
		<tr>
			<th><label for="google_serp_provider">گوگل</label></th>
			<td>
				<select id="google_serp_provider" name="settings[google_serp_provider]">
					<option value="none" <?php selected( $s['google_serp_provider'], 'none' ); ?>>غیرفعال</option>
					<option value="serpapi" <?php selected( $s['google_serp_provider'], 'serpapi' ); ?>>SerpAPI</option>
					<option value="dataforseo" <?php selected( $s['google_serp_provider'], 'dataforseo' ); ?>>DataForSEO</option>
					<option value="cse" <?php selected( $s['google_serp_provider'], 'cse' ); ?>>Google CSE (تقریبی)</option>
				</select>
			</td>
		</tr>
		<tr class="did-prov did-prov-serpapi">
			<th><label for="serpapi_key">کلید SerpAPI</label></th>
			<td><input id="serpapi_key" type="password" class="large-text" dir="ltr" name="settings[serpapi_key]" value="<?php echo esc_attr( $s['serpapi_key'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr class="did-prov did-prov-cse">
			<th><label for="google_cse_key">کلید CSE</label></th>
			<td><input id="google_cse_key" type="password" class="large-text" dir="ltr" name="settings[google_cse_key]" value="<?php echo esc_attr( $s['google_cse_key'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr class="did-prov did-prov-cse">
			<th><label for="google_cse_cx">شناسه CSE</label></th>
			<td><input id="google_cse_cx" type="text" class="regular-text" dir="ltr" name="settings[google_cse_cx]" value="<?php echo esc_attr( $s['google_cse_cx'] ); ?>" /></td>
		</tr>
		<tr>
			<th><label for="dataforseo_login">ورود DataForSEO</label></th>
			<td>
				<input id="dataforseo_login" type="text" class="regular-text" dir="ltr" name="settings[dataforseo_login]" value="<?php echo esc_attr( $s['dataforseo_login'] ); ?>" />
				<p class="description">برای رتبهٔ گوگل (اگر ارائه‌دهنده DataForSEO باشد) و برای بک‌لینک.</p>
			</td>
		</tr>
		<tr>
			<th><label for="dataforseo_password">رمز DataForSEO</label></th>
			<td><input id="dataforseo_password" type="password" class="regular-text" dir="ltr" name="settings[dataforseo_password]" value="<?php echo esc_attr( $s['dataforseo_password'] ); ?>" autocomplete="new-password" /></td>
		</tr>
		<tr>
			<th><label for="rank_device">نتایج</label></th>
			<td>
				<select id="rank_device" name="settings[rank_device]">
					<option value="desktop" <?php selected( $s['rank_device'], 'desktop' ); ?>>دسکتاپ (گوگل / بینگ)</option>
					<option value="mobile" <?php selected( $s['rank_device'], 'mobile' ); ?>>موبایل</option>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="rank_depth">عمق نتایج</label></th>
			<td><input id="rank_depth" type="number" min="10" max="50" name="settings[rank_depth]" value="<?php echo (int) $s['rank_depth']; ?>" /></td>
		</tr>
		<tr>
			<th><label for="max_pages">سقف کرول</label></th>
			<td><input id="max_pages" type="number" min="5" max="100" name="settings[max_pages]" value="<?php echo (int) $s['max_pages']; ?>" /></td>
		</tr>
		<tr>
			<th><label for="schedule">اجرای روزانه</label></th>
			<td>
				<select id="schedule" name="settings[schedule]">
					<option value="off" <?php selected( $s['schedule'], 'off' ); ?>>خاموش</option>
					<option value="daily" <?php selected( $s['schedule'], 'daily' ); ?>>روزانه</option>
				</select>
			</td>
		</tr>
	</table>

	<p><button type="submit" class="button button-primary">ذخیره تنظیمات</button></p>
</form>
