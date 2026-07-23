<?php
defined( 'ABSPATH' ) || exit;
$conv_id = isset( $_GET['conv'] ) ? (int) $_GET['conv'] : 0; // phpcs:ignore
?>
<div class="wrap wbcb-wrap" dir="rtl">
	<div class="wbcb-top">
		<div>
			<h1>چت باکس — صندوق پیام</h1>
			<p class="description">پیام‌های بازدیدکنندگان سایت اینجا می‌آید. پاسخ دهید یا گفتگو را ببندید.</p>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=webakery-chat-box-settings' ) ); ?>">تنظیمات ویجت</a>
	</div>

	<div class="wbcb-inbox" id="wbcb-inbox">
		<aside class="wbcb-sidebar">
			<div class="wbcb-sidebar-tools">
				<input type="search" id="wbcb-search" class="wbcb-search" placeholder="جستجو نام / ایمیل…" />
				<select id="wbcb-filter-status">
					<option value="">همه</option>
					<option value="open">باز</option>
					<option value="closed">بسته</option>
				</select>
			</div>
			<ul class="wbcb-conv-list" id="wbcb-conv-list"></ul>
		</aside>
		<section class="wbcb-thread" id="wbcb-thread">
			<div class="wbcb-thread-empty" id="wbcb-thread-empty">
				<p>یک گفتگو از لیست انتخاب کنید.</p>
			</div>
			<div class="wbcb-thread-active" id="wbcb-thread-active" hidden>
				<header class="wbcb-thread-head">
					<div>
						<strong id="wbcb-thread-name">—</strong>
						<span class="wbcb-thread-meta" id="wbcb-thread-meta"></span>
					</div>
					<div class="wbcb-thread-actions">
						<a class="button button-small" id="wbcb-thread-page" href="#" target="_blank" rel="noopener">صفحه بازدید</a>
						<button type="button" class="button button-small" id="wbcb-thread-close">بستن گفتگو</button>
					</div>
				</header>
				<div class="wbcb-thread-messages" id="wbcb-thread-messages"></div>
				<form class="wbcb-thread-form" id="wbcb-thread-form">
					<textarea id="wbcb-thread-input" rows="2" placeholder="پاسخ شما…"></textarea>
					<button type="submit" class="button button-primary">ارسال پاسخ</button>
				</form>
			</div>
		</section>
	</div>
</div>
