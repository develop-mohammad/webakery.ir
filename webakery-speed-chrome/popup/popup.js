const FIX_LABELS = {
  defer_js: "Defer JS",
  async_css: "Async CSS",
  lazyload: "Lazy load",
  image_dimensions: "ابعاد تصویر",
  font_display: "font-display swap",
  preload_fonts: "Preload فونت",
  preconnect: "Preconnect",
  disable_emojis: "حذف emoji WP",
  cache_headers: "Cache headers",
  preload_lcp: "Preload LCP",
};

const FIX_RISK = {
  defer_js: "low",
  async_css: "medium",
  lazyload: "low",
  image_dimensions: "low",
  font_display: "low",
  preload_fonts: "low",
  preconnect: "low",
  disable_emojis: "low",
  cache_headers: "low",
  preload_lcp: "medium",
};

let lastReport = null;
let currentTab = null;

document.addEventListener("DOMContentLoaded", async () => {
  document.getElementById("btn-analyze").addEventListener("click", analyzeDom);
  document.getElementById("btn-psi").addEventListener("click", analyzePageSpeed);
  document.getElementById("btn-copy").addEventListener("click", copyJson);
  document.getElementById("btn-options").addEventListener("click", () => {
    chrome.runtime.openOptionsPage();
  });

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  currentTab = tab;
  document.getElementById("page-url").textContent = tab?.url || "—";

  if (tab?.url && (tab.url.startsWith("http://") || tab.url.startsWith("https://"))) {
    analyzeDom();
  } else {
    setStatus("این صفحه قابل تحلیل نیست (فقط http/https).", true);
  }
});

function setStatus(message, isError) {
  const el = document.getElementById("status");
  el.hidden = false;
  el.textContent = message;
  el.classList.toggle("error", !!isError);
}

function setScore(value) {
  const el = document.getElementById("score");
  el.textContent = value == null ? "—" : String(value);
  el.className = "score";
  if (value == null) return;
  if (value >= 90) el.classList.add("good");
  else if (value >= 50) el.classList.add("avg");
  else el.classList.add("bad");
}

function renderMetrics(timing) {
  const box = document.getElementById("metrics");
  if (!timing) {
    box.hidden = true;
    return;
  }

  const items = [
    ["FCP", timing.fcp != null ? timing.fcp + "ms" : "—"],
    ["TTFB", timing.ttfb != null ? timing.ttfb + "ms" : "—"],
    ["DOM Ready", timing.domContentLoaded != null ? timing.domContentLoaded + "ms" : "—"],
    ["Load", timing.loadEvent != null ? timing.loadEvent + "ms" : "—"],
  ];

  box.innerHTML = items
    .map(
      ([label, value]) =>
        `<div class="metric"><span>${label}</span><strong>${value}</strong></div>`
    )
    .join("");
  box.hidden = false;
}

function renderIssues(issues) {
  const list = document.getElementById("issues");
  if (!issues || !issues.length) {
    list.innerHTML = '<li class="empty">خطای مهمی پیدا نشد 🎉</li>';
    return;
  }

  list.innerHTML = issues
    .map((issue) => {
      const fixes = (issue.suggested || [])
        .map((slug) => FIX_LABELS[slug] || slug)
        .join("، ");
      return `<li class="issue">
        <strong>${escapeHtml(issue.title)}</strong>
        <small>${escapeHtml(issue.detail || issue.description || "")}</small>
        ${fixes ? `<small>پیشنهاد: ${escapeHtml(fixes)}</small>` : ""}
      </li>`;
    })
    .join("");
}

function renderFixes(slugs) {
  const box = document.getElementById("fixes");
  if (!slugs || !slugs.length) {
    box.innerHTML = '<span class="empty">—</span>';
    return;
  }

  box.innerHTML = slugs
    .map((slug) => {
      const risk = FIX_RISK[slug] || "low";
      const label = FIX_LABELS[slug] || slug;
      return `<span class="chip ${risk === "medium" ? "medium" : ""}">${escapeHtml(label)}</span>`;
    })
    .join("");
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function renderFonts(fonts) {
  const box = document.getElementById("fonts");
  if (!fonts || !fonts.length) {
    box.innerHTML =
      '<p class="empty">فونتی در DOM پیدا نشد. ممکن است داخل CSS خارجی باشد — اسکن PageSpeed را هم بزنید.</p>';
    return;
  }

  box.innerHTML = fonts
    .map((font) => {
      const displayClass = font.displayOk
        ? "ok"
        : font.display === "ندارد" || font.display === "نامشخص"
          ? "bad"
          : "neutral";
      const preloadClass = font.preloaded ? "ok" : "bad";
      const preconnectClass = font.preconnect ? "ok" : "neutral";

      return `<article class="font-row">
        <div class="font-row__name">${escapeHtml(font.name)}</div>
        ${
          font.url
            ? `<div class="font-row__url">${escapeHtml(font.url)}</div>`
            : ""
        }
        <div class="font-badges">
          <span class="badge ${displayClass}">display: ${escapeHtml(font.display)}</span>
          <span class="badge ${preloadClass}">preload: ${font.preloaded ? "بله" : "خیر"}</span>
          <span class="badge ${preconnectClass}">preconnect: ${font.preconnect ? "بله" : "خیر"}</span>
          <span class="badge neutral">${escapeHtml(font.source)}</span>
        </div>
      </article>`;
    })
    .join("");
}

function renderReport(report) {
  lastReport = report;
  setScore(report.performance);
  renderMetrics(report.timing || null);
  renderFonts(report.fonts || []);
  renderIssues(report.issues || []);
  renderFixes(report.suggestedFixes || []);
}

async function analyzeDom() {
  const btn = document.getElementById("btn-analyze");
  btn.disabled = true;
  setStatus("در حال تحلیل DOM صفحه…");

  try {
    const [{ result }] = await chrome.scripting.executeScript({
      target: { tabId: currentTab.id },
      files: ["content/analyzer.js"],
    });

    renderReport(result);
    setStatus("تحلیل محلی صفحه انجام شد.");
  } catch (error) {
    setStatus(error.message || "تحلیل صفحه ناموفق بود.", true);
  } finally {
    btn.disabled = false;
  }
}

async function analyzePageSpeed() {
  const btn = document.getElementById("btn-psi");
  btn.disabled = true;
  setStatus("در حال اسکن Google PageSpeed…");

  try {
    const { psiApiKey, strategy } = await chrome.storage.sync.get({
      psiApiKey: "",
      strategy: "mobile",
    });

    const response = await chrome.runtime.sendMessage({
      type: "RUN_PAGESPEED",
      url: currentTab.url,
      apiKey: psiApiKey,
      strategy,
    });

    if (!response?.ok) {
      throw new Error(response?.error || "خطای PageSpeed");
    }

    const data = response.data;
    renderReport({
      performance: data.performance,
      timing: null,
      issues: data.issues,
      suggestedFixes: data.suggestedFixes,
      mode: "pagespeed",
      raw: data.raw,
    });
    setStatus("اسکن PageSpeed انجام شد.");
  } catch (error) {
    setStatus(error.message || "اسکن PageSpeed ناموفق بود.", true);
  } finally {
    btn.disabled = false;
  }
}

async function copyJson() {
  if (!lastReport) {
    setStatus("اول صفحه را تحلیل کنید.", true);
    return;
  }

  const exportPayload = {
    source: "webakery-speed-chrome",
    exportedAt: new Date().toISOString(),
    url: currentTab?.url || "",
    performance: lastReport.performance,
    suggestedFixes: lastReport.suggestedFixes || [],
    fonts: lastReport.fonts || [],
    issues: (lastReport.issues || []).map((issue) => ({
      id: issue.id,
      title: issue.title,
      detail: issue.detail || issue.description || "",
      suggested: issue.suggested || [],
      score: issue.score,
    })),
    lighthouseResult: lastReport.raw?.lighthouseResult || null,
  };

  await navigator.clipboard.writeText(JSON.stringify(exportPayload, null, 2));
  setStatus("JSON کپی شد — در وردپرس Webakery Speed قابل import است.");
}
