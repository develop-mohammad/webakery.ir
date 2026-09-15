<?php
defined( 'ABSPATH' ) || exit;
$tg     = class_exists( 'WBE_Support' ) ? WBE_Support::telegram_handle() : 'HAJITODAY';
$tg_url = class_exists( 'WBE_Support' ) ? WBE_Support::telegram_chat_url() : 'https://t.me/HAJITODAY';
?>
<button type="button" class="button wbe-bug-fab wbe-bug-open" title="گزارش باگ">گزارش باگ</button>
<div id="wbe-bug-modal" class="wbe-bug-modal" hidden dir="rtl">
	<div class="wbe-bug-modal__backdrop" data-wbe-bug-close></div>
	<div class="wbe-bug-modal__box" role="dialog" aria-labelledby="wbe-bug-title">
		<div class="wbe-bug-modal__head">
			<strong id="wbe-bug-title">گزارش باگ</strong>
			<button type="button" class="button-link wbe-bug-modal__x" data-wbe-bug-close>بستن</button>
		</div>
		<p class="description">توضیح و اسکرین را بنویسید. ارسال به تلگرام <a href="<?php echo esc_url( $tg_url ); ?>" target="_blank" rel="noopener">@<?php echo esc_html( $tg ); ?></a> می‌رود.</p>
		<p>
			<label for="wbe-bug-desc"><strong>توضیح باگ</strong></label>
			<textarea id="wbe-bug-desc" rows="5" class="large-text" placeholder="چه صفحه‌ای، چه کردید، چه شد؟"></textarea>
		</p>
		<p class="wbe-bug-actions">
			<button type="button" class="button" id="wbe-bug-capture">گرفتن اسکرین همین صفحه</button>
			<label class="button">
				انتخاب تصویر
				<input type="file" id="wbe-bug-file" accept="image/*" hidden />
			</label>
		</p>
		<p id="wbe-bug-shot-wrap" class="wbe-bug-shot" hidden>
			<img id="wbe-bug-shot" alt="پیش‌نمایش اسکرین" />
			<button type="button" class="button-link" id="wbe-bug-shot-clear">حذف تصویر</button>
		</p>
		<p id="wbe-bug-status" class="wbe-muted"></p>
		<p>
			<button type="button" class="button button-primary" id="wbe-bug-send">ارسال به تلگرام @<?php echo esc_html( $tg ); ?></button>
		</p>
	</div>
</div>
