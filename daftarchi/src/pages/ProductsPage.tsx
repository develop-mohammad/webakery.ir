import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react'
import {
  flexRender,
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table'
import { Download, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api } from '@/lib/ipc'
import { formatToman } from '@/lib/money'
import { formatJalaliDateTime, toFaDigits } from '@/lib/jalali'
import { cn } from '@/lib/utils'
import type { Category, Product } from '../../shared/models'
import { useSettings } from '@/components/layout/SettingsProvider'

export function ProductsPage() {
  const { settings, refresh } = useSettings()
  const [categories, setCategories] = useState<Category[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [categoryId, setCategoryId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [loading, setLoading] = useState(false)
  const [open, setOpen] = useState(false)

  const load = useCallback(async () => {
    const [cats, list] = await Promise.all([
      api().listCategories(),
      api().listProducts({ categoryId: categoryId ?? undefined, search }),
    ])
    setCategories(cats)
    setProducts(list)
  }, [categoryId, search])

  useEffect(() => {
    load().catch((err) => toast.error(String(err.message || err)))
  }, [load])

  const groups = useMemo(() => {
    const map = new Map<string, Product[]>()
    for (const product of products) {
      const key = product.category_name || 'بدون دسته'
      map.set(key, [...(map.get(key) ?? []), product])
    }
    return [...map.entries()]
  }, [products])

  async function pullSite() {
    setLoading(true)
    try {
      const result = await api().pullWoo()
      await refresh()
      await load()
      toast.success(result.message)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'دریافت از سایت انجام نشد')
    } finally {
      setLoading(false)
    }
  }

  async function savePatch(id: number, field: 'sell_price' | 'buy_price' | 'stock', value: number) {
    try {
      await api().updateProduct({ id, [field]: value })
      await load()
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'ذخیره نشد')
    }
  }

  const connected = Boolean(settings.wc_url && settings.wc_key)

  return (
    <div className="flex h-full min-h-0 gap-3">
      <aside className="flex w-48 shrink-0 flex-col rounded-lg border bg-card">
        <div className="border-b px-3 py-2 text-xs font-medium text-muted-foreground">دسته‌بندی سایت</div>
        <button
          type="button"
          onClick={() => setCategoryId(null)}
          className={cn(
            'px-3 py-2 text-right text-[13px] hover:bg-accent',
            categoryId === null && 'bg-primary/10 font-medium text-primary',
          )}
        >
          همه ({toFaDigits(products.length)})
        </button>
        <div className="min-h-0 flex-1 overflow-auto">
          {categories.map((cat) => (
            <button
              key={cat.id}
              type="button"
              onClick={() => setCategoryId(cat.id)}
              className={cn(
                'flex w-full items-center justify-between px-3 py-2 text-[13px] hover:bg-accent',
                categoryId === cat.id && 'bg-primary/10 font-medium text-primary',
              )}
            >
              <span className="truncate">{cat.name}</span>
              <span className="text-muted-foreground">{toFaDigits(cat.product_count)}</span>
            </button>
          ))}
          {categories.length === 0 ? (
            <p className="px-3 py-4 text-xs text-muted-foreground">هنوز دسته‌ای نیست. از سایت بگیر یا دسته بساز.</p>
          ) : null}
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          <Input
            className="max-w-xs"
            placeholder="جستجوی نام یا SKU / اسکن بارکد"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Button variant="outline" onClick={pullSite} disabled={loading || !connected}>
            <Download className="size-4" />
            {loading ? 'در حال دریافت…' : 'دریافت همه کالاهای سایت'}
          </Button>
          <Button onClick={() => setOpen(true)}>
            <Plus className="size-4" />
            کالای جدید
          </Button>
          {!connected ? (
            <span className="text-xs text-muted-foreground">برای گرفتن کالاهای سایت، از منوی ووکامرس وصل شو.</span>
          ) : settings.wc_last_pull_at ? (
            <span className="text-xs text-muted-foreground">
              آخرین دریافت: {formatJalaliDateTime(settings.wc_last_pull_at)}
            </span>
          ) : (
            <span className="text-xs text-muted-foreground">اتصال هست؛ همه کالاها را از سایت بگیر.</span>
          )}
        </div>

        <div className="min-h-0 flex-1 overflow-auto rounded-lg border bg-card">
          {groups.length === 0 ? (
            <p className="p-8 text-center text-sm text-muted-foreground">کالایی نیست. از سایت بگیر یا دستی اضافه کن.</p>
          ) : (
            groups.map(([cat, rows]) => (
              <CategoryTable
                key={cat}
                title={cat}
                rows={rows}
                onSave={savePatch}
                onDelete={async (id) => {
                  try {
                    await api().deleteProduct(id)
                    await load()
                    toast.success('حذف شد')
                  } catch (err) {
                    toast.error(err instanceof Error ? err.message : 'حذف نشد')
                  }
                }}
              />
            ))
          )}
        </div>
      </div>
      <ProductDialog open={open} onOpenChange={setOpen} categories={categories} onSaved={load} />
    </div>
  )
}

function CategoryTable({
  title,
  rows,
  onSave,
  onDelete,
}: {
  title: string
  rows: Product[]
  onSave: (id: number, field: 'sell_price' | 'buy_price' | 'stock', value: number) => void
  onDelete: (id: number) => void
}) {
  const columns = useMemo<ColumnDef<Product>[]>(
    () => [
      { accessorKey: 'name', header: 'نام', cell: (c) => c.getValue<string>() },
      { accessorKey: 'sku', header: 'SKU', cell: (c) => <span dir="ltr">{c.getValue<string>()}</span> },
      {
        accessorKey: 'buy_price',
        header: 'خرید',
        cell: (c) => (
          <InlineNumber
            value={c.row.original.buy_price}
            onSave={(v) => onSave(c.row.original.id, 'buy_price', v)}
          />
        ),
      },
      {
        accessorKey: 'sell_price',
        header: 'فروش',
        cell: (c) => (
          <InlineNumber
            value={c.row.original.sell_price}
            onSave={(v) => onSave(c.row.original.id, 'sell_price', v)}
          />
        ),
      },
      {
        accessorKey: 'stock',
        header: 'موجودی',
        cell: (c) => (
          <InlineNumber value={c.row.original.stock} onSave={(v) => onSave(c.row.original.id, 'stock', v)} />
        ),
      },
      {
        id: 'actions',
        header: '',
        cell: (c) => (
          <Button variant="ghost" size="icon" onClick={() => onDelete(c.row.original.id)} aria-label="حذف">
            <Trash2 className="size-3.5" />
          </Button>
        ),
      },
    ],
    [onDelete, onSave],
  )

  const table = useReactTable({ data: rows, columns, getCoreRowModel: getCoreRowModel() })

  return (
    <div>
      <div className="sticky top-0 z-10 border-b bg-muted/80 px-3 py-1.5 text-xs font-semibold backdrop-blur">
        {title} — {toFaDigits(rows.length)} کالا
      </div>
      <table className="w-full text-sm">
        <thead>
          {table.getHeaderGroups().map((hg) => (
            <tr key={hg.id} className="border-b text-xs text-muted-foreground">
              {hg.headers.map((h) => (
                <th key={h.id} className="px-3 py-2 text-right font-medium">
                  {flexRender(h.column.columnDef.header, h.getContext())}
                </th>
              ))}
            </tr>
          ))}
        </thead>
        <tbody>
          {table.getRowModel().rows.map((row) => (
            <tr
              key={row.id}
              className={cn('border-b last:border-0', row.original.low_stock && 'bg-warning/10')}
            >
              {row.getVisibleCells().map((cell) => (
                <td key={cell.id} className="px-3 py-1.5">
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function InlineNumber({ value, onSave }: { value: number; onSave: (v: number) => void }) {
  const [editing, setEditing] = useState(false)
  const [text, setText] = useState(String(value))
  useEffect(() => setText(String(value)), [value])
  if (!editing) {
    return (
      <button type="button" className="w-full text-right tabular-nums" onClick={() => setEditing(true)}>
        {formatToman(value).replace(' تومان', '')}
      </button>
    )
  }
  return (
    <input
      autoFocus
      className="h-7 w-24 rounded border px-1 text-left text-sm"
      dir="ltr"
      value={text}
      onChange={(e) => setText(e.target.value)}
      onBlur={() => {
        setEditing(false)
        const n = Number(text.replace(/,/g, ''))
        if (Number.isFinite(n) && n !== value) onSave(Math.floor(n))
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter') (e.target as HTMLInputElement).blur()
        if (e.key === 'Escape') {
          setText(String(value))
          setEditing(false)
        }
      }}
    />
  )
}

function ProductDialog({
  open,
  onOpenChange,
  categories,
  onSaved,
}: {
  open: boolean
  onOpenChange: (v: boolean) => void
  categories: Category[]
  onSaved: () => Promise<void>
}) {
  const [name, setName] = useState('')
  const [sku, setSku] = useState('')
  const [buy, setBuy] = useState('0')
  const [sell, setSell] = useState('0')
  const [stock, setStock] = useState('0')
  const [alert, setAlert] = useState('0')
  const [cat, setCat] = useState<string>('')
  const [newCat, setNewCat] = useState('')

  async function submit(e: FormEvent) {
    e.preventDefault()
    try {
      let categoryId: number | null = cat ? Number(cat) : null
      if (newCat.trim()) {
        const created = await api().createCategory(newCat.trim())
        categoryId = created.id
      }
      await api().createProduct({
        name,
        sku,
        buy_price: Number(buy) || 0,
        sell_price: Number(sell) || 0,
        stock: Number(stock) || 0,
        stock_alert: Number(alert) || 0,
        category_id: categoryId,
      })
      toast.success('کالا اضافه شد')
      onOpenChange(false)
      setName('')
      setSku('')
      await onSaved()
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'ذخیره نشد')
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>کالای جدید</DialogTitle>
        </DialogHeader>
        <form onSubmit={submit} className="grid gap-2">
          <Label>نام</Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} required />
          <Label>SKU</Label>
          <Input dir="ltr" value={sku} onChange={(e) => setSku(e.target.value)} required />
          <div className="grid grid-cols-2 gap-2">
            <div>
              <Label>قیمت خرید</Label>
              <Input dir="ltr" value={buy} onChange={(e) => setBuy(e.target.value)} />
            </div>
            <div>
              <Label>قیمت فروش</Label>
              <Input dir="ltr" value={sell} onChange={(e) => setSell(e.target.value)} />
            </div>
            <div>
              <Label>موجودی</Label>
              <Input dir="ltr" value={stock} onChange={(e) => setStock(e.target.value)} />
            </div>
            <div>
              <Label>حد هشدار</Label>
              <Input dir="ltr" value={alert} onChange={(e) => setAlert(e.target.value)} />
            </div>
          </div>
          <Label>دسته</Label>
          <select
            className="h-9 rounded-md border bg-transparent px-2 text-sm"
            value={cat}
            onChange={(e) => setCat(e.target.value)}
          >
            <option value="">بدون دسته</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>
          <Input placeholder="یا دستهٔ جدید…" value={newCat} onChange={(e) => setNewCat(e.target.value)} />
          <Button type="submit">ذخیره</Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
