import { contextBridge, ipcRenderer } from 'electron'
import { IPC, type AppInfo, type CreateSaleInput, type ProductPatch, type SettingsMap } from '../shared/ipc'
import type { NewProduct } from '../shared/models'

const api = {
  ping: () => ipcRenderer.invoke(IPC.ping) as Promise<string>,
  getAppInfo: () => ipcRenderer.invoke(IPC.appInfo) as Promise<AppInfo>,
  getSettings: () => ipcRenderer.invoke(IPC.settingsGetAll) as Promise<SettingsMap>,
  setSetting: (key: string, value: string) =>
    ipcRenderer.invoke(IPC.settingsSet, key, value) as Promise<SettingsMap>,
  setSettings: (entries: SettingsMap) =>
    ipcRenderer.invoke(IPC.settingsSetMany, entries) as Promise<SettingsMap>,
  listCategories: () => ipcRenderer.invoke(IPC.categoriesList),
  createCategory: (name: string) => ipcRenderer.invoke(IPC.categoriesCreate, name),
  listProducts: (filter?: { categoryId?: number | null; search?: string }) =>
    ipcRenderer.invoke(IPC.productsList, filter),
  searchProducts: (query: string) => ipcRenderer.invoke(IPC.productsSearch, query),
  createProduct: (input: NewProduct) => ipcRenderer.invoke(IPC.productsCreate, input),
  updateProduct: (patch: ProductPatch) => ipcRenderer.invoke(IPC.productsUpdate, patch),
  deleteProduct: (id: number) => ipcRenderer.invoke(IPC.productsDelete, id),
  testWoo: () => ipcRenderer.invoke(IPC.wcTest),
  pullWoo: () => ipcRenderer.invoke(IPC.wcPull),
  pullWooSales: () => ipcRenderer.invoke(IPC.wcPullSales),
  customersStats: () => ipcRenderer.invoke(IPC.customersStats),
  listInvoices: (limit?: number) => ipcRenderer.invoke(IPC.invoicesList, limit),
  createSale: (input: CreateSaleInput, createdAt?: string) =>
    ipcRenderer.invoke(IPC.invoicesCreateSale, input, createdAt),
  dashboard: (preset: 'week' | 'month') => ipcRenderer.invoke(IPC.dashboard, preset),
  comparison: (preset: 'week' | 'month') => ipcRenderer.invoke(IPC.reportsComparison, preset),
  profit: (preset: 'week' | 'month') => ipcRenderer.invoke(IPC.reportsProfit, preset),
  stocktakeRows: () => ipcRenderer.invoke(IPC.reportsStocktake),
  applyStocktake: (counts: { product_id: number; counted_qty: number }[], note: string) =>
    ipcRenderer.invoke(IPC.reportsApplyStocktake, counts, note),
  addExpense: (amount: number, note: string) => ipcRenderer.invoke(IPC.reportsExpense, amount, note),
}

contextBridge.exposeInMainWorld('daftarchi', api)
