import { useCallback, useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Users } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import { formatJalaliDate, formatJalaliDateTime, toFaDigits } from '@/lib/jalali'
import { useSettings } from '@/components/layout/SettingsProvider'
import type { CustomerStats } from '../../shared/models'
import { toEnDigits } from '../../shared/customers'

export function CustomersPage() {
  const { settings, refresh } = useSettings()
  const [stats, setStats] = useState<CustomerStats | null>(null)
  const [search, setSearch] = useState('')
  const [busy, setBusy] = useState(false)
  const connected = Boolean(settings.wc_url && settings.wc_key)

  const load = useCallback(() => {
    api()
      .customersStats()
      .then(setStats)
      .catch(() => setStats(null))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  async function pullCustomers() {
    setBusy(true)
    try {
      const result = await api().pullWooSales()
      await refresh()
      load()
      toast.success(result.message)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'آمار مشتریان نیامد')
    } finally {
      setBusy(false)
    }
  }

  const rows = useMemo(() => {
    const q = toEnDigits(search.trim())
    const list = stats?.list ?? []
    if (!q) return list
    return list.filter((row) => toEnDigits(`${row.name} ${row.phone} ${row.email}`).includes(q))
  }, [search, stats])

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="مشتریان سایت" value={toFaDigits(stats?.total_customers ?? 0)} />
        <Stat label="خریداران این ماه" value={toFaDigits(stats?.month_buyers ?? 0)} />
        <Stat label="خرید این ماه" value={formatToman(stats?.month_spent ?? 0)} />
        <Stat label="میانگین هر سفارش" value={formatToman(stats?.avg_order ?? 0)} />
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Input
          className="max-w-xs"
          placeholder="جستجوی نام، موبایل یا ایمیل"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <Button variant="outline" disabled={!connected || busy} onClick={pullCustomers}>
          {busy ? 'در حال گرفتن آمار…' : 'آمار مشتریان سایت را بیاور'}
        </Button>
        {!connected ? (
          <span className="text-xs text-muted-foreground">برای آمار مشتریان، از «اتصال سایت» وصل شو.</span>
        ) : settings.wc_last_sales_at ? (
          <span className="text-xs text-muted-foreground">
            آخرین آمار: {formatJalaliDateTime(settings.wc_last_sales_at)}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">هنوز آمار مشتریان سایت گرفته نشده.</span>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Users className="size-4" />
            خریداران سایت
          </CardTitle>
          <p className="text-xs text-muted-foreground">
            از سفارش‌های پرداخت‌شدهٔ سایت جمع شده؛ مهمان‌ها هم اگر موبایل یا نام داشته باشند می‌آیند. بدهی صندوق جداست.
          </p>
        </CardHeader>
        <CardContent className="p-0">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-xs text-muted-foreground">
                <th className="px-4 py-2 text-right font-medium">نام</th>
                <th className="px-4 py-2 text-right font-medium">موبایل</th>
                <th className="px-4 py-2 text-right font-medium">ایمیل</th>
                <th className="px-4 py-2 text-right font-medium">سفارش</th>
                <th className="px-4 py-2 text-right font-medium">جمع خرید</th>
                <th className="px-4 py-2 text-right font-medium">آخرین خرید</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={`${row.phone}-${row.email}-${row.name}`} className="border-b last:border-0">
                  <td className="px-4 py-2">{row.name}</td>
                  <td className="px-4 py-2" dir="ltr">
                    {row.phone ? toFaDigits(row.phone) : '—'}
                  </td>
                  <td className="px-4 py-2" dir="ltr">
                    {row.email || '—'}
                  </td>
                  <td className="px-4 py-2">{toFaDigits(row.orders_count)}</td>
                  <td className="px-4 py-2">{formatToman(row.total_spent)}</td>
                  <td className="px-4 py-2">{formatJalaliDate(row.last_order_at)}</td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td className="px-4 py-8 text-center text-muted-foreground" colSpan={6}>
                    هنوز مشتری سایتی نیست. «آمار مشتریان سایت را بیاور» را بزن.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </CardContent>
      </Card>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardContent className="p-4">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="mt-1 text-lg font-semibold">{value}</p>
      </CardContent>
    </Card>
  )
}
