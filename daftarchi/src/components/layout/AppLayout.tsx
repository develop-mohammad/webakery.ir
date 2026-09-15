import { Link, Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { ThemeToggle } from './ThemeToggle'
import { useSettings } from './SettingsProvider'
import { useLicense } from './LicenseProvider'
import { Button } from '@/components/ui/button'
import { toFaDigits } from '@/lib/jalali'

const TITLES: Record<string, { title: string; subtitle: string }> = {
  '/': { title: 'داشبورد', subtitle: 'نمای کلی فروش و صندوق' },
  '/products': { title: 'کالاها', subtitle: 'موجودی، قیمت و برچسب بارکد' },
  '/invoices': { title: 'فاکتورها', subtitle: 'فروش و خرید' },
  '/cash': { title: 'صندوق', subtitle: 'ورودی و خروجی حساب‌ها' },
  '/customers': { title: 'مشتریان', subtitle: 'آمار خریداران سایت' },
  '/reports': { title: 'گزارش‌ها', subtitle: 'سود و زیان و خروجی اکسل' },
  '/settings': { title: 'تنظیمات فروشگاه', subtitle: 'نام، تماس و ظاهر فاکتور' },
  '/woocommerce': { title: 'اتصال به سایت', subtitle: 'سه قدم: روی سایت کلید بساز، اینجا بگذار، کالاها را بیاور' },
  '/telegram': { title: 'ربات تلگرام', subtitle: 'فروش تلگرامی' },
  '/license': { title: 'لایسنس', subtitle: 'شارژ دوره و فعال‌سازی این سیستم' },
}

export function AppLayout() {
  const location = useLocation()
  const { settings } = useSettings()
  const { license } = useLicense()
  const meta = TITLES[location.pathname] ?? { title: 'دفترچی', subtitle: '' }
  const onLicense = location.pathname === '/license'
  const locked = Boolean(license && !license.allowed && !onLicense)
  const warn = Boolean(license && license.allowed && license.warning !== 'none')

  return (
    <div className="flex h-full min-h-0 bg-background" dir="rtl">
      <Sidebar />
      <div className="relative flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 shrink-0 items-center justify-between border-b bg-card/80 px-5 backdrop-blur">
          <div>
            <h1 className="text-[15px] font-semibold leading-tight">{meta.title}</h1>
            <p className="text-xs text-muted-foreground">{meta.subtitle}</p>
          </div>
          <div className="flex items-center gap-4">
            <span className="hidden text-xs text-muted-foreground sm:inline">
              {settings.shop_phone || ''}
            </span>
            <ThemeToggle />
          </div>
        </header>
        {warn && license ? (
          <div
            className={
              license.warning === 'urgent'
                ? 'border-b bg-destructive/10 px-5 py-2 text-sm text-destructive'
                : 'border-b bg-warning/15 px-5 py-2 text-sm'
            }
          >
            {license.message}{' '}
            <Link to="/license" className="font-medium text-primary hover:underline">
              شارژ دوره
            </Link>
            {license.early_eligible ? (
              <span className="text-muted-foreground">
                {' '}
                — تمدید زودهنگام {toFaDigits(license.discount_percent)}٪ تخفیف
              </span>
            ) : null}
          </div>
        ) : null}
        <main className="min-h-0 flex-1 overflow-auto p-5">
          <Outlet />
        </main>
        {locked ? (
          <div className="absolute inset-0 z-30 flex items-center justify-center bg-background/85 p-6 backdrop-blur-sm">
            <div className="max-w-md rounded-lg border bg-card p-5 text-center shadow-sm">
              <h2 className="text-base font-semibold">برای ادامه باید دوره را شارژ کنی</h2>
              <p className="mt-2 text-sm text-muted-foreground">{license?.message}</p>
              <Button asChild className="mt-4">
                <Link to="/license">رفتن به پرداخت</Link>
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}
