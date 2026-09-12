import assert from 'node:assert/strict'
import {
  aggregateSiteCustomers,
  customerFromBilling,
  customerKey,
  normalizeIranPhone,
} from '../shared/customers.ts'

assert.equal(normalizeIranPhone('۰۹۱۲۳۴۵۶۷۸۹'), '09123456789')
assert.equal(normalizeIranPhone('+98 912 345 6789'), '09123456789')
assert.equal(normalizeIranPhone('00989123456789'), '09123456789')
assert.equal(normalizeIranPhone('9123456789'), '09123456789')
assert.equal(normalizeIranPhone('123'), '')

assert.equal(customerKey('علی', '09120000000', ''), 'tel:09120000000')
assert.equal(customerKey('علی', '', 'Ali@Shop.com'), 'mail:ali@shop.com')
assert.equal(customerKey('  علی  رضایی  ', '', ''), 'name:علی رضایی')

const fromBill = customerFromBilling({
  first_name: 'مریم',
  last_name: 'احمدی',
  phone: '0912-111-2233',
  email: 'Mary@Ex.com',
})
assert.equal(fromBill.phone, '09121112233')
assert.equal(fromBill.email, 'mary@ex.com')
assert.equal(fromBill.name, 'مریم احمدی')

const monthStart = '2026-09-01T00:00:00.000Z'
const monthEnd = '2026-10-01T00:00:00.000Z'
const stats = aggregateSiteCustomers(
  [
    {
      name: 'علی',
      phone: '09120000001',
      email: 'a@x.com',
      total: 100_000,
      created_at: '2026-09-05T10:00:00.000Z',
    },
    {
      name: 'علی رضایی',
      phone: '+989120000001',
      email: '',
      total: 50_000,
      created_at: '2026-08-01T10:00:00.000Z',
    },
    {
      name: 'سارا',
      phone: '09120000002',
      email: '',
      total: 20_000,
      created_at: '2026-09-10T10:00:00.000Z',
    },
    {
      name: '',
      phone: '',
      email: '',
      total: 5_000,
      created_at: '2026-09-12T10:00:00.000Z',
    },
  ],
  monthStart,
  monthEnd,
)

assert.equal(stats.total_customers, 3)
assert.equal(stats.month_buyers, 3)
assert.equal(stats.month_spent, 125_000)
assert.equal(stats.avg_order, 43_750)
assert.equal(stats.list[0].name, 'علی رضایی')
assert.equal(stats.list[0].orders_count, 2)
assert.equal(stats.list[0].total_spent, 150_000)
assert.equal(stats.list[0].phone, '09120000001')

console.log('customer stats helpers ok')
