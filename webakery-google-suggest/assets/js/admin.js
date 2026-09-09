(function () {
	'use strict';

	var cfg = window.wbgsAdmin || {};
	var seedEl = document.getElementById('wbgs-seed');
	var startBtn = document.getElementById('wbgs-start');
	var stopBtn = document.getElementById('wbgs-stop');
	var listEl = document.getElementById('wbgs-list');
	var emptyEl = document.getElementById('wbgs-empty');
	var countEl = document.getElementById('wbgs-count');
	var statusEl = document.getElementById('wbgs-status');
	var progressWrap = document.getElementById('wbgs-progress');
	var progressBar = document.getElementById('wbgs-progress-bar');
	var progressText = document.getElementById('wbgs-progress-text');
	var copyBtn = document.getElementById('wbgs-copy');
	var csvBtn = document.getElementById('wbgs-csv');
	var txtBtn = document.getElementById('wbgs-txt');

	if (!seedEl || !startBtn) {
		return;
	}

	var running = false;
	var stopFlag = false;
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

	function renderList() {
		listEl.innerHTML = '';
		items.forEach(function (phrase) {
			var li = document.createElement('li');
			li.textContent = phrase;
			listEl.appendChild(li);
		});
		countEl.textContent = items.length + ' عبارت';
		emptyEl.hidden = items.length > 0;
		copyBtn.disabled = items.length === 0;
		csvBtn.disabled = items.length === 0;
		txtBtn.disabled = items.length === 0;
	}

	function addItems(batch) {
		(batch || []).forEach(function (phrase) {
			if (!phrase || seen[phrase]) {
				return;
			}
			seen[phrase] = true;
			items.push(phrase);
		});
		renderList();
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
		renderList();
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
			});
	});

	stopBtn.addEventListener('click', function () {
		stopFlag = true;
	});

	copyBtn.addEventListener('click', function () {
		var text = items.join('\n');
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
		var rows = ['keyword'].concat(items.map(csvEscape));
		download('google-suggest.csv', rows.join('\n'), 'text/csv;charset=utf-8');
	});

	txtBtn.addEventListener('click', function () {
		download('google-suggest.txt', items.join('\n'), 'text/plain;charset=utf-8');
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
