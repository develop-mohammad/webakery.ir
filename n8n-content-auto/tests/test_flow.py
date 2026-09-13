#!/usr/bin/env python3
import json
import os
import sys
import unittest
from datetime import datetime, timedelta, timezone

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
sys.path.insert(0, os.path.join(ROOT, "worker"))
import run as engine  # noqa: E402


class SheetBlocksTest(unittest.TestCase):
    def test_first_pending_block_collects_keyword_rows(self):
        csv_path = os.path.join(os.path.dirname(__file__), "fixtures", "sheet.csv")
        with open(csv_path, encoding="utf-8") as fh:
            rows = engine.parse_csv_text(fh.read())
        blocks = engine.rows_to_blocks(rows)
        self.assertEqual(len(blocks), 2)
        pending = engine.next_pending(blocks)
        self.assertIsNotNone(pending)
        self.assertEqual(pending["category"], "شیر صنعتی")
        self.assertIn("شیر صنعتی", pending["keywords"])
        self.assertIn("شیر گازی", pending["keywords"])
        self.assertFalse(pending["done"])
        self.assertTrue(blocks[1]["done"])
        self.assertEqual(blocks[1]["category"], "پمپ سانتریفیوژ")


class JalaliTest(unittest.TestCase):
    def test_known_date(self):
        tz = timezone(timedelta(hours=3, minutes=30))
        ts = datetime(2026, 9, 13, 12, 0, tzinfo=tz).timestamp()
        self.assertEqual(engine.today_jalali(ts), "1405/06/22")


class PromptTest(unittest.TestCase):
    def test_user_prompt_placeholders(self):
        tpl = engine.read_prompt("generate.txt")
        text = engine.fill(
            tpl,
            {
                "title": "راهنمای خرید شیر صنعتی",
                "keywords": "شیر صنعتی",
                "lsi": "شیر فلکه",
                "brand": "وب آکری",
            },
        )
        self.assertIn("راهنمای خرید شیر صنعتی", text)
        self.assertIn("شیر صنعتی", text)
        self.assertIn("وب آکری", text)
        self.assertNotIn("{{title}}", text)
        self.assertIn("فقط از این تگ ها استفاده شود", text)


class HumanHtmlTest(unittest.TestCase):
    def test_strips_disallowed_and_half_space(self):
        raw = "<div>x</div><h1>عنوان" + "\u200c" + "تست</h1><p>سلام</p>```"
        html = engine.extract_html("توضیح اضافه\n" + raw)
        self.assertTrue(html.startswith("<h1>"))
        self.assertNotIn("<div>", html)
        self.assertNotIn("\u200c", html)

    def test_verdict_json(self):
        raw = 'متن\n{"human": true, "score": 90, "issues": [], "rewrite": ""}'
        v = engine.parse_verdict(raw)
        self.assertTrue(v["human"])
        self.assertEqual(v["score"], 90)


class PipelineTest(unittest.TestCase):
    def test_fixture_one_block_dry_run(self):
        os.environ["SHEET_FIXTURE"] = os.path.join(os.path.dirname(__file__), "fixtures", "sheet.csv")
        os.environ["AI_PROVIDER"] = "fixture"
        os.environ["AI_KEY"] = "fixture"
        os.environ["BRAND_DEFAULT"] = "وب آکری"
        os.environ["MIN_HUMAN_SCORE"] = "50"
        out = engine.run_next(dry_run=True)
        self.assertTrue(out["ok"], msg=json.dumps(out, ensure_ascii=False))
        row = out["results"][0]
        self.assertEqual(row["category"], "شیر صنعتی")
        self.assertTrue(row["jalali"])
        self.assertGreater(row["words"], 100)
        self.assertGreaterEqual(row["human_2"], 50)


class WorkflowJsonTest(unittest.TestCase):
    def test_n8n_file_has_schedule_and_prompt(self):
        path = os.path.join(ROOT, "workflows", "category-seo-content.json")
        with open(path, encoding="utf-8") as fh:
            data = json.loads(fh.read())
        names = [n["name"] for n in data["nodes"]]
        self.assertIn("هر ساعت یک بلوک", names)
        self.assertIn("خواندن گوگل شیت", names)
        self.assertIn("بررسی انسانی ۲", names)
        self.assertIn("نوشتن متن در دسته", names)
        self.assertIn("تیک و تاریخ شمسی در شیت", names)
        fill = next(n for n in data["nodes"] if n["name"] == "پرکردن پرامپت")
        self.assertIn("نویسنده ارشد سئو", fill["parameters"]["jsCode"])
        self.assertEqual(data["settings"]["timezone"], "Asia/Tehran")
        self.assertIn("هر ساعت یک بلوک", data["connections"])
        self.assertIn("آیا متن انسانی است؟", data["connections"])


if __name__ == "__main__":
    unittest.main()
