import { Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from './Sidebar'
import { ThemeToggle } from './ThemeToggle'
import { useSettings } from './SettingsProvider'

const TITLES: Record<string, { title: string; subtitle: string }> = {
  '/': { title: 'داشبورد', subtitle: 'نمای کلی فروش و صندوق' },
  '/products': { title: 'کالاها', subtitle: 'موجودی، قیمت و برچسب بارکد' },
  '/invoices': { title: 'فاکتورها', subtitle: 'فروش و خرید' },
  '/cash': { title: 'صندوق', subtitle: 'ورودی و خروجی حساب‌ها' },
  '/customers': { title: 'مشتریان', subtitle: 'بدهی و تسویه' },
  '/reports': { title: 'گزارش‌ها', subtitle: 'سود و زیان و خروجی اکسل' },
  '/settings': { title: 'تنظیمات فروشگاه', subtitle: 'نام، تماس و ظاهر فاکتور' },
  '/woocommerce': { title: 'اتصال به سایت', subtitle: 'سه قدم: روی سایت کلید بساز، اینجا بگذار، کالاها را بیاور' },
  '/telegram': { title: 'ربات تلگرام', subtitle: 'فروش تلگرامی' },
  '/license': { title: 'لایسنس', subtitle: 'فعال‌سازی تک‌سیستمی' },
}

export function AppLayout() {
  const location = useLocation()
  const { settings } = useSettings()
  const meta = TITLES[location.pathname] ?? { title: 'دفترچی', subtitle: '' }

  return (
    <div className="flex h-full min-h-0 bg-background" dir="rtl">
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
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
        <main className="min-h-0 flex-1 overflow-auto p-5">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
