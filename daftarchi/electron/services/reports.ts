import { getAllSettings, getDb } from '../database/db'
import type { ComparisonSeries, DashboardData, PeakHour, ProfitReport, SiteOrder, StocktakeRow } from '../../shared/models'
import { listInvoices } from './invoices'
import { getProduct, recordHistory } from './products'
import {
  addDaysYmd,
  dayKeyTehran,
  eachYmd,
  hourInTehran,
  jalaliLabelFromYmd,
  jalaliMonthLabelFromYmd,
  monthRangeYmd,
  tehranYmd,
  weekRangeYmd,
  ymdToIsoStart,
} from '../../shared/period'

type SaleRow = { total: number; created_at: string }

function salesBetween(startYmd: string, endExclusiveYmd: string): SaleRow[] {
  return getDb()
    .prepare(
      `SELECT total, created_at FROM invoices
       WHERE type = 'sale' AND created_at >= ? AND created_at < ?`,
    )
    .all(ymdToIsoStart(startYmd), ymdToIsoStart(endExclusiveYmd)) as SaleRow[]
}

function bucketByDay(rows: SaleRow[]): Map<string, number> {
  const map = new Map<string, number>()
  for (const row of rows) {
    const key = dayKeyTehran(row.created_at)
    map.set(key, (map.get(key) ?? 0) + row.total)
  }
  return map
}

export function comparisonSeries(preset: 'week' | 'month'): ComparisonSeries {
  const range = preset === 'week' ? weekRangeYmd() : monthRangeYmd()
  const currentRows = salesBetween(range.start, range.endExclusive)
  const prevRows = salesBetween(range.prevStart, range.prevEndExclusive)
  const currentMap = bucketByDay(currentRows)
  const prevMap = bucketByDay(prevRows)
  const currentDays = eachYmd(range.start, range.endExclusive)
  const prevDays = eachYmd(range.prevStart, range.prevEndExclusive)
  const len = Math.max(currentDays.length, prevDays.length)
  const points = []
  for (let i = 0; i < len; i += 1) {
    const cur = currentDays[i]
    const prev = prevDays[i]
    points.push({
      key: cur ?? prev ?? String(i),
      label: cur ? jalaliLabelFromYmd(cur) : jalaliLabelFromYmd(prev),
      current: cur ? (currentMap.get(cur) ?? 0) : 0,
      previous: prev ? (prevMap.get(prev) ?? 0) : 0,
    })
  }
  const currentTotal = points.reduce((s, p) => s + p.current, 0)
  const previousTotal = points.reduce((s, p) => s + p.previous, 0)
  const delta =
    previousTotal === 0 ? (currentTotal === 0 ? 0 : 100) : ((currentTotal - previousTotal) / previousTotal) * 100
  return {
    preset,
    current_label: preset === 'week' ? 'این هفته' : jalaliMonthLabelFromYmd(range.start),
    previous_label: preset === 'week' ? 'هفتهٔ قبل' : jalaliMonthLabelFromYmd(range.prevStart),
    current_total: currentTotal,
    previous_total: previousTotal,
    delta_percent: Math.round(delta * 10) / 10,
    points,
  }
}

export function peakHours(preset: 'week' | 'month'): PeakHour[] {
  const range = preset === 'week' ? weekRangeYmd() : monthRangeYmd()
  const rows = salesBetween(range.start, range.endExclusive)
  const hours = Array.from({ length: 24 }, (_, hour) => ({
    hour,
    label: `${hour}:00`,
    total: 0,
    count: 0,
  }))
  for (const row of rows) {
    const h = hourInTehran(row.created_at)
    if (h >= 0 && h < 24) {
      hours[h].total += row.total
      hours[h].count += 1
    }
  }
  return hours
}

export function dashboardData(preset: 'week' | 'month'): DashboardData {
  const today = tehranYmd()
  const tomorrow = ymdToIsoStart(addDaysYmd(today, 1))
  const todayStart = ymdToIsoStart(today)
  const todaySales = getDb()
    .prepare(
      `SELECT COALESCE(SUM(total),0) AS s, COUNT(*) AS c FROM invoices
       WHERE type='sale' AND created_at >= ? AND created_at < ?`,
    )
    .get(todayStart, tomorrow) as { s: number; c: number }
  const month = monthRangeYmd()
  const profit = profitReport(month.start, month.endExclusive)
  const cash = getDb().prepare('SELECT COALESCE(SUM(balance),0) AS b FROM cash_accounts').get() as { b: number }
  const range = preset === 'month' ? month : weekRangeYmd()
  const siteToday = getDb()
    .prepare(
      `SELECT COALESCE(SUM(total),0) AS s, COUNT(*) AS c FROM wc_orders
       WHERE created_at >= ? AND created_at < ?`,
    )
    .get(todayStart, tomorrow) as { s: number; c: number }
  const sitePeriod = (
    getDb()
      .prepare(
        `SELECT COALESCE(SUM(total),0) AS s FROM wc_orders
         WHERE created_at >= ? AND created_at < ?`,
      )
      .get(ymdToIsoStart(range.start), ymdToIsoStart(range.endExclusive)) as { s: number }
  ).s
  const recentSite = getDb()
    .prepare(
      `SELECT id, number, status, total, customer_name, created_at
       FROM wc_orders ORDER BY created_at DESC LIMIT 10`,
    )
    .all() as SiteOrder[]
  const settings = getAllSettings()
  return {
    sales_today: todaySales.s,
    profit_month: profit.profit,
    invoices_today: todaySales.c,
    cash_balance: cash.b,
    site_sales_today: siteToday.s,
    site_orders_today: siteToday.c,
    site_sales_period: sitePeriod,
    site_sales_pulled_at: settings.wc_last_sales_at || '',
    comparison: comparisonSeries(preset),
    peak_hours: peakHours(preset),
    recent_invoices: listInvoices(10),
    recent_site_orders: recentSite,
  }
}

export function profitReport(startYmd: string, endExclusiveYmd: string): ProfitReport {
  const start = ymdToIsoStart(startYmd)
  const end = ymdToIsoStart(endExclusiveYmd)
  const sales = (
    getDb()
      .prepare(`SELECT COALESCE(SUM(total),0) AS s FROM invoices WHERE type='sale' AND created_at >= ? AND created_at < ?`)
      .get(start, end) as { s: number }
  ).s
  const cogs = (
    getDb()
      .prepare(
        `SELECT COALESCE(SUM(ii.qty * ii.buy_price_snapshot),0) AS s
         FROM invoice_items ii
         JOIN invoices i ON i.id = ii.invoice_id
         WHERE i.type='sale' AND i.created_at >= ? AND i.created_at < ?`,
      )
      .get(start, end) as { s: number }
  ).s
  const expenses = (
    getDb()
      .prepare(
        `SELECT COALESCE(SUM(amount),0) AS s FROM cash_transactions
         WHERE kind IN ('expense','manual') AND direction='out' AND created_at >= ? AND created_at < ?`,
      )
      .get(start, end) as { s: number }
  ).s
  const top = getDb()
    .prepare(
      `SELECT ii.product_id, ii.name_snapshot AS name,
              SUM(ii.qty) AS qty, SUM(ii.line_total) AS sales
       FROM invoice_items ii
       JOIN invoices i ON i.id = ii.invoice_id
       WHERE i.type='sale' AND i.created_at >= ? AND i.created_at < ?
       GROUP BY ii.product_id, ii.name_snapshot
       ORDER BY sales DESC
       LIMIT 10`,
    )
    .all(start, end) as ProfitReport['top_products']
  return { sales, cogs, expenses, profit: sales - cogs - expenses, top_products: top }
}

export function stocktakeRows(): StocktakeRow[] {
  return getDb()
    .prepare(
      `SELECT p.id AS product_id, p.name, p.sku, p.stock AS system_qty,
              COALESCE(c.name, 'بدون دسته') AS category_name
       FROM products p
       LEFT JOIN categories c ON c.id = p.category_id
       ORDER BY category_name, p.name`,
    )
    .all() as StocktakeRow[]
}

export function applyStocktake(counts: { product_id: number; counted_qty: number }[], note: string): number {
  const db = getDb()
  const now = new Date().toISOString()
  const run = db.transaction(() => {
    const header = db
      .prepare(`INSERT INTO stocktakes (note, status, created_at, completed_at) VALUES (?, 'completed', ?, ?)`)
      .run(note || '', now, now)
    const stocktakeId = Number(header.lastInsertRowid)
    const insert = db.prepare(
      `INSERT INTO stocktake_items (stocktake_id, product_id, system_qty, counted_qty, diff_qty)
       VALUES (?, ?, ?, ?, ?)`,
    )
    for (const row of counts) {
      const product = getProduct(row.product_id)
      if (!product) continue
      const counted = Math.floor(row.counted_qty)
      insert.run(stocktakeId, product.id, product.stock, counted, counted - product.stock)
      if (counted !== product.stock) {
        db.prepare('UPDATE products SET stock = ?, updated_at = ? WHERE id = ?').run(counted, now, product.id)
        recordHistory(product.id, 'stock', product.stock, counted, 'stocktake')
      }
    }
    return stocktakeId
  })
  return run()
}

export function addManualExpense(amount: number, note: string, accountCode = 'cash'): void {
  const db = getDb()
  const now = new Date().toISOString()
  const account = db.prepare('SELECT id, balance FROM cash_accounts WHERE code = ?').get(accountCode) as
    | { id: number; balance: number }
    | undefined
  if (!account) throw new Error('حساب پیدا نشد')
  db.prepare('UPDATE cash_accounts SET balance = balance - ? WHERE id = ?').run(amount, account.id)
  db.prepare(
    `INSERT INTO cash_transactions (account_id, direction, amount, kind, invoice_id, customer_id, note, created_at)
     VALUES (?, 'out', ?, 'expense', NULL, NULL, ?, ?)`,
  ).run(account.id, amount, note, now)
}
