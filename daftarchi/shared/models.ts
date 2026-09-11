export type Category = {
  id: number
  name: string
  wc_category_id: number | null
  product_count: number
}

export type Product = {
  id: number
  name: string
  sku: string
  buy_price: number
  sell_price: number
  stock: number
  category_id: number | null
  category_name: string
  stock_alert: number
  wc_product_id: number | null
  site_url: string
  low_stock: boolean
  created_at: string
  updated_at: string
}

export type ProductPatch = {
  id: number
  name?: string
  sku?: string
  buy_price?: number
  sell_price?: number
  stock?: number
  category_id?: number | null
  stock_alert?: number
}

export type NewProduct = {
  name: string
  sku: string
  buy_price: number
  sell_price: number
  stock: number
  category_id: number | null
  stock_alert: number
}

export type InvoiceListItem = {
  id: number
  number: string
  type: 'sale' | 'purchase'
  total: number
  payment_type: string
  customer_name: string
  created_at: string
}

export type SaleLineInput = {
  product_id: number
  qty: number
  unit_price: number
}

export type CreateSaleInput = {
  items: SaleLineInput[]
  discount_type: 'none' | 'percent' | 'amount'
  discount_value: number
  payment_type: 'cash' | 'card' | 'credit'
  customer_id: number | null
  note: string
}

export type ComparisonPoint = {
  key: string
  label: string
  current: number
  previous: number
}

export type ComparisonSeries = {
  preset: 'week' | 'month'
  current_label: string
  previous_label: string
  current_total: number
  previous_total: number
  delta_percent: number | null
  points: ComparisonPoint[]
}

export type PeakHour = {
  hour: number
  label: string
  total: number
  count: number
}

export type SiteOrder = {
  id: number
  number: string
  status: string
  total: number
  customer_name: string
  created_at: string
}

export type DashboardData = {
  sales_today: number
  profit_month: number
  invoices_today: number
  cash_balance: number
  site_sales_today: number
  site_orders_today: number
  site_sales_period: number
  site_sales_pulled_at: string
  site_customers: number
  comparison: ComparisonSeries
  peak_hours: PeakHour[]
  recent_invoices: InvoiceListItem[]
  recent_site_orders: SiteOrder[]
}

export type ProfitReport = {
  sales: number
  cogs: number
  expenses: number
  profit: number
  top_products: { product_id: number; name: string; qty: number; sales: number }[]
}

export type StocktakeRow = {
  product_id: number
  name: string
  sku: string
  category_name: string
  system_qty: number
}

export type WcSalesResult = {
  orders: number
  total: number
  message: string
}

export type WcPullResult = {
  categories: number
  created: number
  updated: number
  skipped: number
  orders: number
  customers: number
  message: string
}

export type SiteCustomer = {
  name: string
  phone: string
  email: string
  orders_count: number
  total_spent: number
  last_order_at: string
}

export type CustomerStats = {
  total_customers: number
  month_buyers: number
  month_spent: number
  avg_order: number
  list: SiteCustomer[]
}
