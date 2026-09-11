/** توابع خالص اتصال ووکامرس — بدون دیتابیس */

export function normalizeSiteUrl(raw: string): string {
  return raw
    .trim()
    .replace(/\/+$/, '')
    .replace(/\/wp-admin.*$/i, '')
    .replace(/\/wp-json.*$/i, '')
}

export function toToman(raw: string, currency: 'toman' | 'rial'): number {
  const n = Number(String(raw).replace(/,/g, ''))
  if (!Number.isFinite(n) || n < 0) return 0
  return currency === 'rial' ? Math.round(n / 10) : Math.round(n)
}

export function skuForCatalogItem(sku: string | undefined, wcId: number): string {
  const trimmed = (sku || '').trim()
  return trimmed || `WC-${wcId}`
}

export function skuForVariation(sku: string | undefined, parentId: number, variationId: number): string {
  const trimmed = (sku || '').trim()
  return trimmed || `WC-${parentId}-${variationId}`
}

export function variationDisplayName(
  parentName: string,
  attributes: { name?: string; option?: string }[],
): string {
  const parts = attributes.map((item) => (item.option || '').trim()).filter(Boolean)
  return parts.length ? `${parentName} — ${parts.join(' / ')}` : parentName
}

export function shouldFetchNextPage(itemCount: number, page: number, maxPages = 500): boolean {
  if (itemCount === 0) return false
  if (itemCount < 100) return false
  return page < maxPages
}

export function isHttpUrl(raw: string): boolean {
  try {
    const parsed = new URL(raw.trim())
    return parsed.protocol === 'http:' || parsed.protocol === 'https:'
  } catch {
    return false
  }
}

export const WC_SALE_STATUSES = ['processing', 'completed', 'on-hold'] as const

export function isWcPaidOrder(status: string): boolean {
  return (WC_SALE_STATUSES as readonly string[]).includes(status)
}

export function wcGmtToIso(raw?: string): string {
  const value = (raw || '').trim()
  if (!value) return new Date().toISOString()
  const withZ = /Z$/i.test(value) ? value : `${value}Z`
  const date = new Date(withZ)
  return Number.isNaN(date.getTime()) ? new Date().toISOString() : date.toISOString()
}

export function buildWcUrl(
  siteUrl: string,
  path: string,
  page: number,
  extra: Record<string, string> = {},
): URL {
  const endpoint = new URL(`${normalizeSiteUrl(siteUrl)}/wp-json/wc/v3/${path}`)
  endpoint.searchParams.set('per_page', extra.per_page || '100')
  endpoint.searchParams.set('page', String(page))
  endpoint.searchParams.set('orderby', extra.orderby || 'id')
  endpoint.searchParams.set('order', extra.order || 'asc')
  for (const [key, value] of Object.entries(extra)) {
    if (key === 'per_page' || key === 'orderby' || key === 'order') continue
    endpoint.searchParams.set(key, value)
  }
  return endpoint
}
