/**
 * DOM + performance analyzer injected into the active tab.
 * Keep in sync with Webakery Speed WordPress fix slugs.
 */
(function () {
  "use strict";

  var FIXES = {
    defer_js: { title: "Defer جاوااسکریپت", risk: "low" },
    async_css: { title: "CSS غیر بحرانی async", risk: "medium" },
    lazyload: { title: "Lazy load تصاویر", risk: "low" },
    image_dimensions: { title: "ابعاد تصاویر", risk: "low" },
    font_display: { title: "font-display: swap", risk: "low" },
    preload_fonts: { title: "Preload فونت", risk: "low" },
    preconnect: { title: "Preconnect", risk: "low" },
    disable_emojis: { title: "حذف emoji وردپرس", risk: "low" },
    cache_headers: { title: "Cache headers", risk: "low" },
    preload_lcp: { title: "Preload تصویر LCP", risk: "medium" },
  };

  function absoluteUrl(src) {
    try {
      return new URL(src, window.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function shortUrl(url) {
    if (!url) return "—";
    try {
      var u = new URL(url);
      var path = u.pathname.split("/").pop() || u.hostname;
      return path.length > 42 ? path.slice(0, 39) + "…" : path;
    } catch (e) {
      return String(url).slice(0, 42);
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

  function parseDisplayFromUrl(url) {
    try {
      var value = new URL(url).searchParams.get("display");
      return value || null;
    } catch (e) {
      return (url || "").match(/display=([a-z-]+)/i)?.[1] || null;
    }
  }

  function isFontResourceUrl(url) {
    return /\.(woff2?|ttf|otf|eot)(\?|#|$)/i.test(url || "");
  }

  function isFontCssUrl(url) {
    return /fonts\.(googleapis|gstatic)\.com|fonts\.bunny\.net|use\.typekit|cloud\.typography/i.test(
      url || ""
    );
  }

  function collectPreloads() {
    var map = {};
    Array.prototype.forEach.call(
      document.querySelectorAll('link[rel="preload"], link[rel="prefetch"]'),
      function (link) {
        if (link.href) {
          map[absoluteUrl(link.href)] = {
            as: link.getAttribute("as") || "",
            crossorigin: link.hasAttribute("crossorigin"),
          };
        }
      }
    );
    return map;
  }

  function collectPreconnects() {
    var origins = {};
    Array.prototype.forEach.call(
      document.querySelectorAll(
        'link[rel="preconnect"], link[rel="dns-prefetch"]'
      ),
      function (link) {
        if (link.href) {
          try {
            origins[new URL(link.href).origin] = link.rel;
          } catch (e) {
            /* ignore */
          }
        }
      }
    );
    return origins;
  }

  function analyzeFonts(preloads, preconnects) {
    var fonts = [];
    var seen = {};

    function pushFont(item) {
      var key = item.url || item.name;
      if (!key || seen[key]) return;
      seen[key] = true;

      var abs = item.url ? absoluteUrl(item.url) : "";
      var preloadInfo = abs ? preloads[abs] : null;
      var origin = "";
      try {
        origin = abs ? new URL(abs).origin : "";
      } catch (e) {
        origin = "";
      }

      fonts.push({
        name: item.name || shortUrl(abs) || "font",
        url: abs,
        source: item.source || "unknown",
        display: item.display || "نامشخص",
        displayOk:
          item.display === "swap" ||
          item.display === "optional" ||
          item.display === "fallback",
        preloaded: !!(preloadInfo && (preloadInfo.as === "font" || preloadInfo.as === "style")),
        preloadAs: preloadInfo ? preloadInfo.as : "",
        preconnect: origin ? !!preconnects[origin] : false,
      });
    }

    Array.prototype.forEach.call(
      document.querySelectorAll('link[rel="stylesheet"][href]'),
      function (link) {
        var href = absoluteUrl(link.href);
        if (!isFontCssUrl(href)) return;
        var display = parseDisplayFromUrl(href) || "ندارد";
        if (display === "block" || display === "auto") {
          display = display + " (نیاز به swap)";
        }
        pushFont({
          name: "Font CSS (" + shortUrl(href) + ")",
          url: href,
          source: "stylesheet",
          display: display,
        });
      }
    );

    Array.prototype.forEach.call(document.querySelectorAll("style"), function (node) {
      var text = node.textContent || "";
      var faceRegex = /@font-face\s*\{[^}]*\}/gi;
      var faces = text.match(faceRegex) || [];
      faces.forEach(function (block, index) {
        var family = block.match(/font-family\s*:\s*['"]?([^;'"]+)/i)?.[1]?.trim();
        var display = block.match(/font-display\s*:\s*([a-z-]+)/i)?.[1] || "نامشخص";
        var src = block.match(/url\((['"]?)([^'")]+)\1\)/i)?.[2] || "";
        pushFont({
          name: family || "@font-face #" + (index + 1),
          url: src,
          source: "inline-css",
          display: display,
        });
      });
    });

    try {
      Array.prototype.forEach.call(document.styleSheets, function (sheet) {
        var rules;
        try {
          rules = sheet.cssRules;
        } catch (e) {
          return;
        }
        if (!rules) return;

        Array.prototype.forEach.call(rules, function (rule) {
          if (rule.type !== CSSRule.FONT_FACE_RULE) return;
          var style = rule.style;
          var family = style.getPropertyValue("font-family").replace(/['"]/g, "").trim();
          var display = style.getPropertyValue("font-display").trim() || "نامشخص";
          var src = style.getPropertyValue("src") || "";
          var url = src.match(/url\((['"]?)([^'")]+)\1\)/i)?.[2] || sheet.href || "";
          pushFont({
            name: family || "font-face",
            url: url,
            source: sheet.href ? "css-file" : "stylesheet",
            display: display,
          });
        });
      });
    } catch (e) {
      /* ignore */
    }

    if (window.performance && performance.getEntriesByType) {
      performance.getEntriesByType("resource").forEach(function (entry) {
        if (!isFontResourceUrl(entry.name)) return;
        pushFont({
          name: shortUrl(entry.name),
          url: entry.name,
          source: "loaded-file",
          display: "از CSS",
        });
      });
    }

    return fonts;
  }

  function analyzeDom() {
    var issues = [];
    var suggested = {};
    var preloads = collectPreloads();
    var preconnects = collectPreconnects();
    var fonts = analyzeFonts(preloads, preconnects);

    function add(slug, item) {
      suggested[slug] = true;
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
        !isFontCssUrl(link.href || "")
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
      return rect.top > window.innerHeight && img.loading !== "lazy";
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

    if (fonts.length > 0) {
      var noSwap = fonts.filter(function (f) {
        return !f.displayOk && f.display !== "از CSS" && f.display !== "نامشخص";
      });
      var noDisplay = fonts.filter(function (f) {
        return f.display === "ندارد" || f.display === "نامشخص";
      });
      var noPreload = fonts.filter(function (f) {
        return !f.preloaded && (f.url || "").indexOf("woff") !== -1;
      });
      var cssFontsNoPreload = fonts.filter(function (f) {
        return f.source === "stylesheet" && !f.preloaded;
      });

      if (noSwap.length > 0 || noDisplay.length > 0) {
        add(
          "font_display",
          issue(
            "font-display",
            "font-display: swap نیست",
            (noSwap.length + noDisplay.length) +
              " فونت بدون swap — در وردپرس گزینه font-display swap را فعال کنید",
            ["font_display"],
            0.38
          )
        );
      }

      if (noPreload.length > 0 || cssFontsNoPreload.length > 0) {
        add(
          "preload_fonts",
          issue(
            "preload-fonts",
            "فونت preload نشده",
            noPreload.length +
              " فایل فونت + " +
              cssFontsNoPreload.length +
              " CSS فونت بدون preload",
            ["preload_fonts"],
            0.4
          )
        );
      }

      var needsGstatic = fonts.some(function (f) {
        return (f.url || "").indexOf("gstatic") !== -1 || f.source === "stylesheet";
      });
      if (needsGstatic && !preconnects["https://fonts.gstatic.com"]) {
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
    } else {
      issues.push(
        issue(
          "fonts-not-found",
          "فونت در DOM دیده نشد",
          "شاید فونت داخل CSS خارجی (cross-origin) باشد — اسکن PageSpeed را هم بزنید",
          ["font_display", "preload_fonts"],
          0.7
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
      var heroUrl = absoluteUrl(hero.currentSrc || "");
      var heroPreloaded = !!preloads[heroUrl];
      if (!heroPreloaded && hero.naturalWidth * hero.naturalHeight > 50000) {
        add(
          "preload_lcp",
          issue(
            "lcp-preload",
            "تصویر بزرگ preload نشده",
            "احتمالاً LCP: " + shortUrl(heroUrl),
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
      fonts: fonts,
      stats: {
        scripts: scripts.length,
        styles: styles.length,
        images: images.length,
        fonts: fonts.length,
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
    if (!issues.length) return 95;
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
    fonts: dom.fonts,
    stats: dom.stats,
  };
})();
