<?php
defined( 'ABSPATH' ) || exit;
$s     = NCK_Settings::all();
$halls = NCK_Hall::parse_halls( $s['hall_list'] );
$note  = NCK_Hall::projector_note( (int) $s['projector_price'], (int) $s['projector_minutes'] );
$blank = '…………………….';
?>
<div class="nck-root" dir="rtl" data-nck="hall" data-ms-label="سرکارخانم">
	<form class="nck-form nck-wizard" data-nck-sign-hall data-nck-wizard novalidate>
		<header class="nck-paper-head nck-wizard-brand">
			<?php include NCK_PATH . 'templates/brand-mark.php'; ?>
			<div>
				<p class="nck-kicker">بسمه تعالی</p>
				<h2 class="nck-title">قرارداد اجاره سالن</h2>
				<p class="nck-tag"><?php echo esc_html( $s['hall_org'] ); ?></p>
			</div>
		</header>

		<?php include NCK_PATH . 'templates/wizard-head.php'; ?>

		<div class="nck-alert nck-alert-error" data-nck-error hidden></div>
		<div class="nck-alert nck-alert-ok" data-nck-ok hidden></div>

		<section class="nck-step" data-nck-step data-nck-step-label="برگزارکننده">
			<div class="nck-grid nck-grid-hall">
				<div class="nck-field">
					<label for="nck-hall-honorific">عنوان</label>
					<select id="nck-hall-honorific" name="honorific">
						<option value="mr">آقای</option>
						<option value="ms">سرکارخانم</option>
					</select>
				</div>
				<div class="nck-field nck-field-wide">
					<label for="nck-hall-name">نام برگزارکننده</label>
					<input id="nck-hall-name" name="name" type="text" required placeholder="مثال: علی رضایی" />
				</div>
				<div class="nck-field">
					<label for="nck-hall-nid">شماره ملی</label>
					<input id="nck-hall-nid" name="national_id" type="text" dir="ltr" inputmode="numeric" required maxlength="10" placeholder="۱۰ رقم" />
				</div>
				<div class="nck-field">
					<label for="nck-hall-phone">شماره تماس</label>
					<input id="nck-hall-phone" name="phone" type="tel" dir="ltr" inputmode="numeric" required placeholder="09123456789" />
				</div>
			</div>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="سالن و زمان" hidden>
			<div class="nck-grid nck-grid-hall">
				<div class="nck-field nck-field-wide">
					<label for="nck-hall-room">سالن</label>
					<input id="nck-hall-room" name="hall_name" type="text" list="nck-halls" required placeholder="نام سالن" />
					<datalist id="nck-halls">
						<?php foreach ( $halls as $h ) : ?>
							<option value="<?php echo esc_attr( $h ); ?>"><?php echo esc_html( $h ); ?></option>
						<?php endforeach; ?>
					</datalist>
				</div>
				<div class="nck-field">
					<label for="nck-hall-amount">مبلغ اجاره (تومان)</label>
					<input id="nck-hall-amount" name="amount" type="text" dir="ltr" inputmode="numeric" required placeholder="مثال: 5000000" />
				</div>
				<div class="nck-field">
					<label for="nck-hall-chairs">تعداد صندلی</label>
					<input id="nck-hall-chairs" name="chairs" type="number" min="1" required placeholder="۸۰" />
				</div>
				<?php include NCK_PATH . 'templates/hall-schedule.php'; ?>
				<div class="nck-field nck-field-wide">
					<label class="nck-agree" style="margin:0">
						<input type="checkbox" name="projector" value="1" />
						نیاز به ویدئو پروژکتور
					</label>
				</div>
			</div>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="امضا" hidden>
			<?php
			$nck_sign_title = 'امضای برگزارکننده مراسم';
			$nck_sign_party = 'امضای برگزارکننده';
			include NCK_PATH . 'templates/sign-pad.php';
			?>
		</section>

		<section class="nck-step" data-nck-step data-nck-step-label="مفاد قرارداد" hidden>
			<p class="nck-form-help">متن قرارداد با اطلاعات شما پر شده است. آن را بخوانید و فایل را دانلود کنید.</p>
			<p class="nck-review-actions">
				<button type="button" class="nck-btn" data-nck-download-review>دانلود قرارداد</button>
			</p>
			<article class="nck-paper nck-paper-nested" data-nck-review>
				<p class="nck-preamble">
					این قرارداد بین «<?php echo esc_html( $s['hall_org'] ); ?>» به عنوان طرف اول و
					<span class="nck-blank" data-nck-live="title" data-blank="آقای/سرکارخانم">آقای/سرکارخانم</span>
					<span class="nck-blank" data-nck-live="name" data-blank="<?php echo esc_attr( $blank ); ?>"><?php echo esc_html( $blank ); ?></span>
					به شماره ملی <span class="nck-blank" data-nck-live="national_id" data-blank="<?php echo esc_attr( $blank ); ?>"><?php echo esc_html( $blank ); ?></span>
					و شماره تماس <span class="nck-blank" data-nck-live="phone" data-blank="<?php echo esc_attr( $blank ); ?>"><?php echo esc_html( $blank ); ?></span>
					به عنوان طرف دوم منعقد می‌گردد.
				</p>
				<section class="nck-section">
					<h3>۱- موضوع قرارداد:</h3>
					<p>
						این قرارداد جهت اجاره سالن
						<span class="nck-blank" data-nck-live="hall_name" data-blank="<?php echo esc_attr( $blank ); ?>"><?php echo esc_html( $blank ); ?></span>
						با رعایت مقررات داخلی اتاق به مبلغ
						<span class="nck-blank" data-nck-live="amount" data-blank="<?php echo esc_attr( $blank ); ?>"><?php echo esc_html( $blank ); ?></span>
						منعقد گردید.
					</p>
				</section>
				<section class="nck-section">
					<h3>۲- مدت قرارداد:</h3>
					<p>
						مدت قرارداد از تاریخ
						<span class="nck-blank" data-nck-live="event_date" data-blank="…. / …. / ….">…. / …. / ….</span>
						ساعت <span class="nck-blank" data-nck-live="start_hour" data-blank="….">….</span>
						الی ساعت <span class="nck-blank" data-nck-live="end_hour" data-blank="….">….</span>
						می‌باشد.
					</p>
					<p><?php echo esc_html( $note ); ?></p>
				</section>
				<section class="nck-section">
					<h3>۴- سایر موارد:</h3>
					<p>۴-۱ امکانات سالن به تعداد <span class="nck-blank" data-nck-live="chairs" data-blank="….">….</span> عدد صندلی باشد.</p>
					<p>۴-۲ تهیه مواد مصرفی، پذیرایی به عهده برگزار کننده مراسم می‌باشد که در صورت نیاز با هماهنگی قبلی توسط کافه شاپ مجموعه قابل پیش‌بینی است. بدیهی است خدمات فوق بصورت جداگانه محاسبه و دریافت می‌گردد.</p>
					<p>۴-۳ پس از اخذ مبلغ ورودی تا ۳ روز قبل برگزاری، چنانچه برنامه کنسل گردد، ۱۳ درصد به عنوان خسارت و مالیات کسر خواهد شد، لذا کمتر از ۳ روز مانده به برگزاری مراسم، برنامه کنسل شود به هیچ عنوان وجهی مسترد نخواهد شد.</p>
					<p>۴-۴ برآورد و اعلام خسارات احتمالی و هرگونه ضرر و زیان وارده به سالن و امکانات آن به عهده طرف اول قرارداد بوده و طرف دوم تعهد می‌نماید در صورت ایجاد خسارت، ضرر و زیان آن را طبق برآورد طرف اول پرداخت نماید.</p>
					<p>۴-۵ اخذ کلیه مجوزهای قانونی جهت برگزاری مراسم (اماکن و …) بر عهده طرف دوم می‌باشد و در صورت لغو یا تعطیلی به هر دلیل طرف اول هیچ مسئولیتی در قبال استرداد وجه نخواهد داشت.</p>
					<p>۴-۶ برگزار کننده (طرف دوم) بایستی کلیه شئونات را طبق قوانین جمهوری اسلامی در حین برگزاری مراسم حفظ و از هرگونه بحث‌های سیاسی و مذهبی اجتناب نماید.</p>
					<p>۴-۷ امضاء کننده ذیل این قرارداد برگزار کننده مراسم بوده و حق واگذاری سالن را به شخص یا گروه و نهادی و اداره دیگری ندارد.</p>
					<p>۴-۸ توصیه می‌شود برگزارکننده مراسم قبل از عقد قرارداد حتماً از سالن و جزئیات مورد نظر بازدید بعمل آورده و پیش‌بینی‌های لازم را در کلیه موارد بنماید.</p>
				</section>
				<div class="nck-sign-plate nck-sign-plate-review">
					<p class="nck-sign-plate-kicker">امضای برگزارکننده</p>
					<div class="nck-sign-plate-stage">
						<img class="nck-sign-plate-ink" data-nck-review-ink alt="امضا" hidden />
						<span class="nck-sign-plate-empty" data-nck-review-empty>امضا از مرحله قبل اینجاست.</span>
						<span class="nck-sign-plate-line" aria-hidden="true"></span>
					</div>
					<div class="nck-sign-plate-meta">
						<span>امضای برگزارکننده</span>
						<strong data-nck-live="name">…</strong>
					</div>
				</div>
			</article>
			<label class="nck-agree">
				<input type="checkbox" name="agree" value="1" required />
				مفاد قرارداد را خواندم، فایل را در صورت نیاز دانلود کرده‌ام و شرایط اجارهٔ سالن را می‌پذیرم.
			</label>
		</section>

		<?php
		$nck_pay_id       = 'nck-hall-pay';
		$nck_pay_amount   = '';
		$nck_pay_required = true;
		$nck_pay_from     = 'amount';
		include NCK_PATH . 'templates/pay-step.php';
		?>

		<nav class="nck-wizard-nav">
			<button type="button" class="nck-btn-ghost" data-nck-prev>مرحله قبل</button>
			<button type="button" class="nck-btn" data-nck-next>مرحله بعد</button>
			<button type="submit" class="nck-btn" data-nck-submit hidden>ثبت و پرداخت در سایت</button>
		</nav>
	</form>

	<div class="nck-done" data-nck-done hidden>
		<p class="nck-done-title">قرارداد اجاره سالن ثبت شد.</p>
		<p data-nck-done-msg></p>
		<p>
			<a class="nck-btn" data-nck-print-link href="#" target="_blank" rel="noopener">دانلود و چاپ قرارداد</a>
			<a class="nck-btn nck-btn-ghost" data-nck-pay-link href="#" hidden>پرداخت در سایت</a>
		</p>
	</div>
</div>
