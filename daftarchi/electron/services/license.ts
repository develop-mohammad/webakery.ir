import crypto from 'node:crypto'
import { getAllSettings, setSetting } from '../database/db'
import {
  LICENSE_PRODUCT,
  LICENSE_SERVER,
  buildSnapshot,
  licensePayUrl,
  type LicenseSnapshot,
} from '../../shared/license'

const PRODUCT = LICENSE_PRODUCT

function settings() {
  return getAllSettings()
}

export function ensureLicenseDefaults(): void {
  const s = settings()
  if (!s.license_install_at) setSetting('license_install_at', String(Date.now()))
  if (!s.license_machine_id) {
    const id = `darchi-${crypto.randomBytes(6).toString('hex')}.pc`
    setSetting('license_machine_id', id)
  }
  if (!s.license_status) setSetting('license_status', 'unknown')
  if (!s.license_periods_paid) setSetting('license_periods_paid', '0')
}

export function licenseSnapshot(): LicenseSnapshot {
  ensureLicenseDefaults()
  const s = settings()
  return buildSnapshot({
    installAtMs: Number(s.license_install_at || Date.now()),
    machineId: s.license_machine_id || '',
    key: s.license_key || '',
    email: s.license_email || '',
    expiresAt: s.license_expires_at || '',
    periodsPaid: Number(s.license_periods_paid || '0'),
    serverStatus: (s.license_status as 'valid' | 'invalid' | 'unknown') || 'unknown',
  })
}

export function assertLicenseActive(): void {
  if (!licenseSnapshot().allowed) {
    throw new Error('برای ادامه، دوره لایسنس را شارژ کنید')
  }
}

export function payUrl(planId: string): string {
  const s = settings()
  return licensePayUrl({
    planId,
    machineId: s.license_machine_id || '',
    email: s.license_email || undefined,
    key: s.license_key || undefined,
  })
}

async function apiCall(action: string, body: Record<string, string>): Promise<Record<string, unknown>> {
  const endpoint = new URL(`${LICENSE_SERVER}/api/`)
  endpoint.searchParams.set('action', action)
  const res = await fetch(endpoint, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'User-Agent': 'Daftarchi/1.0 (webakery.ir)',
    },
    body: JSON.stringify(body),
  })
  const text = await res.text()
  try {
    return JSON.parse(text) as Record<string, unknown>
  } catch {
    throw new Error('پاسخ سرور لایسنس خوانده نشد')
  }
}

function applyValid(key: string, payload: Record<string, unknown>): void {
  setSetting('license_key', key)
  setSetting('license_status', 'valid')
  setSetting('license_last_check', new Date().toISOString())
  setSetting('license_email', String(payload.email || settings().license_email || ''))
  setSetting('license_expires_at', String(payload.expires_at || ''))
  if (payload.periods_paid != null) {
    setSetting('license_periods_paid', String(Number(payload.periods_paid) || 0))
  }
}

export async function activateLicense(key: string): Promise<{ ok: boolean; message: string; snapshot: LicenseSnapshot }> {
  ensureLicenseDefaults()
  const trimmed = key.trim()
  if (!trimmed) return { ok: false, message: 'کلید لایسنس را وارد کن.', snapshot: licenseSnapshot() }
  const s = settings()
  const domain = s.license_machine_id
  try {
    await apiCall('activate', {
      license_key: trimmed,
      domain,
      product: PRODUCT,
    })
    const val = await apiCall('validate', {
      license_key: trimmed,
      domain,
      product: PRODUCT,
    })
    if (val.valid || val.success) {
      applyValid(trimmed, val)
      return { ok: true, message: 'لایسنس فعال شد.', snapshot: licenseSnapshot() }
    }
    const msg = String(val.message || errorFa(String(val.error || '')))
    return { ok: false, message: msg, snapshot: licenseSnapshot() }
  } catch (err) {
    return {
      ok: false,
      message: err instanceof Error ? err.message : 'سرور لایسنس در دسترس نیست',
      snapshot: licenseSnapshot(),
    }
  }
}

export async function refreshLicense(): Promise<LicenseSnapshot> {
  ensureLicenseDefaults()
  const s = settings()
  const key = (s.license_key || '').trim()
  if (!key) return licenseSnapshot()
  try {
    const val = await apiCall('validate', {
      license_key: key,
      domain: s.license_machine_id,
      product: PRODUCT,
    })
    if (val.valid || val.success) {
      applyValid(key, val)
    } else if (!val._neterr) {
      setSetting('license_status', 'invalid')
      setSetting('license_last_check', new Date().toISOString())
    }
  } catch {
    // آفلاین: قفل نکن اگر هنوز تاریخ محلی معتبر است
  }
  return licenseSnapshot()
}

function errorFa(code: string): string {
  if (code === 'license_expired') return 'اشتراک این کلید تمام شده.'
  if (code === 'license_revoked') return 'این لایسنس غیرفعال است.'
  if (code === 'already_activated') return 'این کلید روی سیستم دیگری فعال است.'
  if (code === 'license_not_found') return 'کلید پیدا نشد.'
  if (code === 'product_mismatch') return 'این کلید برای دفترچی نیست.'
  if (code === 'domain_not_activated') return 'این کلید روی این سیستم فعال نشده.'
  return 'فعال‌سازی انجام نشد.'
}
