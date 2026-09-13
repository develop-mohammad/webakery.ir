#!/usr/bin/env python3
"""موتور اتوماسیون محتوا — خارج از وردپرس، همان جریان n8n."""
from __future__ import annotations

import csv
import io
import json
import os
import re
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timezone, timedelta
from typing import Any

ZWNJ = "\u200c"
ALLOWED_TAGS = ("h1", "h2", "h3", "p", "table", "tr", "th", "td")
CLICHES = (
    "در دنیای امروز",
    "در عصر حاضر",
    "لازم به ذکر است",
    "ناگفته نماند",
    "نقش بسزایی",
    "از اهمیت بالایی برخوردار",
    "پوشش جامعی",
    "گامی مؤثر",
    "گامی موثر",
    "شایان ذکر است",
)

DONE_VALUES = {
    "true",
    "1",
    "yes",
    "y",
    "done",
    "بله",
    "انجام",
    "انجام شد",
    "x",
    "✓",
    "✔",
}

DEFAULT_COLS = {
    "category": "دسته",
    "title": "عنوان",
    "keywords": "کیورد اصلی",
    "lsi": "کیورد فرعی",
    "brand": "برند",
    "done": "انجام شد",
    "date": "تاریخ",
    "note": "یادداشت",
}


def load_env_file(path: str) -> None:
    if not path or not os.path.isfile(path):
        return
    with open(path, encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, val = line.split("=", 1)
            key = key.strip()
            val = val.strip().strip('"').strip("'")
            if key and key not in os.environ:
                os.environ[key] = val


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def fill(template: str, variables: dict[str, str]) -> str:
    out = template
    for key, value in variables.items():
        out = out.replace("{{" + key + "}}", str(value))
    return out


def split_keywords(raw: str) -> list[str]:
    parts = re.split(r"[\r\n,،;|]+", raw or "")
    return [p.strip() for p in parts if p.strip()]


def is_done(value: Any) -> bool:
    text = str(value or "").strip().lower().replace("۱", "1").replace("۰", "0")
    return text in DONE_VALUES


def parse_csv_text(text: str) -> list[dict[str, str]]:
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    reader = csv.DictReader(io.StringIO(text))
    rows = []
    for i, row in enumerate(reader, start=2):
        item = {k.strip(): (v or "").strip() for k, v in row.items() if k}
        item["_row"] = str(i)
        rows.append(item)
    return rows


def col(settings: dict[str, str], key: str) -> str:
    env_key = {
        "category": "COL_CATEGORY",
        "title": "COL_TITLE",
        "keywords": "COL_KEYWORDS",
        "lsi": "COL_LSI",
        "brand": "COL_BRAND",
        "done": "COL_DONE",
        "date": "COL_DATE",
        "note": "COL_NOTE",
    }[key]
    return settings.get(env_key) or env(env_key) or DEFAULT_COLS[key]


def cell(row: dict[str, str], header: str) -> str:
    if header in row:
        return str(row.get(header) or "").strip()
    lower = {k.lower(): k for k in row}
    if header.lower() in lower:
        return str(row.get(lower[header.lower()]) or "").strip()
    return ""


def rows_to_blocks(rows: list[dict[str, str]], settings: dict[str, str] | None = None) -> list[dict[str, Any]]:
    settings = settings or {}
    c_cat = col(settings, "category")
    c_title = col(settings, "title")
    c_kw = col(settings, "keywords")
    c_lsi = col(settings, "lsi")
    c_brand = col(settings, "brand")
    c_done = col(settings, "done")
    c_date = col(settings, "date")
    blocks: list[dict[str, Any]] = []
    open_block: dict[str, Any] | None = None

    def close() -> None:
        nonlocal open_block
        if open_block:
            blocks.append(open_block)
            open_block = None

    def make(row: dict[str, str], cat: str, title: str, kws: list[str], lsi: list[str], brand: str, done: bool, date: str) -> dict[str, Any]:
        if not title:
            title = cat
        if not cat:
            cat = title
        return {
            "start_row": int(row.get("_row") or 0),
            "rows": [int(row.get("_row") or 0)],
            "category": cat,
            "title": title,
            "keywords": kws,
            "lsi": lsi,
            "brand": brand,
            "done": done,
            "date": date,
        }

    for row in rows:
        cat = cell(row, c_cat)
        title = cell(row, c_title)
        kws = split_keywords(cell(row, c_kw))
        lsi = split_keywords(cell(row, c_lsi))
        brand = cell(row, c_brand)
        done = is_done(cell(row, c_done))
        date = cell(row, c_date)
        empty = not cat and not title and not kws and not lsi and not brand
        if empty:
            close()
            continue
        if cat or title:
            close()
            open_block = make(row, cat, title, kws, lsi, brand, done, date)
            continue
        if open_block:
            open_block["rows"].append(int(row.get("_row") or 0))
            open_block["keywords"] = list(dict.fromkeys(open_block["keywords"] + kws))
            extra = lsi or kws
            open_block["lsi"] = list(dict.fromkeys(open_block["lsi"] + extra))
            if brand and not open_block["brand"]:
                open_block["brand"] = brand
            if done:
                open_block["done"] = True
            if date:
                open_block["date"] = date
        elif kws or lsi:
            open_block = make(row, cat, title, kws, lsi, brand, done, date)
    close()
    return blocks


def next_pending(blocks: list[dict[str, Any]]) -> dict[str, Any] | None:
    for block in blocks:
        if not block.get("done") and block.get("category"):
            return block
    return None


def to_jalali(gy: int, gm: int, gd: int) -> tuple[int, int, int]:
    g_y_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    jy = 0 if gy <= 1600 else 979
    gy -= 621 if gy <= 1600 else 1600
    gy2 = gy + 1 if gm > 2 else gy
    days = (365 * gy) + ((gy2 + 3) // 4) - ((gy2 + 99) // 100) + ((gy2 + 399) // 400) - 80 + gd + g_y_m[gm - 1]
    jy += 33 * (days // 12053)
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    jm_days = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29]
    i = 0
    while i < 11 and days >= jm_days[i]:
        days -= jm_days[i]
        i += 1
    return jy, i + 1, days + 1


def today_jalali(ts: float | None = None) -> str:
    tz = timezone(timedelta(hours=3, minutes=30))
    dt = datetime.fromtimestamp(ts if ts is not None else time.time(), tz=tz)
    jy, jm, jd = to_jalali(dt.year, dt.month, dt.day)
    return f"{jy:04d}/{jm:02d}/{jd:02d}"


def extract_html(raw: str) -> str:
    text = (raw or "").strip()
    fence = re.search(r"```(?:html)?\s*([\s\S]*?)```", text, re.I)
    if fence:
        text = fence.group(1).strip()
    match = re.search(r"<(?:h1|h2|p)\b", text, re.I)
    if match:
        text = text[match.start() :]
    text = text.replace(ZWNJ, " ").replace("\xa0", " ")
    text = re.sub(r"</?(?:html|body|head|article|section|div|span|strong|em|b|i|ul|ol|li|br)[^>]*>", "", text, flags=re.I)
    tags = "|".join(ALLOWED_TAGS)
    text = re.sub(rf"<(?!/?({tags})\b)[^>]+>", "", text, flags=re.I)
    return text.strip()


def strip_text(html: str) -> str:
    text = re.sub(r"<[^>]+>", " ", html or "")
    return re.sub(r"\s+", " ", text).strip()


def word_count(html: str) -> int:
    parts = [p for p in strip_text(html).split(" ") if p]
    return len(parts)


def inspect_html(html: str, brand: str = "") -> dict[str, Any]:
    issues: list[str] = []
    score = 92
    text = strip_text(html)
    if not text:
        return {"score": 0, "issues": ["متن خالی است"]}
    if ZWNJ in (html or ""):
        issues.append("نیم فاصله")
        score -= 12
    found = [c for c in CLICHES if c in text]
    if found:
        issues.append("کلیشه ماشینی: " + "، ".join(found))
        score -= 6 * len(found)
    if word_count(html) < 900:
        issues.append("متن کوتاه است")
        score -= 8
    if len(re.findall(r"<h2\b", html or "", re.I)) < 6:
        issues.append("تیتر h2 کم است")
        score -= 6
    if not re.search(r"<table\b", html or "", re.I):
        issues.append("جدول ندارد")
        score -= 5
    if brand and strip_text(html).count(brand) > 2:
        issues.append("برند بیش از 2 بار")
        score -= 8
    return {"score": max(0, min(100, score)), "issues": issues}


def parse_verdict(raw: str) -> dict[str, Any]:
    match = re.search(r"\{[\s\S]*\}", raw or "")
    data = {}
    if match:
        try:
            data = json.loads(match.group(0))
        except json.JSONDecodeError:
            data = {}
    rewrite = extract_html(str(data.get("rewrite") or ""))
    issues = [str(x).strip() for x in (data.get("issues") or []) if str(x).strip()]
    return {
        "human": bool(data.get("human")),
        "score": int(data.get("score") or 0),
        "issues": issues,
        "rewrite": rewrite,
    }


def prompt_vars(block: dict[str, Any], content: str = "", settings: dict[str, str] | None = None) -> dict[str, str]:
    settings = settings or {}
    kws = [str(x) for x in block.get("keywords") or [] if str(x).strip()]
    lsi = [str(x) for x in block.get("lsi") or [] if str(x).strip()]
    title = str(block.get("title") or "").strip() or str(block.get("category") or "").strip()
    brand = str(block.get("brand") or "").strip() or env("BRAND_DEFAULT")
    return {
        "category": str(block.get("category") or "").strip(),
        "title": title,
        "keywords": "\n".join(kws),
        "lsi": "\n".join(lsi),
        "brand": brand,
        "content": content,
        "min_score": env("MIN_HUMAN_SCORE", "78") or "78",
    }


def read_prompt(name: str) -> str:
    here = os.path.join(os.path.dirname(__file__), "..", "prompts", name)
    with open(here, encoding="utf-8") as fh:
        return fh.read()


def http_json(method: str, url: str, headers: dict[str, str] | None = None, body: Any = None, timeout: int = 60) -> tuple[int, Any, str]:
    data = None
    hdrs = dict(headers or {})
    if body is not None:
        raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
        data = raw
        hdrs.setdefault("Content-Type", "application/json")
    req = urllib.request.Request(url, data=data, headers=hdrs, method=method.upper())
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read().decode("utf-8", "replace")
            try:
                parsed = json.loads(raw) if raw else {}
            except json.JSONDecodeError:
                parsed = {}
            return resp.status, parsed, raw
    except urllib.error.HTTPError as err:
        raw = err.read().decode("utf-8", "replace")
        try:
            parsed = json.loads(raw) if raw else {}
        except json.JSONDecodeError:
            parsed = {}
        return err.code, parsed, raw


def chat(messages: list[dict[str, str]], timeout: int = 180) -> str:
    if env("AI_PROVIDER") == "fixture" or env("AI_KEY") == "fixture":
        return fixture_chat(messages)
    base = env("AI_BASE", "https://api.openai.com/v1").rstrip("/")
    key = env("AI_KEY")
    if not key:
        raise RuntimeError("AI_KEY خالی است")
    code, data, raw = http_json(
        "POST",
        base + "/chat/completions",
        headers={"Authorization": "Bearer " + key},
        body={
            "model": env("AI_MODEL", "gpt-4o-mini"),
            "temperature": 0.7,
            "max_tokens": 8000,
            "messages": messages,
        },
        timeout=timeout,
    )
    if code >= 300:
        msg = ""
        if isinstance(data, dict):
            err = data.get("error")
            if isinstance(err, dict):
                msg = str(err.get("message") or "")
        raise RuntimeError(msg or f"AI HTTP {code}: {raw[:300]}")
    try:
        return str(data["choices"][0]["message"]["content"])
    except (KeyError, IndexError, TypeError) as exc:
        raise RuntimeError("پاسخ مدل نامعتبر بود") from exc


def fixture_chat(messages: list[dict[str, str]]) -> str:
    last = messages[-1]["content"] if messages else ""
    if '"human"' in last or "داور تشخیص" in last:
        return json.dumps({"human": True, "score": 88, "issues": [], "rewrite": ""}, ensure_ascii=False)
    title = "راهنمای خرید تجهیزات صنعتی"
    m = re.search(r"عنوان مقاله:\s*\n+([^\n]+)", last)
    if m:
        title = m.group(1).strip() or title
    brand = env("BRAND_DEFAULT", "وب آکری")
    heads = [
        "معیارهای انتخاب و بررسی فنی",
        "مزایا و معایب گزینه های رایج",
        "راهنمای خرید برای مدیر کارخانه",
        "مقایسه قیمت و خدمات پس از فروش",
        "نکات اجرایی نصب و نگهداری",
        "خطاهای رایج در سفارش گذاری",
        "جدول مقایسه مشخصات",
        "جمع بندی کاربردی",
    ]
    p1 = (
        f"{title} موضوعی است که خریدار صنعتی قبل از سفارش باید آن را بشناسد. "
        "انتخاب نادرست هزینه تعمیر و توقف خط را بالا می برد. در این بررسی معیارهای فنی، "
        "قیمت تمام شده و خدمات پس از فروش کنار هم دیده می شود. هدف این است که مدیر خرید "
        "با داده واقعی تصمیم بگیرد نه با شعار تبلیغاتی. بازار ایران تنوع زیادی دارد و کیفیت "
        "قطعات یکسان نیست. بنابراین مقایسه عملی بین گزینه های رایج ضروری است."
    )
    p2 = (
        "برای خرید باید ظرفیت، جنس بدنه، استاندارد ایمنی و موجودی قطعه یدکی را با هم ببینید. "
        "عدد کاتالوگ به تنهایی کافی نیست. تجربه کارگاه های ایرانی نشان می دهد که خدمات محلی "
        "از برند خارجی بدون نمایندگی مهم تر است. اگر خط تولید پیوسته کار می کند، زمان تامین "
        "قطعه را در قرارداد بنویسید. هزینه خاموشی یک روز اغلب از اختلاف قیمت دو مدل بیشتر است."
    )
    chunks = [f"<h1>{title}</h1>", f"<p>{p1}</p>"]
    for i, h in enumerate(heads):
        chunks.append(f"<h2>{h}</h2>")
        chunks.append(f"<p>{p2}</p>")
        chunks.append(
            "<p>در عمل بهتر است سه استعلام مکتوب بگیرید و گارانتی را با شماره سریال تطبیق دهید. "
            "بازدید حضوری از نمونه نصب شده ریسک را کم می کند. اگر بودجه محدود است مدل میان رده "
            "با قطعه یدکی فراوان انتخاب امن تری است. آموزش اپراتور را هم در قیمت نهایی حساب کنید.</p>"
        )
        if i == 6:
            chunks.append(
                "<table><tr><th>معیار</th><th>اقتصادی</th><th>صنعتی</th></tr>"
                "<tr><td>دوام</td><td>متوسط</td><td>بالا</td></tr>"
                "<tr><td>قطعه یدکی</td><td>محدود</td><td>فراوان</td></tr></table>"
            )
    chunks += [
        "<h2>سوالات متداول</h2>",
        f"<p>بهترین زمان خرید {title} چه فصلی است؟</p>",
        "<p>معمولا وقتی موجودی تامین کننده بالاست تخفیف واقعی تر است.</p>",
        "<p>آیا گارانتی شرکتی کافی است؟</p>",
        "<p>گارانتی بدون قطعه یدکی در شهر شما ارزش کمی دارد.</p>",
        "<p>چطور برند را با نمونه خارجی مقایسه کنیم؟</p>",
        f"<p>{brand} وقتی انتخاب می شود که خدمات و مستندات فارسی مشخص باشد.</p>",
        "<p>حداقل مشخصات فنی لازم چیست؟</p>",
        f"<p>ظرفیت واقعی و استاندارد ایمنی را اول قفل کنید. {brand} را فقط اگر این موارد را پوشش داد نگه دارید.</p>",
    ]
    return "\n".join(chunks)


def wp_taxonomy_path(tax: str) -> str:
    tax = tax or "product_cat"
    return "categories" if tax == "category" else tax


def publish_wordpress(html: str, category: str, dry_run: bool = False) -> dict[str, Any]:
    if dry_run:
        return {"ok": True, "term_id": 0, "error": ""}
    base = env("WP_URL").rstrip("/")
    user = env("WP_USER")
    password = env("WP_APP_PASSWORD")
    tax = env("WP_TAXONOMY", "product_cat")
    if not base or not user or not password:
        raise RuntimeError("WP_URL / WP_USER / WP_APP_PASSWORD لازم است")
    path = wp_taxonomy_path(tax)
    token = urllib.parse.quote(category)
    url = f"{base}/wp-json/wp/v2/{path}?search={token}&per_page=20"
    auth = _basic(user, password)
    code, data, raw = http_json("GET", url, headers={"Authorization": auth}, timeout=40)
    if code >= 300 or not isinstance(data, list) or not data:
        raise RuntimeError(f"دسته در سایت پیدا نشد: {category}")
    term_id = 0
    for item in data:
        if str(item.get("name") or "") == category:
            term_id = int(item.get("id") or 0)
            break
    if not term_id:
        term_id = int(data[0].get("id") or 0)
    put_url = f"{base}/wp-json/wp/v2/{path}/{term_id}"
    code, _, raw = http_json(
        "POST",
        put_url,
        headers={"Authorization": auth},
        body={"description": html},
        timeout=40,
    )
    if code >= 300:
        raise RuntimeError(f"نوشتن دسته ناموفق HTTP {code}: {raw[:300]}")
    return {"ok": True, "term_id": term_id, "error": ""}


def _basic(user: str, password: str) -> str:
    import base64

    blob = base64.b64encode(f"{user}:{password}".encode("utf-8")).decode("ascii")
    return "Basic " + blob


def fetch_sheet_csv(url: str) -> list[dict[str, str]]:
    req = urllib.request.Request(url, headers={"User-Agent": "n8n-content-auto/1.0"})
    with urllib.request.urlopen(req, timeout=30) as resp:
        raw = resp.read().decode("utf-8", "replace")
    return parse_csv_text(raw)


def google_access_token(sa: dict[str, Any]) -> str:
    import base64
    import subprocess
    import tempfile

    def b64url(data: bytes) -> str:
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode("ascii")

    now = int(time.time())
    header = b64url(json.dumps({"alg": "RS256", "typ": "JWT"}).encode())
    claim = b64url(
        json.dumps(
            {
                "iss": sa["client_email"],
                "scope": "https://www.googleapis.com/auth/spreadsheets",
                "aud": "https://oauth2.googleapis.com/token",
                "exp": now + 3600,
                "iat": now,
            }
        ).encode()
    )
    unsigned = f"{header}.{claim}".encode()
    with tempfile.NamedTemporaryFile("w", suffix=".pem", delete=False) as keyfile:
        keyfile.write(str(sa["private_key"]))
        key_path = keyfile.name
    try:
        proc = subprocess.run(
            ["openssl", "dgst", "-sha256", "-sign", key_path],
            input=unsigned,
            capture_output=True,
            check=False,
        )
    finally:
        os.unlink(key_path)
    if proc.returncode != 0:
        raise RuntimeError("امضای JWT گوگل شکست خورد")
    jwt = unsigned.decode() + "." + b64url(proc.stdout)
    body = urllib.parse.urlencode(
        {
            "grant_type": "urn:ietf:params:oauth:grant-type:jwt-bearer",
            "assertion": jwt,
        }
    ).encode()
    req = urllib.request.Request(
        "https://oauth2.googleapis.com/token",
        data=body,
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    with urllib.request.urlopen(req, timeout=20) as resp:
        data = json.loads(resp.read().decode())
    token = data.get("access_token")
    if not token:
        raise RuntimeError("توکن گوگل گرفته نشد")
    return str(token)


def load_service_account() -> dict[str, Any] | None:
    path = env("GOOGLE_SERVICE_ACCOUNT_FILE")
    raw = env("GOOGLE_SERVICE_ACCOUNT_JSON")
    if path and os.path.isfile(path):
        with open(path, encoding="utf-8") as fh:
            raw = fh.read()
    if not raw:
        return None
    data = json.loads(raw)
    return data if isinstance(data, dict) else None


def load_sheet_rows() -> list[dict[str, str]]:
    fixture = env("SHEET_FIXTURE")
    if fixture:
        with open(fixture, encoding="utf-8") as fh:
            return parse_csv_text(fh.read())
    sa = load_service_account()
    sheet_id = env("SHEET_ID")
    tab = env("SHEET_TAB", "Sheet1")
    if sa and sheet_id:
        token = google_access_token(sa)
        rng = urllib.parse.quote(f"{tab}!A1:H400")
        url = f"https://sheets.googleapis.com/v4/spreadsheets/{sheet_id}/values/{rng}"
        code, data, raw = http_json("GET", url, headers={"Authorization": "Bearer " + token}, timeout=30)
        if code >= 300:
            raise RuntimeError(f"خواندن شیت ناموفق HTTP {code}")
        values = data.get("values") or []
        if not values:
            return []
        headers = [str(h).strip() for h in values[0]]
        out = []
        for i, row in enumerate(values[1:], start=2):
            item = {headers[j]: (row[j] if j < len(row) else "") for j in range(len(headers))}
            item["_row"] = str(i)
            out.append(item)
        return out
    if sheet_id:
        gid = env("SHEET_GID", "0")
        url = f"https://docs.google.com/spreadsheets/d/{sheet_id}/export?format=csv&gid={gid}"
        return fetch_sheet_csv(url)
    raise RuntimeError("SHEET_ID یا SHEET_FIXTURE لازم است")


def mark_sheet_done(block: dict[str, Any], jalali: str, dry_run: bool = False) -> None:
    if dry_run:
        return
    script = env("APPS_SCRIPT_URL")
    if script:
        code, _, raw = http_json(
            "POST",
            script,
            body={
                "secret": env("APPS_SCRIPT_SECRET"),
                "row": int(block["start_row"]),
                "done": True,
                "date": jalali,
                "note": "منتشر شد",
            },
            timeout=30,
        )
        if code >= 300:
            raise RuntimeError(f"Apps Script HTTP {code}: {raw[:200]}")
        return
    sa = load_service_account()
    sheet_id = env("SHEET_ID")
    if not sa or not sheet_id:
        raise RuntimeError("برای نوشتن شیت، حساب سرویس یا Apps Script لازم است")
    token = google_access_token(sa)
    tab = env("SHEET_TAB", "Sheet1")
    row = int(block["start_row"])
    # F=انجام G=تاریخ H=یادداشت by default; map letters from env if provided
    mapping = env("SHEET_DONE_RANGE") or f"{tab}!F{row}:H{row}"
    url = f"https://sheets.googleapis.com/v4/spreadsheets/{sheet_id}/values:batchUpdate"
    code, _, raw = http_json(
        "POST",
        url,
        headers={"Authorization": "Bearer " + token},
        body={
            "valueInputOption": "USER_ENTERED",
            "data": [{"range": mapping, "values": [["TRUE", jalali, "منتشر شد"]]}],
        },
        timeout=30,
    )
    if code >= 300:
        raise RuntimeError(f"آپدیت شیت HTTP {code}: {raw[:300]}")


def process_block(block: dict[str, Any], dry_run: bool = False) -> dict[str, Any]:
    log: dict[str, Any] = {
        "ok": False,
        "category": block.get("category"),
        "title": block.get("title"),
        "row": block.get("start_row"),
        "error": "",
        "term_id": 0,
        "jalali": "",
        "human_1": 0,
        "human_2": 0,
        "words": 0,
    }
    vars0 = prompt_vars(block)
    gen_prompt = fill(read_prompt("generate.txt"), vars0)
    html = extract_html(
        chat(
            [
                {"role": "system", "content": "فقط HTML مجاز مقاله را برگردان. تگ های مجاز: h1 h2 h3 p table tr th td."},
                {"role": "user", "content": gen_prompt},
            ]
        )
    )
    if not html:
        log["error"] = "خروجی تولید HTML نبود"
        return log
    h1 = fill(read_prompt("human-1.txt"), prompt_vars(block, html))
    html = extract_html(chat([{"role": "user", "content": h1}])) or html
    log["human_1"] = inspect_html(html, vars0["brand"])["score"]
    h2 = fill(read_prompt("human-2.txt"), prompt_vars(block, html))
    verdict = parse_verdict(chat([{"role": "user", "content": h2}]))
    if verdict["rewrite"]:
        html = verdict["rewrite"]
    local = inspect_html(html, vars0["brand"])
    score = min(verdict["score"] or local["score"], local["score"])
    log["human_2"] = score
    log["words"] = word_count(html)
    minimum = int(env("MIN_HUMAN_SCORE", "78") or 78)
    if not verdict["human"] or score < minimum:
        log["error"] = "متن بعد از دو بررسی انسانی تایید نشد: " + "، ".join(verdict["issues"] or local["issues"])
        return log
    pub = publish_wordpress(html, str(block["category"]), dry_run=dry_run)
    log["term_id"] = pub["term_id"]
    jalali = today_jalali()
    mark_sheet_done(block, jalali, dry_run=dry_run)
    log["ok"] = True
    log["jalali"] = jalali
    return log


def run_next(dry_run: bool = False) -> dict[str, Any]:
    rows = load_sheet_rows()
    blocks = rows_to_blocks(rows)
    pending = next_pending(blocks)
    if not pending:
        return {"ok": True, "message": "بلوک ناتمامی نماند", "results": []}
    result = process_block(pending, dry_run=dry_run)
    return {"ok": bool(result.get("ok")), "results": [result], "error": result.get("error") or ""}


def main() -> int:
    import argparse

    parser = argparse.ArgumentParser(description="اتوماسیون محتوای دسته — خارج از سایت")
    parser.add_argument("--env-file", default=os.path.join(os.path.dirname(__file__), "..", ".env"))
    parser.add_argument("--fixture", default="")
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--once", action="store_true", default=True)
    args = parser.parse_args()
    load_env_file(args.env_file)
    if args.fixture:
        os.environ["SHEET_FIXTURE"] = args.fixture
        os.environ["AI_PROVIDER"] = "fixture"
        os.environ["AI_KEY"] = "fixture"
    out = run_next(dry_run=args.dry_run or bool(args.fixture))
    print(json.dumps(out, ensure_ascii=False, indent=2))
    return 0 if out.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())
