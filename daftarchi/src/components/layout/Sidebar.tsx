import type { ComponentType } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import {
  BarChart3,
  FileText,
  KeyRound,
  LayoutDashboard,
  Package,
  Send,
  Settings,
  Store,
  Users,
  Wallet,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { cn } from '@/lib/utils'
import { useSettings } from './SettingsProvider'

const MAIN_NAV = [
  { to: '/', label: 'داشبورد', icon: LayoutDashboard, end: true },
  { to: '/products', label: 'کالاها', icon: Package },
  { to: '/invoices', label: 'فاکتورها', icon: FileText },
  { to: '/cash', label: 'صندوق', icon: Wallet },
  { to: '/customers', label: 'مشتریان', icon: Users },
  { to: '/reports', label: 'گزارش‌ها', icon: BarChart3 },
] as const

const SOON_NAV = [
  { to: '/telegram', label: 'تلگرام', icon: Send },
  { to: '/license', label: 'لایسنس', icon: KeyRound },
] as const

export function Sidebar() {
  const { settings } = useSettings()
  const shopName = settings.shop_name || 'دفترچی'
  const location = useLocation()

  return (
    <aside className="flex w-[13.25rem] shrink-0 flex-col border-l bg-card">
      <div className="flex items-center gap-2.5 px-3 py-3.5">
        <LogoMark />
        <div className="min-w-0">
          <div className="truncate text-sm font-bold leading-tight">{shopName}</div>
          <div className="text-[11px] text-muted-foreground">حسابداری ساده</div>
        </div>
      </div>
      <Separator />
      <ScrollArea className="flex-1">
        <nav className="flex flex-col gap-0.5 p-2">
          {MAIN_NAV.map((item) => (
            <NavItem
              key={item.to}
              to={item.to}
              label={item.label}
              icon={item.icon}
              end={'end' in item && item.end}
              active={item.to === '/' ? location.pathname === '/' : location.pathname.startsWith(item.to)}
            />
          ))}
        </nav>
      </ScrollArea>
      <Separator />
      <div className="p-2 pb-3">
        <NavItem to="/settings" label="تنظیمات فروشگاه" icon={Settings} />
        <NavItem to="/woocommerce" label="اتصال سایت" icon={Store} />
        <div className="mt-1 space-y-0.5">
          {SOON_NAV.map((item) => (
            <NavItem key={item.to} to={item.to} label={item.label} icon={item.icon} soon />
          ))}
        </div>
      </div>
    </aside>
  )
}

function NavItem({
  to,
  label,
  icon: Icon,
  end,
  soon,
  active,
}: {
  to: string
  label: string
  icon: ComponentType<{ className?: string }>
  end?: boolean
  soon?: boolean
  active?: boolean
}) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        cn(
          'flex items-center gap-2 rounded-md px-2 py-1.5 text-[13px] transition-colors',
          isActive || active
            ? 'bg-primary/10 font-medium text-primary'
            : 'text-foreground/80 hover:bg-accent hover:text-accent-foreground',
          soon && !isActive && 'text-muted-foreground',
        )
      }
    >
      <Icon className="size-4 shrink-0" />
      <span className="min-w-0 flex-1 truncate">{label}</span>
      {soon ? <Badge variant="soon">به‌زودی</Badge> : null}
    </NavLink>
  )
}

function LogoMark() {
  return (
    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
      <svg viewBox="0 0 24 24" className="size-5" fill="none" aria-hidden>
        <path
          d="M7 4.5h8.5A2.5 2.5 0 0 1 18 7v12.2a.8.8 0 0 1-1.2.7L14 18.2l-2.8 1.7a.8.8 0 0 1-.9 0L8 18.2 5.2 19.9A.8.8 0 0 1 4 19.2V7A2.5 2.5 0 0 1 6.5 4.5H7Z"
          stroke="currentColor"
          strokeWidth="1.7"
          strokeLinejoin="round"
        />
        <path d="M8.5 9h7M8.5 12.5h5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
      </svg>
    </span>
  )
}
