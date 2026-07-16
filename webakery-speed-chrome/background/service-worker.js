const FIX_MAP = {
  "render-blocking-resources": ["defer_js", "async_css"],
  "render-blocking-insight": ["defer_js", "async_css"],
  "unused-javascript": ["defer_js"],
  "offscreen-images": ["lazyload"],
  "unsized-images": ["image_dimensions"],
  "font-display": ["font_display", "preload_fonts"],
  "preload-fonts": ["preload_fonts", "preconnect"],
  "uses-rel-preconnect": ["preconnect"],
  "largest-contentful-paint-element": ["preload_lcp"],
  "lcp-lazy-loaded": ["preload_lcp"],
  "uses-long-cache-ttl": ["cache_headers"],
  "efficient-cache-policy": ["cache_headers"],
};

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message.type === "RUN_PAGESPEED") {
    runPageSpeed(message.url, message.apiKey, message.strategy)
      .then((data) => sendResponse({ ok: true, data }))
      .catch((error) =>
        sendResponse({ ok: false, error: error.message || String(error) })
      );
    return true;
  }
});

async function runPageSpeed(url, apiKey, strategy) {
  if (!apiKey) {
    throw new Error("کلید API در تنظیمات افزونه وارد نشده است.");
  }

  const endpoint =
    "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?" +
    new URLSearchParams({
      url,
      key: apiKey,
      strategy: strategy || "mobile",
      category: "performance",
      locale: "fa",
    });

  const response = await fetch(endpoint);
  const body = await response.json();

  if (!response.ok) {
    throw new Error(body.error?.message || "خطا در PageSpeed API");
  }

  return normalizePageSpeed(body, url, strategy || "mobile");
}

function normalizePageSpeed(body, url, strategy) {
  const lh = body.lighthouseResult || {};
  const audits = lh.audits || {};
  const perfScore = lh.categories?.performance?.score;
  const issues = [];

  Object.keys(audits).forEach((auditId) => {
    const audit = audits[auditId];
    const mode = audit.scoreDisplayMode || "";
    const score = audit.score;

    if (["notApplicable", "manual", "informative"].includes(mode)) {
      return;
    }
    if (score === null || score >= 0.9) {
      return;
    }

    const suggested =
      FIX_MAP[auditId] ||
      Object.keys(FIX_MAP).filter((key) => auditId.includes(key)).flatMap((key) => FIX_MAP[key]);

    issues.push({
      id: auditId,
      title: audit.title || auditId,
      detail: audit.displayValue || "",
      description: audit.description || "",
      score,
      suggested: [...new Set(suggested)],
    });
  });

  issues.sort((a, b) => a.score - b.score);

  const suggestedFixes = [
    ...new Set(issues.flatMap((issue) => issue.suggested)),
  ];

  return {
    mode: "pagespeed",
    url: url || lh.finalUrl || "",
    strategy,
    performance:
      typeof perfScore === "number" ? Math.round(perfScore * 100) : null,
    scannedAt: new Date().toISOString(),
    issues,
    suggestedFixes,
    raw: body,
  };
}
