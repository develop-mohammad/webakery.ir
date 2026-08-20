(function () {
	'use strict';

	var steps = Array.prototype.slice.call(document.querySelectorAll('.step'));
	var segs = Array.prototype.slice.call(document.querySelectorAll('.stepper .seg'));
	var stepLabel = document.getElementById('step_label');
	var total = steps.length;

	// PHP از قبل مرحله‌ی درست را با کلاس active مشخص کرده (مثلاً بعد از خطای سرور)
	var current = 0;
	for (var si = 0; si < steps.length; si++) {
		if (steps[si].classList.contains('active')) { current = si; break; }
	}
	var firstPaint = true;

	function fmtStepText(i) {
		var fa = ['۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
		return 'مرحله ' + (fa[i] || (i + 1)) + ' از ' + (fa[total - 1] || total);
	}

	function render() {
		steps.forEach(function (s, i) { s.classList.toggle('active', i === current); });
		segs.forEach(function (s, i) { s.classList.toggle('done', i <= current); });
		if (stepLabel) stepLabel.textContent = fmtStepText(current);
		var activeStep = steps[current];
		if (activeStep) {
			var firstInput = activeStep.querySelector('input, textarea, select');
			if (firstInput) { try { firstInput.focus({ preventScroll: true }); } catch (e) {} }
		}
		if (!firstPaint) {
			var card = document.querySelector('.card');
			if (card) window.scrollTo({ top: card.offsetTop - 20, behavior: 'smooth' });
		}
		firstPaint = false;
		buildSummary();
	}

	function clearError(field) {
		field.classList.remove('has-error');
		var msg = field.querySelector('.err-msg');
		if (msg) msg.style.display = 'none';
	}

	function setError(field, text) {
		field.classList.add('has-error');
		var msg = field.querySelector('.err-msg');
		if (msg) { msg.textContent = text; msg.style.display = 'block'; }
	}

	function validateStep(index) {
		var ok = true;
		var scope = steps[index];
		var fields = scope.querySelectorAll('[data-required], [required]');
		var handledRadioGroups = {};

		fields.forEach(function (input) {
			var field = input.closest('.field');
			if (!field) return;

			if (input.type === 'radio') {
				if (handledRadioGroups[input.name]) return;
				handledRadioGroups[input.name] = true;
				var anyChecked = !!scope.querySelector('input[name="' + input.name + '"]:checked');
				if (!anyChecked) {
					setError(field, input.dataset.errorMsg || 'یکی از گزینه‌ها را انتخاب کنید.');
					ok = false;
				} else {
					clearError(field);
				}
				return;
			}

			var val = (input.value || '').trim();
			var valid = true;

			if (input.type === 'checkbox') {
				valid = input.checked;
			} else if (val === '') {
				valid = false;
			} else if (input.dataset.pattern) {
				valid = new RegExp(input.dataset.pattern).test(toEnglishDigits(val));
			} else if (input.type === 'email') {
				valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
			}

			if (!valid) {
				setError(field, input.dataset.errorMsg || 'این فیلد را کامل و صحیح وارد کنید.');
				ok = false;
			} else {
				clearError(field);
			}
		});
		return ok;
	}

	function toEnglishDigits(s) {
		var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
		return String(s).replace(/[۰-۹]/g, function (d) { return fa.indexOf(d); })
			.replace(/[٠-٩]/g, function (d) { return ar.indexOf(d); });
	}

	document.querySelectorAll('.js-next').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!validateStep(current)) return;
			if (current < total - 1) { current++; render(); }
		});
	});

	document.querySelectorAll('.js-back').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (current > 0) { current--; render(); }
		});
	});

	// رادیو کارت‌ها: هایلایت انتخاب‌شده
	document.querySelectorAll('.radio-card').forEach(function (card) {
		var input = card.querySelector('input[type=radio]');
		if (!input) return;
		input.addEventListener('change', function () {
			var groupName = input.name;
			document.querySelectorAll('input[name="' + groupName + '"]').forEach(function (r) {
				var c = r.closest('.radio-card');
				if (c) c.classList.toggle('is-active', r.checked);
			});
		});
		if (input.checked) card.classList.add('is-active');
	});

	function buildSummary() {
		var box = document.getElementById('review_summary');
		if (!box || current !== total - 1) return;
		var rows = [
			['full_name', '👤 نام و نام خانوادگی'],
			['business_name', '🏪 نام کسب‌وکار / فروشگاه'],
			['mobile', '📱 موبایل متقاضی'],
			['landline', '☎️ تلفن ثابت'],
			['email', '📧 ایمیل'],
			['postal_code', '📮 کد پستی'],
			['website', '🌐 آدرس وب‌سایت'],
			['tax_code', '🧾 کد رهگیری پرونده مالیاتی']
		];
		var html = '';
		rows.forEach(function (r) {
			var el = document.querySelector('[name="' + r[0] + '"]');
			var val = el ? (el.value || '').trim() : '';
			if (val === '') val = '—';
			html += '<div class="row"><span class="k">' + r[1] + '</span><span class="v">' + escapeHtml(val) + '</span></div>';
		});
		var accessInput = document.querySelector('input[name="access_type"]:checked');
		html += '<div class="row"><span class="k">🔑 نوع دسترسی</span><span class="v">' + (accessInput ? escapeHtml(accessInput.parentElement.querySelector('.rt').textContent) : '—') + '</span></div>';
		box.innerHTML = html;
	}

	function escapeHtml(s) {
		var d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	// فرم نهایی: قبل از submit تمام مراحل را دوباره چک کن
	var form = document.getElementById('eo_form');
	if (form) {
		form.addEventListener('submit', function (e) {
			for (var i = 0; i < total; i++) {
				if (!validateStep(i)) {
					current = i;
					render();
					e.preventDefault();
					return;
				}
			}
		});
	}

	render();

	var copyBtn = document.getElementById('copy_share_link');
	if (copyBtn && navigator.clipboard) {
		copyBtn.addEventListener('click', function () {
			navigator.clipboard.writeText(window.location.href).then(function () {
				copyBtn.textContent = '✓ لینک کپی شد';
				setTimeout(function () { copyBtn.textContent = '🔗 کپی لینک صفحه'; }, 1800);
			});
		});
	}
})();
