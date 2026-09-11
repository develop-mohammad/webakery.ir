import assert from 'node:assert/strict'
import {
  buildWcUrl,
  isHttpUrl,
  isWcPaidOrder,
  normalizeSiteUrl,
  shouldFetchNextPage,
  skuForCatalogItem,
  skuForVariation,
  toToman,
  variationDisplayName,
  wcGmtToIso,
} from '../shared/woo.ts'

assert.equal(normalizeSiteUrl('https://shop.com/wp-admin/'), 'https://shop.com')
assert.equal(normalizeSiteUrl('https://shop.com/wp-json/wc/v3'), 'https://shop.com')
assert.equal(skuForCatalogItem('', 42), 'WC-42')
assert.equal(skuForCatalogItem('  ABC  ', 1), 'ABC')
assert.equal(skuForVariation('', 9, 88), 'WC-9-88')
assert.equal(toToman('250000', 'rial'), 25000)
assert.equal(toToman('12,000', 'toman'), 12000)
assert.equal(variationDisplayName('تیشرت', [{ option: 'قرمز' }, { option: 'XL' }]), 'تیشرت — قرمز / XL')
assert.equal(shouldFetchNextPage(100, 1), true)
assert.equal(shouldFetchNextPage(40, 2), false)
assert.equal(shouldFetchNextPage(0, 1), false)

const url = buildWcUrl('https://shop.com/', 'products', 3, { status: 'any' })
assert.equal(url.pathname, '/wp-json/wc/v3/products')
assert.equal(url.searchParams.get('page'), '3')
assert.equal(url.searchParams.get('per_page'), '100')
assert.equal(url.searchParams.get('status'), 'any')
assert.equal(url.searchParams.get('orderby'), 'id')

assert.equal(isHttpUrl('https://shop.com/product/tea'), true)
assert.equal(isHttpUrl('javascript:alert(1)'), false)
assert.equal(isWcPaidOrder('completed'), true)
assert.equal(isWcPaidOrder('cancelled'), false)
assert.ok(wcGmtToIso('2026-01-02T10:00:00').startsWith('2026-01-02'))

console.log('woo helpers ok')
