/**
 * Webakery Unused CSS/JS scanner — runs in page context via chrome.scripting
 */
(function () {
  'use strict';

  function safeQuery(selector) {
    try {
      return document.querySelector(selector);
    } catch (e) {
      return null; // invalid / unsupported selector
    }
  }

  function normalizeSelector(sel) {
    return String(sel || '')
      .replace(/::?(before|after|first-line|first-letter|placeholder|selection|marker|backdrop|file-selector-button)/gi, '')
      .replace(/:(hover|focus|active|visited|focus-visible|focus-within|target|checked|disabled|enabled|optional|required|valid|invalid|read-only|read-write|indeterminate|empty|root|fullscreen|modal|open|link|any-link|local-link|scope|where|is|not|has|dir|lang|nth-child|nth-last-child|nth-of-type|nth-last-of-type|first-child|last-child|first-of-type|last-of-type|only-child|only-of-type)\b(\([^)]*\))?/gi, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function isAlwaysUsedPseudo(sel) {
    return /:(root|host|host-context)\b/i.test(sel) || sel === ':root' || sel === 'html' || sel === 'body';
  }

  function collectCss() {
    const unused = [];
    const used = [];
    const errors = [];
    let totalRules = 0;
    let checkedSelectors = 0;
    const sheets = Array.from(document.styleSheets || []);

    sheets.forEach((sheet, sheetIndex) => {
      let rules;
      try {
        rules = sheet.cssRules || sheet.rules;
      } catch (e) {
        errors.push({
          type: 'cors',
          href: sheet.href || '(inline)',
          message: 'دسترسی به استایل‌شیت به‌خاطر CORS ممکن نیست',
        });
        return;
      }
      if (!rules) return;

      const href = sheet.href || `inline#${sheetIndex + 1}`;

      Array.from(rules).forEach((rule) => {
        walkRule(rule, href);
      });
    });

    function walkRule(rule, href) {
      if (!rule) return;
      if (rule.type === CSSRule.MEDIA_RULE || rule.type === CSSRule.SUPPORTS_RULE) {
        try {
          Array.from(rule.cssRules || []).forEach((r) => walkRule(r, href));
        } catch (e) { /* ignore */ }
        return;
      }
      if (rule.type !== CSSRule.STYLE_RULE) return;

      totalRules += 1;
      const selectorText = rule.selectorText || '';
      if (!selectorText) return;

      const parts = selectorText.split(',').map((s) => s.trim()).filter(Boolean);
      const unusedParts = [];
      const usedParts = [];

      parts.forEach((part) => {
        checkedSelectors += 1;
        if (isAlwaysUsedPseudo(part)) {
          usedParts.push(part);
          return;
        }
        const testSel = normalizeSelector(part) || part;
        const el = safeQuery(testSel);
        if (el) usedParts.push(part);
        else unusedParts.push(part);
      });

      if (unusedParts.length) {
        unused.push({
          href,
          selector: unusedParts.join(', '),
          full: selectorText,
          cssText: rule.cssText,
          usedPartCount: usedParts.length,
          unusedPartCount: unusedParts.length,
        });
      } else {
        used.push({ href, selector: selectorText });
      }
    }

    return {
      totalRules,
      checkedSelectors,
      usedCount: used.length,
      unusedCount: unused.length,
      unused: unused.slice(0, 800),
      truncated: unused.length > 800,
      errors,
      sheets: sheets.map((s, i) => ({
        href: s.href || `inline#${i + 1}`,
        disabled: !!s.disabled,
      })),
    };
  }

  function collectJs() {
    const scripts = Array.from(document.scripts || []);
    const list = scripts.map((s, i) => {
      const src = s.src || '';
      const inline = !src;
      const code = inline ? (s.textContent || '') : '';
      const bytes = inline ? new Blob([code]).size : 0;
      return {
        index: i + 1,
        src: src || '(inline)',
        inline,
        async: !!s.async,
        defer: !!s.defer,
        type: s.type || 'text/javascript',
        bytes,
        id: s.id || '',
      };
    });

    // Resource Timing sizes for external scripts
    let entries = [];
    try {
      entries = performance.getEntriesByType('resource') || [];
    } catch (e) { /* ignore */ }

    const byUrl = {};
    entries.forEach((e) => {
      if (e.initiatorType === 'script' || /\.m?js(\?|$)/i.test(e.name)) {
        byUrl[e.name] = {
          transferSize: e.transferSize || 0,
          encodedBodySize: e.encodedBodySize || 0,
          decodedBodySize: e.decodedBodySize || 0,
          duration: Math.round(e.duration || 0),
        };
      }
    });

    list.forEach((item) => {
      if (!item.inline && byUrl[item.src]) {
        item.transferSize = byUrl[item.src].transferSize;
        item.decodedBodySize = byUrl[item.src].decodedBodySize;
        item.duration = byUrl[item.src].duration;
      }
    });

    // Heuristic: external scripts never referenced elsewhere in HTML attributes/events
    const html = document.documentElement ? document.documentElement.outerHTML : '';
    list.forEach((item) => {
      if (item.inline) {
        item.hint = codeLooksDead(item.bytes) ? 'inline-empty' : 'inline';
        return;
      }
      const file = item.src.split('/').pop().split('?')[0];
      const mentioned = file && html.split(file).length > 2; // more than script tag itself
      item.hint = mentioned ? 'referenced' : 'external';
      // "maybeUnused" = no transfer / zero size often means blocked or unused cache weirdness
      item.maybeUnused = (item.transferSize === 0 && item.decodedBodySize === 0);
    });

    const totalTransfer = list.reduce((a, s) => a + (s.transferSize || s.bytes || 0), 0);

    return {
      count: list.length,
      external: list.filter((s) => !s.inline).length,
      inline: list.filter((s) => s.inline).length,
      totalTransfer,
      scripts: list,
      note: 'تشخیص قطعی JS استفاده‌نشده بدون Coverage API کروم ممکن نیست؛ لیست اسکریپت‌های لودشده + حجم نمایش داده می‌شود.',
    };
  }

  function codeLooksDead(bytes) {
    return !bytes || bytes < 3;
  }

  function formatBytes(n) {
    n = Number(n) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
    return (n / (1024 * 1024)).toFixed(2) + ' MB';
  }

  const css = collectCss();
  const js = collectJs();

  return {
    ok: true,
    url: location.href,
    title: document.title,
    scannedAt: new Date().toISOString(),
    css,
    js,
    summary: {
      cssUnused: css.unusedCount,
      cssTotal: css.totalRules,
      cssErrors: css.errors.length,
      jsCount: js.count,
      jsTransfer: formatBytes(js.totalTransfer),
    },
  };
})();
