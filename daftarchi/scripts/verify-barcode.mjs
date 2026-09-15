import assert from 'node:assert/strict'
import {
  barcodeCandidates,
  extractWooBarcode,
  looksLikeBarcode,
  normalizeBarcode,
} from '../shared/barcode.ts'

/** نمونه‌های جدول پلاگین POS (بارکد ≠ شناسه ووکامرس) */
const POS_SAMPLES = [
  { wcId: 193681, barcode: '6265527300024' },
  { wcId: 193678, barcode: '6265527300123' },
  { wcId: 193670, barcode: '6265527300468' },
  { wcId: 193665, barcode: '6265527301533' },
  { wcId: 193657, barcode: '6265527300277' },
]

/** چاپ روی جعبه: 6 2611936 102075 */
const PACK_PRINTED = '6 2611936 102075'
const PACK_DIGITS = '62611936102075'

assert.equal(normalizeBarcode('۶۲۶۵۵۲۷۳۰۰۰۲۴'), '6265527300024')
assert.equal(normalizeBarcode(PACK_PRINTED), PACK_DIGITS)
assert.equal(normalizeBarcode(']E06265527300024'), '6265527300024')
assert.equal(looksLikeBarcode('193681'), false)
assert.equal(looksLikeBarcode(POS_SAMPLES[0].barcode), true)
assert.equal(looksLikeBarcode(PACK_PRINTED), true)
assert.equal(looksLikeBarcode('شامپو'), false)

for (const sample of POS_SAMPLES) {
  assert.ok(barcodeCandidates(sample.barcode).includes(sample.barcode))
  assert.equal(
    extractWooBarcode({
      sku: `WC-${sample.wcId}`,
      meta_data: [{ key: '_barcode', value: sample.barcode }],
    }),
    sample.barcode,
  )
  assert.notEqual(String(sample.wcId), sample.barcode)
}

assert.equal(
  extractWooBarcode({
    sku: '',
    global_unique_id: '6265527300024',
    meta_data: [],
  }),
  '6265527300024',
)

assert.equal(
  extractWooBarcode({
    sku: 'WC-193681',
    meta_data: [{ key: '_pos_barcode', value: '6265527300024' }],
  }),
  '6265527300024',
)

assert.equal(
  extractWooBarcode({
    sku: '6265527300123',
    meta_data: [],
  }),
  '6265527300123',
)

assert.equal(
  extractWooBarcode({
    sku: 'WC-193681',
    meta_data: [],
  }),
  '',
)

const packCands = barcodeCandidates(PACK_PRINTED)
assert.ok(packCands.includes(PACK_DIGITS))
assert.ok(packCands.includes('6261193610207'))
assert.ok(barcodeCandidates('06265527300024').includes('6265527300024'))

console.log('barcode helpers ok', POS_SAMPLES.length, 'pos samples + pack', PACK_DIGITS)
