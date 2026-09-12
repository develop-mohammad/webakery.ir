import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { ComparisonChart, PeakHoursChart } from '@/components/charts/SalesCharts'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import { toFaDigits } from '@/lib/jalali'
import type { ComparisonSeries, PeakHour, ProfitReport, StocktakeRow } from '../../shared/models'

export function ReportsPage() {
  const [preset, setPreset] = useState<'week' | 'month'>('week')
  const [series, setSeries] = useState<ComparisonSeries | null>(null)
  const [hours, setHours] = useState<PeakHour[]>([])
  const [profit, setProfit] = useState<ProfitReport | null>(null)
  const [rows, setRows] = useState<StocktakeRow[]>([])
  const [counts, setCounts] = useState<Record<number, string>>({})
  const [note, setNote] = useState('')
  const [expense, setExpense] = useState('')
  const [expenseNote, setExpenseNote] = useState('هزینه')
  const [tick, setTick] = useState(0)

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        const [dash, p, stock] = await Promise.all([
          api().dashboard(preset),
          api().profit(preset),
          api().stocktakeRows(),
        ])
        if (cancelled) return
        setSeries(dash.comparison)
        setHours(dash.peak_hours)
        setProfit(p)
        setRows(stock)
        setCounts((prev) => {
          const next = { ...prev }
          for (const row of stock) {
            if (next[row.product_id] === undefined) next[row.product_id] = String(row.system_qty)
          }
          return next
        })
      } catch (err) {
        toast.error(String((err as Error).message || err))
      }
    })()
    return () => {
      cancelled = true
    }
  }, [preset, tick])

  return (
    <Tabs defaultValue="sales">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <TabsList>
          <TabsTrigger value="sales">فروش</TabsTrigger>
          <TabsTrigger value="profit">سود</TabsTrigger>
          <TabsTrigger value="stock">انبارگردانی</TabsTrigger>
        </TabsList>
        <div className="flex gap-1">
          <Button variant={preset === 'week' ? 'default' : 'outline'} size="sm" onClick={() => setPreset('week')}>
            هفتگی
          </Button>
          <Button variant={preset === 'month' ? 'default' : 'outline'} size="sm" onClick={() => setPreset('month')}>
            ماهانه
          </Button>
        </div>
      </div>

      <TabsContent value="sales" className="space-y-3">
        <Card>
          <CardHeader>
            <CardTitle>
              مقایسه {series?.current_label} با {series?.previous_label}
            </CardTitle>
          </CardHeader>
          <CardContent>{series ? <ComparisonChart series={series} /> : null}</CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>ساعت پیک خرید</CardTitle>
          </CardHeader>
          <CardContent>
            <PeakHoursChart hours={hours} />
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="profit" className="space-y-3">
        <div className="grid gap-3 sm:grid-cols-4">
          <Mini label="فروش" value={formatToman(profit?.sales ?? 0)} />
          <Mini label="قیمت تمام‌شده" value={formatToman(profit?.cogs ?? 0)} />
          <Mini label="هزینه‌ها" value={formatToman(profit?.expenses ?? 0)} />
          <Mini label="سود" value={formatToman(profit?.profit ?? 0)} />
        </div>
        <Card>
          <CardHeader>
            <CardTitle>ثبت هزینه دستی</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-wrap gap-2">
            <Input className="w-40" dir="ltr" placeholder="مبلغ" value={expense} onChange={(e) => setExpense(e.target.value)} />
            <Input className="w-56" placeholder="توضیح (اجاره، حقوق…)" value={expenseNote} onChange={(e) => setExpenseNote(e.target.value)} />
            <Button
              onClick={async () => {
                try {
                  await api().addExpense(Number(expense) || 0, expenseNote)
                  toast.success('هزینه ثبت شد')
                  setExpense('')
                  setTick((n) => n + 1)
                } catch (err) {
                  toast.error(err instanceof Error ? err.message : 'ثبت نشد')
                }
              }}
            >
              ثبت
            </Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>پرفروش‌ها</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-xs text-muted-foreground">
                  <th className="px-4 py-2 text-right">کالا</th>
                  <th className="px-4 py-2 text-right">تعداد</th>
                  <th className="px-4 py-2 text-right">فروش</th>
                </tr>
              </thead>
              <tbody>
                {(profit?.top_products ?? []).map((p) => (
                  <tr key={p.product_id} className="border-b last:border-0">
                    <td className="px-4 py-2">{p.name}</td>
                    <td className="px-4 py-2">{toFaDigits(p.qty)}</td>
                    <td className="px-4 py-2">{formatToman(p.sales)}</td>
                  </tr>
                ))}
                {!profit?.top_products.length ? (
                  <tr>
                    <td colSpan={3} className="px-4 py-6 text-center text-muted-foreground">
                      در این بازه فروشی نیست
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="stock">
        <Card>
          <CardHeader>
            <CardTitle>انبارگردانی</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <Input placeholder="یادداشت (اختیاری)" value={note} onChange={(e) => setNote(e.target.value)} />
            <div className="max-h-[420px] overflow-auto rounded border">
              <table className="w-full text-sm">
                <thead className="sticky top-0 bg-muted">
                  <tr className="text-xs text-muted-foreground">
                    <th className="px-3 py-2 text-right">دسته</th>
                    <th className="px-3 py-2 text-right">کالا</th>
                    <th className="px-3 py-2 text-right">سیستم</th>
                    <th className="px-3 py-2 text-right">شمارش</th>
                    <th className="px-3 py-2 text-right">اختلاف</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => {
                    const counted = Number(counts[row.product_id] ?? row.system_qty)
                    const diff = counted - row.system_qty
                    return (
                      <tr key={row.product_id} className="border-t">
                        <td className="px-3 py-1.5 text-xs text-muted-foreground">{row.category_name}</td>
                        <td className="px-3 py-1.5">
                          {row.name}
                          <div className="text-[11px] text-muted-foreground" dir="ltr">
                            {row.sku}
                          </div>
                        </td>
                        <td className="px-3 py-1.5">{toFaDigits(row.system_qty)}</td>
                        <td className="px-3 py-1.5">
                          <Input
                            className="h-8 w-20"
                            dir="ltr"
                            value={counts[row.product_id] ?? String(row.system_qty)}
                            onChange={(e) => setCounts((c) => ({ ...c, [row.product_id]: e.target.value }))}
                          />
                        </td>
                        <td className={`px-3 py-1.5 ${diff ? 'text-warning' : ''}`}>{toFaDigits(diff)}</td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
            <Button
              onClick={async () => {
                try {
                  await api().applyStocktake(
                    rows.map((row) => ({
                      product_id: row.product_id,
                      counted_qty: Number(counts[row.product_id] ?? row.system_qty),
                    })),
                    note,
                  )
                  toast.success('انبارگردانی ثبت شد و موجودی به‌روز شد')
                  setTick((n) => n + 1)
                } catch (err) {
                  toast.error(err instanceof Error ? err.message : 'ثبت نشد')
                }
              }}
            >
              ثبت انبارگردانی
            </Button>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  )
}

function Mini({ label, value }: { label: string; value: string }) {
  return (
    <Card>
      <CardContent className="p-4">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="mt-1 font-semibold">{value}</p>
      </CardContent>
    </Card>
  )
}
