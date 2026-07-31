/**
 * تست‌های بصری ورود آسان (Canvas Preview)
 * اجرا: node visual-tests/run.mjs
 *
 * حالات:
 * 1) مرحله موبایل (desktop)
 * 2) مرحله کد OTP (desktop)
 * 3) حالت خطا (desktop)
 * 4) مرحله موبایل (mobile 390x844)
 * 5) مرحله کد (mobile)
 */
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const OUT = process.env.WBL_VISUAL_OUT || '/opt/cursor/artifacts/visual-tests';
const BASE = process.env.WBL_PREVIEW_URL || 'http://127.0.0.1:8765/canvas-preview.html?v=103';

const cases = [
  {
    id: '01-desktop-phone',
    name: 'Desktop — مرحله موبایل',
    viewport: { width: 1280, height: 800 },
    setup: async (page) => {
      await page.click('#btn-phone');
      await page.waitForTimeout(400);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-step="phone"]');
      await expectHidden(page, '[data-wbl-step="code"]');
    },
  },
  {
    id: '02-desktop-code',
    name: 'Desktop — مرحله کد',
    viewport: { width: 1280, height: 800 },
    setup: async (page) => {
      await page.click('#btn-code');
      await page.waitForTimeout(400);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-step="code"]');
      await expectHidden(page, '[data-wbl-step="phone"]');
    },
  },
  {
    id: '03-desktop-error',
    name: 'Desktop — نمایش خطا',
    viewport: { width: 1280, height: 800 },
    setup: async (page) => {
      await page.click('#btn-phone');
      await page.click('#btn-err');
      await page.waitForTimeout(400);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-error]');
      await expectHidden(page, '[data-wbl-step="code"]');
    },
  },
  {
    id: '04-desktop-ajax-transition',
    name: 'Desktop — ارسال کد و انتقال',
    viewport: { width: 1280, height: 800 },
    setup: async (page) => {
      await page.click('#btn-phone');
      await page.fill('input[name="phone"]', '09123456789');
      await page.click('[data-wbl-send]');
      await page.waitForSelector('[data-wbl-step="code"]:not([hidden])', { timeout: 5000 });
      await page.waitForTimeout(350);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-step="code"]');
      await expectHidden(page, '[data-wbl-step="phone"]');
    },
  },
  {
    id: '05-mobile-phone',
    name: 'Mobile — مرحله موبایل',
    viewport: { width: 390, height: 844 },
    setup: async (page) => {
      await page.click('#btn-phone');
      await page.waitForTimeout(400);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-step="phone"]');
      await expectHidden(page, '[data-wbl-step="code"]');
    },
  },
  {
    id: '06-mobile-code',
    name: 'Mobile — مرحله کد',
    viewport: { width: 390, height: 844 },
    setup: async (page) => {
      await page.click('#btn-code');
      await page.waitForTimeout(400);
    },
    assert: async (page) => {
      await expectVisible(page, '[data-wbl-step="code"]');
      await expectHidden(page, '[data-wbl-step="phone"]');
    },
  },
];

async function expectVisible(page, sel) {
  const el = page.locator(sel);
  const hidden = await el.evaluate((n) => n.hasAttribute('hidden') || getComputedStyle(n).display === 'none');
  if (hidden) throw new Error(`Expected visible: ${sel}`);
}

async function expectHidden(page, sel) {
  const el = page.locator(sel);
  const hidden = await el.evaluate((n) => n.hasAttribute('hidden') || getComputedStyle(n).display === 'none');
  if (!hidden) throw new Error(`Expected hidden: ${sel}`);
}

async function main() {
  await mkdir(OUT, { recursive: true });
  const browser = await chromium.launch({ headless: true });
  const results = [];

  for (const c of cases) {
    const page = await browser.newPage({ viewport: c.viewport });
    const row = { id: c.id, name: c.name, ok: false, error: null, screenshot: null };
    try {
      await page.goto(BASE, { waitUntil: 'networkidle', timeout: 20000 });
      await c.setup(page);
      await c.assert(page);
      const shot = path.join(OUT, `${c.id}.png`);
      await page.locator('.stage').screenshot({ path: shot });
      row.screenshot = shot;
      row.ok = true;
      console.log(`PASS  ${c.id} — ${c.name}`);
    } catch (e) {
      row.error = String(e && e.message ? e.message : e);
      const shot = path.join(OUT, `${c.id}-FAIL.png`);
      try {
        await page.screenshot({ path: shot, fullPage: true });
        row.screenshot = shot;
      } catch {}
      console.error(`FAIL  ${c.id} — ${c.name}: ${row.error}`);
    }
    await page.close();
    results.push(row);
  }

  await browser.close();

  const passed = results.filter((r) => r.ok).length;
  const failed = results.length - passed;
  const report = {
    generatedAt: new Date().toISOString(),
    baseUrl: BASE,
    outDir: OUT,
    passed,
    failed,
    results,
  };
  await writeFile(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2));
  await writeFile(
    path.join(OUT, 'report.md'),
    [
      '# گزارش تست بصری — ورود آسان',
      '',
      `- تاریخ: ${report.generatedAt}`,
      `- URL: ${BASE}`,
      `- نتیجه: **${passed}/${results.length}** موفق`,
      '',
      ...results.map((r) => {
        const img = r.screenshot ? path.basename(r.screenshot) : '-';
        return `## ${r.ok ? '✅' : '❌'} ${r.name}\n- id: \`${r.id}\`\n- screenshot: \`${img}\`${r.error ? `\n- error: ${r.error}` : ''}\n`;
      }),
    ].join('\n')
  );

  console.log(`\nDone: ${passed}/${results.length} passed → ${OUT}`);
  if (failed) process.exit(1);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
