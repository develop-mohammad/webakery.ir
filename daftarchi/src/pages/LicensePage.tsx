import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api } from '@/lib/ipc'
import { formatJalaliDate, toFaDigits } from '@/lib/jalali'
import { useLicense } from '@/components/layout/LicenseProvider'
import { cn } from '@/lib/utils'

export function LicensePage() {
  const { license, refresh } = useLicense()
  const [key, setKey] = useState('')
  const [email, setEmail] = useState(license?.email ?? '')
  const [busy, setBusy] = useState(false)

  async function activate() {
    setBusy(true)
    try {
      if (email.trim()) await api().setSetting('license_email', email.trim())
      const result = await api().activateLicense(key)
      await refresh()
      if (result.ok) toast.success(result.message)
      else toast.error(result.message)
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'فعال نشد')
    } finally {
      setBusy(false)
    }
  }

  async function sync() {
    setBusy(true)
    try {
      await api().refreshLicense()
      await refresh()
      toast.success('وضعیت از سرور به‌روز شد')
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'سرور جواب نداد')
    } finally {
      setBusy(false)
    }
  }

  async function pay(planId: string) {
    try {
      if (email.trim()) await api().setSetting('license_email', email.trim())
      const href = await api().licensePayUrl(planId)
      window.open(href, '_blank', 'noreferrer')
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'لینک پرداخت ساخته نشد')
    }
  }

  const snap = license
  const kindLabel =
    snap?.kind === 'licensed' ? 'فعال' : snap?.kind === 'trial' ? 'آزمایشی' : 'نیاز به شارژ'

  return (
    <div className="mx-auto grid max-w-4xl gap-4 lg:grid-cols-[1fr_18rem]">
      <div className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle>وضعیت دوره</CardTitle>
            <CardDescription>{snap?.message}</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
            <Meta label="وضعیت" value={kindLabel} />
            <Meta label="روز مانده" value={toFaDigits(snap?.days_left ?? 0)} />
            <Meta
              label="پایان دوره"
              value={snap?.expires_at ? formatJalaliDate(snap.expires_at) : '—'}
            />
            <Meta label="دوره‌های خریده‌شده" value={toFaDigits(snap?.periods_paid ?? 0)} />
            <Meta label="کلید" value={snap?.key_masked || 'هنوز وارد نشده'} />
            <Meta label="شناسه این سیستم" value={snap?.machine_id || '—'} />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>شارژ دوره</CardTitle>
            <CardDescription>
              هر خرید، همان مدت را به پایان دوره اضافه می‌کند. اگر حداقل سه روز زودتر تمدید کنی، با توجه به
              سابقه‌ات بین ۵ تا ۹ درصد تخفیف می‌گیری.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 sm:grid-cols-3">
            {(snap?.plans ?? []).map((plan) => (
              <button
                key={plan.plan_id}
                type="button"
                onClick={() => pay(plan.plan_id)}
                className={cn(
                  'rounded-lg border bg-card p-3 text-right transition-colors hover:border-primary',
                  plan.badge && 'border-primary/40',
                )}
              >
                <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                  <span>{plan.label}</span>
                  {plan.badge ? <span className="text-primary">{plan.badge}</span> : null}
                </div>
                {plan.discount_percent > 0 ? (
                  <p className="mt-2 text-sm">
                    <span className="text-muted-foreground line-through">
                      {toFaDigits(plan.price_toman.toLocaleString('fa-IR'))}
                    </span>{' '}
                    <span className="font-semibold">
                      {toFaDigits(plan.pay_toman.toLocaleString('fa-IR'))} تومان
                    </span>
                    <span className="mt-1 block text-[11px] text-primary">
                      تمدید زودهنگام {toFaDigits(plan.discount_percent)}٪
                    </span>
                  </p>
                ) : (
                  <p className="mt-2 font-semibold">
                    {toFaDigits(plan.price_toman.toLocaleString('fa-IR'))} تومان
                  </p>
                )}
                <p className="mt-1 text-[11px] text-muted-foreground">{plan.hint}</p>
              </button>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>فعال‌سازی با کلید</CardTitle>
            <CardDescription>بعد از پرداخت، کلید را اینجا بگذار. فقط روی همین سیستم کار می‌کند.</CardDescription>
          </CardHeader>
          <CardContent className="grid gap-2">
            <Label>ایمیل خرید</Label>
            <Input
              dir="ltr"
              className="text-left"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
            />
            <Label>کلید لایسنس</Label>
            <Input
              dir="ltr"
              className="text-left"
              value={key}
              onChange={(e) => setKey(e.target.value)}
              placeholder="DAFTAR-••••"
            />
            <div className="flex flex-wrap gap-2">
              <Button disabled={busy} onClick={activate}>
                فعال کن
              </Button>
              <Button variant="outline" disabled={busy} onClick={sync}>
                بررسی پرداخت
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="h-fit">
        <CardHeader>
          <CardTitle className="text-base">اگر نپردازی</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-muted-foreground">
          <p>دادهٔ فروشگاه پاک نمی‌شود؛ فقط ثبت فروش و تغییر کالا تا شارژ بعدی بسته است.</p>
          <p>نزدیک پایان دوره، بالای صفحه هشدار می‌آید.</p>
          <Link to="/" className="text-primary hover:underline">
            بازگشت به داشبورد
          </Link>
        </CardContent>
      </Card>
    </div>
  )
}

function Meta({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-muted-foreground">{label}</p>
      <p className="mt-0.5 font-medium" dir={/^[A-Za-z0-9._-]+$/.test(value) ? 'ltr' : 'rtl'}>
        {value}
      </p>
    </div>
  )
}
