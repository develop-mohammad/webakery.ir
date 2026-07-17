<?php
defined( 'ABSPATH' ) || exit;
$s = NM_Settings::all();
$today = NM_Jalali::today();
$specialists = NM_Specialist::all_active();
$cats = NM_Questions::categories();
$pre_sp = (int) ( $atts['specialist_id'] ?? 0 );
?>
<div class="nm-app" dir="rtl" style="--nm-primary:<?php echo esc_attr( $s['primary_color'] ); ?>;--nm-accent:<?php echo esc_attr( $s['accent_color'] ); ?>">
	<div class="nm-hero">
		<div class="nm-hero-badge">رزرو نوبت مشاوره</div>
		<h2 class="nm-hero-title"><?php echo esc_html( $s['business_name'] ); ?></h2>
		<p class="nm-hero-sub">تقویم شمسی ایرانی · انتخاب آنلاین · پرداخت امن</p>
	</div>

	<div class="nm-steps">
		<button type="button" class="nm-step is-active" data-step="1">۱. متخصص</button>
		<button type="button" class="nm-step" data-step="2">۲. تاریخ</button>
		<button type="button" class="nm-step" data-step="3">۳. اطلاعات</button>
		<button type="button" class="nm-step" data-step="4">۴. تایید</button>
	</div>

	<form class="nm-form" id="nm-booking-form" enctype="multipart/form-data">
		<input type="hidden" name="specialist_id" id="nm-specialist-id" value="<?php echo esc_attr( $pre_sp ); ?>" />
		<input type="hidden" name="jalali_date" id="nm-jalali-date" value="" />
		<input type="hidden" name="start_time" id="nm-start-time" value="" />
		<input type="hidden" name="duration" id="nm-duration" value="<?php echo esc_attr( (int) $s['default_duration'] ); ?>" />

		<section class="nm-panel is-active" data-panel="1">
			<?php if ( count( $specialists ) > 1 || NM_Pro::is_active() ) : ?>
				<div class="nm-specialist-grid" id="nm-specialists">
					<?php if ( empty( $specialists ) ) : ?>
						<div class="nm-empty">هنوز متخصصی تعریف نشده — از پیشخوان یک متخصص اضافه کنید یا با قیمت پیش‌فرض ادامه دهید.</div>
						<button type="button" class="nm-btn nm-btn-primary nm-pick-sp" data-id="0">ادامه با نوبت عمومی</button>
					<?php else : foreach ( $specialists as $sp ) : ?>
						<button type="button" class="nm-sp-card nm-pick-sp<?php echo $pre_sp === (int) $sp->id ? ' is-selected' : ''; ?>" data-id="<?php echo (int) $sp->id; ?>" data-duration="<?php echo (int) $sp->duration; ?>">
							<div class="nm-sp-avatar"><?php echo $sp->avatar_id ? wp_get_attachment_image( $sp->avatar_id, 'thumbnail' ) : '<span>👤</span>'; ?></div>
							<div class="nm-sp-meta">
								<strong><?php echo esc_html( $sp->name ); ?></strong>
								<span><?php echo esc_html( $sp->skills ); ?></span>
								<em><?php echo esc_html( NM_Settings::format_price( $sp->price ) ); ?> · <?php echo (int) $sp->duration; ?> دقیقه</em>
							</div>
						</button>
					<?php endforeach; endif; ?>
				</div>
			<?php else :
				$sp = $specialists ? $specialists[0] : null;
				if ( $sp ) : ?>
					<input type="hidden" id="nm-auto-sp" value="<?php echo (int) $sp->id; ?>" data-duration="<?php echo (int) $sp->duration; ?>" />
					<div class="nm-sp-card is-selected">
						<strong><?php echo esc_html( $sp->name ); ?></strong>
						<span><?php echo esc_html( $sp->skills ); ?></span>
					</div>
				<?php else : ?>
					<input type="hidden" id="nm-auto-sp" value="0" data-duration="<?php echo (int) $s['default_duration']; ?>" />
					<div class="nm-empty">نوبت عمومی مشاوره</div>
				<?php endif; ?>
				<button type="button" class="nm-btn nm-btn-primary" data-next="2">انتخاب تاریخ</button>
			<?php endif; ?>
		</section>

		<section class="nm-panel" data-panel="2">
			<div class="nm-calendar-card">
				<div class="nm-cal-nav">
					<button type="button" id="nm-cal-prev" aria-label="ماه قبل">›</button>
					<strong id="nm-cal-label">—</strong>
					<button type="button" id="nm-cal-next" aria-label="ماه بعد">‹</button>
				</div>
				<div class="nm-cal-weekdays" id="nm-cal-weekdays"></div>
				<div class="nm-cal-grid" id="nm-cal-grid"></div>
			</div>
			<div class="nm-slots" id="nm-slots">
				<p class="nm-muted">پس از انتخاب روز، ساعات آزاد نمایش داده می‌شود.</p>
			</div>
			<div class="nm-actions">
				<button type="button" class="nm-btn nm-btn-ghost" data-prev="1">بازگشت</button>
				<button type="button" class="nm-btn nm-btn-primary" data-next="3" disabled id="nm-to-info">ادامه</button>
			</div>
		</section>

		<section class="nm-panel" data-panel="3">
			<div class="nm-fields">
				<label>نام و نام خانوادگی *
					<input type="text" name="customer_name" required autocomplete="name" />
				</label>
				<label>شماره تماس *
					<input type="tel" name="customer_phone" required autocomplete="tel" inputmode="tel" />
				</label>
				<label>ایمیل <?php echo ! empty( $s['require_email'] ) ? '*' : ''; ?>
					<input type="email" name="customer_email" <?php echo ! empty( $s['require_email'] ) ? 'required' : ''; ?> autocomplete="email" />
				</label>
				<label>شهر <?php echo ! empty( $s['require_city'] ) ? '*' : ''; ?>
					<input type="text" name="customer_city" <?php echo ! empty( $s['require_city'] ) ? 'required' : ''; ?> />
				</label>
				<label>جنسیت <?php echo ! empty( $s['require_gender'] ) ? '*' : ''; ?>
					<select name="customer_gender" <?php echo ! empty( $s['require_gender'] ) ? 'required' : ''; ?>>
						<option value="">انتخاب کنید</option>
						<option value="female">زن</option>
						<option value="male">مرد</option>
						<option value="other">سایر</option>
					</select>
				</label>
				<label>دسته‌بندی مشکل
					<select name="problem_category" id="nm-problem-cat">
						<option value="">انتخاب دسته‌بندی</option>
						<?php foreach ( $cats as $c ) : ?>
							<option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>

			<div id="nm-dynamic-questions" class="nm-questions"></div>

			<label class="nm-full">شرح مشکل
				<textarea name="description" rows="4" placeholder="توضیح کوتاه از موضوع مشاوره…"></textarea>
			</label>

			<div class="nm-uploads">
				<?php if ( ! empty( $s['enable_photo'] ) ) : ?>
				<label class="nm-upload">📷 ارسال عکس
					<input type="file" name="photo" accept="image/*" />
				</label>
				<?php endif; ?>
				<?php if ( ! empty( $s['enable_voice'] ) ) : ?>
				<label class="nm-upload">🎤 ارسال ویس
					<input type="file" name="voice" accept="audio/*,video/webm" />
				</label>
				<?php endif; ?>
			</div>

			<div class="nm-actions">
				<button type="button" class="nm-btn nm-btn-ghost" data-prev="2">بازگشت</button>
				<button type="button" class="nm-btn nm-btn-primary" data-next="4">بازبینی</button>
			</div>
		</section>

		<section class="nm-panel" data-panel="4">
			<div class="nm-summary" id="nm-summary"></div>
			<div class="nm-actions">
				<button type="button" class="nm-btn nm-btn-ghost" data-prev="3">بازگشت</button>
				<button type="submit" class="nm-btn nm-btn-primary nm-btn-pay" id="nm-submit">پرداخت و تایید نوبت</button>
			</div>
			<div class="nm-result" id="nm-result" hidden></div>
		</section>
	</form>
</div>
