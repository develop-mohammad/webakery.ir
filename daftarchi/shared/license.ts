/** لایسنس دوره‌ای دفترچی — قوانین خالص، بدون شبکه */

export const LICENSE_PRODUCT = 'daftarchi'
export const LICENSE_SERVER = 'https://webakery.ir/license-server'
export const TRIAL_DAYS = 7
export const EARLY_RENEWAL_DAYS = 3
export const WARN_DAYS = 14
export const WARN_SOON_DAYS = 7
export const WARN_URGENT_DAYS = 3

/** قیمت‌ها به تومان — همان ارقام license-server به ریال ÷ ۱۰ */
export const LICENSE_PLANS = [
  {
    id: '1m',
    months: 1,
    price_toman: 199_000,
    label: 'یک‌ماهه',
    hint: 'برای شروع یا تست فروشگاه',
    badge: '',
  },
  {
    id: '3m',
    months: 3,
    price_toman: 499_000,
    label: 'سه‌ماهه',
    hint: 'به‌صرفه‌تر از سه بار ماهانه',
    badge: 'پیشنهادی',
  },
  {
    id: '12m',
    months: 12,
    price_toman: 1_490_000,
    label: 'یک‌ساله',
    hint: 'کمترین هزینهٔ ماهانه',
    badge: '',
  },
] as const

export type LicensePlanId = (typeof LICENSE_PLANS)[number]['id']

export type LicenseWarning = 'none' | 'notice' | 'warn' | 'urgent'
export type LicenseKind = 'licensed' | 'trial' | 'locked'

export type LicenseQuote = {
  plan_id: string
  months: number
  label: string
  hint: string
  badge: string
  price_toman: number
  pay_toman: number
  discount_percent: number
  discount_toman: number
  early: boolean
}

export type LicenseSnapshot = {
  allowed: boolean
  kind: LicenseKind
  warning: LicenseWarning
  days_left: number
  expires_at: string
  email: string
  key_masked: string
  machine_id: string
  periods_paid: number
  early_eligible: boolean
  discount_percent: number
  plans: LicenseQuote[]
  pay_base: string
  message: string
}

export function loyaltyDiscountPercent(periodsPaid: number): number {
  const n = Math.max(0, Math.floor(periodsPaid))
  return Math.min(9, 5 + Math.max(0, n - 1))
}

export function isEarlyRenewal(daysLeft: number): boolean {
  return daysLeft >= EARLY_RENEWAL_DAYS
}

export function warningLevel(daysLeft: number, kind: LicenseKind): LicenseWarning {
  if (kind === 'locked') return 'urgent'
  if (daysLeft <= WARN_URGENT_DAYS) return 'urgent'
  if (daysLeft <= WARN_SOON_DAYS) return 'warn'
  if (daysLeft <= WARN_DAYS) return 'notice'
  return 'none'
}

export function daysBetween(fromMs: number, toMs: number): number {
  return Math.ceil((toMs - fromMs) / 86_400_000)
}

export function trialDaysLeft(installAtMs: number, nowMs = Date.now()): number {
  const end = installAtMs + TRIAL_DAYS * 86_400_000
  return Math.max(0, daysBetween(nowMs, end))
}

export function expiryDaysLeft(expiresAt: string, nowMs = Date.now()): number {
  const raw = (expiresAt || '').trim()
  if (!raw) return 0
  const end = Date.parse(/T/.test(raw) ? raw : `${raw}T23:59:59+03:30`)
  if (Number.isNaN(end)) return 0
  return Math.max(0, daysBetween(nowMs, end))
}

export function maskLicenseKey(key: string): string {
  const value = key.trim()
  if (value.length < 10) return value ? '••••' : ''
  return `${value.slice(0, 8)}••••`
}

export function quotePlan(
  plan: (typeof LICENSE_PLANS)[number],
  periodsPaid: number,
  daysLeft: number,
  hasPaidLicense: boolean,
): LicenseQuote {
  const early = hasPaidLicense && isEarlyRenewal(daysLeft)
  const percent = early ? loyaltyDiscountPercent(periodsPaid) : 0
  const discount = Math.round((plan.price_toman * percent) / 100)
  return {
    plan_id: plan.id,
    months: plan.months,
    label: plan.label,
    hint: plan.hint,
    badge: plan.badge,
    price_toman: plan.price_toman,
    pay_toman: plan.price_toman - discount,
    discount_percent: percent,
    discount_toman: discount,
    early,
  }
}

export function quoteAllPlans(periodsPaid: number, daysLeft: number, hasPaidLicense: boolean): LicenseQuote[] {
  return LICENSE_PLANS.map((plan) => quotePlan(plan, periodsPaid, daysLeft, hasPaidLicense))
}

export function licensePayUrl(opts: {
  planId: string
  machineId: string
  email?: string
  key?: string
}): string {
  const url = new URL(`${LICENSE_SERVER}/pay/`)
  url.searchParams.set('plugin', LICENSE_PRODUCT)
  url.searchParams.set('plan', opts.planId)
  url.searchParams.set('domain', opts.machineId)
  if (opts.email) url.searchParams.set('email', opts.email)
  if (opts.key) url.searchParams.set('key', opts.key)
  return url.toString()
}

export function buildSnapshot(input: {
  nowMs?: number
  installAtMs: number
  machineId: string
  key: string
  email: string
  expiresAt: string
  periodsPaid: number
  serverStatus: 'valid' | 'invalid' | 'unknown'
}): LicenseSnapshot {
  const now = input.nowMs ?? Date.now()
  const hasKey = Boolean(input.key.trim())
  const lifetime = hasKey && !input.expiresAt.trim() && input.serverStatus !== 'invalid'
  const paidDays = lifetime ? 3650 : hasKey ? expiryDaysLeft(input.expiresAt, now) : 0
  const trialLeft = trialDaysLeft(input.installAtMs, now)
  const licensed = hasKey && input.serverStatus !== 'invalid' && paidDays > 0
  const trial = !hasKey && trialLeft > 0
  const kind: LicenseKind = licensed ? 'licensed' : trial ? 'trial' : 'locked'
  const daysLeft = licensed ? paidDays : trial ? trialLeft : 0
  const hasPaidLicense = hasKey && input.periodsPaid > 0 && paidDays > 0
  const plans = quoteAllPlans(input.periodsPaid, daysLeft, hasPaidLicense)
  const discount = plans.find((p) => p.early)?.discount_percent ?? 0
  const warning = warningLevel(daysLeft, kind)
  const message = snapshotMessage(kind, daysLeft, discount)
  return {
    allowed: kind !== 'locked',
    kind,
    warning,
    days_left: daysLeft,
    expires_at: input.expiresAt,
    email: input.email,
    key_masked: maskLicenseKey(input.key),
    machine_id: input.machineId,
    periods_paid: input.periodsPaid,
    early_eligible: hasPaidLicense && isEarlyRenewal(daysLeft),
    discount_percent: discount,
    plans,
    pay_base: `${LICENSE_SERVER}/pay/`,
    message,
  }
}

export function snapshotMessage(kind: LicenseKind, daysLeft: number, discountPercent: number): string {
  if (kind === 'locked') {
    return 'دوره تمام شده. برای ادامهٔ کار باید یکی از دوره‌ها را شارژ کنی.'
  }
  if (kind === 'trial') {
    return `آزمایشی: ${daysLeft} روز مانده. بعد از آن بدون شارژ دوره، برنامه قفل می‌شود.`
  }
  if (daysLeft <= WARN_URGENT_DAYS) {
    return `فقط ${daysLeft} روز به پایان دوره مانده. اگر همین حالا تمدید کنی، تخفیف سابقه اعمال می‌شود.`
  }
  if (daysLeft <= WARN_DAYS && discountPercent > 0) {
    return `${daysLeft} روز تا پایان دوره. تمدید زودهنگام ${discountPercent}٪ تخفیف دارد.`
  }
  if (daysLeft <= WARN_DAYS) {
    return `${daysLeft} روز تا پایان دوره مانده.`
  }
  return `لایسنس فعال است — ${daysLeft} روز مانده.`
}
