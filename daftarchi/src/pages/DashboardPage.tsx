import { FileText, Package, TrendingUp, Wallet } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { formatToman } from '@/lib/money'

const STATS = [
  { label: 'فروش امروز', value: 0, icon: TrendingUp },
  { label: 'سود این ماه', value: 0, icon: Wallet },
  { label: 'فاکتورهای امروز', count: 0, icon: FileText },
  { label: 'موجودی صندوق', value: 0, icon: Package },
]

export function DashboardPage() {
  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {STATS.map((item) => (
          <Card key={item.label}>
            <CardContent className="flex items-start justify-between p-4">
              <div>
                <p className="text-xs text-muted-foreground">{item.label}</p>
                <p className="mt-1 text-lg font-semibold">
                  {'count' in item ? item.count : formatToman(item.value)}
                </p>
              </div>
              <item.icon className="size-4 text-primary" />
            </CardContent>
          </Card>
        ))}
      </div>
      <ModuleSoon note="نمودار ۳۰ روز و جدول آخرین فاکتورها در مرحله بعد اضافه می‌شود." />
    </div>
  )
}

function ModuleSoon({ note }: { note: string }) {
  return (
    <div className="rounded-lg border border-dashed bg-card px-4 py-8 text-center text-sm text-muted-foreground">
      {note}
    </div>
  )
}
