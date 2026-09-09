(function () {
	'use strict';

	var cfg = window.wbgsAdmin || {};
	var seedEl = document.getElementById('wbgs-seed');
	var startBtn = document.getElementById('wbgs-start');
	var stopBtn = document.getElementById('wbgs-stop');
	var listEl = document.getElementById('wbgs-list');
	var treeEl = document.getElementById('wbgs-tree');
	var clusterEl = document.getElementById('wbgs-cluster');
	var emptyEl = document.getElementById('wbgs-empty');
	var countEl = document.getElementById('wbgs-count');
	var statusEl = document.getElementById('wbgs-status');
	var progressWrap = document.getElementById('wbgs-progress');
	var progressBar = document.getElementById('wbgs-progress-bar');
	var progressText = document.getElementById('wbgs-progress-text');
	var copyBtn = document.getElementById('wbgs-copy');
	var csvBtn = document.getElementById('wbgs-csv');
	var txtBtn = document.getElementById('wbgs-txt');
	var viewListBtn = document.getElementById('wbgs-view-list');
	var viewTreeBtn = document.getElementById('wbgs-view-tree');
	var viewClusterBtn = document.getElementById('wbgs-view-cluster');
	var filtersEl = document.getElementById('wbgs-intent-filters');
	var googleRoot = document.querySelector('[data-wbgs-ui="google"]');

	if (!seedEl || !startBtn) {
		return;
	}

	var running = false;
	var stopFlag = false;
	var view = 'list';
	var intentFilter = 'all';
	var items = [];
	var seen = {};

	var INTENT_RULES = {
		navigational: ['دیجی کالا', 'دیجیکالا', 'آمازون', 'دیوار', 'اینستاگرام', 'ترب', 'amazon', 'digikala', 'instagram', '.com', '.ir'],
		transactional: ['خرید', 'فروش', 'سفارش', 'ارزان', 'تخفیف', 'قیمت', 'اینترنتی', 'آنلاین', 'buy', 'price', 'cheap', 'order', 'shop'],
		informational: ['چیست', 'چیه', 'چگونه', 'چطور', 'چرا', 'یعنی', 'آموزش', 'راهنما', 'معنی', 'how', 'what', 'why'],
		commercial: ['بهترین', 'مقایسه', 'بررسی', 'انواع', 'مدل', 'تفاوت', 'best', 'review', 'compare']
	};

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

	function wordCount(text) {
		return tokens(text).length;
	}

	function isLongTail(text) {
		return wordCount(text) >= 4;
	}

	function visibleItems() {
		if (intentFilter === 'all') {
			return items.slice();
		}
		if (intentFilter === 'longtail') {
			return items.filter(function (row) {
				return isLongTail(row.text);
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
		if (viewListBtn) {
			viewListBtn.classList.toggle('wbgs-view-on', view === 'list');
		}
		if (viewTreeBtn) {
			viewTreeBtn.classList.toggle('wbgs-view-on', view === 'tree');
		}
		if (viewClusterBtn) {
			viewClusterBtn.classList.toggle('wbgs-view-on', view === 'cluster');
		}
	}

	function renderFilters() {
		if (!filtersEl) {
			return;
		}
		var counts = { all: items.length };
		items.forEach(function (row) {
			counts[row.intent] = (counts[row.intent] || 0) + 1;
		});
		filtersEl.innerHTML = '';
		filtersEl.hidden = items.length === 0;
		var longCount = items.filter(function (row) {
			return isLongTail(row.text);
		}).length;
		var keys = ['all', 'longtail', 'informational', 'commercial', 'transactional', 'navigational'];
		keys.forEach(function (key) {
			if (key === 'longtail') {
				if (!longCount) {
					return;
				}
			} else if (key !== 'all' && !counts[key]) {
				return;
			}
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = key === intentFilter ? 'is-on' : '';
			var label = key === 'all' ? 'همه' : (key === 'longtail' ? 'لانگ‌تیل' : intentLabel(key));
			var n = key === 'longtail' ? longCount : (counts[key] || 0);
			btn.textContent = label + ' ' + n;
			btn.addEventListener('click', function () {
				intentFilter = key;
				render();
			});
			filtersEl.appendChild(btn);
		});
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
			if (isLongTail(row.text)) {
				var lt = document.createElement('span');
				lt.className = 'wbgs-intent wbgs-intent-longtail';
				lt.textContent = 'لانگ‌تیل';
				kw.appendChild(document.createTextNode(' '));
				kw.appendChild(lt);
			}
			li.appendChild(kw);
			li.appendChild(intentBadge(row));
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
			var leaves = [];
			(function walk(n) {
				leaves = leaves.concat(n.leaves || []);
				Object.keys(n.children || {}).forEach(function (k) {
					walk(n.children[k]);
				});
			})(child);
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

	function render() {
		countEl.textContent = items.length + ' عبارت';
		emptyEl.hidden = items.length > 0;
		copyBtn.disabled = items.length === 0;
		csvBtn.disabled = items.length === 0;
		txtBtn.disabled = items.length === 0;
		if (viewListBtn) {
			viewListBtn.disabled = items.length === 0;
		}
		if (viewTreeBtn) {
			viewTreeBtn.disabled = items.length === 0;
		}
		if (viewClusterBtn) {
			viewClusterBtn.disabled = items.length === 0;
		}
		if (googleRoot && items.length) {
			googleRoot.classList.add('has-results');
		}
		renderFilters();
		renderList();
		renderTree();
		renderCluster();
		setView(view);
	}

	function addItems(batch) {
		(batch || []).forEach(function (raw, idx) {
			var text = '';
			var relevance = 0;
			var rank = idx + 1;
			if (typeof raw === 'string') {
				text = raw;
			} else if (raw && typeof raw === 'object') {
				text = raw.text || '';
				relevance = parseInt(raw.relevance, 10) || 0;
				rank = parseInt(raw.rank, 10) || rank;
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
				return;
			}
			var row = {
				text: text,
				relevance: relevance,
				rank: rank,
				count: 1,
				intent: classifyIntent(text),
				searches: null
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
		if (/[",\n]/.test(value)) {
			return '"' + value.replace(/"/g, '""') + '"';
		}
		return value;
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
		stopFlag = false;
		view = 'list';
		intentFilter = 'all';
		render();
		setBusy(true);
		setStatus('');
		setProgress(0, 1);

		post('wbgs_queries', { seed: seed, modes: selectedModes() })
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
				if (items.length) {
					view = 'list';
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
		var header = ['keyword', 'intent', 'longtail', 'words', 'searches', 'branch'];
		var rows = [header.join(',')].concat(items.map(function (row) {
			return [
				csvEscape(row.text),
				csvEscape(intentLabel(row.intent)),
				isLongTail(row.text) ? '1' : '0',
				wordCount(row.text),
				row.searches == null ? '' : row.searches,
				csvEscape(branchPath(seed, row.text).join(' > '))
			].join(',');
		}));
		download('google-suggest.csv', rows.join('\n'), 'text/csv;charset=utf-8');
	});

	txtBtn.addEventListener('click', function () {
		download('google-suggest.txt', items.map(function (row) {
			return row.text;
		}).join('\n'), 'text/plain;charset=utf-8');
	});

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
				if (out.http === 429 || (out.json && out.json.data && out.json.data.code === 'limited')) {
					throw new Error(i18n('limited'));
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

	if (seedEl && startBtn) {
		seedEl.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				startBtn.click();
			}
		});
	}
})();
