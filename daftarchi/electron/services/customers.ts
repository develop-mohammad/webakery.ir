import { getDb } from '../database/db'
import { aggregateSiteCustomers } from '../../shared/customers'
import { monthRangeYmd, ymdToIsoStart } from '../../shared/period'
import type { CustomerStats } from '../../shared/models'

type OrderRow = {
  name: string
  phone: string
  email: string
  total: number
  created_at: string
}

export function customerStats(): CustomerStats {
  const month = monthRangeYmd()
  const rows = getDb()
    .prepare(
      `SELECT customer_name AS name, customer_phone AS phone, customer_email AS email,
              total, created_at
       FROM wc_orders`,
    )
    .all() as OrderRow[]
  return aggregateSiteCustomers(rows, ymdToIsoStart(month.start), ymdToIsoStart(month.endExclusive))
}
