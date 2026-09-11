<?php
defined( 'ABSPATH' ) || exit;
$center = NCK_Learner::center_name();
$tag    = NCK_Learner::tagline();
$today  = NCK_Jalali::format( NCK_Jalali::today()['y'], NCK_Jalali::today()['m'], NCK_Jalali::today()['d'] );

$chips = static function ( $name, array $opts, $type = 'checkbox', $required = false ) {
	$i = 0;
	$suffix = 'checkbox' === $type ? '[]' : '';
	echo '<div class="nck-chips">';
	foreach ( $opts as $k => $label ) {
		$req = ( $required && 0 === $i ) ? ' required' : '';
		echo '<label class="nck-chip"><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name . $suffix ) . '" value="' . esc_attr( $k ) . '"' . $req . ' /> ' . esc_html( $label ) . '</label>';
		$i++;
	}
	echo '</div>';
};
?>
<div class="nck-root" dir="rtl" data-nck="learner">
	<form class="nck-form nck-wizard" data-nck-sign-learner data-nck-wizard novalidate>
		<header class="nck-paper-head nck-wizard-brand">
			<div class="nck-mark" aria-hidden="true">
				<svg viewBox="0 0 48 48" width="42" height="42"><path fill="currentColor" d="M24 4c1.2 6 3 10 8 14-6 1-10 4-12 10-2-6-6-9-12-10 5-4 6.8-8 8-14 2 5 4 8 8 10z"/><path fill="currentColor" opacity=".55" d="M24 28c2 6 5 10 12 14-8-1-14 1-16 8-2-7-8-9-16-8 7-4 10-8 12-14 2 4 4 6 8 8z"/></svg>
			</div>
			<div>
				<p class="nck-kicker"><?php echo esc_html( $center ); ?></p>
				<h2 class="nck-title">فرم پذیرش و شناخت فراگیر</h2>
				<p class="nck-tag"><?php echo esc_html( $tag ); ?></p>
			</div>
		</header>

		<?php include NCK_PATH . 'templates/wizard-head.php'; ?>

		<div class="nck-alert nck-alert-error" data-nck-error hidden></div>
		<div class="nck-alert nck-alert-ok" data-nck-ok hidden></div>

		<section class="nck-step" data-nck-step data-nck-step-label="آشنایی و پرداخت">
			<fieldset class="nck-fieldset">
				<legend>نحوه آشنایی با نهال</legend>
				<?php $chips( 'heard', NCK_Learner::heard_options() ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>وضعیت پرداخت</legend>
				<?php $chips( 'payment', NCK_Learner::payment_options(), 'radio', true ); ?>
				<div class="nck-grid nck-grid-hall" style="margin-top:12px">
					<div class="nck-field">
						<label for="nck-pay-amount">مبلغ پرداخت (تومان)</label>
						<input id="nck-pay-amount" name="pay_amount" type="text" dir="ltr" placeholder="مثلاً 2500000" />
						<p class="nck-note">اگر مبلغ را وارد کنید، سفارش در ووکامرس و حسابدار هم ثبت می‌شود.</p>
					</div>
					<div class="nck-field">
						<label for="nck-pay-date">تاریخ پرداخت</label>
						<input id="nck-pay-date" name="pay_date" type="text" dir="ltr" placeholder="1404/06/20" />
					</div>
					<div class="nck-field">
						<label for="nck-pay-ref">شماره پیگیری</label>
						<input id="nck-pay-ref" name="pay_ref" type="text" dir="ltr" />
					</div>
				</div>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>اطلاعات پذیرش</legend>
				<div class="nck-grid nck-grid-hall">
					<div class="nck-field">
						<label for="nck-learner-code">کد فراگیر</label>
						<input id="nck-learner-code" name="learner_code" type="text" />
					</div>
					<div class="nck-field">
						<label for="nck-admit-date">تاریخ پذیرش</label>
						<input id="nck-admit-date" name="admit_date" type="text" dir="ltr" value="<?php echo esc_attr( $today ); ?>" placeholder="1404/06/20" />
					</div>
					<div class="nck-field">
						<label for="nck-staff">مسئول پذیرش</label>
						<input id="nck-staff" name="staff_name" type="text" />
					</div>
					<div class="nck-field nck-field-wide">
						<label for="nck-class-limit">محدودیت سنی روز و ساعت کلاس فراگیر</label>
						<input id="nck-class-limit" name="class_limit" type="text" />
					</div>
				</div>
			</fieldset>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="اطلاعات فراگیر" hidden>
			<fieldset class="nck-fieldset">
				<legend>اطلاعات فراگیر</legend>
				<div class="nck-grid nck-grid-hall">
					<div class="nck-field nck-field-wide">
						<label for="nck-lr-name">نام و نام خانوادگی</label>
						<input id="nck-lr-name" name="name" type="text" autocomplete="name" required />
					</div>
					<div class="nck-field">
						<label for="nck-birth">تاریخ تولد</label>
						<input id="nck-birth" name="birth_date" type="text" dir="ltr" required placeholder="1392/06/20" />
					</div>
					<div class="nck-field">
						<label for="nck-lr-nid">کد ملی</label>
						<input id="nck-lr-nid" name="national_id" type="text" dir="ltr" inputmode="numeric" required maxlength="10" placeholder="۱۰ رقم" />
					</div>
					<div class="nck-field">
						<label for="nck-grade">پایه تحصیلی</label>
						<input id="nck-grade" name="grade" type="text" required />
					</div>
					<div class="nck-field">
						<label for="nck-school">مدرسه</label>
						<input id="nck-school" name="school" type="text" required />
					</div>
					<div class="nck-field">
						<label for="nck-lr-phone">شماره تماس</label>
						<input id="nck-lr-phone" name="phone" type="tel" dir="ltr" inputmode="numeric" placeholder="09123456789" />
					</div>
					<div class="nck-field nck-field-wide">
						<label for="nck-addr">آدرس</label>
						<input id="nck-addr" name="address" type="text" required />
					</div>
				</div>
			</fieldset>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="اطلاعات والدین" hidden>
			<div class="nck-parents">
				<fieldset class="nck-fieldset">
					<legend>پدر</legend>
					<div class="nck-field">
						<label for="nck-father-name">نام و نام خانوادگی</label>
						<input id="nck-father-name" name="father_name" type="text" />
					</div>
					<div class="nck-field">
						<label for="nck-father-job">شغل</label>
						<input id="nck-father-job" name="father_job" type="text" />
					</div>
					<div class="nck-field">
						<label for="nck-father-phone">شماره تماس</label>
						<input id="nck-father-phone" name="father_phone" type="tel" dir="ltr" inputmode="numeric" placeholder="09123456789" />
					</div>
				</fieldset>
				<fieldset class="nck-fieldset">
					<legend>مادر</legend>
					<div class="nck-field">
						<label for="nck-mother-name">نام و نام خانوادگی</label>
						<input id="nck-mother-name" name="mother_name" type="text" />
					</div>
					<div class="nck-field">
						<label for="nck-mother-job">شغل</label>
						<input id="nck-mother-job" name="mother_job" type="text" />
					</div>
					<div class="nck-field">
						<label for="nck-mother-phone">شماره تماس</label>
						<input id="nck-mother-phone" name="mother_phone" type="tel" dir="ltr" inputmode="numeric" placeholder="09123456789" />
					</div>
				</fieldset>
			</div>
			<p class="nck-form-help">نام حداقل یکی از والدین و یک شماره تماس معتبر لازم است.</p>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="دوره و شناخت" data-nck-require-one="term,seasonal" data-nck-require-msg="حداقل یک دوره ترمی یا فصلی را انتخاب کنید." hidden>
			<fieldset class="nck-fieldset">
				<legend>دوره‌های ترمی</legend>
				<?php $chips( 'term', NCK_Learner::term_options() ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>دوره‌های فصلی — نوع ثبت‌نام</legend>
				<?php $chips( 'seasonal', NCK_Learner::seasonal_options() ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>اطلاعات پزشکی — در صورت وجود علامت بزنید</legend>
				<?php $chips( 'medical', NCK_Learner::medical_options() ); ?>
				<div class="nck-field" style="margin-top:12px">
					<label for="nck-med-notes">توضیحات</label>
					<textarea id="nck-med-notes" name="medical_notes" rows="3"></textarea>
				</div>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>در کار گروهی معمولاً…</legend>
				<?php $chips( 'group_work', NCK_Learner::group_options(), 'radio', true ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>وقتی با مسئله جدید روبه‌رو می‌شود معمولاً…</legend>
				<?php $chips( 'problem', NCK_Learner::problem_options(), 'radio', true ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>سبک یادگیری فرزندتان — بیشتر چگونه یاد می‌گیرد؟</legend>
				<?php $chips( 'learning', NCK_Learner::learning_options(), 'radio', true ); ?>
			</fieldset>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="اهداف و امضا" data-nck-require-one="goals" data-nck-require-msg="حداقل یک هدف ثبت‌نام را انتخاب کنید." hidden>
			<fieldset class="nck-fieldset">
				<legend>هدف شما از ثبت‌نام در نهال و تعهدات</legend>
				<?php $chips( 'goals', NCK_Learner::goal_options() ); ?>
			</fieldset>
			<fieldset class="nck-fieldset">
				<legend>قوانین و تعهدات</legend>
				<label class="nck-agree">
					<input type="checkbox" name="agree_rules" value="1" required />
					قوانین آموزشی و انضباطی نهال را مطالعه و می‌پذیرم.
				</label>
				<label class="nck-agree">
					<input type="checkbox" name="agree_contact" value="1" required />
					مسئولیت به‌روزرسانی اطلاعات تماس و تغییرات را بر عهده می‌گیرم.
				</label>
				<label class="nck-agree">
					<input type="checkbox" name="agree_photo" value="1" required />
					استفاده از تصاویر فرزندم (عکس و فیلم) در کلاس‌ها توسط مجموعه نهال موافقم.
				</label>
			</fieldset>
			<?php
			$nck_sign_title = 'امضای والدین / سرپرست';
			$nck_sign_party = 'امضای والدین';
			include NCK_PATH . 'templates/sign-pad.php';
			?>
			<div class="nck-field">
				<label for="nck-sign-date">تاریخ</label>
				<input id="nck-sign-date" name="sign_date" type="text" dir="ltr" value="<?php echo esc_attr( $today ); ?>" placeholder="1404/06/20" />
			</div>
			<p class="nck-slogan"><?php echo esc_html( NCK_Learner::slogan() ); ?></p>
		</section>

		<nav class="nck-wizard-nav">
			<button type="button" class="nck-btn-ghost" data-nck-prev>مرحله قبل</button>
			<button type="button" class="nck-btn" data-nck-next>مرحله بعد</button>
			<button type="submit" class="nck-btn" data-nck-submit hidden>ثبت فرم پذیرش</button>
		</nav>
	</form>

	<div class="nck-done" data-nck-done hidden>
		<p class="nck-done-title">فرم پذیرش ثبت شد.</p>
		<p data-nck-done-msg></p>
		<p>
			<a class="nck-btn" data-nck-print-link href="#" target="_blank" rel="noopener">مشاهده و چاپ فرم</a>
		</p>
	</div>
</div>
