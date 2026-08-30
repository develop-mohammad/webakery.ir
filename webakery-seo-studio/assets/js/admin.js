(function () {
	'use strict';

	var state = {
		view: 'dashboard',
		projectId: 0,
		projects: [],
		days: 30,
		keywords: [],
	};

	function $(sel, root) {
		return (root || document).querySelector(sel);
	}

	function el(html) {
		var t = document.createElement('template');
		t.innerHTML = html.trim();
		return t.content.firstElementChild;
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function faDate(g) {
		if (!g) return '—';
		var p = String(g).slice(0, 10).split('-');
		if (p.length < 3) return esc(g);
		var gy = +p[0], gm = +p[1], gd = +p[2];
		var g_y_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
		var jy = gy <= 1600 ? 0 : 979;
		gy -= gy <= 1600 ? 621 : 1600;
		var gy2 = gm > 2 ? gy + 1 : gy;
		var days = 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + g_y_m[gm - 1];
		jy += 33 * Math.floor(days / 12053);
		days %= 12053;
		jy += 4 * Math.floor(days / 1461);
		days %= 1461;
		if (days > 365) {
			jy += Math.floor((days - 1) / 365);
			days = (days - 1) % 365;
		}
		var jmDays = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
		var jm = 0;
		for (; jm < 11 && days >= jmDays[jm]; jm++) days -= jmDays[jm];
		var jd = days + 1;
		return jy + '/' + String(jm + 1).padStart(2, '0') + '/' + String(jd).padStart(2, '0');
	}

	function num(n) {
		return (n == null ? 0 : n).toLocaleString('fa-IR');
	}

	function post(action, data) {
		if (window.WbssDemo && typeof window.WbssDemo.handle === 'function') {
			return Promise.resolve(window.WbssDemo.handle(action, Object.assign({
				project_id: state.projectId,
				days: state.days,
			}, data || {})));
		}
		var body = new URLSearchParams();
		body.set('action', action);
		body.set('nonce', WBSS.nonce);
		body.set('project_id', String(state.projectId || 0));
		body.set('days', String(state.days));
		Object.keys(data || {}).forEach(function (k) {
			var v = data[k];
			if (v && typeof v === 'object' && !(v instanceof File)) {
				Object.keys(v).forEach(function (sk) {
					body.set(k + '[' + sk + ']', v[sk] == null ? '' : String(v[sk]));
				});
			} else {
				body.set(k, v == null ? '' : String(v));
			}
		});
		return fetch(WBSS.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		}).then(function (r) { return r.json(); });
	}

	function toast(msg, bad) {
		var old = $('.wbss-toast');
		if (old) old.remove();
		var n = el('<div class="wbss-toast' + (bad ? ' is-bad' : '') + '">' + esc(msg) + '</div>');
		document.body.appendChild(n);
		setTimeout(function () { n.remove(); }, 2800);
	}

	function pill(text, cls) {
		return '<span class="wbss-pill ' + (cls || '') + '">' + esc(text) + '</span>';
	}

	function rankCell(pos) {
		pos = parseInt(pos, 10) || 0;
		if (!pos || pos >= 101) return '<span class="wbss-muted">خارج از ۱۰۰</span>';
		return '<strong class="wbss-rank">' + pos + '</strong>';
	}

	function changeCell(ch) {
		ch = parseInt(ch, 10) || 0;
		if (!ch) return '<span class="wbss-muted">۰</span>';
		if (ch > 0) return '<span class="wbss-up">▲ ' + ch + '</span>';
		return '<span class="wbss-down">▼ ' + Math.abs(ch) + '</span>';
	}

	function table(headers, rowsHtml) {
		return '<div class="wbss-table-wrap"><table class="wbss-table"><thead><tr>' +
			headers.map(function (h) { return '<th>' + h + '</th>'; }).join('') +
			'</tr></thead><tbody>' + (rowsHtml || '<tr><td colspan="' + headers.length + '" class="wbss-empty">' + WBSS.i18n.empty + '</td></tr>') +
			'</tbody></table></div>';
	}

	function card(title, body, extra) {
		return '<section class="wbss-card' + (extra || '') + '"><header class="wbss-card-h"><h3>' + title + '</h3></header><div class="wbss-card-b">' + body + '</div></section>';
	}

	/* ─── fields ─── */
	var FIELDS = {
		keywords: [
			{ name: 'keyword', label: 'کیورد', type: 'text', required: true },
			{ name: 'intent', label: 'نیت جستجو', type: 'select', opts: 'intent' },
			{ name: 'volume', label: 'حجم جستجو', type: 'number' },
			{ name: 'difficulty', label: 'سختی (۰–۱۰۰)', type: 'number' },
			{ name: 'page_url', label: 'آدرس صفحه هدف', type: 'url' },
			{ name: 'position', label: 'رتبه فعلی (اختیاری)', type: 'number' },
			{ name: 'checked_at', label: 'تاریخ چک رتبه', type: 'text', placeholder: '۱۴۰۵/۰۶/۰۸ یا 2026-08-30' },
			{ name: 'status', label: 'وضعیت', type: 'select', opts: 'kw_status' },
			{ name: 'notes', label: 'یادداشت ریسرچ', type: 'textarea' },
		],
		content: [
			{ name: 'title', label: 'عنوان محتوا', type: 'text', required: true },
			{ name: 'url', label: 'آدرس', type: 'url' },
			{ name: 'keyword_id', label: 'کیورد هدف', type: 'keyword' },
			{ name: 'word_count', label: 'تعداد کلمه', type: 'number' },
			{ name: 'status', label: 'وضعیت', type: 'select', opts: 'content_st' },
			{ name: 'published_at', label: 'تاریخ انتشار', type: 'text', placeholder: '۱۴۰۵/۰۶/۰۱' },
			{ name: 'notes', label: 'یادداشت', type: 'textarea' },
		],
		technical: [
			{ name: 'title', label: 'عنوان اقدام', type: 'text', required: true },
			{ name: 'category', label: 'دسته', type: 'select', opts: 'tech_cat' },
			{ name: 'severity', label: 'شدت', type: 'select', opts: 'severity' },
			{ name: 'status', label: 'وضعیت', type: 'select', opts: 'tech_st' },
			{ name: 'page_url', label: 'صفحه', type: 'url' },
			{ name: 'notes', label: 'توضیح', type: 'textarea' },
		],
		backlinks: [
			{ name: 'source_url', label: 'آدرس منبع', type: 'url', required: true },
			{ name: 'target_url', label: 'صفحه هدف', type: 'url' },
			{ name: 'anchor', label: 'انکر', type: 'text' },
			{ name: 'rel_type', label: 'نوع لینک', type: 'select', opts: 'rel' },
			{ name: 'da', label: 'اعتبار دامنه (DA)', type: 'number' },
			{ name: 'status', label: 'وضعیت', type: 'select', opts: 'bl_st' },
			{ name: 'acquired_at', label: 'تاریخ دریافت', type: 'text' },
			{ name: 'notes', label: 'یادداشت', type: 'textarea' },
		],
		press: [
			{ name: 'outlet', label: 'رسانه / سایت', type: 'text', required: true },
			{ name: 'topic', label: 'موضوع', type: 'text' },
			{ name: 'article_url', label: 'لینک رپورتاژ', type: 'url' },
			{ name: 'target_url', label: 'لینک هدف', type: 'url' },
			{ name: 'cost', label: 'هزینه (تومان)', type: 'number' },
			{ name: 'follow_type', label: 'فالو', type: 'select', opts: 'rel' },
			{ name: 'status', label: 'وضعیت', type: 'select', opts: 'press_st' },
			{ name: 'publish_date', label: 'تاریخ انتشار', type: 'text' },
			{ name: 'notes', label: 'یادداشت', type: 'textarea' },
		],
		project: [
			{ name: 'name', label: 'نام پروژه', type: 'text', required: true },
			{ name: 'domain', label: 'دامنه', type: 'url' },
			{ name: 'notes', label: 'توضیح', type: 'textarea' },
		],
		rank: [
			{ name: 'keyword_id', label: 'کیورد', type: 'hidden' },
			{ name: 'position', label: 'جایگاه (۱ تا ۱۰۰، خالی = خارج)', type: 'number', required: true },
			{ name: 'checked_at', label: 'تاریخ', type: 'text', required: true },
			{ name: 'device', label: 'دستگاه', type: 'select', options: { desktop: 'دسکتاپ', mobile: 'موبایل' } },
			{ name: 'page_url', label: 'آدرس رتبه‌گرفته', type: 'url' },
		],
	};

	function optsHtml(map, selected) {
		return Object.keys(map).map(function (k) {
			return '<option value="' + k + '"' + (String(selected) === k ? ' selected' : '') + '>' + esc(map[k]) + '</option>';
		}).join('');
	}

	function renderFields(kind, item) {
		item = item || {};
		return FIELDS[kind].map(function (f) {
			var val = item[f.name] != null ? item[f.name] : '';
			if ((f.name.indexOf('_at') >= 0 || f.name.indexOf('date') >= 0) && val && String(val).indexOf('-') === 4) {
				val = faDate(val);
			}
			var req = f.required ? ' required' : '';
			var lab = '<label class="wbss-lab">' + f.label + (f.required ? ' *' : '') + '</label>';
			if (f.type === 'hidden') {
				return '<input type="hidden" name="' + f.name + '" value="' + esc(val) + '">';
			}
			if (f.type === 'textarea') {
				return '<div class="wbss-fg">' + lab + '<textarea name="' + f.name + '" rows="3">' + esc(val) + '</textarea></div>';
			}
			if (f.type === 'select') {
				var map = f.options || WBSS.i18n[f.opts] || {};
				return '<div class="wbss-fg">' + lab + '<select name="' + f.name + '">' + optsHtml(map, val) + '</select></div>';
			}
			if (f.type === 'keyword') {
				var opts = '<option value="0">—</option>' + state.keywords.map(function (k) {
					return '<option value="' + k.id + '"' + (String(val) === String(k.id) ? ' selected' : '') + '>' + esc(k.keyword) + '</option>';
				}).join('');
				return '<div class="wbss-fg">' + lab + '<select name="' + f.name + '">' + opts + '</select></div>';
			}
			return '<div class="wbss-fg">' + lab + '<input type="' + (f.type === 'number' ? 'number' : 'text') + '" name="' + f.name + '" value="' + esc(val) + '" placeholder="' + esc(f.placeholder || '') + '"' + req + '></div>';
		}).join('');
	}

	function openModal(title, kind, item) {
		$('#wbss-modal-title').textContent = title;
		var form = $('#wbss-form');
		form.innerHTML = '<input type="hidden" name="id" value="' + esc(item && item.id ? item.id : '') + '">' + renderFields(kind, item);
		form.dataset.kind = kind;
		$('#wbss-modal').hidden = false;
		var first = form.querySelector('input:not([type=hidden]),textarea,select');
		if (first) first.focus();
	}

	function closeModal() {
		$('#wbss-modal').hidden = true;
	}

	function formData(form) {
		var o = {};
		new FormData(form).forEach(function (v, k) { o[k] = v; });
		return o;
	}

	/* ─── views ─── */
	function setView(view) {
		state.view = view;
		document.querySelectorAll('.wbss-nav-btn').forEach(function (b) {
			b.classList.toggle('is-active', b.getAttribute('data-view') === view);
		});
		$('#wbss-title').textContent = WBSS.i18n.modules[view] || view;
		var add = $('#wbss-add');
		var canAdd = ['keywords', 'content', 'technical', 'backlinks', 'press'].indexOf(view) >= 0;
		add.hidden = !canAdd;
		add.textContent = canAdd ? 'ثبت ' + (WBSS.i18n.modules[view] || '') : '';
		$('#wbss-days-wrap').hidden = view !== 'dashboard' && view !== 'activity';
		loadView();
	}

	function loadView() {
		var box = $('#wbss-view');
		box.innerHTML = '<p class="wbss-loading">در حال بارگذاری…</p>';
		if (state.view === 'dashboard') return loadDashboard();
		if (state.view === 'settings') return loadSettings();
		if (state.view === 'activity') return loadActivity();
		if (state.view === 'keywords') return loadKeywords();
		return loadList(state.view);
	}

	function loadDashboard() {
		post('wbss_dashboard').then(function (res) {
			if (!res.success) throw new Error();
			var d = res.data;
			var k = d.kpis;
			var html = '<div class="wbss-kpis">' +
				kpi('امتیاز سئو', k.score, 'از ۱۰۰', scoreCls(k.score)) +
				kpi('میانگین رتبه', k.avg_rank || '—', k.ranked + ' کیورد رتبه‌دار') +
				kpi('بهبود رتبه', k.improved, k.dropped + ' افت') +
				kpi('اقدامات بازه', k.actions, state.days + ' روز اخیر') +
				kpi('محتوای منتشر', k.content_pub, k.content_all + ' کل') +
				kpi('بک‌لینک زنده', k.backlinks, '') +
				kpi('رپورتاژ ماه', k.press, k.press_cost ? num(k.press_cost) + ' تومان' : '') +
				kpi('تکنیکال باز', k.tech_open, k.tech_done + ' انجام‌شده') +
				'</div>';

			html += '<div class="wbss-grid-2">';
			html += card('روند میانگین رتبه (پایین بهتر است)', '<canvas class="wbss-cv" id="wbss-rank-line"></canvas>');
			html += card('توزیع جایگاه کیوردها', '<canvas class="wbss-cv" id="wbss-rank-donut"></canvas>');
			html += '</div>';
			html += '<div class="wbss-grid-2">';
			html += card('اقدامات ثبت‌شده بر اساس بخش', '<canvas class="wbss-cv" id="wbss-mod-bar"></canvas>');
			html += card('بیشترین تغییر رتبه', moversHtml(d.movers));
			html += '</div>';
			html += card('آخرین اقدامات', activityList(d.activity));
			$('#wbss-view').innerHTML = html;

			var labels = (d.series || []).map(function (r) { return faDate(r.d).slice(5); });
			var vals = (d.series || []).map(function (r) { return parseFloat(r.avg_pos); });
			if (window.WbssCharts) {
				WbssCharts.line($('#wbss-rank-line'), labels, vals, { invert: true, min: 1, max: Math.max(20, Math.ceil((Math.max.apply(null, vals.concat([10]))) / 5) * 5) });
				WbssCharts.donut(
					$('#wbss-rank-donut'),
					['۱–۳', '۴–۱۰', '۱۱–۲۰', '۲۱–۵۰', '۵۰+'],
					[d.buckets.top3, d.buckets.top10, d.buckets.top20, d.buckets.top50, d.buckets.other],
					['#137333', '#1e8e3e', '#1565c0', '#e37400', '#5f6368']
				);
				var mods = d.by_mod || [];
				WbssCharts.bar(
					$('#wbss-mod-bar'),
					mods.map(function (m) { return WBSS.i18n.mod_label[m.module] || m.module; }),
					mods.map(function (m) { return parseInt(m.n, 10); })
				);
			}
		}).catch(function () {
			$('#wbss-view').innerHTML = '<p class="wbss-empty">' + WBSS.i18n.error + '</p>';
		});
	}

	function scoreCls(n) {
		if (n >= 75) return 'is-good';
		if (n >= 50) return 'is-mid';
		return 'is-bad';
	}

	function kpi(label, value, hint, cls) {
		return '<div class="wbss-kpi ' + (cls || '') + '"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong><small>' + esc(hint || '') + '</small></div>';
	}

	function moversHtml(rows) {
		if (!rows || !rows.length) return '<p class="wbss-empty">هنوز رتبهٔ مقایسه‌ای نیست.</p>';
		var body = rows.map(function (r) {
			return '<tr><td>' + esc(r.keyword) + '</td><td>' + rankCell(r.current_rank) + '</td><td>' + changeCell(r.change) + '</td></tr>';
		}).join('');
		return table(['کیورد', 'رتبه', 'تغییر'], body);
	}

	function activityList(rows) {
		if (!rows || !rows.length) return '<p class="wbss-empty">' + WBSS.i18n.empty + '</p>';
		return '<ol class="wbss-feed">' + rows.map(function (a) {
			return '<li><b>' + esc(WBSS.i18n.mod_label[a.module] || a.module) + '</b> · ' +
				esc(WBSS.i18n.act_label[a.action_type] || a.action_type) +
				'<span>' + esc(a.title) + '</span>' +
				'<time>' + esc(faDate(a.created_at)) + '</time></li>';
		}).join('') + '</ol>';
	}

	function loadKeywords() {
		post('wbss_list', { module: 'keywords' }).then(function (res) {
			if (!res.success) throw new Error();
			state.keywords = res.data.items || [];
			var body = state.keywords.map(function (r) {
				return '<tr>' +
					'<td><strong>' + esc(r.keyword) + '</strong><div class="wbss-muted">' + esc(r.page_url || '') + '</div></td>' +
					'<td>' + pill(WBSS.i18n.intent[r.intent] || r.intent) + '</td>' +
					'<td>' + num(r.volume) + '</td>' +
					'<td>' + rankCell(r.current_rank) + '</td>' +
					'<td>' + changeCell(r.change) + '</td>' +
					'<td>' + esc(faDate(r.last_checked)) + '</td>' +
					'<td>' + pill(WBSS.i18n.kw_status[r.status] || r.status) + '</td>' +
					'<td class="wbss-acts">' +
						'<button type="button" class="button-link" data-act="rank" data-id="' + r.id + '">رتبه</button>' +
						'<button type="button" class="button-link" data-act="edit" data-id="' + r.id + '">ویرایش</button>' +
						'<button type="button" class="button-link is-danger" data-act="del" data-id="' + r.id + '">حذف</button>' +
					'</td></tr>';
			}).join('');
			$('#wbss-view').innerHTML = card('کیورد ریسرچ و پایش رتبه', table(
				['کیورد', 'نیت', 'حجم', 'رتبه', 'تغییر', 'آخرین چک', 'وضعیت', ''],
				body
			)) + '<p class="wbss-hint">با دکمه «رتبه» هر چک جدید ثبت می‌شود؛ نمودار نمای کلی از همین تاریخچه ساخته می‌شود.</p>';
			bindTable('keywords');
		}).catch(failView);
	}

	function loadList(module) {
		post('wbss_list', { module: module }).then(function (res) {
			if (!res.success) throw new Error();
			var items = res.data.items || [];
			var html = '';
			if (module === 'content') {
				html = table(['عنوان', 'کیورد', 'کلمه', 'وضعیت', 'انتشار', ''], items.map(function (r) {
					var kw = state.keywords.find(function (k) { return String(k.id) === String(r.keyword_id); });
					return '<tr><td><strong>' + esc(r.title) + '</strong><div class="wbss-muted">' + esc(r.url || '') + '</div></td>' +
						'<td>' + esc(kw ? kw.keyword : '—') + '</td><td>' + num(r.word_count) + '</td>' +
						'<td>' + pill(WBSS.i18n.content_st[r.status] || r.status) + '</td>' +
						'<td>' + esc(faDate(r.published_at)) + '</td>' + acts(r.id) + '</tr>';
				}).join(''));
			} else if (module === 'technical') {
				html = table(['اقدام', 'دسته', 'شدت', 'وضعیت', 'صفحه', ''], items.map(function (r) {
					return '<tr><td><strong>' + esc(r.title) + '</strong></td>' +
						'<td>' + pill(WBSS.i18n.tech_cat[r.category] || r.category) + '</td>' +
						'<td>' + pill(WBSS.i18n.severity[r.severity] || r.severity, 'sev-' + r.severity) + '</td>' +
						'<td>' + pill(WBSS.i18n.tech_st[r.status] || r.status) + '</td>' +
						'<td class="wbss-clip">' + esc(r.page_url || '') + '</td>' + acts(r.id) + '</tr>';
				}).join(''));
			} else if (module === 'backlinks') {
				html = table(['منبع', 'انکر', 'نوع', 'DA', 'وضعیت', 'تاریخ', ''], items.map(function (r) {
					return '<tr><td class="wbss-clip"><a href="' + esc(r.source_url) + '" target="_blank" rel="noopener">' + esc(r.source_url) + '</a></td>' +
						'<td>' + esc(r.anchor || '') + '</td>' +
						'<td>' + pill(WBSS.i18n.rel[r.rel_type] || r.rel_type) + '</td>' +
						'<td>' + num(r.da) + '</td>' +
						'<td>' + pill(WBSS.i18n.bl_st[r.status] || r.status) + '</td>' +
						'<td>' + esc(faDate(r.acquired_at)) + '</td>' + acts(r.id) + '</tr>';
				}).join(''));
			} else if (module === 'press') {
				html = table(['رسانه', 'موضوع', 'هزینه', 'فالو', 'وضعیت', 'تاریخ', ''], items.map(function (r) {
					return '<tr><td><strong>' + esc(r.outlet) + '</strong><div class="wbss-muted">' + esc(r.article_url || '') + '</div></td>' +
						'<td>' + esc(r.topic || '') + '</td>' +
						'<td>' + num(r.cost) + '</td>' +
						'<td>' + pill(WBSS.i18n.rel[r.follow_type] || r.follow_type) + '</td>' +
						'<td>' + pill(WBSS.i18n.press_st[r.status] || r.status) + '</td>' +
						'<td>' + esc(faDate(r.publish_date)) + '</td>' + acts(r.id) + '</tr>';
				}).join(''));
			}
			$('#wbss-view').innerHTML = card(WBSS.i18n.modules[module], html);
			bindTable(module);
		}).catch(failView);
	}

	function acts(id) {
		return '<td class="wbss-acts">' +
			'<button type="button" class="button-link" data-act="edit" data-id="' + id + '">ویرایش</button>' +
			'<button type="button" class="button-link is-danger" data-act="del" data-id="' + id + '">حذف</button></td>';
	}

	function bindTable(module) {
		$('#wbss-view').onclick = function (e) {
			var btn = e.target.closest('[data-act]');
			if (!btn) return;
			var id = btn.getAttribute('data-id');
			var act = btn.getAttribute('data-act');
			if (act === 'del') {
				if (!confirm(WBSS.i18n.confirm_del)) return;
				post('wbss_delete', { module: module, id: id }).then(function (r) {
					if (r.success) { toast(WBSS.i18n.ok); loadView(); }
					else toast((r.data && r.data.message) || WBSS.i18n.error, true);
				});
			} else if (act === 'edit') {
				post('wbss_get', { module: module, id: id }).then(function (r) {
					if (r.success) openModal('ویرایش', module, r.data.item);
				});
			} else if (act === 'rank') {
				openModal('ثبت رتبه جدید', 'rank', { keyword_id: id, checked_at: WBSS.todayFa, position: '' });
			}
		};
	}

	function loadActivity() {
		post('wbss_list', { module: 'activity' }).then(function (res) {
			if (!res.success) throw new Error();
			var items = res.data.items || [];
			var body = items.map(function (a) {
				return '<tr><td>' + esc(faDate(a.created_at)) + '</td>' +
					'<td>' + pill(WBSS.i18n.mod_label[a.module] || a.module) + '</td>' +
					'<td>' + esc(WBSS.i18n.act_label[a.action_type] || a.action_type) + '</td>' +
					'<td>' + esc(a.title) + '</td></tr>';
			}).join('');
			$('#wbss-view').innerHTML = card('گزارش زمانی همه اقدامات', table(['تاریخ', 'بخش', 'نوع', 'شرح'], body));
		}).catch(failView);
	}

	function loadSettings() {
		var rows = state.projects.map(function (p) {
			return '<tr><td><strong>' + esc(p.name) + '</strong></td><td>' + esc(p.domain || '') + '</td>' +
				'<td class="wbss-acts">' +
				'<button type="button" class="button-link" data-act="pedit" data-id="' + p.id + '">ویرایش</button>' +
				'<button type="button" class="button-link is-danger" data-act="pdel" data-id="' + p.id + '">حذف</button></td></tr>';
		}).join('');
		$('#wbss-view').innerHTML =
			'<div class="wbss-toolbar"><button type="button" class="button button-primary" id="wbss-new-project">پروژه جدید</button>' +
			'<button type="button" class="button" id="wbss-seed">افزودن داده نمونه</button></div>' +
			card('پروژه‌های سئو', table(['نام', 'دامنه', ''], rows));
		$('#wbss-new-project').onclick = function () { openModal('پروژه جدید', 'project', {}); };
		$('#wbss-seed').onclick = function () {
			if (!confirm('یک پروژه نمونه با دادهٔ نمایشی اضافه شود؟')) return;
			post('wbss_reseed').then(function (r) {
				if (r.success) { toast('داده نمونه اضافه شد'); boot(); }
			});
		};
		$('#wbss-view').onclick = function (e) {
			var btn = e.target.closest('[data-act]');
			if (!btn) return;
			var id = btn.getAttribute('data-id');
			if (btn.getAttribute('data-act') === 'pdel') {
				if (!confirm(WBSS.i18n.confirm_del)) return;
				post('wbss_delete_project', { id: id }).then(function () { boot(); });
			} else {
				var p = state.projects.find(function (x) { return String(x.id) === String(id); });
				openModal('ویرایش پروژه', 'project', p || { id: id });
			}
		};
	}

	function failView() {
		$('#wbss-view').innerHTML = '<p class="wbss-empty">' + WBSS.i18n.error + '</p>';
	}

	function fillProjects() {
		var sel = $('#wbss-project');
		sel.innerHTML = state.projects.map(function (p) {
			return '<option value="' + p.id + '"' + (String(p.id) === String(state.projectId) ? ' selected' : '') + '>' + esc(p.name) + '</option>';
		}).join('');
		if (!state.projects.length) {
			sel.innerHTML = '<option value="0">پروژه‌ای نیست</option>';
		}
	}

	function boot() {
		post('wbss_projects').then(function (res) {
			state.projects = (res.success && res.data.items) || [];
			if (!state.projectId && state.projects[0]) state.projectId = state.projects[0].id;
			fillProjects();
			return post('wbss_list', { module: 'keywords' });
		}).then(function (res) {
			state.keywords = (res && res.success && res.data.items) || [];
			setView(state.view);
		}).catch(function () {
			setView('dashboard');
		});
	}

	document.addEventListener('click', function (e) {
		var nav = e.target.closest('.wbss-nav-btn');
		if (nav) setView(nav.getAttribute('data-view'));
	});

	$('#wbss-project').addEventListener('change', function () {
		state.projectId = parseInt(this.value, 10) || 0;
		loadView();
		post('wbss_list', { module: 'keywords' }).then(function (r) {
			if (r.success) state.keywords = r.data.items || [];
		});
	});

	$('#wbss-days').addEventListener('change', function () {
		state.days = parseInt(this.value, 10) || 30;
		loadView();
	});

	$('#wbss-add').addEventListener('click', function () {
		openModal('ثبت ' + (WBSS.i18n.modules[state.view] || ''), state.view, {});
	});

	$('#wbss-modal-close').addEventListener('click', closeModal);
	$('#wbss-modal-cancel').addEventListener('click', closeModal);
	$('#wbss-modal').addEventListener('click', function (e) {
		if (e.target === $('#wbss-modal')) closeModal();
	});

	$('#wbss-form').addEventListener('submit', function (e) {
		e.preventDefault();
		var kind = this.dataset.kind;
		var data = formData(this);
		var req = kind === 'project'
			? post('wbss_save_project', { data: data })
			: kind === 'rank'
				? post('wbss_save_rank', { data: data })
				: post('wbss_save', { module: kind, data: data });
		req.then(function (r) {
			if (!r.success) {
				toast((r.data && r.data.message) || WBSS.i18n.error, true);
				return;
			}
			toast(WBSS.i18n.ok);
			closeModal();
			if (kind === 'project') boot();
			else loadView();
		});
	});

	$('#wbss-export').addEventListener('click', function () {
		post('wbss_export').then(function (r) {
			if (!r.success) {
				toast((r.data && r.data.message) || WBSS.i18n.error, true);
				return;
			}
			var blob = new Blob([JSON.stringify(r.data, null, 2)], { type: 'application/json' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = 'seo-studio-' + (state.projectId || 'project') + '.json';
			a.click();
		});
	});

	boot();
})();
