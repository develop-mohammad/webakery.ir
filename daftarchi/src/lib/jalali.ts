import jalaali from 'jalaali-js'

const faDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']

export function toFaDigits(value: string | number): string {
  return String(value).replace(/\d/g, (d) => faDigits[Number(d)] ?? d)
}

/** ISO → ۱۴۰۴/۰۶/۲۰ */
export function formatJalaliDate(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  const { jy, jm, jd } = jalaali.toJalaali(date)
  const pad = (n: number) => String(n).padStart(2, '0')
  return toFaDigits(`${jy}/${pad(jm)}/${pad(jd)}`)
}

export function formatJalaliDateTime(iso: string): string {
  const date = new Date(iso)
  if (Number.isNaN(date.getTime())) return '—'
  const { jy, jm, jd } = jalaali.toJalaali(date)
  const pad = (n: number) => String(n).padStart(2, '0')
  const time = `${pad(date.getHours())}:${pad(date.getMinutes())}`
  return toFaDigits(`${jy}/${pad(jm)}/${pad(jd)} ${time}`)
}

export function nowIso(): string {
  return new Date().toISOString()
}
