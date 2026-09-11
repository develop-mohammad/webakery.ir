import { getDb } from '../database/db'
import type { Category, NewProduct, Product, ProductPatch } from '../../shared/models'

const PRODUCT_SELECT = `
  SELECT p.id, p.name, p.sku, p.buy_price, p.sell_price, p.stock,
         p.category_id, COALESCE(c.name, 'بدون دسته') AS category_name,
         p.stock_alert, p.wc_product_id, COALESCE(p.site_url, '') AS site_url,
         p.created_at, p.updated_at
  FROM products p
  LEFT JOIN categories c ON c.id = p.category_id
`

function mapProduct(row: Omit<Product, 'low_stock'>): Product {
  return { ...row, site_url: row.site_url || '', low_stock: row.stock_alert > 0 && row.stock <= row.stock_alert }
}

export function listCategories(): Category[] {
  return getDb()
    .prepare(
      `SELECT c.id, c.name, c.wc_category_id,
              (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
       FROM categories c
       ORDER BY c.name COLLATE NOCASE`,
    )
    .all() as Category[]
}

export function listProducts(filter?: { categoryId?: number | null; search?: string }): Product[] {
  const where: string[] = []
  const params: unknown[] = []
  if (filter?.categoryId) {
    where.push('p.category_id = ?')
    params.push(filter.categoryId)
  }
  if (filter?.search?.trim()) {
    where.push('(p.name LIKE ? OR p.sku LIKE ?)')
    const q = `%${filter.search.trim()}%`
    params.push(q, q)
  }
  const sql = `${PRODUCT_SELECT} ${where.length ? `WHERE ${where.join(' AND ')}` : ''} ORDER BY category_name, p.name`
  const rows = getDb().prepare(sql).all(...params) as Omit<Product, 'low_stock'>[]
  return rows.map(mapProduct)
}

export function searchProducts(query: string, limit = 12): Product[] {
  const q = `%${query.trim()}%`
  const rows = getDb()
    .prepare(`${PRODUCT_SELECT} WHERE p.name LIKE ? OR p.sku LIKE ? ORDER BY p.name LIMIT ?`)
    .all(q, q, limit) as Omit<Product, 'low_stock'>[]
  return rows.map(mapProduct)
}

export function getProduct(id: number): Product | undefined {
  const row = getDb().prepare(`${PRODUCT_SELECT} WHERE p.id = ?`).get(id) as Omit<Product, 'low_stock'> | undefined
  return row ? mapProduct(row) : undefined
}

export function createCategory(name: string, wcCategoryId: number | null = null): Category {
  const now = new Date().toISOString()
  const trimmed = name.trim()
  if (!trimmed) throw new Error('نام دسته خالی است')
  const existing = getDb()
    .prepare('SELECT id FROM categories WHERE name = ? COLLATE NOCASE')
    .get(trimmed) as { id: number } | undefined
  if (existing) {
    if (wcCategoryId) {
      getDb().prepare('UPDATE categories SET wc_category_id = ?, updated_at = ? WHERE id = ?').run(wcCategoryId, now, existing.id)
    }
    return listCategories().find((c) => c.id === existing.id)!
  }
  const result = getDb()
    .prepare('INSERT INTO categories (name, wc_category_id, created_at, updated_at) VALUES (?, ?, ?, ?)')
    .run(trimmed, wcCategoryId, now, now)
  return listCategories().find((c) => c.id === Number(result.lastInsertRowid))!
}

export function findCategoryByWc(wcId: number): Category | undefined {
  return listCategories().find((c) => c.wc_category_id === wcId)
}

export function createProduct(input: NewProduct, source = 'form'): Product {
  const now = new Date().toISOString()
  const sku = input.sku.trim()
  if (!input.name.trim()) throw new Error('نام کالا لازم است')
  if (!sku) throw new Error('کد کالا (SKU) لازم است')
  const result = getDb()
    .prepare(
      `INSERT INTO products (name, sku, buy_price, sell_price, stock, category_id, stock_alert, created_at, updated_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    )
    .run(
      input.name.trim(),
      sku,
      input.buy_price,
      input.sell_price,
      input.stock,
      input.category_id,
      input.stock_alert,
      now,
      now,
    )
  const id = Number(result.lastInsertRowid)
  recordHistory(id, 'buy_price', 0, input.buy_price, source)
  recordHistory(id, 'sell_price', 0, input.sell_price, source)
  recordHistory(id, 'stock', 0, input.stock, source)
  return getProduct(id)!
}

export function updateProduct(patch: ProductPatch, source = 'inline'): Product {
  const current = getProduct(patch.id)
  if (!current) throw new Error('کالا پیدا نشد')
  const now = new Date().toISOString()
  const next = {
    name: patch.name?.trim() ?? current.name,
    sku: patch.sku?.trim() ?? current.sku,
    buy_price: patch.buy_price ?? current.buy_price,
    sell_price: patch.sell_price ?? current.sell_price,
    stock: patch.stock ?? current.stock,
    category_id: patch.category_id === undefined ? current.category_id : patch.category_id,
    stock_alert: patch.stock_alert ?? current.stock_alert,
  }
  if (!next.name) throw new Error('نام کالا لازم است')
  if (!next.sku) throw new Error('کد کالا لازم است')
  getDb()
    .prepare(
      `UPDATE products SET name=?, sku=?, buy_price=?, sell_price=?, stock=?, category_id=?, stock_alert=?, updated_at=?
       WHERE id=?`,
    )
    .run(
      next.name,
      next.sku,
      next.buy_price,
      next.sell_price,
      next.stock,
      next.category_id,
      next.stock_alert,
      now,
      patch.id,
    )
  if (next.buy_price !== current.buy_price) recordHistory(patch.id, 'buy_price', current.buy_price, next.buy_price, source)
  if (next.sell_price !== current.sell_price) recordHistory(patch.id, 'sell_price', current.sell_price, next.sell_price, source)
  if (next.stock !== current.stock) recordHistory(patch.id, 'stock', current.stock, next.stock, source)
  return getProduct(patch.id)!
}

export function deleteProduct(id: number): void {
  const used = getDb().prepare('SELECT 1 FROM invoice_items WHERE product_id = ? LIMIT 1').get(id)
  if (used) throw new Error('این کالا در فاکتور استفاده شده و حذف نمی‌شود')
  getDb().prepare('DELETE FROM products WHERE id = ?').run(id)
}

export function findBySku(sku: string): Product | undefined {
  const row = getDb()
    .prepare(`${PRODUCT_SELECT} WHERE p.sku = ? COLLATE NOCASE`)
    .get(sku) as Omit<Product, 'low_stock'> | undefined
  return row ? mapProduct(row) : undefined
}

export function findByWcId(wcId: number): Product | undefined {
  const row = getDb()
    .prepare(`${PRODUCT_SELECT} WHERE p.wc_product_id = ?`)
    .get(wcId) as Omit<Product, 'low_stock'> | undefined
  return row ? mapProduct(row) : undefined
}

export function setProductWcId(id: number, wcId: number): void {
  getDb().prepare('UPDATE products SET wc_product_id = ?, updated_at = ? WHERE id = ?').run(wcId, new Date().toISOString(), id)
}

export function setProductSiteUrl(id: number, url: string): void {
  getDb()
    .prepare('UPDATE products SET site_url = ?, updated_at = ? WHERE id = ?')
    .run(url.trim(), new Date().toISOString(), id)
}

export function recordHistory(
  productId: number,
  field: string,
  oldValue: number,
  newValue: number,
  source: string,
): void {
  getDb()
    .prepare(
      `INSERT INTO price_history (product_id, field, old_value, new_value, source, created_at)
       VALUES (?, ?, ?, ?, ?, ?)`,
    )
    .run(productId, field, oldValue, newValue, source, new Date().toISOString())
}

export function adjustStock(productId: number, delta: number, source: string): void {
  const product = getProduct(productId)
  if (!product) throw new Error('کالا پیدا نشد')
  const next = product.stock + delta
  if (next < 0) throw new Error(`موجودی «${product.name}» کافی نیست`)
  getDb()
    .prepare('UPDATE products SET stock = ?, updated_at = ? WHERE id = ?')
    .run(next, new Date().toISOString(), productId)
  recordHistory(productId, 'stock', product.stock, next, source)
}
