import assert from 'node:assert/strict'
import {
  EARLY_RENEWAL_DAYS,
  TRIAL_DAYS,
  buildSnapshot,
  isEarlyRenewal,
  licensePayUrl,
  loyaltyDiscountPercent,
  quoteAllPlans,
  warningLevel,
} from '../shared/license.ts'

assert.equal(loyaltyDiscountPercent(1), 5)
assert.equal(loyaltyDiscountPercent(2), 6)
assert.equal(loyaltyDiscountPercent(3), 7)
assert.equal(loyaltyDiscountPercent(4), 8)
assert.equal(loyaltyDiscountPercent(5), 9)
assert.equal(loyaltyDiscountPercent(20), 9)
assert.equal(isEarlyRenewal(EARLY_RENEWAL_DAYS), true)
assert.equal(isEarlyRenewal(2), false)
assert.equal(warningLevel(20, 'licensed'), 'none')
assert.equal(warningLevel(10, 'licensed'), 'notice')
assert.equal(warningLevel(5, 'licensed'), 'warn')
assert.equal(warningLevel(2, 'licensed'), 'urgent')
assert.equal(warningLevel(6, 'trial'), 'none')
assert.equal(warningLevel(5, 'trial'), 'warn')

const firstBuy = quoteAllPlans(0, 7, false)
assert.equal(firstBuy[0].discount_percent, 0)
assert.equal(firstBuy[1].plan_id, '3m')

const early = quoteAllPlans(2, 10, true)
assert.equal(early[0].discount_percent, 6)
assert.equal(early[0].pay_toman, early[0].price_toman - Math.round((early[0].price_toman * 6) / 100))

const locked = buildSnapshot({
  nowMs: Date.parse('2026-09-12T00:00:00+03:30'),
  installAtMs: Date.parse('2026-01-01T00:00:00+03:30'),
  machineId: 'darchi-test.pc',
  key: '',
  email: '',
  expiresAt: '',
  periodsPaid: 0,
  serverStatus: 'unknown',
})
assert.equal(locked.kind, 'locked')
assert.equal(locked.allowed, false)

const trial = buildSnapshot({
  nowMs: Date.parse('2026-09-12T00:00:00+03:30'),
  installAtMs: Date.parse('2026-09-10T00:00:00+03:30'),
  machineId: 'darchi-test.pc',
  key: '',
  email: '',
  expiresAt: '',
  periodsPaid: 0,
  serverStatus: 'unknown',
})
assert.equal(trial.kind, 'trial')
assert.ok(trial.days_left <= TRIAL_DAYS)
assert.equal(trial.allowed, true)

const licensed = buildSnapshot({
  nowMs: Date.parse('2026-09-12T00:00:00+03:30'),
  installAtMs: Date.parse('2026-01-01T00:00:00+03:30'),
  machineId: 'darchi-test.pc',
  key: 'DAFTAR-AAAA-BBBB-CCCC-DDDD',
  email: 'a@x.com',
  expiresAt: '2026-09-20',
  periodsPaid: 3,
  serverStatus: 'valid',
})
assert.equal(licensed.kind, 'licensed')
assert.equal(licensed.early_eligible, true)
assert.equal(licensed.discount_percent, 7)
assert.ok(licensed.warning !== 'none')

const pay = licensePayUrl({ planId: '3m', machineId: 'darchi-test.pc', email: 'a@x.com' })
assert.ok(pay.includes('plugin=daftarchi'))
assert.ok(pay.includes('plan=3m'))

console.log('license helpers ok')
