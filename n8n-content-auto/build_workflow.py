#!/usr/bin/env python3
"""ساخت JSON ورک‌فلو n8n."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parent
GENERATE = (ROOT / "prompts/generate.txt").read_text(encoding="utf-8")
HUMAN1 = (ROOT / "prompts/human-1.txt").read_text(encoding="utf-8")
HUMAN2 = (ROOT / "prompts/human-2.txt").read_text(encoding="utf-8")


def sticky(nid: str, name: str, x: int, y: int, w: int, h: int, text: str) -> dict:
    return {
        "parameters": {"content": text, "height": h, "width": w},
        "id": nid,
        "name": name,
        "type": "n8n-nodes-base.stickyNote",
        "typeVersion": 1,
        "position": [x, y],
    }


def js_pick_block() -> str:
    return r"""
const col = {
  cat: $env.COL_CATEGORY || 'دسته',
  title: $env.COL_TITLE || 'عنوان',
  kw: $env.COL_KEYWORDS || 'کیورد اصلی',
  lsi: $env.COL_LSI || 'کیورد فرعی',
  brand: $env.COL_BRAND || 'برند',
  done: $env.COL_DONE || 'انجام شد',
  date: $env.COL_DATE || 'تاریخ',
};
const rows = $input.all().map((item, idx) => {
  const j = item.json;
  return Object.assign({ row_number: j.row_number || j.rowNumber || (idx + 2) }, j);
});
function val(row, key) {
  const v = row[key];
  return v == null ? '' : String(v).trim();
}
function isDone(v) {
  const s = String(v || '').trim().toLowerCase();
  return ['true','1','yes','y','done','بله','انجام','انجام شد','x','✓','✔'].includes(s);
}
function splitKw(s) {
  return String(s || '').split(/[\n,،;|]+/).map(x => x.trim()).filter(Boolean);
}
const blocks = [];
let open = null;
function close() { if (open) { blocks.push(open); open = null; } }
for (const row of rows) {
  const cat = val(row, col.cat);
  const title = val(row, col.title);
  const kws = splitKw(val(row, col.kw));
  const lsi = splitKw(val(row, col.lsi));
  const brand = val(row, col.brand);
  const done = isDone(val(row, col.done));
  const date = val(row, col.date);
  const empty = !cat && !title && !kws.length && !lsi.length && !brand;
  if (empty) { close(); continue; }
  if (cat || title) {
    close();
    open = {
      row_number: Number(row.row_number),
      rows: [Number(row.row_number)],
      category: cat || title,
      title: title || cat,
      keywords: kws,
      lsi: lsi,
      brand: brand,
      done,
      date,
    };
    continue;
  }
  if (open) {
    open.rows.push(Number(row.row_number));
    open.keywords = [...new Set(open.keywords.concat(kws))];
    open.lsi = [...new Set(open.lsi.concat(lsi.length ? lsi : kws))];
    if (brand && !open.brand) open.brand = brand;
    if (done) open.done = true;
    if (date) open.date = date;
  }
}
close();
const next = blocks.find(b => !b.done && b.category);
if (!next) return [];
next.keyword_text = (next.keywords || []).join('\n');
next.lsi_text = (next.lsi || []).join('\n');
next.brand = next.brand || $env.BRAND_DEFAULT || '';
return [{ json: next }];
""".strip()


def js_fill_prompts() -> str:
    g = json.dumps(GENERATE, ensure_ascii=False)
    h1 = json.dumps(HUMAN1, ensure_ascii=False)
    h2 = json.dumps(HUMAN2, ensure_ascii=False)
    return f"""
function fill(tpl, vars) {{
  let out = tpl;
  for (const [k, v] of Object.entries(vars)) {{
    if (k === 'content') continue;
    out = out.replaceAll('{{{{' + k + '}}}}', v == null ? '' : String(v));
  }}
  return out;
}}
const j = $json;
const vars = {{
  title: j.title || j.category || '',
  category: j.category || '',
  keywords: j.keyword_text || (j.keywords || []).join('\\n'),
  lsi: j.lsi_text || (j.lsi || []).join('\\n'),
  brand: j.brand || $env.BRAND_DEFAULT || '',
  content: j.html || '',
  min_score: $env.MIN_HUMAN_SCORE || '78',
}};
j.prompt_generate = fill({g}, vars);
j.prompt_human_1 = fill({h1}, vars);
j.prompt_human_2 = fill({h2}, vars);
return [{{ json: j }}];
""".strip()


def js_extract_html(from_path: str, merge_from: str, field: str) -> str:
    return f"""
let raw = '';
try {{
  raw = {from_path};
}} catch (e) {{
  raw = String($json);
}}
raw = String(raw || '').trim();
const fence = raw.match(/```(?:html)?\\s*([\\s\\S]*?)```/i);
if (fence) raw = fence[1].trim();
const idx = raw.search(/<(?:h1|h2|p)\\b/i);
if (idx >= 0) raw = raw.slice(idx);
raw = raw.replace(/\\u200c/g, ' ');
raw = raw.replace(/<\\/?(?:html|body|head|article|section|div|span|strong|em|b|i|ul|ol|li|br)[^>]*>/gi, '');
const j = Object.assign({{}}, $({json.dumps(merge_from, ensure_ascii=False)}).first().json);
j.{field} = raw.trim();
j.html = j.{field};
return [{{ json: j }}];
""".strip()


def js_verdict() -> str:
    return r"""
let raw = '';
try { raw = $json.choices[0].message.content; } catch (e) { raw = JSON.stringify($json); }
const m = String(raw).match(/\{[\s\S]*\}/);
let data = { human: false, score: 0, issues: [], rewrite: '' };
if (m) { try { data = Object.assign(data, JSON.parse(m[0])); } catch (e) {} }
const prev = $('استخراج پس از بررسی ۱').first().json;
let html = prev.html;
if (data.rewrite) {
  let r = String(data.rewrite);
  const idx = r.search(/<(?:h1|h2|p)\b/i);
  if (idx >= 0) r = r.slice(idx);
  html = r.replace(/\u200c/g, ' ').trim();
}
const min = Number($env.MIN_HUMAN_SCORE || 78);
const human_ok = Boolean(data.human) && Number(data.score) >= min;
return [{ json: Object.assign({}, prev, {
  html,
  human: Boolean(data.human),
  score: Number(data.score) || 0,
  issues: data.issues || [],
  human_ok,
}) }];
""".strip()


def js_pick_term() -> str:
    return r"""
const prev = $('پس از تایید انسانی').first().json;
const list = Array.isArray($json) ? $json : ($json ? [$json] : []);
const name = prev.category;
let term = list.find(t => t && t.name === name) || list[0];
if (!term || !term.id) {
  throw new Error('دسته در سایت پیدا نشد: ' + name);
}
return [{ json: Object.assign({}, prev, { term_id: term.id }) }];
""".strip()


def js_jalali() -> str:
    return r"""
function toJalali(gy, gm, gd) {
  const g_y_m = [0,31,59,90,120,151,181,212,243,273,304,334];
  let jy = gy <= 1600 ? 0 : 979;
  gy -= gy <= 1600 ? 621 : 1600;
  const gy2 = gm > 2 ? gy + 1 : gy;
  let days = 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + g_y_m[gm - 1];
  jy += 33 * Math.floor(days / 12053);
  days %= 12053;
  jy += 4 * Math.floor(days / 1461);
  days %= 1461;
  if (days > 365) {
    jy += Math.floor((days - 1) / 365);
    days = (days - 1) % 365;
  }
  const jm_days = [31,31,31,31,31,31,30,30,30,30,30,29];
  let i = 0;
  for (; i < 11 && days >= jm_days[i]; i++) days -= jm_days[i];
  return [jy, i + 1, days + 1];
}
const tz = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Tehran' }));
const [jy, jm, jd] = toJalali(tz.getFullYear(), tz.getMonth() + 1, tz.getDate());
const jalali = String(jy).padStart(4,'0') + '/' + String(jm).padStart(2,'0') + '/' + String(jd).padStart(2,'0');
const prev = $('انتخاب شناسه دسته').first().json;
const doneCol = $env.COL_DONE || 'انجام شد';
const dateCol = $env.COL_DATE || 'تاریخ';
const noteCol = $env.COL_NOTE || 'یادداشت';
const out = Object.assign({}, prev, { jalali, row_number: prev.row_number });
out[doneCol] = 'TRUE';
out[dateCol] = jalali;
out[noteCol] = 'منتشر شد';
return [{ json: out }];
""".strip()


def http_ai(name: str, nid: str, x: int, y: int, prompt_field: str) -> dict:
    return {
        "parameters": {
            "method": "POST",
            "url": "={{ $env.AI_BASE }}/chat/completions",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "Authorization", "value": "=Bearer {{ $env.AI_KEY }}"},
                    {"name": "Content-Type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": f"={{ JSON.stringify({{ model: $env.AI_MODEL, temperature: 0.7, max_tokens: 8000, messages: [{{ role: 'user', content: $json.{prompt_field} }}] }}) }}",
            "options": {"timeout": 180000},
        },
        "id": nid,
        "name": name,
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [x, y],
        "retryOnFail": True,
        "maxTries": 2,
        "waitBetweenTries": 4000,
    }


def sheets_read() -> dict:
    return {
        "parameters": {
            "resource": "sheet",
            "operation": "read",
            "documentId": {"__rl": True, "value": "={{ $env.SHEET_ID }}", "mode": "id"},
            "sheetName": {"__rl": True, "value": "={{ $env.SHEET_TAB }}", "mode": "name"},
            "options": {},
        },
        "id": "node-sheets-read",
        "name": "خواندن گوگل شیت",
        "type": "n8n-nodes-base.googleSheets",
        "typeVersion": 4.5,
        "position": [280, 300],
    }


def sheets_update() -> dict:
    return {
        "parameters": {
            "resource": "sheet",
            "operation": "update",
            "documentId": {"__rl": True, "value": "={{ $env.SHEET_ID }}", "mode": "id"},
            "sheetName": {"__rl": True, "value": "={{ $env.SHEET_TAB }}", "mode": "name"},
            "columns": {
                "mappingMode": "autoMapInputData",
                "value": {},
                "matchingColumns": "row_number",
                "schema": [],
                "attemptToConvertTypes": False,
                "convertFieldsToString": False,
            },
            "options": {},
        },
        "id": "node-sheets-update",
        "name": "تیک و تاریخ شمسی در شیت",
        "type": "n8n-nodes-base.googleSheets",
        "typeVersion": 4.5,
        "position": [3660, 180],
    }


def connect(src: str, dst: str, index: int = 0) -> dict:
    return {src: {"main": [[{"node": dst, "type": "main", "index": 0}] for _ in range(index)] + [[{"node": dst, "type": "main", "index": 0}]]}}


def main() -> None:
    nodes = [
        sticky(
            "note-where",
            "کجا اجرا میشود",
            0,
            40,
            520,
            220,
            "## این ورک‌فلو داخل وردپرس نیست\n\nروی **n8n** (داکر یا کلود) اجرا می‌شود.\nهر ساعت **یک بلوک** شیت را برمی‌دارد، با پرامپت شما مقاله می‌سازد، دو بار انسانی می‌کند، متن دسته را از REST وردپرس می‌نویسد، بعد تاریخ شمسی و تیک را در شیت می‌زند.",
        ),
        {
            "parameters": {},
            "id": "node-manual",
            "name": "اجرای دستی",
            "type": "n8n-nodes-base.manualTrigger",
            "typeVersion": 1,
            "position": [0, 300],
        },
        {
            "parameters": {"rule": {"interval": [{"field": "hours", "hoursInterval": 1}]}},
            "id": "node-schedule",
            "name": "هر ساعت یک بلوک",
            "type": "n8n-nodes-base.scheduleTrigger",
            "typeVersion": 1.2,
            "position": [0, 500],
        },
        sheets_read(),
        {
            "parameters": {"jsCode": js_pick_block()},
            "id": "node-pick",
            "name": "انتخاب بلوک بعدی",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [520, 300],
        },
        {
            "parameters": {"jsCode": js_fill_prompts()},
            "id": "node-fill",
            "name": "پرکردن پرامپت",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [740, 300],
        },
        http_ai("تولید مقاله", "node-ai-gen", 980, 300, "prompt_generate"),
        {
            "parameters": {"jsCode": js_extract_html("$json.choices[0].message.content", "پرکردن پرامپت", "html")},
            "id": "node-html1",
            "name": "استخراج HTML",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [1220, 300],
        },
        {
            "parameters": {"jsCode": "const j = $json; j.prompt_human_1 = j.prompt_human_1.replaceAll('{{content}}', j.html || ''); return [{ json: j }];"},
            "id": "node-fill-h1",
            "name": "پرامپت بررسی ۱",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [1440, 300],
        },
        http_ai("بررسی انسانی ۱", "node-ai-h1", 1660, 300, "prompt_human_1"),
        {
            "parameters": {"jsCode": js_extract_html("$json.choices[0].message.content", "پرامپت بررسی ۱", "html")},
            "id": "node-html2",
            "name": "استخراج پس از بررسی ۱",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [1880, 300],
        },
        {
            "parameters": {"jsCode": "const j = $json; j.prompt_human_2 = j.prompt_human_2.replaceAll('{{content}}', j.html || ''); return [{ json: j }];"},
            "id": "node-fill-h2",
            "name": "پرامپت بررسی ۲",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [2100, 220],
        },
        http_ai("بررسی انسانی ۲", "node-ai-h2", 2100, 400, "prompt_human_2"),
        {
            "parameters": {"jsCode": js_verdict()},
            "id": "node-verdict",
            "name": "پس از تایید انسانی",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [2320, 300],
        },
        {
            "parameters": {
                "conditions": {
                    "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "loose"},
                    "conditions": [
                        {
                            "id": "cond-human",
                            "leftValue": "={{ $json.human_ok }}",
                            "rightValue": True,
                            "operator": {"type": "boolean", "operation": "true", "singleValue": True},
                        }
                    ],
                    "combinator": "and",
                },
                "options": {},
            },
            "id": "node-if",
            "name": "آیا متن انسانی است؟",
            "type": "n8n-nodes-base.if",
            "typeVersion": 2,
            "position": [2540, 300],
        },
        {
            "parameters": {
                "url": "={{ $env.WP_URL.replace(/\\/$/, '') }}/wp-json/wp/v2/{{ $env.WP_TAXONOMY === 'category' ? 'categories' : $env.WP_TAXONOMY }}?search={{ encodeURIComponent($json.category) }}&per_page=20",
                "authentication": "genericCredentialType",
                "genericAuthType": "httpBasicAuth",
                "options": {"timeout": 40000},
            },
            "id": "node-wp-search",
            "name": "پیدا کردن دسته در سایت",
            "type": "n8n-nodes-base.httpRequest",
            "typeVersion": 4.2,
            "position": [2780, 180],
        },
        {
            "parameters": {"jsCode": js_pick_term()},
            "id": "node-term",
            "name": "انتخاب شناسه دسته",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [3000, 180],
        },
        {
            "parameters": {
                "method": "POST",
                "url": "={{ $env.WP_URL.replace(/\\/$/, '') }}/wp-json/wp/v2/{{ $env.WP_TAXONOMY === 'category' ? 'categories' : $env.WP_TAXONOMY }}/{{ $json.term_id }}",
                "authentication": "genericCredentialType",
                "genericAuthType": "httpBasicAuth",
                "sendBody": True,
                "specifyBody": "json",
                "jsonBody": "={{ JSON.stringify({ description: $json.html }) }}",
                "options": {"timeout": 40000},
            },
            "id": "node-wp-write",
            "name": "نوشتن متن در دسته",
            "type": "n8n-nodes-base.httpRequest",
            "typeVersion": 4.2,
            "position": [3220, 180],
        },
        {
            "parameters": {"jsCode": js_jalali()},
            "id": "node-jalali",
            "name": "تاریخ شمسی",
            "type": "n8n-nodes-base.code",
            "typeVersion": 2,
            "position": [3440, 180],
        },
        sheets_update(),
        sticky(
            "note-fail",
            "اگر انسانی نبود",
            2540,
            520,
            360,
            140,
            "اگر امتیاز انسانی کافی نباشد، در سایت چیزی نوشته نمی‌شود و تیک شیت زده نمی‌شود. ساعت بعد دوباره همین بلوک را تلاش می‌کند.",
        ),
        {
            "parameters": {"amount": 0},
            "id": "node-stop",
            "name": "توقف بدون انتشار",
            "type": "n8n-nodes-base.wait",
            "typeVersion": 1.1,
            "position": [2780, 520],
        },
    ]

    # Wait node with 0 might still wait; use noOp instead
    nodes[-1] = {
        "parameters": {},
        "id": "node-stop",
        "name": "توقف بدون انتشار",
        "type": "n8n-nodes-base.noOp",
        "typeVersion": 1,
        "position": [2780, 520],
    }

    connections = {
        "اجرای دستی": {"main": [[{"node": "خواندن گوگل شیت", "type": "main", "index": 0}]]},
        "هر ساعت یک بلوک": {"main": [[{"node": "خواندن گوگل شیت", "type": "main", "index": 0}]]},
        "خواندن گوگل شیت": {"main": [[{"node": "انتخاب بلوک بعدی", "type": "main", "index": 0}]]},
        "انتخاب بلوک بعدی": {"main": [[{"node": "پرکردن پرامپت", "type": "main", "index": 0}]]},
        "پرکردن پرامپت": {"main": [[{"node": "تولید مقاله", "type": "main", "index": 0}]]},
        "تولید مقاله": {"main": [[{"node": "استخراج HTML", "type": "main", "index": 0}]]},
        "استخراج HTML": {"main": [[{"node": "پرامپت بررسی ۱", "type": "main", "index": 0}]]},
        "پرامپت بررسی ۱": {"main": [[{"node": "بررسی انسانی ۱", "type": "main", "index": 0}]]},
        "بررسی انسانی ۱": {"main": [[{"node": "استخراج پس از بررسی ۱", "type": "main", "index": 0}]]},
        "استخراج پس از بررسی ۱": {"main": [[{"node": "پرامپت بررسی ۲", "type": "main", "index": 0}]]},
        "پرامپت بررسی ۲": {"main": [[{"node": "بررسی انسانی ۲", "type": "main", "index": 0}]]},
        "بررسی انسانی ۲": {"main": [[{"node": "پس از تایید انسانی", "type": "main", "index": 0}]]},
        "پس از تایید انسانی": {"main": [[{"node": "آیا متن انسانی است؟", "type": "main", "index": 0}]]},
        "آیا متن انسانی است؟": {
            "main": [
                [{"node": "پیدا کردن دسته در سایت", "type": "main", "index": 0}],
                [{"node": "توقف بدون انتشار", "type": "main", "index": 0}],
            ]
        },
        "پیدا کردن دسته در سایت": {"main": [[{"node": "انتخاب شناسه دسته", "type": "main", "index": 0}]]},
        "انتخاب شناسه دسته": {"main": [[{"node": "نوشتن متن در دسته", "type": "main", "index": 0}]]},
        "نوشتن متن در دسته": {"main": [[{"node": "تاریخ شمسی", "type": "main", "index": 0}]]},
        "تاریخ شمسی": {"main": [[{"node": "تیک و تاریخ شمسی در شیت", "type": "main", "index": 0}]]},
    }

    workflow = {
        "name": "اتوماسیون محتوای دسته — خارج از سایت",
        "nodes": nodes,
        "connections": connections,
        "active": False,
        "settings": {"executionOrder": "v1", "timezone": "Asia/Tehran"},
        "meta": {"templateCredsSetupCompleted": False},
        "tags": [{"name": "webakery"}, {"name": "seo"}],
    }
    out = ROOT / "workflows/category-seo-content.json"
    out.write_text(json.dumps(workflow, ensure_ascii=False, indent=2), encoding="utf-8")
    print("wrote", out, "nodes", len(nodes))


if __name__ == "__main__":
    main()
