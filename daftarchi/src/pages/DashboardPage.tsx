import { useCallback, useEffect, useState } from 'react'
import { Banknote, FileText, Store, TrendingUp, Users, Wallet } from 'lucide-react'
import { toast } from 'sonner'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Button } from '@/components/ui/button'
import { ComparisonChart, PeakHoursChart } from '@/components/charts/SalesCharts'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import { formatJalaliDate, formatJalaliDateTime, toFaDigits } from '@/lib/jalali'
import { useSettings } from '@/components/layout/SettingsProvider'
import type { DashboardData } from '../../shared/models'

export function DashboardPage() {
  const { settings, refresh } = useSettings()
  const [preset, setPreset] = useState<'week' | 'month'>('week')
  const [data, setData] = useState<DashboardData | null>(null)
  const [busy, setBusy] = useState(false)
  const connected = Boolean(settings.wc_url && settings.wc_key)

  const load = useCallback(() => {
    api()
      .dashboard(preset)
      .then(setData)
      .catch(() => setData(null))
  }, [preset])

  useEffect(() => {
    load()
  }, [load])

  async function pullSiteSales() {
    setBusy(true)
    try {
      const result = await api().pullWooSales()
      await refresh()
      load()
      toast.success(result.message)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'آمار سایت نیامد')
    } finally {
      setBusy(false)
    }
  }

  const delta = data?.comparison.delta_percent
  const deltaText =
    delta == null
      ? ''
      : `${delta > 0 ? '+' : ''}${toFaDigits(delta)}٪ نسبت به ${data?.comparison.previous_label}`
  const periodLabel = preset === 'week' ? 'این هفته' : 'این ماه'

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="فروش صندوق امروز" value={formatToman(data?.sales_today ?? 0)} icon={TrendingUp} />
        <Stat label="سود این ماه" value={formatToman(data?.profit_month ?? 0)} icon={Banknote} />
        <Stat label="فاکتورهای امروز" value={toFaDigits(data?.invoices_today ?? 0)} icon={FileText} />
        <Stat label="موجودی صندوق" value={formatToman(data?.cash_balance ?? 0)} icon={Wallet} />
      </div>

      <div className="grid gap-3 sm:grid-cols-3">
        <Stat label="فروش سایت امروز" value={formatToman(data?.site_sales_today ?? 0)} icon={Store} />
        <Stat
          label={`فروش سایت ${periodLabel}`}
          value={formatToman(data?.site_sales_period ?? 0)}
          icon={Store}
        />
        <Stat label="مشتریان سایت" value={toFaDigits(data?.site_customers ?? 0)} icon={Users} />
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Button variant="outline" disabled={!connected || busy} onClick={pullSiteSales}>
          {busy ? 'در حال گرفتن آمار سایت…' : 'آمار فروش سایت را بیاور'}
        </Button>
        {!connected ? (
          <span className="text-xs text-muted-foreground">برای آمار سایت، از «اتصال سایت» وصل شو.</span>
        ) : data?.site_sales_pulled_at ? (
          <span className="text-xs text-muted-foreground">
            آخرین آمار سایت: {formatJalaliDateTime(data.site_sales_pulled_at)} — امروز{' '}
            {toFaDigits(data.site_orders_today)} سفارش
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">هنوز آمار فروش سایت گرفته نشده.</span>
        )}
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle>مقایسه فروش صندوق</CardTitle>
            <p className="mt-1 text-xs text-muted-foreground">{deltaText}</p>
          </div>
          <Tabs value={preset} onValueChange={(v) => setPreset(v as 'week' | 'month')}>
            <TabsList>
              <TabsTrigger value="week">هفتگی</TabsTrigger>
              <TabsTrigger value="month">ماهانه</TabsTrigger>
            </TabsList>
          </Tabs>
        </CardHeader>
        <CardContent>
          {data ? <ComparisonChart series={data.comparison} /> : <p className="text-sm text-muted-foreground">در حال بارگذاری…</p>}
        </CardContent>
      </Card>

      <div className="grid gap-3 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>ساعت پیک خرید صندوق</CardTitle>
          </CardHeader>
          <CardContent>{data ? <PeakHoursChart hours={data.peak_hours} /> : null}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>آخرین فاکتورهای صندوق</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-xs text-muted-foreground">
                  <th className="px-4 py-2 text-right font-medium">شماره</th>
                  <th className="px-4 py-2 text-right font-medium">مبلغ</th>
                  <th className="px-4 py-2 text-right font-medium">تاریخ</th>
                </tr>
              </thead>
              <tbody>
                {(data?.recent_invoices ?? []).map((inv) => (
                  <tr key={inv.id} className="border-b last:border-0">
                    <td className="px-4 py-2" dir="ltr">
                      {inv.number}
                    </td>
                    <td className="px-4 py-2">{formatToman(inv.total)}</td>
                    <td className="px-4 py-2">{formatJalaliDate(inv.created_at)}</td>
                  </tr>
                ))}
                {!data?.recent_invoices.length ? (
                  <tr>
                    <td className="px-4 py-6 text-center text-muted-foreground" colSpan={3}>
                      هنوز فاکتوری نیست
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>آخرین سفارش‌های سایت</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-xs text-muted-foreground">
                <th className="px-4 py-2 text-right font-medium">شماره</th>
                <th className="px-4 py-2 text-right font-medium">مشتری</th>
                <th className="px-4 py-2 text-right font-medium">مبلغ</th>
                <th className="px-4 py-2 text-right font-medium">تاریخ</th>
              </tr>
            </thead>
            <tbody>
              {(data?.recent_site_orders ?? []).map((order) => (
                <tr key={order.id} className="border-b last:border-0">
                  <td className="px-4 py-2" dir="ltr">
                    {order.number}
                  </td>
                  <td className="px-4 py-2">{order.customer_name || '—'}</td>
                  <td className="px-4 py-2">{formatToman(order.total)}</td>
                  <td className="px-4 py-2">{formatJalaliDate(order.created_at)}</td>
                </tr>
              ))}
              {!data?.recent_site_orders.length ? (
                <tr>
                  <td className="px-4 py-6 text-center text-muted-foreground" colSpan={4}>
                    هنوز سفارش سایتی نیست. «آمار فروش سایت را بیاور» را بزن.
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

function Stat({ label, value, icon: Icon }: { label: string; value: string; icon: typeof Wallet }) {
  return (
    <Card>
      <CardContent className="flex items-start justify-between p-4">
        <div>
          <p className="text-xs text-muted-foreground">{label}</p>
          <p className="mt-1 text-lg font-semibold">{value}</p>
        </div>
        <Icon className="size-4 text-primary" />
      </CardContent>
    </Card>
  )
}
