(function () {
	'use strict';

	var cfg = window.wbgsAdmin || {};
	var seedEl = document.getElementById('wbgs-seed');
	var seedBEl = document.getElementById('wbgs-seed-b');
	var startBtn = document.getElementById('wbgs-start');
	var stopBtn = document.getElementById('wbgs-stop');
	var listEl = document.getElementById('wbgs-list');
	var treeEl = document.getElementById('wbgs-tree');
	var clusterEl = document.getElementById('wbgs-cluster');
	var briefEl = document.getElementById('wbgs-brief');
	var compareEl = document.getElementById('wbgs-compare');
	var emptyEl = document.getElementById('wbgs-empty');
	var countEl = document.getElementById('wbgs-count');
	var statusEl = document.getElementById('wbgs-status');
	var progressWrap = document.getElementById('wbgs-progress');
	var progressBar = document.getElementById('wbgs-progress-bar');
	var progressText = document.getElementById('wbgs-progress-text');
	var copyBtn = document.getElementById('wbgs-copy');
	var csvBtn = document.getElementById('wbgs-csv');
	var txtBtn = document.getElementById('wbgs-txt');
	var briefTxtBtn = document.getElementById('wbgs-brief-txt');
	var viewListBtn = document.getElementById('wbgs-view-list');
	var viewTreeBtn = document.getElementById('wbgs-view-tree');
	var viewClusterBtn = document.getElementById('wbgs-view-cluster');
	var viewBriefBtn = document.getElementById('wbgs-view-brief');
	var viewCompareBtn = document.getElementById('wbgs-view-compare');
	var filtersEl = document.getElementById('wbgs-intent-filters');
	var historyEl = document.getElementById('wbgs-history');
	var loadBtn = document.getElementById('wbgs-load');
	var saveBtn = document.getElementById('wbgs-save');
	var compareSavedBtn = document.getElementById('wbgs-compare-saved');
	var usageEl = document.getElementById('wbgs-usage');
	var googleRoot = document.querySelector('[data-wbgs-ui="google"]');
	var LS_KEY = 'wbgs_reports_v1';

	if (!seedEl || !startBtn) {
		return;
	}

	var running = false;
	var stopFlag = false;
	var view = 'list';
	var intentFilter = 'all';
	var items = [];
	var seen = {};
	var itemsA = [];
	var itemsB = [];
	var lastSeedA = '';
	var lastSeedB = '';
	var lastCompare = null;

	var INTENT_RULES = {
		navigational: ['دیجی کالا', 'دیجیکالا', 'آمازون', 'دیوار', 'اینستاگرام', 'ترب', 'amazon', 'digikala', 'instagram', '.com', '.ir'],
		transactional: ['خرید', 'فروش', 'سفارش', 'ارزان', 'تخفیف', 'قیمت', 'اینترنتی', 'آنلاین', 'buy', 'price', 'cheap', 'order', 'shop'],
		informational: ['چیست', 'چیه', 'چگونه', 'چطور', 'چرا', 'یعنی', 'آموزش', 'راهنما', 'معنی', 'how', 'what', 'why'],
		commercial: ['بهترین', 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت', 'best', 'review', 'compare']
	};

	var QUESTION_MARKS = ['چیست', 'چیه', 'چگونه', 'چطور', 'چرا', 'یعنی', 'معنی', 'تعریف', 'آیا', 'what', 'how', 'why', 'which', 'when', 'where'];
	var GEO_MARKS = cfg.geo || [];
	var SEASON_MARKS = cfg.seasonal || [];
	var BRAND_MARKS = cfg.brands || [];

	function i18n(key) {
		return (cfg.i18n && cfg.i18n[key]) || key;
	}

	function intentLabel(key) {
		return (cfg.intents && cfg.intents[key]) || key;
	}

	function classifyIntent(text) {
		var t = String(text || '');
		var groups = ['navigational', 'transactional', 'informational', 'commercial'];
		for (var g = 0; g < groups.length; g++) {
			var key = groups[g];
			for (var i = 0; i < INTENT_RULES[key].length; i++) {
				if (t.toLowerCase().indexOf(INTENT_RULES[key][i].toLowerCase()) !== -1) {
					return key;
				}
			}
		}
		return 'commercial';
	}

	function isQuestion(text) {
		var t = String(text || '');
		if (t.indexOf('؟') !== -1 || t.indexOf('?') !== -1) {
			return true;
		}
		var low = t.toLowerCase();
		for (var i = 0; i < QUESTION_MARKS.length; i++) {
			if (low.indexOf(QUESTION_MARKS[i].toLowerCase()) !== -1) {
				return true;
			}
		}
		return false;
	}

	function competition(row) {
		var relevance = parseInt(row.relevance, 10) || 0;
		var rank = parseInt(row.rank, 10) || 10;
		var count = Math.max(1, parseInt(row.count, 10) || 1);
		var words = wordCount(row.text);
		var visibility = relevance > 0 ? Math.min(50, Math.round(relevance / 20)) : Math.max(0, 40 - rank * 3);
		var intentPts = 22;
		if (row.intent === 'transactional') {
			intentPts = 30;
		} else if (row.intent === 'commercial') {
			intentPts = 22;
		} else if (row.intent === 'navigational') {
			intentPts = 16;
		} else if (row.intent === 'informational') {
			intentPts = 8;
		}
		var lengthPts = words <= 1 ? 20 : (words === 2 ? 14 : (words === 3 ? 8 : 2));
		var repeatPts = Math.min(10, (count - 1) * 3);
		return Math.max(1, Math.min(100, visibility + intentPts + lengthPts + repeatPts));
	}

	function competitionBand(score) {
		if (score >= 67) {
			return { key: 'high', fa: 'بالا' };
		}
		if (score >= 34) {
			return { key: 'mid', fa: 'متوسط' };
		}
		return { key: 'low', fa: 'پایین' };
	}

	function suggestScore(row) {
		var relevance = parseInt(row.relevance, 10) || 0;
		var count = Math.max(1, parseInt(row.count, 10) || 1);
		var rank = parseInt(row.rank, 10) || 10;
		return (relevance * 2) + (count * 20) + Math.max(0, 16 - rank);
	}

	function setStatus(text, kind) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = text || '';
		statusEl.classList.remove('is-error', 'is-ok');
		if (kind) {
			statusEl.classList.add(kind === 'error' ? 'is-error' : 'is-ok');
		}
	}

	function showUsage(snap) {
		if (!usageEl) {
			return;
		}
		snap = snap || cfg.usage || {};
		var used = parseInt(snap.used, 10) || 0;
		var cap = parseInt(snap.cap, 10) || 0;
		var text = i18n('usage').indexOf('%s') !== -1 ? i18n('usage').replace('%s', String(used)) : ('امروز ' + used + ' درخواست به گوگل');
		if (cap) {
			text += ' · سقف ' + cap;
		} else if (snap.admin) {
			text += ' · مدیر بدون سقف روزانه';
		}
		usageEl.textContent = text;
		usageEl.hidden = false;
	}

	function selectedModes() {
		var modes = {};
		document.querySelectorAll('input[name="wbgs-mode"]:checked').forEach(function (el) {
			modes[el.value] = '1';
		});
		return modes;
	}

	function tokens(text) {
		return String(text || '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);
	}

	function sameTokens(a, b) {
		if (a.length !== b.length) {
			return false;
		}
		for (var i = 0; i < a.length; i++) {
			if (a[i] !== b[i]) {
				return false;
			}
		}
		return true;
	}

	function branchPath(seed, keyword) {
		var st = tokens(seed);
		var tt = tokens(keyword);
		if (!tt.length) {
			return [];
		}
		if (st.length && sameTokens(st, tt)) {
			return [];
		}
		if (st.length && tt.length >= st.length && sameTokens(tt.slice(0, st.length), st)) {
			return tt.slice(st.length);
		}
		if (st.length && tt.length >= st.length && sameTokens(tt.slice(-st.length), st)) {
			return tt.slice(0, tt.length - st.length);
		}
		if (st.length) {
			return tt.filter(function (tok) {
				return st.indexOf(tok) === -1;
			});
		}
		return tt;
	}

	function clusterName(seed, keyword) {
		var path = branchPath(seed, keyword);
		return path.length ? path[0] : String(seed || '').replace(/\s+/g, ' ').trim();
	}

	function wordCount(text) {
		return tokens(text).length;
	}

	function isLongTail(text) {
		return wordCount(text) >= 4;
	}

	function lengthKey(text) {
		var n = wordCount(text);
		if (n >= 4) {
			return 'long';
		}
		if (n === 3) {
			return 'mid';
		}
		return 'short';
	}

	function lengthLabel(key) {
		return (cfg.lengths && cfg.lengths[key]) || key;
	}

	function extraLabel(key) {
		return (cfg.extras && cfg.extras[key]) || key;
	}

	function hasMark(text, list) {
		var t = String(text || '').toLowerCase();
		for (var i = 0; i < list.length; i++) {
			if (t.indexOf(String(list[i]).toLowerCase()) !== -1) {
				return true;
			}
		}
		return false;
	}

	function isGeo(text) {
		return hasMark(text, GEO_MARKS);
	}

	function isSeasonal(text) {
		return hasMark(text, SEASON_MARKS);
	}

	function isBranded(text) {
		return hasMark(text, BRAND_MARKS);
	}

	function isLsi(text) {
		var seed = (seedEl.value || '').replace(/\s+/g, ' ').trim();
		var t = String(text || '').replace(/\s+/g, ' ').trim();
		if (!seed || !t || seed === t) {
			return false;
		}
		return t.toLowerCase().indexOf(seed.toLowerCase()) === -1;
	}

	function visibleItems() {
		if (intentFilter === 'all') {
			return items.slice();
		}
		if (intentFilter === 'short' || intentFilter === 'mid' || intentFilter === 'longtail') {
			var want = intentFilter === 'longtail' ? 'long' : intentFilter;
			return items.filter(function (row) {
				return lengthKey(row.text) === want;
			});
		}
		if (intentFilter === 'question') {
			return items.filter(function (row) {
				return isQuestion(row.text);
			});
		}
		if (intentFilter === 'geo') {
			return items.filter(function (row) {
				return isGeo(row.text);
			});
		}
		if (intentFilter === 'seasonal') {
			return items.filter(function (row) {
				return isSeasonal(row.text);
			});
		}
		if (intentFilter === 'lsi') {
			return items.filter(function (row) {
				return isLsi(row.text);
			});
		}
		if (intentFilter === 'branded') {
			return items.filter(function (row) {
				return isBranded(row.text);
			});
		}
		if (intentFilter === 'unbranded') {
			return items.filter(function (row) {
				return !isBranded(row.text);
			});
		}
		return items.filter(function (row) {
			return row.intent === intentFilter;
		});
	}

	function longtailQueries() {
		var cap = parseInt(cfg.longtailCap, 10) || 50;
		var ranked = items.slice().sort(function (a, b) {
			return (b.relevance || 0) - (a.relevance || 0);
		});
		var out = [];
		var used = {};
		ranked.forEach(function (row) {
			var n = wordCount(row.text);
			if (n < 2 || n > 6 || used[row.text]) {
				return;
			}
			used[row.text] = true;
			out.push(row.text + ' ');
		});
		return out.slice(0, cap);
	}

	function formatNum(n) {
		n = parseInt(n, 10);
		if (!n && n !== 0) {
			return '';
		}
		return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function searchesCell(row) {
		var el = document.createElement('span');
		if (row.searches == null) {
			el.className = 'wbgs-searches is-empty';
			el.textContent = '—';
			el.title = i18n('vol_off');
			return el;
		}
		el.className = 'wbgs-searches';
		el.textContent = formatNum(row.searches);
		el.title = 'میانگین جستجوی ماهانهٔ واقعی از Keyword Planner';
		return el;
	}

	function intentBadge(row) {
		var el = document.createElement('span');
		el.className = 'wbgs-intent wbgs-intent-' + (row.intent || 'commercial');
		el.textContent = intentLabel(row.intent || 'commercial');
		return el;
	}

	function compBadge(row) {
		var score = competition(row);
		var band = competitionBand(score);
		var el = document.createElement('span');
		el.className = 'wbgs-comp wbgs-comp-' + band.key;
		el.textContent = score + ' ' + band.fa;
		el.title = i18n('comp_note');
		return el;
	}

	function setView(next) {
		view = next;
		if (listEl) {
			listEl.hidden = view !== 'list';
		}
		if (treeEl) {
			treeEl.hidden = view !== 'tree';
		}
		if (clusterEl) {
			clusterEl.hidden = view !== 'cluster';
		}
		if (briefEl) {
			briefEl.hidden = view !== 'brief';
		}
		if (compareEl) {
			compareEl.hidden = view !== 'compare';
		}
		if (viewListBtn) {
			viewListBtn.classList.toggle('wbgs-view-on', view === 'list');
		}
		if (viewTreeBtn) {
			viewTreeBtn.classList.toggle('wbgs-view-on', view === 'tree');
		}
		if (viewClusterBtn) {
			viewClusterBtn.classList.toggle('wbgs-view-on', view === 'cluster');
		}
		if (viewBriefBtn) {
			viewBriefBtn.classList.toggle('wbgs-view-on', view === 'brief');
		}
		if (viewCompareBtn) {
			viewCompareBtn.classList.toggle('wbgs-view-on', view === 'compare');
		}
	}

	function filterCount(key) {
		if (key === 'all') {
			return items.length;
		}
		var prev = intentFilter;
		intentFilter = key;
		var n = visibleItems().length;
		intentFilter = prev;
		return n;
	}

	function addFilterGroup(title, keys) {
		var box = document.createElement('div');
		box.className = 'wbgs-filter-group';
		var cap = document.createElement('span');
		cap.className = 'wbgs-filter-cap';
		cap.textContent = title;
		box.appendChild(cap);
		var any = false;
		keys.forEach(function (key) {
			var n = filterCount(key);
			if (key !== 'all' && !n) {
				return;
			}
			any = true;
			var label = key === 'all' ? 'همه' : (
				key === 'short' || key === 'mid' || key === 'longtail'
					? lengthLabel(key === 'longtail' ? 'long' : key)
					: (key === 'question' ? 'سوالی' : (
						(cfg.extras && cfg.extras[key]) || intentLabel(key)
					))
			);
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = key === intentFilter ? 'is-on' : '';
			btn.textContent = label + ' ' + n;
			btn.addEventListener('click', function () {
				intentFilter = key;
				render();
			});
			box.appendChild(btn);
		});
		if (any) {
			filtersEl.appendChild(box);
		}
	}

	function renderFilters() {
		if (!filtersEl) {
			return;
		}
		filtersEl.innerHTML = '';
		filtersEl.hidden = items.length === 0;
		if (!items.length) {
			return;
		}
		addFilterGroup('همه', ['all']);
		addFilterGroup('طول و حجم', ['short', 'mid', 'longtail']);
		addFilterGroup('قصد جستجو', ['informational', 'navigational', 'commercial', 'transactional']);
		addFilterGroup('جغرافیا و زمان', ['geo', 'seasonal']);
		addFilterGroup('معنایی', ['lsi']);
		addFilterGroup('برند', ['branded', 'unbranded']);
		addFilterGroup('سوالی', ['question']);
	}

	function renderList() {
		if (!listEl) {
			return;
		}
		listEl.innerHTML = '';
		visibleItems().forEach(function (row) {
			var li = document.createElement('li');
			var kw = document.createElement('span');
			kw.className = 'wbgs-kw';
			kw.appendChild(document.createTextNode(row.text));
			function tag(cls, label) {
				var el = document.createElement('span');
				el.className = 'wbgs-intent ' + cls;
				el.textContent = label;
				kw.appendChild(document.createTextNode(' '));
				kw.appendChild(el);
			}
			var lk = lengthKey(row.text);
			tag('wbgs-intent-len-' + lk, lengthLabel(lk));
			if (isQuestion(row.text)) {
				tag('wbgs-intent-question', 'سوالی');
			}
			if (isGeo(row.text)) {
				tag('wbgs-intent-geo', extraLabel('geo'));
			}
			if (isSeasonal(row.text)) {
				tag('wbgs-intent-seasonal', extraLabel('seasonal'));
			}
			if (isLsi(row.text)) {
				tag('wbgs-intent-lsi', extraLabel('lsi'));
			}
			tag(isBranded(row.text) ? 'wbgs-intent-branded' : 'wbgs-intent-unbranded', extraLabel(isBranded(row.text) ? 'branded' : 'unbranded'));
			li.appendChild(kw);
			li.appendChild(intentBadge(row));
			li.appendChild(compBadge(row));
			li.appendChild(searchesCell(row));
			listEl.appendChild(li);
		});
	}

	function emptyNode(label) {
		return { label: label, children: {}, leaves: [], searches: 0, count: 0 };
	}

	function buildTree(seed, rows) {
		var root = emptyNode(seed || 'ریشه');
		rows.forEach(function (row) {
			var path = branchPath(seed, row.text);
			if (!path.length) {
				root.leaves.push(row);
				return;
			}
			var node = root;
			path.forEach(function (tok, i) {
				if (!node.children[tok]) {
					node.children[tok] = emptyNode(tok);
				}
				if (i === path.length - 1) {
					node.children[tok].leaves.push(row);
				} else {
					node = node.children[tok];
				}
			});
		});
		function rollup(node) {
			var count = node.leaves.length;
			var searches = 0;
			node.leaves.forEach(function (leaf) {
				searches += leaf.searches || 0;
			});
			Object.keys(node.children).forEach(function (key) {
				rollup(node.children[key]);
				count += node.children[key].count;
				searches += node.children[key].searches;
			});
			node.count = count;
			node.searches = searches;
		}
		rollup(root);
		return root;
	}

	function flattenLeaves(node) {
		var out = (node.leaves || []).slice();
		Object.keys(node.children || {}).forEach(function (key) {
			out = out.concat(flattenLeaves(node.children[key]));
		});
		return out;
	}

	function renderKNode(node, open) {
		var wrap = document.createElement('div');
		var keys = Object.keys(node.children).sort(function (a, b) {
			return (node.children[b].searches || node.children[b].count) - (node.children[a].searches || node.children[a].count);
		});
		keys.forEach(function (key) {
			var child = node.children[key];
			var box = document.createElement('div');
			var row = document.createElement('div');
			row.className = 'wbgs-ktree-row is-branch';
			var tog = document.createElement('span');
			tog.className = 'wbgs-ktree-tog';
			tog.textContent = open ? '▾' : '▸';
			var name = document.createElement('span');
			name.className = 'wbgs-kw';
			name.appendChild(tog);
			name.appendChild(document.createTextNode(child.label + ' (' + child.count + ')'));
			row.appendChild(name);
			var gap = document.createElement('span');
			gap.className = 'wbgs-intent wbgs-intent-branch';
			gap.textContent = 'شاخه';
			row.appendChild(gap);
			row.appendChild(searchesCell({ searches: child.searches || null }));
			var kids = document.createElement('div');
			kids.className = 'wbgs-ktree-kids';
			kids.hidden = !open;
			kids.appendChild(renderKNode(child, child.count < 8));
			row.addEventListener('click', function () {
				kids.hidden = !kids.hidden;
				tog.textContent = kids.hidden ? '▸' : '▾';
			});
			box.appendChild(row);
			box.appendChild(kids);
			wrap.appendChild(box);
		});
		if (node.leaves.length) {
			node.leaves.slice().sort(function (a, b) {
				return (b.searches || 0) - (a.searches || 0);
			}).forEach(function (row) {
				var line = document.createElement('div');
				line.className = 'wbgs-ktree-row';
				var kw = document.createElement('span');
				kw.className = 'wbgs-kw';
				kw.textContent = row.text;
				line.appendChild(kw);
				line.appendChild(intentBadge(row));
				line.appendChild(searchesCell(row));
				wrap.appendChild(line);
			});
		}
		return wrap;
	}

	function renderTree() {
		if (!treeEl) {
			return;
		}
		treeEl.innerHTML = '';
		var head = document.createElement('div');
		head.className = 'wbgs-ktree-head';
		['عبارت', 'اینتنت', 'سرچ ماهانه'].forEach(function (label) {
			var cell = document.createElement('span');
			cell.textContent = label;
			head.appendChild(cell);
		});
		treeEl.appendChild(head);
		treeEl.appendChild(renderKNode(buildTree((seedEl.value || '').trim(), visibleItems()), true));
	}

	function renderCluster() {
		if (!clusterEl) {
			return;
		}
		clusterEl.innerHTML = '';
		var seed = (seedEl.value || '').trim();
		var tree = buildTree(seed, items);
		var mmap = document.createElement('div');
		mmap.className = 'wbgs-mmap';
		var hub = document.createElement('div');
		hub.className = 'wbgs-mmap-hub';
		var hubTitle = document.createElement('strong');
		hubTitle.textContent = seed || 'پیلار';
		var hubSub = document.createElement('span');
		hubSub.textContent = 'پیلار — صفحهٔ ستون محتوا';
		hub.appendChild(hubTitle);
		hub.appendChild(hubSub);
		mmap.appendChild(hub);
		var grid = document.createElement('div');
		grid.className = 'wbgs-mmap-grid';
		Object.keys(tree.children).sort(function (a, b) {
			return (tree.children[b].searches || tree.children[b].count) - (tree.children[a].searches || tree.children[a].count);
		}).forEach(function (key) {
			var child = tree.children[key];
			var card = document.createElement('article');
			card.className = 'wbgs-mmap-card';
			var intents = {};
			var leaves = flattenLeaves(child);
			leaves.forEach(function (row) {
				intents[row.intent] = (intents[row.intent] || 0) + 1;
			});
			var top = 'commercial';
			var max = 0;
			Object.keys(intents).forEach(function (k) {
				if (intents[k] > max) {
					max = intents[k];
					top = k;
				}
			});
			var h = document.createElement('h3');
			h.appendChild(document.createTextNode(key));
			h.appendChild(intentBadge({ intent: top }));
			card.appendChild(h);
			var meta = document.createElement('p');
			meta.className = 'wbgs-hint';
			meta.textContent = child.count + ' عبارت' + (child.searches ? ' · مجموع سرچ ماهانه ' + formatNum(child.searches) : '');
			card.appendChild(meta);
			var ul = document.createElement('ul');
			leaves.slice(0, 10).forEach(function (row) {
				var li = document.createElement('li');
				var t = document.createElement('span');
				t.textContent = row.text;
				li.appendChild(t);
				li.appendChild(searchesCell(row));
				ul.appendChild(li);
			});
			card.appendChild(ul);
			grid.appendChild(card);
		});
		mmap.appendChild(grid);
		clusterEl.appendChild(mmap);
	}

	function charLen(text) {
		return Array.from(String(text || '')).length;
	}

	function pickTitle(phrases) {
		var best = '';
		var bestScore = -1;
		phrases.forEach(function (phrase) {
			phrase = String(phrase || '').replace(/\s+/g, ' ').trim();
			if (!phrase) {
				return;
			}
			var n = wordCount(phrase);
			var len = charLen(phrase);
			if (n < 2 || n > 8 || len > 70) {
				return;
			}
			if (classifyIntent(phrase) === 'navigational') {
				return;
			}
			var score = 100 - Math.abs(40 - len);
			if (isQuestion(phrase)) {
				score -= 12;
			}
			if (score > bestScore) {
				bestScore = score;
				best = phrase;
			}
		});
		if (best) {
			return best;
		}
		return phrases.length ? String(phrases[0]).replace(/\s+/g, ' ').trim() : '';
	}

	function pickMeta(phrases, title) {
		title = String(title || '').replace(/\s+/g, ' ').trim();
		var parts = [];
		for (var i = 0; i < phrases.length; i++) {
			var phrase = String(phrases[i] || '').replace(/\s+/g, ' ').trim();
			if (!phrase || phrase === title) {
				continue;
			}
			parts.push(phrase);
			if (charLen(parts.join('، ')) >= 120 || parts.length >= 3) {
				break;
			}
		}
		var joined = parts.join('، ');
		if (charLen(joined) > 160) {
			joined = Array.from(joined).slice(0, 157).join('') + '…';
		}
		return joined;
	}

	function briefFromRows(seed, name, rows, isPillar) {
		var phrases = [];
		var questions = [];
		var comps = [];
		rows.forEach(function (row) {
			if (!row.text) {
				return;
			}
			phrases.push(row.text);
			if (isQuestion(row.text)) {
				questions.push(row.text);
			}
			comps.push(competition(row));
		});
		var title = isPillar && phrases.indexOf(seed) !== -1 ? seed : pickTitle(phrases);
		var h2 = [];
		questions.forEach(function (q) {
			if (q !== title) {
				h2.push(q);
			}
		});
		phrases.forEach(function (p) {
			if (p === title || h2.indexOf(p) !== -1) {
				return;
			}
			h2.push(p);
			if (h2.length >= 8) {
				return;
			}
		});
		h2 = h2.slice(0, isPillar ? 10 : 8);
		var avg = comps.length ? Math.round(comps.reduce(function (a, b) { return a + b; }, 0) / comps.length) : 0;
		var band = competitionBand(avg);
		var intents = {};
		rows.forEach(function (row) {
			intents[row.intent] = (intents[row.intent] || 0) + 1;
		});
		var top = 'commercial';
		var max = 0;
		Object.keys(intents).forEach(function (k) {
			if (intents[k] > max) {
				max = intents[k];
				top = k;
			}
		});
		return {
			cluster: name,
			is_pillar: !!isPillar,
			h1: title,
			h2: h2,
			questions: questions,
			title: title,
			meta: pickMeta(phrases, title),
			intent: top,
			intent_fa: intentLabel(top),
			count: phrases.length,
			competition: avg,
			competition_band_fa: band.fa
		};
	}

	function buildBriefs(seed, rows) {
		var tree = buildTree(seed, rows);
		var out = [briefFromRows(seed, seed, rows, true)];
		Object.keys(tree.children).forEach(function (key) {
			out.push(briefFromRows(seed, key, flattenLeaves(tree.children[key]), false));
		});
		return out;
	}

	function renderBrief() {
		if (!briefEl) {
			return;
		}
		briefEl.innerHTML = '';
		if (!items.length) {
			return;
		}
		var seed = (seedEl.value || '').trim();
		var note = document.createElement('p');
		note.className = 'wbgs-hint';
		note.textContent = 'H1، H2، عنوان و متا فقط از پیشنهادهای واقعی گوگل هستند. ' + i18n('comp_note');
		briefEl.appendChild(note);
		buildBriefs(seed, items).forEach(function (b) {
			var card = document.createElement('article');
			card.className = 'wbgs-brief-card' + (b.is_pillar ? ' is-pillar' : '');
			var h = document.createElement('h3');
			h.appendChild(document.createTextNode((b.is_pillar ? 'پیلار: ' : 'کلاستر: ') + b.cluster));
			h.appendChild(intentBadge({ intent: b.intent }));
			card.appendChild(h);
			var meta = document.createElement('p');
			meta.className = 'wbgs-hint';
			meta.textContent = b.count + ' عبارت · رقابت نسبی ' + b.competition + ' (' + b.competition_band_fa + ')';
			card.appendChild(meta);
			[['H1', b.h1], ['عنوان', b.title], ['متا', b.meta]].forEach(function (pair) {
				if (!pair[1]) {
					return;
				}
				var p = document.createElement('p');
				var lab = document.createElement('strong');
				lab.textContent = pair[0] + ': ';
				p.appendChild(lab);
				p.appendChild(document.createTextNode(pair[1]));
				card.appendChild(p);
			});
			if (b.h2.length) {
				var h2t = document.createElement('p');
				h2t.innerHTML = '<strong>H2</strong>';
				card.appendChild(h2t);
				var ul = document.createElement('ul');
				b.h2.forEach(function (line) {
					var li = document.createElement('li');
					li.textContent = line;
					ul.appendChild(li);
				});
				card.appendChild(ul);
			}
			briefEl.appendChild(card);
		});
	}

	function buildCompare(a, b) {
		var mapA = {};
		var mapB = {};
		a.forEach(function (r) {
			mapA[r.text] = r;
		});
		b.forEach(function (r) {
			mapB[r.text] = r;
		});
		var both = [];
		var onlyA = [];
		var onlyB = [];
		Object.keys(mapA).forEach(function (t) {
			if (mapB[t]) {
				both.push(mapA[t]);
			} else {
				onlyA.push(mapA[t]);
			}
		});
		Object.keys(mapB).forEach(function (t) {
			if (!mapA[t]) {
				onlyB.push(mapB[t]);
			}
		});
		return {
			both: both,
			only_a: onlyA,
			only_b: onlyB,
			stats: {
				a: a.length,
				b: b.length,
				shared: both.length,
				only_a: onlyA.length,
				only_b: onlyB.length
			}
		};
	}

	function renderCompare() {
		if (!compareEl) {
			return;
		}
		compareEl.innerHTML = '';
		if (!lastCompare) {
			var hint = document.createElement('p');
			hint.className = 'wbgs-hint';
			hint.textContent = i18n('need_b');
			compareEl.appendChild(hint);
			return;
		}
		var stats = lastCompare.stats;
		var head = document.createElement('p');
		head.className = 'wbgs-hint';
		head.textContent = (lastSeedA || 'عبارت ۱') + ' (' + stats.a + ') در برابر ' + (lastSeedB || 'عبارت ۲') + ' (' + stats.b + ') — مشترک ' + stats.shared + ' · فقط اولی ' + stats.only_a + ' · فقط دومی ' + stats.only_b;
		compareEl.appendChild(head);
		[
			{ title: 'مشترک', rows: lastCompare.both },
			{ title: 'فقط «' + (lastSeedA || 'اول') + '»', rows: lastCompare.only_a },
			{ title: 'فقط «' + (lastSeedB || 'دوم') + '»', rows: lastCompare.only_b }
		].forEach(function (col) {
			var card = document.createElement('article');
			card.className = 'wbgs-compare-card';
			var h = document.createElement('h3');
			h.textContent = col.title + ' (' + col.rows.length + ')';
			card.appendChild(h);
			var ul = document.createElement('ul');
			col.rows.slice(0, 80).forEach(function (row) {
				var li = document.createElement('li');
				li.textContent = row.text;
				ul.appendChild(li);
			});
			card.appendChild(ul);
			compareEl.appendChild(card);
		});
	}

	function setButtons() {
		var empty = items.length === 0;
		[copyBtn, csvBtn, txtBtn, briefTxtBtn, viewListBtn, viewTreeBtn, viewClusterBtn, viewBriefBtn, saveBtn].forEach(function (btn) {
			if (btn) {
				btn.disabled = empty;
			}
		});
		if (viewCompareBtn) {
			viewCompareBtn.disabled = !lastCompare;
		}
		if (loadBtn) {
			loadBtn.disabled = !(historyEl && historyEl.value);
		}
		if (compareSavedBtn) {
			compareSavedBtn.disabled = empty || !(historyEl && historyEl.value);
		}
	}

	function render() {
		if (countEl) {
			countEl.textContent = items.length + ' عبارت';
		}
		if (emptyEl) {
			emptyEl.hidden = items.length > 0;
		}
		setButtons();
		if (googleRoot && items.length) {
			googleRoot.classList.add('has-results');
		}
		renderFilters();
		renderList();
		renderTree();
		renderCluster();
		renderBrief();
		renderCompare();
		setView(view);
	}

	function addItems(batch) {
		(batch || []).forEach(function (raw, idx) {
			var text = '';
			var relevance = 0;
			var rank = idx + 1;
			var intent = '';
			var searches = null;
			var count = 1;
			if (typeof raw === 'string') {
				text = raw;
			} else if (raw && typeof raw === 'object') {
				text = raw.text || '';
				relevance = parseInt(raw.relevance, 10) || 0;
				rank = parseInt(raw.rank, 10) || rank;
				count = parseInt(raw.count, 10) || 1;
				intent = raw.intent || '';
				if (raw.searches != null && raw.searches !== '') {
					searches = parseInt(raw.searches, 10);
					if (isNaN(searches)) {
						searches = null;
					}
				}
			}
			if (!text) {
				return;
			}
			if (seen[text]) {
				var prev = seen[text];
				prev.count += 1;
				prev.relevance = Math.max(prev.relevance, relevance);
				if (rank && rank < prev.rank) {
					prev.rank = rank;
				}
				if (searches != null) {
					prev.searches = searches;
				}
				return;
			}
			var row = {
				text: text,
				relevance: relevance,
				rank: rank,
				count: count,
				intent: intent || classifyIntent(text),
				searches: searches
			};
			seen[text] = row;
			items.push(row);
		});
		render();
	}

	function setBusy(on) {
		running = on;
		startBtn.disabled = on || !cfg.licensed;
		if (stopBtn) {
			stopBtn.hidden = !on;
		}
		seedEl.disabled = on || !cfg.licensed;
		if (seedBEl) {
			seedBEl.disabled = on || !cfg.licensed;
		}
		document.querySelectorAll('input[name="wbgs-mode"]').forEach(function (el) {
			el.disabled = on || !cfg.licensed;
		});
		if (googleRoot) {
			googleRoot.classList.toggle('is-busy', on);
		}
	}

	function setProgress(current, total) {
		if (!progressWrap) {
			return;
		}
		var pct = total ? Math.round((current / total) * 100) : 0;
		progressWrap.hidden = false;
		progressBar.style.width = pct + '%';
		progressText.textContent = current + ' از ' + total + ' کوئری';
	}

	function post(action, extra) {
		var body = new window.URLSearchParams();
		body.set('action', action);
		body.set('nonce', cfg.nonce || '');
		Object.keys(extra || {}).forEach(function (key) {
			var val = extra[key];
			if (Array.isArray(val)) {
				val.forEach(function (v) {
					body.append(key + '[]', v);
				});
			} else if (val && typeof val === 'object') {
				Object.keys(val).forEach(function (inner) {
					body.set(key + '[' + inner + ']', val[inner]);
				});
			} else {
				body.set(key, val);
			}
		});
		return fetch(cfg.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (res) {
			return res.json().then(function (json) {
				return { http: res.status, json: json };
			});
		});
	}

	function sleep(ms) {
		return new Promise(function (resolve) {
			window.setTimeout(resolve, ms);
		});
	}

	function download(name, content, mime) {
		var blob = new Blob([content], { type: mime });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = name;
		document.body.appendChild(a);
		a.click();
		a.remove();
		URL.revokeObjectURL(url);
	}

	function csvEscape(value) {
		value = String(value == null ? '' : value);
		if (/[",\n\r]/.test(value)) {
			return '"' + value.replace(/"/g, '""') + '"';
		}
		return value;
	}

	function briefMap(seed, rows) {
		var map = {};
		buildBriefs(seed, rows).forEach(function (b) {
			if (!b.is_pillar) {
				map[b.cluster] = b;
			}
		});
		return map;
	}

	function excelCsv(seed, rows) {
		var header = ['keyword', 'words', 'length', 'intent', 'longtail', 'question', 'geo', 'seasonal', 'lsi', 'branded', 'cluster', 'pillar', 'suggest_relevance', 'suggest_rank', 'suggest_score', 'relative_competition', 'competition_band', 'monthly_searches', 'title', 'meta'];
		var briefs = briefMap(seed, rows);
		var pillar = buildBriefs(seed, rows)[0] || { title: '', meta: '' };
		var lines = [header.join(',')];
		rows.forEach(function (row) {
			var cluster = clusterName(seed, row.text);
			var brief = briefs[cluster] || pillar;
			var band = competitionBand(competition(row));
			lines.push([
				csvEscape(row.text),
				wordCount(row.text),
				csvEscape(lengthLabel(lengthKey(row.text))),
				csvEscape(intentLabel(row.intent)),
				isLongTail(row.text) ? '1' : '0',
				isQuestion(row.text) ? '1' : '0',
				isGeo(row.text) ? '1' : '0',
				isSeasonal(row.text) ? '1' : '0',
				isLsi(row.text) ? '1' : '0',
				isBranded(row.text) ? '1' : '0',
				csvEscape(cluster),
				csvEscape(seed),
				row.relevance || 0,
				row.rank || 0,
				suggestScore(row),
				competition(row),
				csvEscape(band.fa),
				row.searches == null ? '' : row.searches,
				csvEscape(brief.title || ''),
				csvEscape(brief.meta || '')
			].join(','));
		});
		return '\uFEFF' + lines.join('\n');
	}

	function briefText(seed, rows) {
		var lines = [
			'بریف محتوا — ' + seed,
			'منبع: فقط پیشنهادهای واقعی Autocomplete گوگل',
			i18n('comp_note'),
			''
		];
		buildBriefs(seed, rows).forEach(function (b) {
			lines.push((b.is_pillar ? '## پیلار: ' : '## کلاستر: ') + b.cluster);
			lines.push('H1: ' + b.h1);
			lines.push('عنوان: ' + b.title);
			lines.push('متا: ' + b.meta);
			lines.push('اینتنت: ' + b.intent_fa);
			lines.push('امتیاز رقابت نسبی: ' + b.competition + ' (' + b.competition_band_fa + ')');
			if (b.h2.length) {
				lines.push('H2:');
				b.h2.forEach(function (h) {
					lines.push('- ' + h);
				});
			}
			if (b.questions.length) {
				lines.push('سوالات:');
				b.questions.forEach(function (q) {
					lines.push('- ' + q);
				});
			}
			lines.push('');
		});
		return lines.join('\n');
	}

	function localReports() {
		try {
			var raw = window.localStorage.getItem(LS_KEY);
			var list = raw ? JSON.parse(raw) : [];
			return Array.isArray(list) ? list : [];
		} catch (err) {
			return [];
		}
	}

	function writeLocalReports(list) {
		try {
			window.localStorage.setItem(LS_KEY, JSON.stringify((list || []).slice(0, 40)));
		} catch (err) {
			/* ignore quota */
		}
	}

	function compactRow(row) {
		return {
			text: row.text,
			relevance: row.relevance || 0,
			rank: row.rank || 0,
			count: row.count || 1,
			intent: row.intent || 'commercial',
			searches: row.searches == null ? null : row.searches
		};
	}

	function refreshHistory() {
		if (!historyEl) {
			return;
		}
		var merged = [];
		var seenId = {};
		localReports().forEach(function (r) {
			if (r && r.id && !seenId[r.id]) {
				seenId[r.id] = r;
				merged.push(r);
			}
		});
		(cfg.reportList || []).forEach(function (r) {
			if (r && r.id && !seenId[r.id]) {
				seenId[r.id] = r;
				merged.push({ id: r.id, seed: r.seed, created: r.created, count: r.count, remote: true });
			}
		});
		historyEl.innerHTML = '';
		var ph = document.createElement('option');
		ph.value = '';
		ph.textContent = merged.length ? (merged.length + ' گزارش') : 'تاریخچه خالی است';
		historyEl.appendChild(ph);
		merged.forEach(function (r) {
			var o = document.createElement('option');
			o.value = r.id;
			o.textContent = (r.seed || '—') + ' — ' + (r.count || 0) + ' عبارت';
			historyEl.appendChild(o);
		});
		setButtons();
	}

	function persistLocalAuto() {
		if (!items.length || !seedEl) {
			return;
		}
		var packed = {
			id: 'local-' + Date.now(),
			seed: (seedEl.value || '').trim(),
			created: Math.floor(Date.now() / 1000),
			count: items.length,
			rows: items.map(compactRow)
		};
		var list = localReports().filter(function (r) {
			return r.seed !== packed.seed;
		});
		list.unshift(packed);
		writeLocalReports(list);
		refreshHistory();
	}

	function saveCurrent() {
		if (!items.length || !seedEl) {
			return;
		}
		persistLocalAuto();
		setStatus(i18n('saved'), 'ok');
		if (!cfg.canSave) {
			return;
		}
		post('wbgs_report_save', {
			seed: (seedEl.value || '').trim(),
			rows: JSON.stringify(items.map(compactRow))
		}).then(function (out) {
			if (out.json && out.json.success && out.json.data && out.json.data.list) {
				cfg.reportList = out.json.data.list;
				refreshHistory();
			}
		}).catch(function () {
			/* local copy already saved */
		});
	}

	function findLocal(id) {
		var list = localReports();
		for (var i = 0; i < list.length; i++) {
			if (list[i].id === id) {
				return list[i];
			}
		}
		return null;
	}

	function applyReport(report) {
		items = [];
		seen = {};
		lastCompare = null;
		itemsB = [];
		if (seedEl) {
			seedEl.value = report.seed || '';
		}
		lastSeedA = report.seed || '';
		addItems(report.rows || []);
		view = 'list';
		setView(view);
		setStatus(i18n('loaded'), 'ok');
	}

	function loadSelected() {
		if (!historyEl || !historyEl.value) {
			return;
		}
		var id = historyEl.value;
		var local = findLocal(id);
		if (local) {
			applyReport(local);
			return;
		}
		post('wbgs_report_get', { id: id }).then(function (out) {
			if (!out.json || !out.json.success || !out.json.data || !out.json.data.report) {
				throw new Error((out.json && out.json.data && out.json.data.message) || i18n('network'));
			}
			applyReport(out.json.data.report);
		}).catch(function (err) {
			setStatus(err.message || i18n('network'), 'error');
		});
	}

	function compareSelected() {
		if (!historyEl || !historyEl.value || !items.length) {
			setStatus(i18n('need_b'), 'error');
			return;
		}
		var id = historyEl.value;
		var local = findLocal(id);
		function use(report) {
			itemsB = report.rows || [];
			lastSeedB = report.seed || '';
			lastCompare = buildCompare(items, itemsB);
			view = 'compare';
			render();
			setStatus(i18n('compare'), 'ok');
		}
		if (local) {
			use(local);
			return;
		}
		post('wbgs_report_get', { id: id }).then(function (out) {
			if (!out.json || !out.json.success || !out.json.data || !out.json.data.report) {
				throw new Error((out.json && out.json.data && out.json.data.message) || i18n('network'));
			}
			use(out.json.data.report);
		}).catch(function (err) {
			setStatus(err.message || i18n('network'), 'error');
		});
	}

	function loadVolumes() {
		if (!items.length) {
			return Promise.resolve();
		}
		if (!cfg.ads) {
			setStatus(i18n('vol_off'), '');
			return Promise.resolve();
		}
		setStatus(i18n('vol_wait'), '');
		return post('wbgs_volumes', {
			keywords: items.map(function (row) {
				return row.text;
			})
		}).then(function (out) {
			if (!out.json || !out.json.success) {
				var msg = out.json && out.json.data && out.json.data.message ? out.json.data.message : i18n('vol_off');
				setStatus(msg, 'error');
				return;
			}
			var map = (out.json.data && out.json.data.volumes) || {};
			items.forEach(function (row) {
				var hit = map[row.text] || map[String(row.text).replace(/\s+/g, ' ').trim()];
				if (hit && hit.searches != null) {
					row.searches = parseInt(hit.searches, 10);
					if (isNaN(row.searches)) {
						row.searches = null;
					}
				}
			});
			render();
			setStatus(i18n('done'), 'ok');
		});
	}

	function runQueries(queries) {
		var delay = Math.max(150, parseInt(cfg.delay, 10) || 300);
		var i = 0;
		function next() {
			if (stopFlag) {
				setStatus(i18n('stopped'), 'ok');
				return Promise.resolve();
			}
			if (i >= queries.length) {
				setProgress(queries.length, queries.length);
				if (!items.length) {
					setStatus(i18n('none'), 'error');
				} else {
					setStatus(i18n('done'), 'ok');
				}
				return Promise.resolve();
			}
			var q = queries[i];
			setProgress(i, queries.length);
			return post('wbgs_fetch', { q: q }).then(function (out) {
				if (out.json && out.json.data && out.json.data.usage) {
					cfg.usage = out.json.data.usage;
					showUsage(cfg.usage);
				}
				if (out.http === 429 || (out.json && out.json.data && out.json.data.code === 'limited')) {
					throw new Error((out.json && out.json.data && out.json.data.message) || i18n('limited'));
				}
				if (!out.json || !out.json.success) {
					var msg = out.json && out.json.data && out.json.data.message ? out.json.data.message : i18n('network');
					throw new Error(msg);
				}
				addItems(out.json.data.items || []);
				i += 1;
				setProgress(i, queries.length);
				return sleep(delay).then(next);
			});
		}
		return next();
	}

	function runExtractForSeed(seed) {
		items = [];
		seen = {};
		if (seedEl) {
			seedEl.value = seed;
		}
		return post('wbgs_queries', { seed: seed, modes: selectedModes() })
			.then(function (out) {
				if (!out.json || !out.json.success) {
					var msg = out.json && out.json.data && out.json.data.message ? out.json.data.message : i18n('network');
					throw new Error(msg);
				}
				return runQueries(out.json.data.queries || []);
			})
			.then(function () {
				if (stopFlag || !items.length || !selectedModes().longtail) {
					return;
				}
				var extra = longtailQueries();
				if (!extra.length) {
					return;
				}
				setStatus(i18n('longtail'), '');
				return runQueries(extra);
			})
			.then(function () {
				return items.slice();
			});
	}

	startBtn.addEventListener('click', function () {
		if (running) {
			return;
		}
		if (!cfg.licensed) {
			setStatus(i18n('locked'), 'error');
			return;
		}
		var seed = (seedEl.value || '').trim();
		if (!seed) {
			setStatus(i18n('empty'), 'error');
			seedEl.focus();
			return;
		}
		items = [];
		seen = {};
		itemsA = [];
		itemsB = [];
		lastCompare = null;
		lastSeedA = seed;
		lastSeedB = seedBEl ? (seedBEl.value || '').trim() : '';
		stopFlag = false;
		view = 'list';
		intentFilter = 'all';
		render();
		setBusy(true);
		setStatus('');
		setProgress(0, 1);

		runExtractForSeed(seed)
			.then(function (rowsA) {
				itemsA = rowsA || [];
				if (stopFlag || !lastSeedB) {
					return;
				}
				setStatus(i18n('comp_b'), '');
				return runExtractForSeed(lastSeedB).then(function (rowsB) {
					itemsB = rowsB || [];
					lastCompare = buildCompare(itemsA, itemsB);
					items = [];
					seen = {};
					if (seedEl) {
						seedEl.value = lastSeedA;
					}
					addItems(itemsA);
				});
			})
			.then(function () {
				if (items.length) {
					view = lastCompare ? 'compare' : 'list';
					persistLocalAuto();
					return loadVolumes();
				}
			})
			.catch(function (err) {
				setStatus(err.message || i18n('network'), 'error');
			})
			.then(function () {
				setBusy(false);
				if (items.length) {
					render();
				}
			});
	});

	if (stopBtn) {
		stopBtn.addEventListener('click', function () {
			stopFlag = true;
		});
	}
	if (viewListBtn) {
		viewListBtn.addEventListener('click', function () {
			setView('list');
		});
	}
	if (viewTreeBtn) {
		viewTreeBtn.addEventListener('click', function () {
			setView('tree');
		});
	}
	if (viewClusterBtn) {
		viewClusterBtn.addEventListener('click', function () {
			setView('cluster');
		});
	}
	if (viewBriefBtn) {
		viewBriefBtn.addEventListener('click', function () {
			setView('brief');
		});
	}
	if (viewCompareBtn) {
		viewCompareBtn.addEventListener('click', function () {
			setView('compare');
		});
	}
	if (saveBtn) {
		saveBtn.addEventListener('click', saveCurrent);
	}
	if (loadBtn) {
		loadBtn.addEventListener('click', loadSelected);
	}
	if (compareSavedBtn) {
		compareSavedBtn.addEventListener('click', compareSelected);
	}
	if (historyEl) {
		historyEl.addEventListener('change', setButtons);
	}

	copyBtn.addEventListener('click', function () {
		var text = items.map(function (row) {
			return row.text;
		}).join('\n');
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				setStatus(i18n('copy_ok'), 'ok');
			}).catch(function () {
				setStatus(i18n('copy_err'), 'error');
			});
			return;
		}
		setStatus(i18n('copy_err'), 'error');
	});

	csvBtn.addEventListener('click', function () {
		var seed = (seedEl.value || '').trim();
		download('google-suggest.csv', excelCsv(seed, items), 'text/csv;charset=utf-8');
	});

	txtBtn.addEventListener('click', function () {
		download('google-suggest.txt', items.map(function (row) {
			return row.text;
		}).join('\n'), 'text/plain;charset=utf-8');
	});

	if (briefTxtBtn) {
		briefTxtBtn.addEventListener('click', function () {
			var seed = (seedEl.value || '').trim();
			download('content-brief.txt', briefText(seed, items), 'text/plain;charset=utf-8');
		});
	}

	if (seedEl && startBtn) {
		seedEl.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				startBtn.click();
			}
		});
	}

	showUsage(cfg.usage);
	refreshHistory();
})();
