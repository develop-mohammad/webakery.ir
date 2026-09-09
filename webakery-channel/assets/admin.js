(function () {
	var post = document.getElementById('wbcn-post');
	var chips = document.getElementById('wbcn-formats');
	var text = document.getElementById('wbcn-text');
	var hint = document.getElementById('wbcn-format-hint');
	var postId = document.getElementById('wbcn-post-id');
	var formatEl = document.getElementById('wbcn-format');
	var copyBtn = document.getElementById('wbcn-copy');
	if (!text || typeof wbcnAdmin === 'undefined') return;

	var hints = {
		tip: 'یک برداشت سریع برای اسکرول‌کردن — ذخیره و بازنشر بالا',
		checklist: 'کاربر ذخیره می‌کند و برمی‌گردد — سیو و اشتراک در گروه‌ها',
		question: 'نظر گرفتن یعنی دیده شدن در فید — کامنت و بحث در کانال',
		mistake: 'کنجکاوی + حس «من هم این کار را می‌کردم»',
		summary: 'ارزش کامل بدون خروج از تلگرام — اعتماد و کلیک روی لینک',
		quote: 'کوتاه، قابل اسکرین‌شات — رشد ارگانیک با فوروارد',
		before_after: 'مشکل → راه‌حل از دل مطلب — مناسب فروش',
		cta: 'قلاب + لینک سایت — ترافیک به وردپرس',
		myth: 'چالش ذهن مخاطب متخصص — بحث و اعتبار',
		thread: 'یک مطلب = سه پست پشت سر هم — حضور بیشتر در فید'
	};

	function load() {
		var fd = new FormData();
		fd.append('action', 'wbcn_preview');
		fd.append('nonce', wbcnAdmin.nonce);
		fd.append('post_id', post ? post.value : '0');
		fd.append('format', formatEl ? formatEl.value : 'tip');
		fetch(wbcnAdmin.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.success && data.data && data.data.text) {
					text.value = data.data.text;
				}
			});
	}

	if (post) {
		post.addEventListener('change', function () {
			if (postId) postId.value = post.value;
			load();
		});
	}
	if (chips) {
		chips.addEventListener('click', function (e) {
			var btn = e.target.closest('.wbcn-chip');
			if (!btn) return;
			chips.querySelectorAll('.wbcn-chip').forEach(function (c) { c.classList.remove('is-on'); });
			btn.classList.add('is-on');
			var f = btn.getAttribute('data-format');
			if (formatEl) formatEl.value = f;
			if (hint && hints[f]) hint.textContent = hints[f];
			load();
		});
	}
	if (copyBtn) {
		copyBtn.addEventListener('click', function () {
			text.select();
			document.execCommand('copy');
			copyBtn.textContent = 'کپی شد';
			setTimeout(function () { copyBtn.textContent = 'کپی متن'; }, 1500);
		});
	}
})();
