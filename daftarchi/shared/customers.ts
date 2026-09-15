/** آمار خریداران سایت — از سفارش‌های ووکامرس، بدون بدهی صندوق */

import type { CustomerStats, SiteCustomer } from './models'

const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹'
const AR_DIGITS = '٠١٢٣٤٥٦٧٨٩'

export function toEnDigits(raw: string): string {
  return raw.replace(/[۰-۹٠-٩]/g, (ch) => {
    const fa = FA_DIGITS.indexOf(ch)
    if (fa >= 0) return String(fa)
    const ar = AR_DIGITS.indexOf(ch)
    return ar >= 0 ? String(ar) : ch
  })
}

/** موبایل ایرانی → 09xxxxxxxxx ؛ نامعتبر یعنی رشته خالی */
export function normalizeIranPhone(raw: string): string {
  let s = toEnDigits(raw || '').replace(/[\s\-().]/g, '')
  if (!s) return ''
  if (s.startsWith('+98')) s = `0${s.slice(3)}`
  else if (s.startsWith('0098')) s = `0${s.slice(4)}`
  else if (s.startsWith('98') && s.length >= 12) s = `0${s.slice(2)}`
  if (/^9\d{9}$/.test(s)) s = `0${s}`
  return /^09\d{9}$/.test(s) ? s : ''
}

export function customerKey(name: string, phone: string, email: string): string {
  const p = normalizeIranPhone(phone)
  if (p) return `tel:${p}`
  const e = (email || '').trim().toLowerCase()
  if (e) return `mail:${e}`
  const n = (name || '').trim().replace(/\s+/g, ' ')
  if (n) return `name:${n}`
  return 'guest'
}

export function customerFromBilling(billing?: {
  first_name?: string
  last_name?: string
  phone?: string
  email?: string
}): { name: string; phone: string; email: string } {
  const name = `${billing?.first_name || ''} ${billing?.last_name || ''}`.trim()
  const rawPhone = (billing?.phone || '').trim()
  return {
    name,
    phone: normalizeIranPhone(rawPhone) || rawPhone,
    email: (billing?.email || '').trim().toLowerCase(),
  }
}

export type SiteOrderRow = {
  name: string
  phone: string
  email: string
  total: number
  created_at: string
}

export function aggregateSiteCustomers(
  orders: SiteOrderRow[],
  monthStartIso: string,
  monthEndExclusiveIso: string,
): CustomerStats {
  const map = new Map<string, SiteCustomer>()
  let monthSpent = 0
  const monthKeys = new Set<string>()
  let orderSum = 0

  for (const order of orders) {
    const key = customerKey(order.name, order.phone, order.email)
    const phone = normalizeIranPhone(order.phone) || (order.phone || '').trim()
    const email = (order.email || '').trim().toLowerCase()
    const name = (order.name || '').trim() || 'بدون نام'
    const existing = map.get(key)
    if (!existing) {
      map.set(key, {
        name,
        phone,
        email,
        orders_count: 1,
        total_spent: order.total,
        last_order_at: order.created_at,
      })
    } else {
      existing.orders_count += 1
      existing.total_spent += order.total
      if (order.created_at > existing.last_order_at) existing.last_order_at = order.created_at
      if (name !== 'بدون نام' && (existing.name === 'بدون نام' || name.length > existing.name.length)) {
        existing.name = name
      }
      if (phone && !existing.phone) existing.phone = phone
      if (email && !existing.email) existing.email = email
    }
    orderSum += order.total
    if (order.created_at >= monthStartIso && order.created_at < monthEndExclusiveIso) {
      monthSpent += order.total
      monthKeys.add(key)
    }
  }

  const list = [...map.values()].sort((a, b) => b.total_spent - a.total_spent)
  const orderCount = orders.length
  return {
    total_customers: list.length,
    month_buyers: monthKeys.size,
    month_spent: monthSpent,
    avg_order: orderCount ? Math.round(orderSum / orderCount) : 0,
    list,
  }
}
