import jalaali from 'jalaali-js'
import { getDb, getAllSettings, setSetting } from '../database/db'
import type { CreateSaleInput, InvoiceListItem } from '../../shared/models'
import { adjustStock, getProduct } from './products'
import { tehranYmd } from '../../shared/period'

function jalaliYear(): number {
  const [gy, gm, gd] = tehranYmd().split('-').map(Number)
  return jalaali.toJalaali(gy, gm, gd).jy
}

function nextSaleNumber(): string {
  const n = Number(getAllSettings().sale_counter || '0') + 1
  setSetting('sale_counter', String(n))
  return `F-${jalaliYear()}-${String(n).padStart(5, '0')}`
}

export function listInvoices(limit = 50): InvoiceListItem[] {
  return getDb()
    .prepare(
      `SELECT i.id, i.number, i.type, i.total, i.payment_type, i.created_at,
              COALESCE(c.name, '—') AS customer_name
       FROM invoices i
       LEFT JOIN customers c ON c.id = i.customer_id
       ORDER BY i.created_at DESC
       LIMIT ?`,
    )
    .all(limit) as InvoiceListItem[]
}

export function createSale(input: CreateSaleInput, createdAt = new Date().toISOString()): InvoiceListItem {
  if (!input.items.length) throw new Error('حداقل یک کالا لازم است')
  const db = getDb()
  const run = db.transaction(() => {
    let subtotal = 0
    const lines = input.items.map((line) => {
      const product = getProduct(line.product_id)
      if (!product) throw new Error('کالا پیدا نشد')
      const qty = Math.floor(line.qty)
      if (qty <= 0) throw new Error('تعداد نامعتبر است')
      const unit = Math.max(0, Math.floor(line.unit_price))
      const lineTotal = qty * unit
      subtotal += lineTotal
      return { product, qty, unit, lineTotal }
    })
    let discountAmount = 0
    if (input.discount_type === 'percent') {
      discountAmount = Math.round((subtotal * Math.min(100, Math.max(0, input.discount_value))) / 100)
    } else if (input.discount_type === 'amount') {
      discountAmount = Math.min(subtotal, Math.max(0, input.discount_value))
    }
    const total = subtotal - discountAmount
    const number = nextSaleNumber()
    const accountCode =
      input.payment_type === 'card' ? 'bank' : input.payment_type === 'cash' ? 'cash' : null
    const inv = db
      .prepare(
        `INSERT INTO invoices (number, type, customer_id, discount_type, discount_value, subtotal,
           discount_amount, total, payment_type, account_code, note, created_at)
         VALUES (?, 'sale', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
      )
      .run(
        number,
        input.customer_id,
        input.discount_type,
        input.discount_value,
        subtotal,
        discountAmount,
        total,
        input.payment_type,
        accountCode,
        input.note || '',
        createdAt,
      )
    const invoiceId = Number(inv.lastInsertRowid)
    const insertItem = db.prepare(
      `INSERT INTO invoice_items (invoice_id, product_id, name_snapshot, sku_snapshot, qty, unit_price, line_total, buy_price_snapshot)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
    )
    for (const line of lines) {
      insertItem.run(
        invoiceId,
        line.product.id,
        line.product.name,
        line.product.sku,
        line.qty,
        line.unit,
        line.lineTotal,
        line.product.buy_price,
      )
      adjustStock(line.product.id, -line.qty, 'sale')
    }
    if (input.payment_type === 'credit') {
      if (!input.customer_id) throw new Error('برای نسیه باید مشتری انتخاب شود')
      db.prepare('UPDATE customers SET debt = debt + ?, updated_at = ? WHERE id = ?').run(
        total,
        createdAt,
        input.customer_id,
      )
      db.prepare(
        `INSERT INTO customer_ledger (customer_id, kind, amount, invoice_id, note, created_at)
         VALUES (?, 'invoice', ?, ?, '', ?)`,
      ).run(input.customer_id, total, invoiceId, createdAt)
    } else if (accountCode) {
      const account = db.prepare('SELECT id FROM cash_accounts WHERE code = ?').get(accountCode) as { id: number }
      db.prepare('UPDATE cash_accounts SET balance = balance + ? WHERE id = ?').run(total, account.id)
      db.prepare(
        `INSERT INTO cash_transactions (account_id, direction, amount, kind, invoice_id, customer_id, note, created_at)
         VALUES (?, 'in', ?, 'sale', ?, ?, ?, ?)`,
      ).run(account.id, total, invoiceId, input.customer_id, number, createdAt)
    }
    return {
      id: invoiceId,
      number,
      type: 'sale' as const,
      total,
      payment_type: input.payment_type,
      customer_name: '—',
      created_at: createdAt,
    }
  })
  return run()
}
