(function () {
	'use strict';

	var cfg = window.wbgsAdmin || {};
	var seedEl = document.getElementById('wbgs-seed');
	var startBtn = document.getElementById('wbgs-start');
	var stopBtn = document.getElementById('wbgs-stop');
	var listEl = document.getElementById('wbgs-list');
	var treeEl = document.getElementById('wbgs-tree');
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

	if (!seedEl || !startBtn) {
		return;
	}

	var running = false;
	var stopFlag = false;
	var view = 'list';
	var items = [];
	var seen = {};

	function i18n(key) {
		return (cfg.i18n && cfg.i18n[key]) || key;
	}

	function setStatus(text, kind) {
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

	function score(row) {
		var relevance = row.relevance || 0;
		var count = Math.max(1, row.count || 1);
		var rank = row.rank || 10;
		return (relevance * 2) + (count * 20) + Math.max(0, 16 - rank);
	}

	function applyVolume() {
		var max = 1;
		items.forEach(function (row) {
			max = Math.max(max, score(row));
		});
		items.forEach(function (row) {
			row.volume = Math.round((100 * score(row)) / max);
		});
	}

	function volBar(n) {
		var wrap = document.createElement('span');
		wrap.className = 'wbgs-vol';
		wrap.title = 'میزان سرچ نسبی از سجست گوگل';
		var bar = document.createElement('span');
		bar.className = 'wbgs-vol-bar';
		var fill = document.createElement('i');
		fill.style.width = Math.max(0, Math.min(100, n || 0)) + '%';
		bar.appendChild(fill);
		var num = document.createElement('span');
		num.className = 'wbgs-vol-n';
		num.textContent = String(n || 0);
		wrap.appendChild(bar);
		wrap.appendChild(num);
		return wrap;
	}

	function setView(next) {
		view = next;
		if (listEl) {
			listEl.hidden = view !== 'list';
		}
		if (treeEl) {
			treeEl.hidden = view !== 'tree';
		}
		if (viewListBtn) {
			viewListBtn.classList.toggle('wbgs-view-on', view === 'list');
		}
		if (viewTreeBtn) {
			viewTreeBtn.classList.toggle('wbgs-view-on', view === 'tree');
		}
	}

	function renderList() {
		if (!listEl) {
			return;
		}
		listEl.innerHTML = '';
		items.forEach(function (row) {
			var li = document.createElement('li');
			var kw = document.createElement('span');
			kw.className = 'wbgs-kw';
			kw.textContent = row.text;
			li.appendChild(kw);
			li.appendChild(volBar(row.volume));
			listEl.appendChild(li);
		});
	}

	function emptyNode(label) {
		return { label: label, children: {}, leaves: [], volume: 0, count: 0 };
	}

	function buildTree(seed) {
		var root = emptyNode(seed || 'ریشه');
		items.forEach(function (row) {
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
			var volume = 0;
			node.leaves.forEach(function (leaf) {
				volume = Math.max(volume, leaf.volume || 0);
			});
			Object.keys(node.children).forEach(function (key) {
				rollup(node.children[key]);
				count += node.children[key].count;
				volume = Math.max(volume, node.children[key].volume);
			});
			node.count = count;
			node.volume = volume;
		}
		rollup(root);
		return root;
	}

	function renderNode(node) {
		var keys = Object.keys(node.children);
		var wrap = document.createElement('div');
		if (!keys.length && !node.leaves.length) {
			return wrap;
		}

		keys.sort(function (a, b) {
			return (node.children[b].volume || 0) - (node.children[a].volume || 0);
		});

		keys.forEach(function (key) {
			var child = node.children[key];
			var det = document.createElement('details');
			det.open = keys.length < 12;
			var sum = document.createElement('summary');
			var title = document.createElement('span');
			title.textContent = child.label + ' (' + child.count + ')';
			sum.appendChild(title);
			sum.appendChild(volBar(child.volume));
			det.appendChild(sum);
			det.appendChild(renderNode(child));
			wrap.appendChild(det);
		});

		if (node.leaves.length) {
			var ul = document.createElement('ul');
			ul.className = 'wbgs-tree-leaves';
			node.leaves.slice().sort(function (a, b) {
				return (b.volume || 0) - (a.volume || 0);
			}).forEach(function (row) {
				var li = document.createElement('li');
				var kw = document.createElement('span');
				kw.className = 'wbgs-kw';
				kw.textContent = row.text;
				li.appendChild(kw);
				li.appendChild(volBar(row.volume));
				ul.appendChild(li);
			});
			wrap.appendChild(ul);
		}
		return wrap;
	}

	function renderTree() {
		if (!treeEl) {
			return;
		}
		var seed = (seedEl.value || '').trim();
		treeEl.innerHTML = '';
		treeEl.appendChild(renderNode(buildTree(seed)));
	}

	function render() {
		applyVolume();
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
		renderList();
		renderTree();
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
			var row = { text: text, relevance: relevance, rank: rank, count: 1, volume: 0 };
			seen[text] = row;
			items.push(row);
		});
		render();
	}

	function setBusy(on) {
		running = on;
		startBtn.disabled = on || !cfg.licensed;
		stopBtn.hidden = !on;
		seedEl.disabled = on || !cfg.licensed;
		document.querySelectorAll('input[name="wbgs-mode"]').forEach(function (el) {
			el.disabled = on || !cfg.licensed;
		});
	}

	function setProgress(current, total) {
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
			if (val && typeof val === 'object' && !Array.isArray(val)) {
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
			.catch(function (err) {
				setStatus(err.message || i18n('network'), 'error');
			})
			.then(function () {
				setBusy(false);
				if (items.length) {
					view = 'tree';
					render();
				}
			});
	});

	stopBtn.addEventListener('click', function () {
		stopFlag = true;
	});

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
		var header = ['keyword', 'volume', 'relevance', 'count', 'branch'];
		var rows = [header.join(',')].concat(items.map(function (row) {
			return [
				csvEscape(row.text),
				row.volume || 0,
				row.relevance || 0,
				row.count || 1,
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
})();
