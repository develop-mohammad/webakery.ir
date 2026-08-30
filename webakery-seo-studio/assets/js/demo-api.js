/**
 * بک‌اند لوکال برای canvas-preview — داده در حافظه مرورگر.
 */
(function (global) {
	'use strict';

	function today() {
		var d = new Date();
		return d.toISOString().slice(0, 10);
	}

	function ago(n) {
		var d = new Date();
		d.setDate(d.getDate() - n);
		return d.toISOString().slice(0, 10);
	}

	function nid(store) {
		store._id = (store._id || 20) + 1;
		return store._id;
	}

	function seed() {
		var s = { _id: 40, projects: [], keywords: [], ranks: [], content: [], technical: [], backlinks: [], press: [], activity: [] };
		s.projects.push({ id: 1, name: 'وب‌آکری', domain: 'https://webakery.ir', notes: 'پروژه نمونه' });
		var kws = [
			['افزونه وردپرس', 'commercial', 2400, 48, 'https://webakery.ir', [18, 16, 14, 11, 9, 8, 6]],
			['افزونه نوبت‌دهی', 'transactional', 880, 36, 'https://webakery.ir/nobat-man', [28, 22, 19, 15, 12, 9, 7]],
			['حسابداری ووکامرس', 'commercial', 720, 41, 'https://webakery.ir/hesabdar', [34, 29, 24, 21, 18, 14, 11]],
			['چت آنلاین سایت', 'transactional', 1300, 44, 'https://webakery.ir/chat', [12, 11, 9, 8, 7, 6, 4]],
			['سئو سایت وردپرسی', 'informational', 1900, 55, 'https://webakery.ir/blog/seo', [52, 44, 38, 31, 27, 23, 19]],
			['رزرو نوبت آنلاین', 'transactional', 2100, 50, 'https://webakery.ir/nobat-man', [25, 21, 18, 16, 14, 12, 10]],
		];
		var offs = [42, 35, 28, 21, 14, 7, 0];
		kws.forEach(function (k, i) {
			var id = i + 1;
			s.keywords.push({
				id: id, project_id: 1, keyword: k[0], intent: k[1], volume: k[2], difficulty: k[3],
				page_url: k[4], notes: 'نمونه', status: 'active', created_at: ago(40),
			});
			k[5].forEach(function (pos, ri) {
				s.ranks.push({ id: nid(s), keyword_id: id, checked_at: ago(offs[ri]), position: pos, page_url: k[4] });
			});
			log(s, 1, 'keywords', 'created', 'کیورد ریسرچ: ' + k[0], id, 40 - i);
		});
		[
			['راهنمای نصب نوبت من', 'https://webakery.ir/blog/nobat', 2, 1680, 'published', 20],
			['چک‌لیست سئو تکنیکال', 'https://webakery.ir/blog/tech-seo', 5, 2140, 'published', 12],
			['مقاله چت و نرخ تبدیل', 'https://webakery.ir/blog/chat-cvr', 4, 980, 'draft', 0],
		].forEach(function (c, i) {
			s.content.push({
				id: i + 1, project_id: 1, title: c[0], url: c[1], keyword_id: c[2], word_count: c[3],
				status: c[4], published_at: c[4] === 'draft' ? '' : ago(c[5]), notes: '',
			});
			log(s, 1, 'content', 'created', 'تولید محتوا: ' + c[0], i + 1, c[5] || 2);
		});
		[
			['نقشه سایت XML', 'index', 'high', 'done'],
			['بهبود LCP صفحه اصلی', 'speed', 'critical', 'in_progress'],
			['اسکیما محصول', 'schema', 'medium', 'open'],
		].forEach(function (t, i) {
			s.technical.push({
				id: i + 1, project_id: 1, title: t[0], category: t[1], severity: t[2], status: t[3], page_url: 'https://webakery.ir', notes: '',
			});
			log(s, 1, 'technical', 'created', 'ثبت تکنیکال: ' + t[0], i + 1, 10 - i);
		});
		s.backlinks.push({
			id: 1, project_id: 1, source_url: 'https://example-news.ir/wp', target_url: 'https://webakery.ir',
			anchor: 'افزونه وردپرس', rel_type: 'dofollow', da: 42, status: 'live', acquired_at: ago(18), notes: '',
		});
		s.backlinks.push({
			id: 2, project_id: 1, source_url: 'https://old-blog.ir/review', target_url: 'https://webakery.ir',
			anchor: 'وب‌آکری', rel_type: 'dofollow', da: 19, status: 'lost', acquired_at: ago(30), notes: '',
		});
		log(s, 1, 'backlinks', 'created', 'بک‌لینک جدید: example-news.ir', 1, 18);
		s.press.push({
			id: 1, project_id: 1, outlet: 'دیجیاتو', article_url: 'https://digiato.example/x', target_url: 'https://webakery.ir',
			topic: 'ابزارهای وردپرس ایرانی', cost: 8500000, publish_date: ago(16), follow_type: 'dofollow', status: 'published', notes: '',
		});
		s.press.push({
			id: 2, project_id: 1, outlet: 'زومیت', article_url: 'https://zoomit.example/seo', target_url: 'https://webakery.ir/blog/seo',
			topic: 'سئو فروشگاه وردپرسی', cost: 12000000, publish_date: ago(4), follow_type: 'dofollow', status: 'published', notes: '',
		});
		log(s, 1, 'press', 'created', 'رپورتاژ: دیجیاتو', 1, 16);
		log(s, 1, 'rank', 'checked', 'ثبت رتبه «افزونه وردپرس»: جایگاه ۶', 1, 0);
		return s;
	}

	function log(s, pid, module, action, title, eid, daysAgo) {
		var d = new Date();
		if (daysAgo) d.setDate(d.getDate() - daysAgo);
		s.activity.unshift({
			id: nid(s), project_id: pid, module: module, action_type: action, entity_id: eid || 0,
			title: title, created_at: d.toISOString().slice(0, 19).replace('T', ' '),
		});
	}

	function enrichKw(s, pid) {
		return s.keywords.filter(function (k) { return Number(k.project_id) === Number(pid); }).map(function (k) {
			var rs = s.ranks.filter(function (r) { return Number(r.keyword_id) === Number(k.id); })
				.sort(function (a, b) { return a.checked_at < b.checked_at ? 1 : -1; });
			var cur = rs[0] ? rs[0].position : 0;
			var prev = rs[1] ? rs[1].position : 0;
			return Object.assign({}, k, {
				current_rank: cur,
				previous_rank: prev,
				last_checked: rs[0] ? rs[0].checked_at : '',
				change: cur && prev ? prev - cur : 0,
			});
		}).reverse();
	}

	function dashboard(s, pid, days) {
		var kw = enrichKw(s, pid);
		var ranked = kw.filter(function (k) { return k.current_rank && k.current_rank < 101; });
		var avg = 0;
		if (ranked.length) {
			avg = Math.round((ranked.reduce(function (a, k) { return a + Number(k.current_rank); }, 0) / ranked.length) * 10) / 10;
		}
		var buckets = { top3: 0, top10: 0, top20: 0, top50: 0, other: 0 };
		ranked.forEach(function (k) {
			var p = Number(k.current_rank);
			if (p <= 3) buckets.top3++;
			else if (p <= 10) buckets.top10++;
			else if (p <= 20) buckets.top20++;
			else if (p <= 50) buckets.top50++;
			else buckets.other++;
		});
		var since = ago(days);
		var map = {};
		s.ranks.forEach(function (r) {
			if (r.checked_at < since || r.position >= 101) return;
			var k = s.keywords.find(function (x) { return Number(x.id) === Number(r.keyword_id) && Number(x.project_id) === Number(pid); });
			if (!k) return;
			if (!map[r.checked_at]) map[r.checked_at] = [];
			map[r.checked_at].push(Number(r.position));
		});
		var series = Object.keys(map).sort().map(function (d) {
			var arr = map[d];
			return { d: d, avg_pos: arr.reduce(function (a, b) { return a + b; }, 0) / arr.length };
		});
		var by = {};
		s.activity.forEach(function (a) {
			if (Number(a.project_id) !== Number(pid)) return;
			by[a.module] = (by[a.module] || 0) + 1;
		});
		var by_mod = Object.keys(by).map(function (m) { return { module: m, n: by[m] }; });
		var movers = kw.slice().sort(function (a, b) { return Math.abs(b.change) - Math.abs(a.change); }).slice(0, 8);
		var content = s.content.filter(function (c) { return Number(c.project_id) === Number(pid); });
		var tech = s.technical.filter(function (c) { return Number(c.project_id) === Number(pid); });
		var bl = s.backlinks.filter(function (c) { return Number(c.project_id) === Number(pid); });
		var pr = s.press.filter(function (c) { return Number(c.project_id) === Number(pid); });
		var score = Math.max(0, Math.min(100, 55 + buckets.top3 * 4 + buckets.top10 * 2 - tech.filter(function (t) { return t.status !== 'done'; }).length * 3));
		return {
			kpis: {
				score: score,
				avg_rank: avg,
				keywords: kw.length,
				ranked: ranked.length,
				improved: kw.filter(function (k) { return k.change > 0; }).length,
				dropped: kw.filter(function (k) { return k.change < 0; }).length,
				content_pub: content.filter(function (c) { return c.status !== 'draft'; }).length,
				content_all: content.length,
				tech_open: tech.filter(function (t) { return t.status !== 'done'; }).length,
				tech_done: tech.filter(function (t) { return t.status === 'done'; }).length,
				backlinks: bl.filter(function (b) { return b.status === 'live'; }).length,
				press: pr.filter(function (p) { return p.status === 'published'; }).length,
				press_cost: pr.reduce(function (a, p) { return a + Number(p.cost || 0); }, 0),
				actions: s.activity.filter(function (a) { return Number(a.project_id) === Number(pid); }).length,
			},
			buckets: buckets,
			series: series,
			by_mod: by_mod,
			movers: movers,
			activity: s.activity.filter(function (a) { return Number(a.project_id) === Number(pid); }).slice(0, 12),
			days: days,
		};
	}

	var KEY = 'wbss_laptop_store_v1';

	function persist() {
		try {
			global.localStorage.setItem(KEY, JSON.stringify(store));
		} catch (e) { /* private mode */ }
	}

	function loadStore() {
		try {
			var raw = global.localStorage.getItem(KEY);
			if (raw) {
				var parsed = JSON.parse(raw);
				if (parsed && parsed.projects) {
					return parsed;
				}
			}
		} catch (e) { /* ignore */ }
		var fresh = seed();
		try {
			global.localStorage.setItem(KEY, JSON.stringify(fresh));
		} catch (err) { /* ignore */ }
		return fresh;
	}

	var store = loadStore();

	function ok(data) { return { success: true, data: data }; }
	function fail(message) { return { success: false, data: { message: message } }; }

	function handle(action, req) {
		var pid = Number(req.project_id || 1);
		var days = Number(req.days || 30);
		if (action === 'wbss_projects') return ok({ items: store.projects });
		if (action === 'wbss_dashboard') return ok(dashboard(store, pid, days));
		if (action === 'wbss_list') {
			var m = req.module;
			if (m === 'keywords') return ok({ items: enrichKw(store, pid) });
			if (m === 'activity') return ok({ items: store.activity.filter(function (a) { return Number(a.project_id) === Number(pid); }) });
			return ok({ items: (store[m] || []).filter(function (r) { return Number(r.project_id) === Number(pid); }) });
		}
		if (action === 'wbss_get') {
			var list = store[req.module] || [];
			var item = list.find(function (r) { return String(r.id) === String(req.id); });
			return item ? ok({ item: item }) : fail('یافت نشد.');
		}
		if (action === 'wbss_save' || action === 'wbss_save_project' || action === 'wbss_save_rank') {
			var data = req.data || {};
			if (action === 'wbss_save_project') {
				if (data.id) {
					var p = store.projects.find(function (x) { return String(x.id) === String(data.id); });
					if (p) Object.assign(p, data);
					persist();
					return ok({ id: Number(data.id) });
				}
				var np = { id: nid(store), name: data.name, domain: data.domain, notes: data.notes };
				store.projects.push(np);
				persist();
				return ok({ id: np.id });
			}
			if (action === 'wbss_save_rank') {
				var when = data.checked_at && /^\d{4}-\d{2}-\d{2}/.test(String(data.checked_at)) ? String(data.checked_at).slice(0, 10) : today();
				store.ranks.push({
					id: nid(store), keyword_id: Number(data.keyword_id), checked_at: when,
					position: Number(data.position) || 101, page_url: data.page_url || '',
				});
				var kw = store.keywords.find(function (k) { return String(k.id) === String(data.keyword_id); });
				log(store, pid, 'rank', 'checked', 'ثبت رتبه «' + (kw ? kw.keyword : '') + '»: جایگاه ' + data.position, data.keyword_id, 0);
				persist();
				return ok({ id: store._id });
			}
			var mod = req.module;
			data.project_id = pid;
			if (data.id) {
				var row = (store[mod] || []).find(function (r) { return String(r.id) === String(data.id); });
				if (row) Object.assign(row, data);
				log(store, pid, mod, 'updated', 'ویرایش مورد', data.id, 0);
				persist();
				return ok({ id: Number(data.id) });
			}
			data.id = nid(store);
			store[mod] = store[mod] || [];
			store[mod].push(data);
			log(store, pid, mod, 'created', 'ثبت مورد جدید', data.id, 0);
			persist();
			return ok({ id: data.id });
		}
		if (action === 'wbss_delete') {
			store[req.module] = (store[req.module] || []).filter(function (r) { return String(r.id) !== String(req.id); });
			log(store, pid, req.module, 'deleted', 'حذف مورد', req.id, 0);
			persist();
			return ok({});
		}
		if (action === 'wbss_delete_project') {
			store.projects = store.projects.filter(function (p) { return String(p.id) !== String(req.id); });
			persist();
			return ok({});
		}
		if (action === 'wbss_export') return ok(store);
		if (action === 'wbss_import') {
			var incoming = req.data || {};
			if (!incoming.projects) return fail('فایل پشتیبان نامعتبر است.');
			store = incoming;
			persist();
			return ok({ id: 1 });
		}
		if (action === 'wbss_empty') {
			store = {
				_id: 1,
				projects: [ { id: 1, name: 'پروژه من', domain: '', notes: '' } ],
				keywords: [], ranks: [], content: [], technical: [], backlinks: [], press: [], activity: [],
			};
			persist();
			return ok({ id: 1 });
		}
		if (action === 'wbss_reseed') { store = seed(); persist(); return ok({ id: 1 }); }
		return fail('اکشن نامعتبر');
	}

	global.WbssDemo = { handle: handle };
})(window);
