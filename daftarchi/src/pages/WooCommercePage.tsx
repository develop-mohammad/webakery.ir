import { useState, type FormEvent } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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
      toast.success('ذخیره شد')
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
      toast.error(err instanceof Error ? err.message : 'وصل نشد. آدرس و دو کلید را دوباره چک کن.')
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
      toast.error(err instanceof Error ? err.message : 'کالاها نیامدند')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="mx-auto grid max-w-5xl gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">قدم ۱ — روی سایت این کار را بکن</CardTitle>
        </CardHeader>
        <CardContent>
          <ol className="space-y-2.5 text-[13.5px] leading-7">
            <li>
              <b>۱.</b> سایت فروشگاهت را در اینترنت باز کن و وارد <b>پیشخوان</b> شو
              <span className="block text-muted-foreground">همان جایی که محصول اضافه می‌کنی، نه صفحهٔ مشتری.</span>
            </li>
            <li>
              <b>۲.</b> از منوی راست بزن: <b>ووکامرس</b> ← <b>پیکربندی</b>
            </li>
            <li>
              <b>۳.</b> بالای صفحه بزن: <b>پیشرفته</b> ← <b>REST API</b>
            </li>
            <li>
              <b>۴.</b> بزن: <b>افزودن کلید</b>
            </li>
            <li>
              <b>۵.</b> دسترسی را بگذار روی <b>خواندن/نوشتن</b> و ذخیره کن
            </li>
            <li>
              <b>۶.</b> دو تا نوشتهٔ انگلیسی بهت می‌دهد. هر دو را کپی کن و در قدم ۲ بچسبان.
            </li>
          </ol>
        </CardContent>
      </Card>

      <form onSubmit={save}>
        <Card>
          <CardHeader>
            <CardTitle className="text-base">قدم ۲ — همان‌ها را اینجا بگذار</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <Label>آدرس فروشگاهت</Label>
              <Input
                dir="ltr"
                className="text-left"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                placeholder="https://example.com"
              />
              <p className="text-[11px] text-muted-foreground">فقط آدرس اصلی. wp-admin ننویس.</p>
            </div>
            <div className="space-y-1.5">
              <Label>کلید اول (ck_)</Label>
              <Input
                dir="ltr"
                className="text-left"
                value={key}
                onChange={(e) => setKey(e.target.value)}
                placeholder="ck_...."
              />
            </div>
            <div className="space-y-1.5">
              <Label>کلید دوم (cs_)</Label>
              <Input
                dir="ltr"
                className="text-left"
                type="password"
                value={secret}
                onChange={(e) => setSecret(e.target.value)}
                placeholder="cs_...."
              />
            </div>
            <div className="space-y-1.5">
              <Label>قیمت‌های سایت به چه واحدی است؟</Label>
              <select
                className="h-9 w-full rounded-md border bg-transparent px-2 text-sm"
                value={currency}
                onChange={(e) => setCurrency(e.target.value)}
              >
                <option value="toman">تومان</option>
                <option value="rial">ریال</option>
              </select>
            </div>
            <div className="flex flex-wrap gap-2 pt-1">
              <Button type="button" variant="outline" disabled={busy} onClick={test}>
                ببین وصل می‌شود؟
              </Button>
              <Button type="submit" variant="ghost" disabled={busy}>
                فقط ذخیره
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card className="mt-4">
          <CardHeader>
            <CardTitle className="text-base">قدم ۳ — کالاها را بیاور داخل دفترچی</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-[13px] leading-6 text-muted-foreground">
              اول «ببین وصل می‌شود؟» را بزن. اگر پیام موفقیت دیدی، بعد این دکمه را بزن تا کالاها بیایند صفحهٔ «کالاها».
            </p>
            <Button type="button" disabled={busy} onClick={pull}>
              {busy ? 'صبر کن، دارد می‌آورد…' : 'کالاهای سایت را بیاور'}
            </Button>
            {settings.wc_last_pull_at ? (
              <p className="text-xs text-muted-foreground">
                آخرین بار آمد: {formatJalaliDateTime(settings.wc_last_pull_at)}
              </p>
            ) : null}
          </CardContent>
        </Card>
      </form>
    </div>
  )
}
