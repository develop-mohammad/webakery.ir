import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useSettings } from '@/components/layout/SettingsProvider'
import { api } from '@/lib/ipc'
import { formatJalaliDateTime } from '@/lib/jalali'

export function WooCommercePage() {
  const { settings, updateSettings, refresh } = useSettings()
  const [url, setUrl] = useState(settings.wc_url ?? '')
  const [key, setKey] = useState(settings.wc_key ?? '')
  const [secret, setSecret] = useState(settings.wc_secret ?? '')
  const [currency, setCurrency] = useState(settings.wc_currency || 'toman')
  const [busy, setBusy] = useState(false)

  async function persist() {
    await updateSettings({
      wc_url: url.trim().replace(/\/+$/, ''),
      wc_key: key.trim(),
      wc_secret: secret.trim(),
      wc_currency: currency,
    })
  }

  async function save(e: FormEvent) {
    e.preventDefault()
    setBusy(true)
    try {
      await persist()
      toast.success('اتصال ذخیره شد')
    } catch {
      toast.error('ذخیره نشد')
    } finally {
      setBusy(false)
    }
  }

  async function test() {
    setBusy(true)
    try {
      await persist()
      const msg = await api().testWoo()
      toast.success(msg)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'اتصال برقرار نشد')
    } finally {
      setBusy(false)
    }
  }

  async function pull() {
    setBusy(true)
    try {
      await persist()
      const result = await api().pullWoo()
      await refresh()
      toast.success(result.message)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'دریافت انجام نشد')
    } finally {
      setBusy(false)
    }
  }

  return (
    <form onSubmit={save} className="mx-auto max-w-xl space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>اتصال به سایت ووکامرس</CardTitle>
          <CardDescription>
            از ووکامرس ← تنظیمات ← پیشرفته ← REST API یک کلید با دسترسی خواندن/نوشتن بساز. بعد «دریافت همه کالاها» همهٔ محصولات
            منتشرشده، پیش‌نویس، متغیر و بدون SKU را وارد حسابداری می‌کند. قیمت فروش بعد از اولین دریافت مال دفترچی است.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="space-y-1.5">
            <Label>آدرس سایت</Label>
            <Input
              dir="ltr"
              className="text-left"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="https://shop.com"
            />
          </div>
          <div className="space-y-1.5">
            <Label>Consumer Key</Label>
            <Input dir="ltr" className="text-left" value={key} onChange={(e) => setKey(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Consumer Secret</Label>
            <Input dir="ltr" className="text-left" type="password" value={secret} onChange={(e) => setSecret(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>واحد پول سایت</Label>
            <select
              className="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
              value={currency}
              onChange={(e) => setCurrency(e.target.value)}
            >
              <option value="toman">تومان</option>
              <option value="rial">ریال (÷۱۰ هنگام ورود)</option>
            </select>
          </div>
          {settings.wc_last_pull_at ? (
            <p className="text-xs text-muted-foreground">
              آخرین دریافت کالا: {formatJalaliDateTime(settings.wc_last_pull_at)}
            </p>
          ) : (
            <p className="text-xs text-muted-foreground">هنوز کالایی از سایت گرفته نشده است.</p>
          )}
          <div className="flex flex-wrap gap-2">
            <Button type="submit" disabled={busy}>
              ذخیره اتصال
            </Button>
            <Button type="button" variant="outline" disabled={busy} onClick={test}>
              تست اتصال
            </Button>
            <Button type="button" variant="secondary" disabled={busy} onClick={pull}>
              {busy ? 'در حال دریافت…' : 'دریافت همه کالاهای سایت'}
            </Button>
          </div>
        </CardContent>
      </Card>
    </form>
  )
}
