import assert from 'node:assert/strict'
import http from 'node:http'
import {
  buildWcUrl,
  shouldFetchNextPage,
  skuForCatalogItem,
  skuForVariation,
  variationDisplayName,
} from '../shared/woo.ts'
import { customerFromBilling, customerKey } from '../shared/customers.ts'

function json(res, body, total, pages) {
  res.setHeader('Content-Type', 'application/json')
  res.setHeader('x-wp-total', String(total))
  res.setHeader('x-wp-totalpages', String(pages))
  res.end(JSON.stringify(body))
}

const products = [
  ...Array.from({ length: 100 }, (_, i) => ({
    id: i + 1,
    name: `کالا ${i + 1}`,
    sku: i === 0 ? '' : `SKU-${i + 1}`,
    type: 'simple',
    regular_price: '10000',
    price: '10000',
    manage_stock: true,
    stock_quantity: 3,
    categories: [{ id: 2, name: 'پوشاک' }],
  })),
  {
    id: 101,
    name: 'تیشرت',
    sku: 'TEE',
    type: 'variable',
    regular_price: '',
    price: '',
    manage_stock: false,
    stock_quantity: null,
    categories: [{ id: 2, name: 'پوشاک' }],
  },
]

const variations = [
  {
    id: 201,
    sku: '',
    regular_price: '120000',
    attributes: [{ option: 'قرمز' }],
  },
  {
    id: 202,
    sku: 'TEE-BLU',
    regular_price: '130000',
    attributes: [{ option: 'آبی' }],
  },
]

const server = http.createServer((req, res) => {
  const url = new URL(req.url || '/', 'http://127.0.0.1')
  const page = Number(url.searchParams.get('page') || '1')
  if (url.pathname.endsWith('/products/categories')) {
    return json(res, [{ id: 2, name: 'پوشاک' }], 1, 1)
  }
  if (url.pathname.endsWith('/products/101/variations')) {
    return json(res, variations, variations.length, 1)
  }
  if (url.pathname.endsWith('/products')) {
    const chunk = page === 1 ? products.slice(0, 100) : products.slice(100)
    return json(res, chunk, products.length, 2)
  }
  if (url.pathname.endsWith('/orders')) {
    const orders = [
      {
        id: 501,
        number: '501',
        status: 'completed',
        total: '150000',
        date_created_gmt: '2026-09-01T10:00:00',
        billing: { first_name: 'علی', last_name: 'رضایی', phone: '09120000001', email: 'ali@ex.com' },
        line_items: [{ quantity: 1 }],
      },
      {
        id: 502,
        number: '502',
        status: 'processing',
        total: '80000',
        date_created_gmt: '2026-09-08T12:00:00',
        billing: { first_name: 'سارا', last_name: '', phone: '+98 912 000 0002', email: '' },
        line_items: [{ quantity: 2 }],
      },
    ]
    return json(res, orders, orders.length, 1)
  }
  res.statusCode = 404
  res.end('no')
})

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve))
const port = server.address().port
const imported = new Map()

async function fetchAll(path) {
  const all = []
  let page = 1
  for (;;) {
    const endpoint = buildWcUrl(`http://127.0.0.1:${port}`, path, page, { status: 'any' })
    const res = await fetch(endpoint)
    const items = await res.json()
    all.push(...items)
    if (!shouldFetchNextPage(items.length, page)) break
    page += 1
  }
  return all
}

const cats = await fetchAll('products/categories')
const list = await fetchAll('products')
for (const item of list) {
  if (item.type === 'variable') {
    const vars = await fetchAll(`products/${item.id}/variations`)
    for (const v of vars) {
      imported.set(v.id, {
        name: variationDisplayName(item.name, v.attributes || []),
        sku: skuForVariation(v.sku, item.id, v.id),
      })
    }
    continue
  }
  imported.set(item.id, {
    name: item.name,
    sku: skuForCatalogItem(item.sku, item.id),
  })
}

assert.equal(cats.length, 1)
assert.equal(imported.size, 102)
assert.equal(imported.get(1).sku, 'WC-1')
assert.equal(imported.get(201).sku, 'WC-101-201')
assert.equal(imported.get(201).name, 'تیشرت — قرمز')
assert.equal(imported.get(202).sku, 'TEE-BLU')

const orders = await fetchAll('orders')
assert.equal(orders.length, 2)
const first = customerFromBilling(orders[0].billing)
const second = customerFromBilling(orders[1].billing)
assert.equal(first.phone, '09120000001')
assert.equal(second.phone, '09120000002')
assert.equal(customerKey(first.name, first.phone, first.email), 'tel:09120000001')
assert.notEqual(
  customerKey(first.name, first.phone, first.email),
  customerKey(second.name, second.phone, second.email),
)

server.close()
console.log('woo mock catalog pull ok', imported.size, 'orders', orders.length)
