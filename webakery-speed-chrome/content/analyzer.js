/**
 * DOM + performance analyzer injected into the active tab.
 * Keep in sync with Webakery Speed WordPress fix slugs.
 */
(function () {
  "use strict";

  var FIXES = {
    defer_js: {
      title: "Defer جاوااسکریپت",
      risk: "low",
    },
    async_css: {
      title: "CSS غیر بحرانی async",
      risk: "medium",
    },
    lazyload: {
      title: "Lazy load تصاویر",
      risk: "low",
    },
    image_dimensions: {
      title: "ابعاد تصاویر",
      risk: "low",
    },
    font_display: {
      title: "font-display: swap",
      risk: "low",
    },
    preload_fonts: {
      title: "Preload فونت",
      risk: "low",
    },
    preconnect: {
      title: "Preconnect",
      risk: "low",
    },
    disable_emojis: {
      title: "حذف emoji وردپرس",
      risk: "low",
    },
    cache_headers: {
      title: "Cache headers",
      risk: "low",
    },
    preload_lcp: {
      title: "Preload تصویر LCP",
      risk: "medium",
    },
  };

  function absoluteUrl(src) {
    try {
      return new URL(src, window.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function issue(id, title, detail, suggested, score) {
    return {
      id: id,
      title: title,
      detail: detail,
      suggested: suggested || [],
      score: typeof score === "number" ? score : 0.5,
    };
  }

  function analyzeDom() {
    var issues = [];
    var suggested = {};

    function add(slug, item) {
      if (!suggested[slug]) {
        suggested[slug] = true;
      }
      issues.push(item);
    }

    var scripts = Array.prototype.slice.call(
      document.querySelectorAll("script[src]")
    );
    var blockingScripts = scripts.filter(function (script) {
      var src = script.getAttribute("src") || "";
      var isModule = script.getAttribute("type") === "module";
      return (
        !script.defer &&
        !script.async &&
        !isModule &&
        src.indexOf("jquery") === -1
      );
    });

    if (blockingScripts.length > 0) {
      add(
        "defer_js",
        issue(
          "render-blocking-scripts",
          "اسکریپت‌های render-blocking",
          blockingScripts.length + " اسکریپت بدون defer/async",
          ["defer_js"],
          0.35
        )
      );
    }

    var styles = Array.prototype.slice.call(
      document.querySelectorAll('link[rel="stylesheet"]')
    );
    var blockingStyles = styles.filter(function (link) {
      return (
        link.media !== "print" &&
        !link.getAttribute("onload") &&
        (link.href || "").indexOf("fonts.googleapis.com") === -1
      );
    });

    if (blockingStyles.length > 2) {
      add(
        "async_css",
        issue(
          "render-blocking-css",
          "فایل CSS مسدودکننده رندر",
          blockingStyles.length + " فایل CSS در head",
          ["async_css"],
          0.45
        )
      );
    }

    var images = Array.prototype.slice.call(document.images);
    var offscreenWithoutLazy = images.filter(function (img) {
      var rect = img.getBoundingClientRect();
      var belowFold = rect.top > window.innerHeight;
      return belowFold && img.loading !== "lazy";
    });

    if (offscreenWithoutLazy.length > 0) {
      add(
        "lazyload",
        issue(
          "offscreen-images",
          "تصاویر بدون lazy load",
          offscreenWithoutLazy.length + " تصویر خارج از viewport",
          ["lazyload"],
          0.4
        )
      );
    }

    var unsized = images.filter(function (img) {
      return !img.getAttribute("width") || !img.getAttribute("height");
    });

    if (unsized.length > 0) {
      add(
        "image_dimensions",
        issue(
          "unsized-images",
          "تصاویر بدون width/height",
          unsized.length + " تصویر بدون ابعاد",
          ["image_dimensions"],
          0.42
        )
      );
    }

    var fontLinks = Array.prototype.slice.call(
      document.querySelectorAll('link[href*="fonts.googleapis.com"]')
    );
    var fontsWithoutSwap = fontLinks.filter(function (link) {
      return link.href.indexOf("display=") === -1;
    });

    if (fontsWithoutSwap.length > 0) {
      add(
        "font_display",
        issue(
          "font-display",
          "فونت بدون display=swap",
          fontsWithoutSwap.length + " لینک Google Fonts",
          ["font_display", "preload_fonts"],
          0.38
        )
      );
    }

    var hasPreloadFont = document.querySelector(
      'link[rel="preload"][as="font"], link[rel="preload"][as="style"][href*="fonts"]'
    );
    if (fontLinks.length > 0 && !hasPreloadFont) {
      add(
        "preload_fonts",
        issue(
          "preload-fonts",
          "فونت preload نشده",
          "هیچ preload برای فونت پیدا نشد",
          ["preload_fonts", "preconnect"],
          0.4
        )
      );
    }

    var hasGstaticPreconnect = document.querySelector(
      'link[rel="preconnect"][href*="fonts.gstatic.com"]'
    );
    if (fontLinks.length > 0 && !hasGstaticPreconnect) {
      add(
        "preconnect",
        issue(
          "uses-rel-preconnect",
          "preconnect به fonts.gstatic نیست",
          "برای فونت Google preconnect اضافه کنید",
          ["preconnect"],
          0.55
        )
      );
    }

    var hero =
      images.length > 0
        ? images.reduce(function (best, img) {
            var area = img.naturalWidth * img.naturalHeight;
            return area > best.area ? { img: img, area: area } : best;
          }, { img: null, area: 0 }).img
        : null;

    if (hero) {
      var heroPreloaded = document.querySelector(
        'link[rel="preload"][as="image"][href="' + hero.currentSrc + '"]'
      );
      if (!heroPreloaded && hero.naturalWidth * hero.naturalHeight > 50000) {
        add(
          "preload_lcp",
          issue(
            "lcp-preload",
            "تصویر بزرگ preload نشده",
            "احتمالاً LCP: " + (hero.currentSrc || "").split("/").pop(),
            ["preload_lcp", "lazyload"],
            0.48
          )
        );
      }
    }

    if (
      scripts.some(function (s) {
        return (s.src || "").indexOf("wp-emoji-release.min.js") !== -1;
      })
    ) {
      add(
        "disable_emojis",
        issue(
          "wp-emoji",
          "اسکریپت emoji وردپرس",
          "wp-emoji-release.min.js بارگذاری شده",
          ["disable_emojis"],
          0.6
        )
      );
    }

    return {
      issues: issues,
      suggestedFixes: Object.keys(suggested),
      stats: {
        scripts: scripts.length,
        styles: styles.length,
        images: images.length,
        blockingScripts: blockingScripts.length,
        blockingStyles: blockingStyles.length,
      },
    };
  }

  function analyzeTiming() {
    var nav = performance.getEntriesByType("navigation")[0];
    var paint = performance.getEntriesByType("paint");
    var fcp = paint.find(function (p) {
      return p.name === "first-contentful-paint";
    });

    return {
      url: window.location.href,
      title: document.title,
      domContentLoaded: nav ? Math.round(nav.domContentLoadedEventEnd) : null,
      loadEvent: nav ? Math.round(nav.loadEventEnd) : null,
      ttfb: nav ? Math.round(nav.responseStart - nav.requestStart) : null,
      fcp: fcp ? Math.round(fcp.startTime) : null,
      transferSize: nav ? nav.transferSize : null,
      scannedAt: new Date().toISOString(),
    };
  }

  function scoreFromIssues(issues) {
    if (!issues.length) {
      return 95;
    }
    var penalty = issues.reduce(function (sum, item) {
      return sum + (1 - item.score) * 12;
    }, 0);
    return Math.max(20, Math.min(95, Math.round(95 - penalty)));
  }

  var dom = analyzeDom();
  var timing = analyzeTiming();

  return {
    mode: "dom",
    fixes: FIXES,
    performance: scoreFromIssues(dom.issues),
    timing: timing,
    issues: dom.issues,
    suggestedFixes: dom.suggestedFixes,
    stats: dom.stats,
  };
})();
