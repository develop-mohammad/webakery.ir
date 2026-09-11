import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import type { Product } from '../../shared/models'

type Line = { product: Product; qty: number; unit_price: number }

export function InvoicesPage() {
  const [query, setQuery] = useState('')
  const [hits, setHits] = useState<Product[]>([])
  const [lines, setLines] = useState<Line[]>([])
  const [discountType, setDiscountType] = useState<'none' | 'percent' | 'amount'>('none')
  const [discountValue, setDiscountValue] = useState('0')
  const [payment, setPayment] = useState<'cash' | 'card' | 'credit'>('cash')

  useEffect(() => {
    if (!query.trim()) {
      setHits([])
      return
    }
    const t = setTimeout(() => {
      api()
        .searchProducts(query)
        .then(setHits)
        .catch(() => setHits([]))
    }, 200)
    return () => clearTimeout(t)
  }, [query])

  const subtotal = lines.reduce((s, l) => s + l.qty * l.unit_price, 0)
  const disc =
    discountType === 'percent'
      ? Math.round((subtotal * Number(discountValue || 0)) / 100)
      : discountType === 'amount'
        ? Number(discountValue || 0)
        : 0
  const total = Math.max(0, subtotal - disc)

  function addProduct(product: Product) {
    setLines((prev) => {
      const found = prev.find((l) => l.product.id === product.id)
      if (found) return prev.map((l) => (l.product.id === product.id ? { ...l, qty: l.qty + 1 } : l))
      return [...prev, { product, qty: 1, unit_price: product.sell_price }]
    })
    setQuery('')
    setHits([])
  }

  async function save() {
    try {
      const inv = await api().createSale({
        items: lines.map((l) => ({ product_id: l.product.id, qty: l.qty, unit_price: l.unit_price })),
        discount_type: discountType,
        discount_value: Number(discountValue || 0),
        payment_type: payment,
        customer_id: null,
        note: '',
      })
      toast.success(`فاکتور ${inv.number} ثبت شد`)
      setLines([])
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'ثبت نشد')
    }
  }

  return (
    <div className="grid gap-3 lg:grid-cols-[1fr_18rem]">
      <Card>
        <CardHeader>
          <CardTitle>فاکتور فروش</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="relative">
            <Input
              placeholder="جستجو یا اسکن بارکد + Enter"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && hits[0]) addProduct(hits[0])
              }}
            />
            {hits.length ? (
              <div className="absolute z-20 mt-1 w-full rounded-md border bg-card shadow">
                {hits.map((p) => (
                  <button
                    key={p.id}
                    type="button"
                    className="flex w-full justify-between px-3 py-2 text-sm hover:bg-accent"
                    onClick={() => addProduct(p)}
                  >
                    <span>
                      {p.name} <span className="text-xs text-muted-foreground">({p.sku})</span>
                    </span>
                    <span>{formatToman(p.sell_price)}</span>
                  </button>
                ))}
              </div>
            ) : null}
          </div>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-xs text-muted-foreground">
                <th className="py-2 text-right">کالا</th>
                <th className="py-2 text-right">تعداد</th>
                <th className="py-2 text-right">قیمت</th>
                <th className="py-2 text-right">جمع</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.product.id} className="border-b">
                  <td className="py-2">{line.product.name}</td>
                  <td className="py-2">
                    <Input
                      className="h-8 w-16"
                      dir="ltr"
                      value={line.qty}
                      onChange={(e) =>
                        setLines((prev) =>
                          prev.map((l) =>
                            l.product.id === line.product.id ? { ...l, qty: Number(e.target.value) || 0 } : l,
                          ),
                        )
                      }
                    />
                  </td>
                  <td className="py-2">{formatToman(line.unit_price)}</td>
                  <td className="py-2">{formatToman(line.qty * line.unit_price)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>جمع</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3 text-sm">
          <div className="flex justify-between">
            <span>جمع جزء</span>
            <span>{formatToman(subtotal)}</span>
          </div>
          <select
            className="h-9 w-full rounded-md border bg-transparent px-2"
            value={discountType}
            onChange={(e) => setDiscountType(e.target.value as 'none' | 'percent' | 'amount')}
          >
            <option value="none">بدون تخفیف</option>
            <option value="percent">تخفیف درصدی</option>
            <option value="amount">تخفیف مبلغی</option>
          </select>
          {discountType !== 'none' ? (
            <Input dir="ltr" value={discountValue} onChange={(e) => setDiscountValue(e.target.value)} />
          ) : null}
          <select
            className="h-9 w-full rounded-md border bg-transparent px-2"
            value={payment}
            onChange={(e) => setPayment(e.target.value as 'cash' | 'card' | 'credit')}
          >
            <option value="cash">نقدی</option>
            <option value="card">کارت‌به‌کارت</option>
            <option value="credit">نسیه</option>
          </select>
          <div className="flex justify-between text-base font-semibold">
            <span>قابل پرداخت</span>
            <span>{formatToman(total)}</span>
          </div>
          <Button className="w-full" disabled={!lines.length} onClick={save}>
            ثبت فاکتور
          </Button>
        </CardContent>
      </Card>
    </div>
  )
}
