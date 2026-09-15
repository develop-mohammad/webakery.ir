/** نرمال‌سازی و تشخیص بارکد EAN/GTIN — اسکنر USB مثل صفحه‌کلید رقم می‌فرستد */

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩'

const BARCODE_META_KEYS = new Set([
  '_barcode',
  'barcode',
  '_alg_ean',
  '_wpm_gtin_code',
  '_gtin',
  'gtin',
  '_ean',
  'ean',
  '_hwp_gtin',
  '_ywbc_barcode_value',
  '_op_barcode',
  '_pos_barcode',
  '_global_unique_id',
  'global_unique_id',
  '_wc_gtin',
  '_product_barcode',
  'product_barcode',
])

export function toEnDigits(raw: string): string {
  return String(raw || '').replace(/[۰-۹٠-٩]/g, (ch) => {
    const fa = FA_DIGITS.indexOf(ch)
    if (fa >= 0) return String(fa)
    const ar = AR_DIGITS.indexOf(ch)
    return ar >= 0 ? String(ar) : ch
  })
}

/** ارقام لاتین، بدون فاصله و خط تیره */
export function normalizeBarcode(raw: string): string {
  let value = toEnDigits(String(raw || '')).trim()
  value = value.replace(/^\][A-Za-z0-9]{2}/, '')
  value = value.replace(/[\s\-*_]/g, '')
  value = value.replace(/[^0-9A-Za-z]/g, '')
  return value
}

/** اسکنر HID معمولاً ۸ تا ۱۴ رقم + Enter می‌فرستد */
export function looksLikeBarcode(raw: string): boolean {
  return /^\d{8,14}$/.test(normalizeBarcode(raw))
}

/**
 * شکل‌های رایج یک بارکد برای جستجو:
 * فاصلهٔ چاپ، صفر پیشوند GTIN-14، تبدیل ۱۳↔۱۴.
 */
export function barcodeCandidates(raw: string): string[] {
  const normalized = normalizeBarcode(raw)
  if (!normalized) return []
  const set = new Set<string>([normalized])
  if (!/^\d+$/.test(normalized)) return [...set]

  const stripped = normalized.replace(/^0+/, '')
  if (stripped) set.add(stripped)

  if (normalized.length === 14 && normalized.startsWith('0')) set.add(normalized.slice(1))
  if (normalized.length === 13) set.add(`0${normalized}`)
  if (normalized.length === 12) set.add(`0${normalized}`)
  if (normalized.length === 14 && !normalized.startsWith('0')) {
    set.add(normalized.slice(1))
    set.add(normalized.slice(0, 13))
  }
  return [...set]
}

export type WooBarcodeSource = {
  sku?: string
  global_unique_id?: string
  meta_data?: { key?: string; value?: unknown }[]
}

function metaString(value: unknown): string {
  if (value == null) return ''
  if (Array.isArray(value)) return metaString(value[0])
  if (typeof value === 'object') return ''
  return String(value)
}

function firstBarcodeValue(raw: string): string {
  const normalized = normalizeBarcode(raw)
  return looksLikeBarcode(normalized) ? normalized : ''
}

/** بارکد پلاگین POS / GTIN ووکامرس — نه شناسهٔ عددی کالا */
export function extractWooBarcode(product: WooBarcodeSource): string {
  const fromGuid = firstBarcodeValue(product.global_unique_id || '')
  if (fromGuid) return fromGuid

  for (const meta of product.meta_data || []) {
    const key = String(meta.key || '')
    const lower = key.toLowerCase()
    const known = BARCODE_META_KEYS.has(key) || BARCODE_META_KEYS.has(lower)
    const looks = /(barcode|ean|gtin)/i.test(key)
    if (!known && !looks) continue
    const found = firstBarcodeValue(metaString(meta.value))
    if (found) return found
  }

  return firstBarcodeValue(product.sku || '')
}
