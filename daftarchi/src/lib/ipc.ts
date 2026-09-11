import type { AppInfo, DaftarchiApi, SettingsMap } from '../../shared/ipc'

const fallback: DaftarchiApi = {
  ping: async () => 'offline',
  getAppInfo: async (): Promise<AppInfo> => ({
    version: '1.0.0',
    dbPath: '',
    backupsDir: '',
    packaged: false,
  }),
  getSettings: async (): Promise<SettingsMap> => ({
    shop_name: 'دفترچی',
    theme: localStorage.getItem('daftarchi-theme') || 'light',
  }),
  setSetting: async (key, value) => {
    if (key === 'theme') localStorage.setItem('daftarchi-theme', value)
    return fallback.getSettings()
  },
  setSettings: async (entries) => {
    if (entries.theme) localStorage.setItem('daftarchi-theme', entries.theme)
    return fallback.getSettings()
  },
  listCategories: async () => [],
  createCategory: async () => {
    throw new Error('آفلاین')
  },
  listProducts: async () => [],
  searchProducts: async () => [],
  createProduct: async () => {
    throw new Error('آفلاین')
  },
  updateProduct: async () => {
    throw new Error('آفلاین')
  },
  deleteProduct: async () => undefined,
  testWoo: async () => 'آفلاین',
  pullWoo: async () => ({ categories: 0, created: 0, updated: 0, skipped: 0, orders: 0, message: 'آفلاین' }),
  pullWooSales: async () => ({ orders: 0, total: 0, message: 'آفلاین' }),
  listInvoices: async () => [],
  createSale: async () => {
    throw new Error('آفلاین')
  },
  dashboard: async () => ({
    sales_today: 0,
    profit_month: 0,
    invoices_today: 0,
    cash_balance: 0,
    site_sales_today: 0,
    site_orders_today: 0,
    site_sales_period: 0,
    site_sales_pulled_at: '',
    comparison: {
      preset: 'week',
      current_label: 'این هفته',
      previous_label: 'هفتهٔ قبل',
      current_total: 0,
      previous_total: 0,
      delta_percent: 0,
      points: [],
    },
    peak_hours: [],
    recent_invoices: [],
    recent_site_orders: [],
  }),
  comparison: async () => ({
    preset: 'week',
    current_label: '',
    previous_label: '',
    current_total: 0,
    previous_total: 0,
    delta_percent: 0,
    points: [],
  }),
  profit: async () => ({ sales: 0, cogs: 0, expenses: 0, profit: 0, top_products: [] }),
  stocktakeRows: async () => [],
  applyStocktake: async () => 0,
  addExpense: async () => undefined,
}

export function api(): DaftarchiApi {
  return window.daftarchi ?? fallback
}

export type { DaftarchiApi, SettingsMap, AppInfo }
