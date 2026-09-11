import { useEffect, useState } from 'react'
import { Banknote, FileText, TrendingUp, Wallet } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ComparisonChart, PeakHoursChart } from '@/components/charts/SalesCharts'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import { formatJalaliDate, toFaDigits } from '@/lib/jalali'
import type { DashboardData } from '../../shared/models'

export function DashboardPage() {
  const [preset, setPreset] = useState<'week' | 'month'>('week')
  const [data, setData] = useState<DashboardData | null>(null)

  useEffect(() => {
    api()
      .dashboard(preset)
      .then(setData)
      .catch(() => setData(null))
  }, [preset])

  const delta = data?.comparison.delta_percent
  const deltaText =
    delta == null
      ? ''
      : `${delta > 0 ? '+' : ''}${toFaDigits(delta)}٪ نسبت به ${data?.comparison.previous_label}`

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Stat label="فروش امروز" value={formatToman(data?.sales_today ?? 0)} icon={TrendingUp} />
        <Stat label="سود این ماه" value={formatToman(data?.profit_month ?? 0)} icon={Banknote} />
        <Stat label="فاکتورهای امروز" value={toFaDigits(data?.invoices_today ?? 0)} icon={FileText} />
        <Stat label="موجودی صندوق" value={formatToman(data?.cash_balance ?? 0)} icon={Wallet} />
      </div>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0">
          <div>
            <CardTitle>مقایسه فروش</CardTitle>
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
            <CardTitle>ساعت پیک خرید</CardTitle>
          </CardHeader>
          <CardContent>{data ? <PeakHoursChart hours={data.peak_hours} /> : null}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>آخرین فاکتورها</CardTitle>
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
