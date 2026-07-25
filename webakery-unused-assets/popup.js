let lastResult = null;
const $ = (id) => document.getElementById(id);

function setPageText(text) {
  $('page').textContent = text;
}

async function getActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  return tab;
}

function canScan(url) {
  if (!url) return false;
  if (/^(chrome|chrome-extension|edge|about|devtools):/i.test(url)) return false;
  return /^https?:/i.test(url) || url.startsWith('file:');
}

/** Injected into the page */
function scanPage() {
  function safeQuery(selector) {
    try { return document.querySelector(selector); } catch (e) { return null; }
  }
  function normalizeSelector(sel) {
    return String(sel || '')
      .replace(/::?(before|after|first-line|first-letter|placeholder|selection|marker|backdrop|file-selector-button)/gi, '')
      .replace(/:(hover|focus|active|visited|focus-visible|focus-within|target|checked|disabled|enabled|optional|required|valid|invalid|read-only|read-write|indeterminate|empty|root|fullscreen|modal|open|link|any-link|local-link|scope)\b(\([^)]*\))?/gi, '')
      .replace(/:(nth-child|nth-last-child|nth-of-type|nth-last-of-type|not|is|where|has|lang|dir)\([^)]*\)/gi, '')
      .replace(/\s+/g, ' ')
      .trim();
  }
  function isAlwaysUsedPseudo(sel) {
    return /:(root|host)\b/i.test(sel) || sel === ':root' || sel === 'html' || sel === 'body' || sel === '*';
  }
  function formatBytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
  }

  const unused = [];
  const errors = [];
  let totalRules = 0;
  let checkedSelectors = 0;
  const sheets = Array.from(document.styleSheets || []);

  function walkRule(rule, href) {
    if (!rule) return;
    if (rule.type === CSSRule.MEDIA_RULE || rule.type === CSSRule.SUPPORTS_RULE) {
      try { Array.from(rule.cssRules || []).forEach((r) => walkRule(r, href)); } catch (e) {}
      return;
    }
    if (rule.type !== CSSRule.STYLE_RULE) return;
    totalRules += 1;
    const selectorText = rule.selectorText || '';
    if (!selectorText) return;
    const parts = selectorText.split(',').map((s) => s.trim()).filter(Boolean);
    const unusedParts = [];
    parts.forEach((part) => {
      checkedSelectors += 1;
      if (isAlwaysUsedPseudo(part)) return;
      const testSel = normalizeSelector(part) || part;
      if (!safeQuery(testSel)) unusedParts.push(part);
    });
    if (unusedParts.length) {
      unused.push({
        href: href,
        selector: unusedParts.join(', '),
        full: selectorText,
        cssText: rule.cssText,
      });
    }
  }

  sheets.forEach((sheet, sheetIndex) => {
    let rules;
    try {
      rules = sheet.cssRules || sheet.rules;
    } catch (e) {
      errors.push({ href: sheet.href || '(inline)', message: 'CORS — قابل خواندن نیست' });
      return;
    }
    if (!rules) return;
    const href = sheet.href || ('inline#' + (sheetIndex + 1));
    Array.from(rules).forEach((rule) => walkRule(rule, href));
  });

  const scripts = Array.from(document.scripts || []).map((s, i) => {
    const src = s.src || '';
    const inline = !src;
    const code = inline ? (s.textContent || '') : '';
    const bytes = inline ? new Blob([code]).size : 0;
    return {
      index: i + 1,
      src: src || '(inline)',
      inline: inline,
      async: !!s.async,
      defer: !!s.defer,
      type: s.type || 'text/javascript',
      bytes: bytes,
      id: s.id || '',
    };
  });

  let entries = [];
  try { entries = performance.getEntriesByType('resource') || []; } catch (e) {}
  const byUrl = {};
  entries.forEach((e) => {
    if (e.initiatorType === 'script' || /\.m?js(\?|$)/i.test(e.name)) {
      byUrl[e.name] = {
        transferSize: e.transferSize || 0,
        decodedBodySize: e.decodedBodySize || 0,
        duration: Math.round(e.duration || 0),
      };
    }
  });
  scripts.forEach((item) => {
    if (!item.inline && byUrl[item.src]) {
      item.transferSize = byUrl[item.src].transferSize;
      item.decodedBodySize = byUrl[item.src].decodedBodySize;
      item.duration = byUrl[item.src].duration;
    }
  });

  const totalTransfer = scripts.reduce((a, s) => a + (s.transferSize || s.bytes || 0), 0);

  return {
    ok: true,
    url: location.href,
    title: document.title,
    scannedAt: new Date().toISOString(),
    css: {
      totalRules: totalRules,
      checkedSelectors: checkedSelectors,
      unusedCount: unused.length,
      unused: unused.slice(0, 800),
      truncated: unused.length > 800,
      errors: errors,
    },
    js: {
      count: scripts.length,
      external: scripts.filter((s) => !s.inline).length,
      inline: scripts.filter((s) => s.inline).length,
      totalTransfer: totalTransfer,
      scripts: scripts,
      note: 'تشخیص قطعی JS اجرا‌نشده بدون Coverage ممکن نیست؛ لیست اسکریپت‌ها و حجم نمایش داده می‌شود.',
    },
    summary: {
      cssUnused: unused.length,
      cssTotal: totalRules,
      cssErrors: errors.length,
      jsCount: scripts.length,
      jsTransfer: formatBytes(totalTransfer),
    },
  };
}

async function scan() {
  const btn = $('scan');
  btn.disabled = true;
  btn.textContent = 'در حال اسکن…';

  try {
    const tab = await getActiveTab();
    if (!tab || !tab.id) throw new Error('تب فعال پیدا نشد.');
    if (!canScan(tab.url || '')) {
      throw new Error('روی صفحات سیستمی کروم نمی‌شود اسکن کرد.');
    }

    setPageText((tab.title || 'بدون عنوان') + '\n' + tab.url);

    const [{ result: report }] = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      world: 'MAIN',
      func: scanPage,
    });

    if (!report || !report.ok) throw new Error('نتیجه اسکن نامعتبر بود.');
    lastResult = report;
    render(report);
  } catch (err) {
    console.error(err);
    setPageText('خطا: ' + (err && err.message ? err.message : String(err)));
    $('stats').hidden = true;
    $('copyCss').disabled = true;
    $('exportJson').disabled = true;
  } finally {
    btn.disabled = false;
    btn.textContent = '🔍 اسکن صفحه فعلی';
  }
}

function formatBytesLocal(n) {
  n = Number(n) || 0;
  if (n < 1024) return n + ' B';
  if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
  return (n / (1024 * 1024)).toFixed(2) + ' MB';
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function render(report) {
  $('stats').hidden = false;
  $('sCssUnused').textContent = String(report.summary.cssUnused);
  $('sCssTotal').textContent = String(report.summary.cssTotal);
  $('sJs').textContent = String(report.summary.jsCount);
  $('sJsSize').textContent = report.summary.jsTransfer;
  $('copyCss').disabled = report.summary.cssUnused === 0;
  $('exportJson').disabled = false;

  const errBox = $('cssErrors');
  if (report.css.errors && report.css.errors.length) {
    errBox.hidden = false;
    errBox.innerHTML = '⚠ ' + report.css.errors.length + ' استایل‌شیت به‌خاطر CORS خوانده نشد:<br>' +
      report.css.errors.slice(0, 5).map((e) => escapeHtml(e.href)).join('<br>');
  } else {
    errBox.hidden = true;
    errBox.textContent = '';
  }

  const cssList = $('cssList');
  if (!report.css.unused.length) {
    cssList.className = 'list empty';
    cssList.textContent = 'قانون CSS بلااستفاده‌ای پیدا نشد (یا همه استایل‌ها CORS هستند).';
  } else {
    cssList.className = 'list';
    cssList.innerHTML = report.css.unused.map((u) => (
      '<div class="item"><code>' + escapeHtml(u.selector) + '</code>' +
      '<small>' + escapeHtml(u.href) + '</small></div>'
    )).join('') + (report.css.truncated ? '<div class="item"><small>فقط ۸۰۰ مورد اول نمایش داده شد.</small></div>' : '');
  }

  const jsNote = $('jsNote');
  jsNote.hidden = false;
  jsNote.textContent = report.js.note;

  const jsList = $('jsList');
  if (!report.js.scripts.length) {
    jsList.className = 'list empty';
    jsList.textContent = 'اسکریپتی پیدا نشد.';
  } else {
    jsList.className = 'list';
    jsList.innerHTML = report.js.scripts.map((s) => {
      const size = s.inline
        ? formatBytesLocal(s.bytes)
        : formatBytesLocal(s.transferSize || s.decodedBodySize || 0);
      const badges = [];
      if (s.inline) badges.push('<span class="badge">inline</span>');
      if (s.async) badges.push('<span class="badge">async</span>');
      if (s.defer) badges.push('<span class="badge">defer</span>');
      if (!s.inline && !s.transferSize && !s.decodedBodySize) {
        badges.push('<span class="badge warn">no size</span>');
      }
      return '<div class="item"><code>' + escapeHtml(s.src) + '</code>' +
        '<small>#' + s.index + ' · ' + size + (s.duration ? (' · ' + s.duration + 'ms') : '') + '</small> ' +
        badges.join(' ') + '</div>';
    }).join('');
  }
}

document.querySelectorAll('.tab').forEach((tab) => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach((t) => t.classList.remove('active'));
    document.querySelectorAll('.panel').forEach((p) => p.classList.remove('active'));
    tab.classList.add('active');
    $('tab-' + tab.dataset.tab).classList.add('active');
  });
});

$('scan').addEventListener('click', scan);

$('copyCss').addEventListener('click', async () => {
  if (!lastResult) return;
  const css = lastResult.css.unused.map((u) => '/* ' + u.href + ' */\n' + u.cssText).join('\n\n');
  await navigator.clipboard.writeText(css || '');
  $('copyCss').textContent = 'کپی شد ✓';
  setTimeout(() => { $('copyCss').textContent = 'کپی CSS بلااستفاده'; }, 1200);
});

$('exportJson').addEventListener('click', async () => {
  if (!lastResult) return;
  await navigator.clipboard.writeText(JSON.stringify(lastResult, null, 2));
  $('exportJson').textContent = 'کپی شد ✓';
  setTimeout(() => { $('exportJson').textContent = 'خروجی JSON'; }, 1200);
});

(async function init() {
  try {
    const tab = await getActiveTab();
    if (tab) setPageText((tab.title || 'بدون عنوان') + '\n' + (tab.url || ''));
    else setPageText('تب فعالی نیست');
  } catch (e) {
    setPageText('آماده اسکن');
  }
})();
