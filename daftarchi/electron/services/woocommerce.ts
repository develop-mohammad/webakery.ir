import { getAllSettings, setSetting } from '../database/db'
import { getDb } from '../database/db'
import type { WcPullResult } from '../../shared/models'
import {
  buildWcUrl,
  normalizeSiteUrl,
  shouldFetchNextPage,
  skuForCatalogItem,
  skuForVariation,
  toToman,
  variationDisplayName,
} from '../../shared/woo'
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
  status?: string
  regular_price: string
  price: string
  stock_quantity: number | null
  manage_stock: boolean
  categories: { id: number; name: string }[]
  attributes?: { name?: string; option?: string }[]
}
type WcVariation = WcProduct & { parent_id?: number }

function wcConfig() {
  const s = getAllSettings()
  const url = normalizeSiteUrl(s.wc_url || '')
  const key = s.wc_key || ''
  const secret = s.wc_secret || ''
  const currency: 'toman' | 'rial' = s.wc_currency === 'rial' ? 'rial' : 'toman'
  if (!url || !key || !secret) throw new Error('آدرس سایت و کلید ووکامرس را در اتصال سایت وارد کنید')
  return { url, key, secret, currency }
}

async function wcFetch<T>(
  path: string,
  page = 1,
  extra: Record<string, string> = {},
): Promise<{ items: T[]; totalPages: number; total: number }> {
  const { url, key, secret } = wcConfig()
  const endpoint = buildWcUrl(url, path, page, extra)
  endpoint.searchParams.set('consumer_key', key)
  endpoint.searchParams.set('consumer_secret', secret)
  const auth = Buffer.from(`${key}:${secret}`).toString('base64')
  const res = await fetch(endpoint, {
    headers: {
      Authorization: `Basic ${auth}`,
      Accept: 'application/json',
      'User-Agent': 'Daftarchi/1.0 (webakery.ir)',
    },
  })
  if (!res.ok) {
    const body = await res.text()
    throw new Error(`ووکامرس ${res.status}: ${body.slice(0, 180) || res.statusText}`)
  }
  const items = (await res.json()) as T[]
  const totalPages = Number(res.headers.get('x-wp-totalpages') || '1')
  const total = Number(res.headers.get('x-wp-total') || items.length)
  return { items, totalPages, total }
}

async function wcFetchAll<T>(path: string, extra: Record<string, string> = {}): Promise<T[]> {
  const all: T[] = []
  let page = 1
  for (;;) {
    const { items } = await wcFetch<T>(path, page, extra)
    all.push(...items)
    if (!shouldFetchNextPage(items.length, page)) break
    page += 1
  }
  return all
}

function categoryIdOf(product: WcProduct): number | null {
  const wcCat = product.categories?.[0]
  if (!wcCat) return null
  const localCat = findCategoryByWc(wcCat.id) ?? createCategory(wcCat.name, wcCat.id)
  return localCat.id
}

function allocateSku(preferred: string, wcId: number): string {
  let sku = preferred
  let n = 2
  for (;;) {
    const found = findBySku(sku)
    if (!found || found.wc_product_id == null || found.wc_product_id === wcId) return sku
    sku = `${preferred}-${n}`
    n += 1
    if (n > 80) return `WC-${wcId}`
  }
}

function upsertFromSite(
  wcId: number,
  name: string,
  preferredSku: string,
  price: number,
  stock: number,
  categoryId: number | null,
): 'created' | 'updated' {
  const sku = allocateSku(preferredSku, wcId)
  const existing = findByWcId(wcId) ?? findBySku(sku)
  if (existing) {
    setProductWcId(existing.id, wcId)
    const boundBefore = Boolean(existing.wc_product_id)
    updateProduct(
      {
        id: existing.id,
        name,
        sku,
        category_id: categoryId,
        stock: boundBefore ? existing.stock : stock,
        sell_price: boundBefore ? existing.sell_price : price,
      },
      'wc_pull',
    )
    return 'updated'
  }
  const product = createProduct(
    {
      name,
      sku,
      buy_price: 0,
      sell_price: price,
      stock,
      category_id: categoryId,
      stock_alert: 0,
    },
    'wc_pull',
  )
  setProductWcId(product.id, wcId)
  return 'created'
}

function importSimple(item: WcProduct, currency: 'toman' | 'rial'): 'created' | 'updated' {
  const price = toToman(item.regular_price || item.price || '0', currency)
  const stock = item.manage_stock ? Number(item.stock_quantity ?? 0) : 0
  return upsertFromSite(
    item.id,
    item.name,
    skuForCatalogItem(item.sku, item.id),
    price,
    stock,
    categoryIdOf(item),
  )
}

async function importVariable(parent: WcProduct, currency: 'toman' | 'rial'): Promise<{
  created: number
  updated: number
}> {
  const variations = await wcFetchAll<WcVariation>(`products/${parent.id}/variations`, {
    status: 'any',
  })
  let created = 0
  let updated = 0
  const categoryId = categoryIdOf(parent)
  for (const item of variations) {
    const price = toToman(item.regular_price || item.price || '0', currency)
    const stock = item.manage_stock ? Number(item.stock_quantity ?? 0) : 0
    const name = variationDisplayName(parent.name, item.attributes || [])
    const result = upsertFromSite(
      item.id,
      name,
      skuForVariation(item.sku, parent.id, item.id),
      price,
      stock,
      categoryId,
    )
    if (result === 'created') created += 1
    else updated += 1
  }
  return { created, updated }
}

export async function testWooConnection(): Promise<string> {
  const { items, total } = await wcFetch<WcProduct>('products', 1, {
    status: 'any',
    per_page: '1',
  })
  const count = total || items.length
  return `اتصال برقرار است — ${count} کالا روی سایت`
}

export async function pullWooProducts(): Promise<WcPullResult> {
  const { currency } = wcConfig()
  let created = 0
  let updated = 0
  let skipped = 0

  const categories = await wcFetchAll<WcCategory>('products/categories', { hide_empty: 'false' })
  for (const cat of categories) {
    createCategory(cat.name, cat.id)
  }

  const products = await wcFetchAll<WcProduct>('products', { status: 'any' })
  for (const item of products) {
    if (item.type === 'variable') {
      const result = await importVariable(item, currency)
      created += result.created
      updated += result.updated
      if (result.created + result.updated === 0) skipped += 1
      continue
    }
    const result = importSimple(item, currency)
    if (result === 'created') created += 1
    else updated += 1
  }

  const message = `${created} کالای جدید، ${updated} به‌روز، ${skipped} رد شد`
  setSetting('wc_last_pull_at', new Date().toISOString())
  getDb()
    .prepare(
      `INSERT INTO sync_log (direction, action, product_id, message, created_at)
       VALUES ('pull', 'products', NULL, ?, ?)`,
    )
    .run(`دسته ${categories.length} — ${message}`, new Date().toISOString())

  return {
    categories: categories.length,
    created,
    updated,
    skipped,
    message,
  }
}
