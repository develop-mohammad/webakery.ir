/** کانال‌های IPC بین رندرر و فرایند اصلی */

import type {
  Category,
  ComparisonSeries,
  CreateSaleInput,
  DashboardData,
  InvoiceListItem,
  NewProduct,
  Product,
  ProductPatch,
  ProfitReport,
  StocktakeRow,
  WcPullResult,
  WcSalesResult,
} from './models'

export type { Category, Product, ProductPatch, NewProduct, InvoiceListItem, CreateSaleInput, DashboardData, ComparisonSeries, ProfitReport, StocktakeRow, WcPullResult, WcSalesResult }

export const IPC = {
  ping: 'ping',
  settingsGetAll: 'settings:getAll',
  settingsSet: 'settings:set',
  settingsSetMany: 'settings:setMany',
  appInfo: 'app:info',
  categoriesList: 'categories:list',
  categoriesCreate: 'categories:create',
  productsList: 'products:list',
  productsSearch: 'products:search',
  productsCreate: 'products:create',
  productsUpdate: 'products:update',
  productsDelete: 'products:delete',
  wcTest: 'wc:test',
  wcPull: 'wc:pull',
  wcPullSales: 'wc:pullSales',
  invoicesList: 'invoices:list',
  invoicesCreateSale: 'invoices:createSale',
  dashboard: 'dashboard:get',
  reportsComparison: 'reports:comparison',
  reportsProfit: 'reports:profit',
  reportsStocktake: 'reports:stocktake',
  reportsApplyStocktake: 'reports:applyStocktake',
  reportsExpense: 'reports:expense',
} as const

export type SettingsMap = Record<string, string>

export type AppInfo = {
  version: string
  dbPath: string
  backupsDir: string
  packaged: boolean
}

export type DaftarchiApi = {
  ping: () => Promise<string>
  getAppInfo: () => Promise<AppInfo>
  getSettings: () => Promise<SettingsMap>
  setSetting: (key: string, value: string) => Promise<SettingsMap>
  setSettings: (entries: SettingsMap) => Promise<SettingsMap>
  listCategories: () => Promise<Category[]>
  createCategory: (name: string) => Promise<Category>
  listProducts: (filter?: { categoryId?: number | null; search?: string }) => Promise<Product[]>
  searchProducts: (query: string) => Promise<Product[]>
  createProduct: (input: NewProduct) => Promise<Product>
  updateProduct: (patch: ProductPatch) => Promise<Product>
  deleteProduct: (id: number) => Promise<void>
  testWoo: () => Promise<string>
  pullWoo: () => Promise<WcPullResult>
  pullWooSales: () => Promise<WcSalesResult>
  listInvoices: (limit?: number) => Promise<InvoiceListItem[]>
  createSale: (input: CreateSaleInput, createdAt?: string) => Promise<InvoiceListItem>
  dashboard: (preset: 'week' | 'month') => Promise<DashboardData>
  comparison: (preset: 'week' | 'month') => Promise<ComparisonSeries>
  profit: (preset: 'week' | 'month') => Promise<ProfitReport>
  stocktakeRows: () => Promise<StocktakeRow[]>
  applyStocktake: (counts: { product_id: number; counted_qty: number }[], note: string) => Promise<number>
  addExpense: (amount: number, note: string) => Promise<void>
}
