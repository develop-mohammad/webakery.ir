import { getAllSettings, setSetting } from '../database/db'
import { getDb } from '../database/db'
import type { WcPullResult } from '../../shared/models'
import {
  createCategory,
  createProduct,
  findBySku,
  findByWcId,
  findCategoryByWc,
  setProductWcId,
  updateProduct,
} from './products'

type WcCategory = { id: number; name: string }
type WcProduct = {
  id: number
  name: string
  sku: string
  type: string
  regular_price: string
  price: string
  stock_quantity: number | null
  manage_stock: boolean
  categories: { id: number; name: string }[]
}

function wcConfig() {
  const s = getAllSettings()
  const url = (s.wc_url || '').replace(/\/+$/, '')
  const key = s.wc_key || ''
  const secret = s.wc_secret || ''
  const currency: 'toman' | 'rial' = s.wc_currency === 'rial' ? 'rial' : 'toman'
  if (!url || !key || !secret) throw new Error('آدرس سایت و کلید ووکامرس را در اتصال سایت وارد کنید')
  return { url, key, secret, currency }
}

function toToman(raw: string, currency: 'toman' | 'rial'): number {
  const n = Number(String(raw).replace(/,/g, ''))
  if (!Number.isFinite(n) || n < 0) return 0
  const toman = currency === 'rial' ? Math.round(n / 10) : Math.round(n)
  return toman
}

async function wcFetch<T>(path: string, page = 1): Promise<{ items: T[]; totalPages: number }> {
  const { url, key, secret } = wcConfig()
  const endpoint = new URL(`${url}/wp-json/wc/v3/${path}`)
  endpoint.searchParams.set('per_page', '100')
  endpoint.searchParams.set('page', String(page))
  const auth = Buffer.from(`${key}:${secret}`).toString('base64')
  const res = await fetch(endpoint, {
    headers: { Authorization: `Basic ${auth}`, Accept: 'application/json' },
  })
  if (!res.ok) {
    const body = await res.text()
    throw new Error(`ووکامرس ${res.status}: ${body.slice(0, 180) || res.statusText}`)
  }
  const items = (await res.json()) as T[]
  const totalPages = Number(res.headers.get('x-wp-totalpages') || '1')
  return { items, totalPages }
}

export async function testWooConnection(): Promise<string> {
  const { items } = await wcFetch<WcProduct>('products', 1)
  return `${items.length} کالا از سایت خوانده شد`
}

export async function pullWooProducts(): Promise<WcPullResult> {
  const { currency } = wcConfig()
  let catCount = 0
  let created = 0
  let updated = 0
  let skipped = 0

  let catPage = 1
  let catPages = 1
  while (catPage <= catPages) {
    const { items, totalPages } = await wcFetch<WcCategory>('products/categories', catPage)
    catPages = totalPages
    for (const cat of items) {
      createCategory(cat.name, cat.id)
      catCount += 1
    }
    catPage += 1
  }

  let page = 1
  let pages = 1
  while (page <= pages) {
    const { items, totalPages } = await wcFetch<WcProduct>('products', page)
    pages = totalPages
    for (const item of items) {
      if (item.type && item.type !== 'simple') {
        skipped += 1
        continue
      }
      const sku = (item.sku || '').trim()
      if (!sku) {
        skipped += 1
        continue
      }
      const price = toToman(item.regular_price || item.price || '0', currency)
      const stock = item.manage_stock ? Number(item.stock_quantity ?? 0) : 0
      const wcCat = item.categories?.[0]
      let categoryId: number | null = null
      if (wcCat) {
        const localCat = findCategoryByWc(wcCat.id) ?? createCategory(wcCat.name, wcCat.id)
        categoryId = localCat.id
      }
      const existing = findByWcId(item.id) ?? findBySku(sku)
      if (existing) {
        setProductWcId(existing.id, item.id)
        updateProduct(
          {
            id: existing.id,
            name: item.name,
            sku,
            category_id: categoryId,
            stock: existing.wc_product_id ? existing.stock : stock,
            sell_price: existing.wc_product_id ? existing.sell_price : price,
          },
          'wc_pull',
        )
        updated += 1
      } else {
        const product = createProduct(
          {
            name: item.name,
            sku,
            buy_price: 0,
            sell_price: price,
            stock,
            category_id: categoryId,
            stock_alert: 0,
          },
          'wc_pull',
        )
        setProductWcId(product.id, item.id)
        created += 1
      }
    }
    page += 1
  }

  setSetting('wc_last_pull_at', new Date().toISOString())
  getDb()
    .prepare(
      `INSERT INTO sync_log (direction, action, product_id, message, created_at)
       VALUES ('pull', 'products', NULL, ?, ?)`,
    )
    .run(
      `دسته ${catCount} — جدید ${created} — به‌روز ${updated} — ردشده ${skipped}`,
      new Date().toISOString(),
    )

  return {
    categories: catCount,
    created,
    updated,
    skipped,
    message: `${created} کالای جدید، ${updated} به‌روز، ${skipped} رد شد`,
  }
}
