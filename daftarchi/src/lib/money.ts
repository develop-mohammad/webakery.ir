import { toFaDigits } from './jalali'

/** تومان با جداکننده هزارگان فارسی */
export function formatToman(amount: number): string {
  const signed = amount < 0 ? '−' : ''
  return `${signed}${toFaDigits(Math.abs(amount).toLocaleString('en-US'))} تومان`
}
