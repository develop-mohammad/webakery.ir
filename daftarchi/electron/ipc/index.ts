import { app, ipcMain } from 'electron'
import { IPC } from '../../shared/ipc'
import type { AppInfo, CreateSaleInput, ProductPatch, SettingsMap } from '../../shared/ipc'
import { getAllSettings, setSetting, setSettings } from '../database/db'
import { getAppPaths } from '../backup'
import {
  createCategory,
  createProduct,
  deleteProduct,
  listCategories,
  listProducts,
  searchProducts,
  updateProduct,
} from '../services/products'
import { pullWooProducts, pullWooSales, testWooConnection } from '../services/woocommerce'
import { customerStats } from '../services/customers'
import {
  activateLicense,
  assertLicenseActive,
  licenseSnapshot,
  payUrl,
  refreshLicense,
} from '../services/license'
import { createSale, listInvoices } from '../services/invoices'
import {
  addManualExpense,
  applyStocktake,
  comparisonSeries,
  dashboardData,
  profitReport,
  stocktakeRows,
} from '../services/reports'
import { monthRangeYmd, weekRangeYmd } from '../../shared/period'
import type { NewProduct } from '../../shared/models'

export function registerIpc(): void {
  ipcMain.handle(IPC.ping, () => 'ok')
  ipcMain.handle(IPC.settingsGetAll, (): SettingsMap => getAllSettings())
  ipcMain.handle(IPC.settingsSet, (_e, key: string, value: string) => {
    if (typeof key !== 'string' || typeof value !== 'string') throw new Error('کلید یا مقدار نامعتبر است')
    setSetting(key, value)
    return getAllSettings()
  })
  ipcMain.handle(IPC.settingsSetMany, (_e, entries: SettingsMap) => {
    const clean: SettingsMap = {}
    for (const [key, value] of Object.entries(entries || {})) {
      if (typeof key === 'string' && typeof value === 'string') clean[key] = value
    }
    setSettings(clean)
    return getAllSettings()
  })
  ipcMain.handle(IPC.appInfo, (): AppInfo => {
    const paths = getAppPaths()
    return { version: app.getVersion(), dbPath: paths.dbPath, backupsDir: paths.backups, packaged: app.isPackaged }
  })

  ipcMain.handle(IPC.categoriesList, () => listCategories())
  ipcMain.handle(IPC.categoriesCreate, (_e, name: string) => {
    assertLicenseActive()
    return createCategory(String(name || ''))
  })
  ipcMain.handle(IPC.productsList, (_e, filter?: { categoryId?: number | null; search?: string }) =>
    listProducts(filter),
  )
  ipcMain.handle(IPC.productsSearch, (_e, query: string) => searchProducts(String(query || '')))
  ipcMain.handle(IPC.productsCreate, (_e, input: NewProduct) => {
    assertLicenseActive()
    return createProduct(input)
  })
  ipcMain.handle(IPC.productsUpdate, (_e, patch: ProductPatch) => {
    assertLicenseActive()
    return updateProduct(patch)
  })
  ipcMain.handle(IPC.productsDelete, (_e, id: number) => {
    assertLicenseActive()
    deleteProduct(Number(id))
  })
  ipcMain.handle(IPC.wcTest, () => testWooConnection())
  ipcMain.handle(IPC.wcPull, () => {
    assertLicenseActive()
    return pullWooProducts()
  })
  ipcMain.handle(IPC.wcPullSales, () => {
    assertLicenseActive()
    return pullWooSales()
  })
  ipcMain.handle(IPC.customersStats, () => customerStats())
  ipcMain.handle(IPC.invoicesList, (_e, limit?: number) => listInvoices(Number(limit) || 50))
  ipcMain.handle(IPC.invoicesCreateSale, (_e, input: CreateSaleInput, createdAt?: string) => {
    assertLicenseActive()
    return createSale(input, createdAt)
  })
  ipcMain.handle(IPC.dashboard, (_e, preset: 'week' | 'month') => dashboardData(preset === 'month' ? 'month' : 'week'))
  ipcMain.handle(IPC.reportsComparison, (_e, preset: 'week' | 'month') =>
    comparisonSeries(preset === 'month' ? 'month' : 'week'),
  )
  ipcMain.handle(IPC.reportsProfit, (_e, preset: 'week' | 'month') => {
    const range = preset === 'month' ? monthRangeYmd() : weekRangeYmd()
    return profitReport(range.start, range.endExclusive)
  })
  ipcMain.handle(IPC.reportsStocktake, () => stocktakeRows())
  ipcMain.handle(
    IPC.reportsApplyStocktake,
    (_e, counts: { product_id: number; counted_qty: number }[], note: string) => {
      assertLicenseActive()
      return applyStocktake(counts || [], String(note || ''))
    },
  )
  ipcMain.handle(IPC.reportsExpense, (_e, amount: number, note: string) => {
    assertLicenseActive()
    addManualExpense(Math.floor(Number(amount) || 0), String(note || ''))
  })
  ipcMain.handle(IPC.licenseStatus, () => licenseSnapshot())
  ipcMain.handle(IPC.licenseActivate, (_e, key: string) => activateLicense(String(key || '')))
  ipcMain.handle(IPC.licenseRefresh, () => refreshLicense())
  ipcMain.handle(IPC.licensePayUrl, (_e, planId: string) => payUrl(String(planId || '3m')))
}
