import jalaali from 'jalaali-js'

const WEEKDAY_SAT_OFFSET: Record<string, number> = {
  Sat: 0,
  Sun: 1,
  Mon: 2,
  Tue: 3,
  Wed: 4,
  Thu: 5,
  Fri: 6,
}

export function tehranYmd(date = new Date()): string {
  return date.toLocaleDateString('en-CA', { timeZone: 'Asia/Tehran' })
}

export function ymdToIsoStart(ymd: string): string {
  return new Date(`${ymd}T00:00:00+03:30`).toISOString()
}

export function addDaysYmd(ymd: string, days: number): string {
  const date = new Date(`${ymd}T12:00:00+03:30`)
  date.setTime(date.getTime() + days * 86_400_000)
  return date.toLocaleDateString('en-CA', { timeZone: 'Asia/Tehran' })
}

export function weekdayFromSat(date = new Date()): number {
  const short = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Tehran',
    weekday: 'short',
  })
    .format(date)
    .slice(0, 3)
  return WEEKDAY_SAT_OFFSET[short] ?? 0
}

export function jalaliLabelFromYmd(ymd: string): string {
  const [gy, gm, gd] = ymd.split('-').map(Number)
  const { jy, jm, jd } = jalaali.toJalaali(gy, gm, gd)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${jy}/${pad(jm)}/${pad(jd)}`
}

export function jalaliMonthLabelFromYmd(ymd: string): string {
  const months = [
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
  ]
  const [gy, gm, gd] = ymd.split('-').map(Number)
  const { jy, jm } = jalaali.toJalaali(gy, gm, gd)
  return `${months[jm - 1] ?? ''} ${jy}`
}

export function hourInTehran(isoStr: string): number {
  return Number(
    new Date(isoStr).toLocaleString('en-GB', {
      timeZone: 'Asia/Tehran',
      hour: '2-digit',
      hourCycle: 'h23',
    }),
  )
}

export function dayKeyTehran(isoStr: string): string {
  return new Date(isoStr).toLocaleDateString('en-CA', { timeZone: 'Asia/Tehran' })
}

export function weekRangeYmd(now = new Date()): {
  start: string
  endExclusive: string
  prevStart: string
  prevEndExclusive: string
} {
  const today = tehranYmd(now)
  const start = addDaysYmd(today, -weekdayFromSat(now))
  const endExclusive = addDaysYmd(start, 7)
  const prevStart = addDaysYmd(start, -7)
  return { start, endExclusive, prevStart, prevEndExclusive: start }
}

export function monthRangeYmd(now = new Date()): {
  start: string
  endExclusive: string
  prevStart: string
  prevEndExclusive: string
} {
  const today = tehranYmd(now)
  const [gy, gm, gd] = today.split('-').map(Number)
  const { jy, jm } = jalaali.toJalaali(gy, gm, gd)
  const startG = jalaali.toGregorian(jy, jm, 1)
  const start = toYmd(startG.gy, startG.gm, startG.gd)
  const nextJm = jm === 12 ? 1 : jm + 1
  const nextJy = jm === 12 ? jy + 1 : jy
  const nextG = jalaali.toGregorian(nextJy, nextJm, 1)
  const endExclusive = toYmd(nextG.gy, nextG.gm, nextG.gd)
  const prevJm = jm === 1 ? 12 : jm - 1
  const prevJy = jm === 1 ? jy - 1 : jy
  const prevG = jalaali.toGregorian(prevJy, prevJm, 1)
  const prevStart = toYmd(prevG.gy, prevG.gm, prevG.gd)
  return { start, endExclusive, prevStart, prevEndExclusive: start }
}

function toYmd(y: number, m: number, d: number): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${y}-${pad(m)}-${pad(d)}`
}

export function eachYmd(start: string, endExclusive: string): string[] {
  const days: string[] = []
  let cursor = start
  let guard = 0
  while (cursor < endExclusive && guard < 400) {
    days.push(cursor)
    cursor = addDaysYmd(cursor, 1)
    guard += 1
  }
  return days
}
