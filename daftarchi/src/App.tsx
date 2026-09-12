import { HashRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AppLayout } from '@/components/layout/AppLayout'
import { SettingsProvider } from '@/components/layout/SettingsProvider'
import { LicenseProvider } from '@/components/layout/LicenseProvider'
import { Toaster } from '@/components/ui/sonner'
import { CashPage } from '@/pages/CashPage'
import { ComingSoonPage } from '@/pages/ComingSoonPage'
import { CustomersPage } from '@/pages/CustomersPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { InvoicesPage } from '@/pages/InvoicesPage'
import { LicensePage } from '@/pages/LicensePage'
import { ProductsPage } from '@/pages/ProductsPage'
import { ReportsPage } from '@/pages/ReportsPage'
import { SettingsPage } from '@/pages/SettingsPage'
import { WooCommercePage } from '@/pages/WooCommercePage'

export default function App() {
  return (
    <SettingsProvider>
      <LicenseProvider>
      <HashRouter
        future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
      >
        <Routes>
          <Route element={<AppLayout />}>
            <Route index element={<DashboardPage />} />
            <Route path="products" element={<ProductsPage />} />
            <Route path="invoices" element={<InvoicesPage />} />
            <Route path="cash" element={<CashPage />} />
            <Route path="customers" element={<CustomersPage />} />
            <Route path="reports" element={<ReportsPage />} />
            <Route path="settings" element={<SettingsPage />} />
            <Route path="woocommerce" element={<WooCommercePage />} />
            <Route
              path="telegram"
              element={
                <ComingSoonPage title="ربات تلگرام" body="فروش تلگرامی بعد از فاز ۱ به همین موجودی وصل می‌شود." />
              }
            />
            <Route path="license" element={<LicensePage />} />
            <Route
              path="*"
              element={<Navigate to="/" replace />}
            />
          </Route>
        </Routes>
      </HashRouter>
      </LicenseProvider>
      <Toaster />
    </SettingsProvider>
  )
}
