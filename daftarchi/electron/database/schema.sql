PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS schema_meta (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS categories (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  name            TEXT NOT NULL UNIQUE COLLATE NOCASE,
  wc_category_id  INTEGER,
  created_at      TEXT NOT NULL,
  updated_at      TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS products (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  name           TEXT    NOT NULL,
  sku            TEXT    NOT NULL UNIQUE COLLATE NOCASE,
  buy_price      INTEGER NOT NULL DEFAULT 0,
  sell_price     INTEGER NOT NULL DEFAULT 0,
  stock          INTEGER NOT NULL DEFAULT 0,
  category_id    INTEGER REFERENCES categories(id) ON DELETE SET NULL,
  stock_alert    INTEGER NOT NULL DEFAULT 0,
  wc_product_id  INTEGER,
  site_url       TEXT    NOT NULL DEFAULT '',
  created_at     TEXT    NOT NULL,
  updated_at     TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_name ON products(name);

CREATE TABLE IF NOT EXISTS price_history (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id  INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  field       TEXT    NOT NULL,
  old_value   INTEGER NOT NULL,
  new_value   INTEGER NOT NULL,
  source      TEXT    NOT NULL,
  created_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_price_history_product ON price_history(product_id, created_at);

CREATE TABLE IF NOT EXISTS customers (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT    NOT NULL,
  phone      TEXT    NOT NULL DEFAULT '',
  debt       INTEGER NOT NULL DEFAULT 0,
  created_at TEXT    NOT NULL,
  updated_at TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_customers_phone ON customers(phone);

CREATE TABLE IF NOT EXISTS cash_accounts (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  code     TEXT    NOT NULL UNIQUE,
  name     TEXT    NOT NULL,
  balance  INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS invoices (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  number          TEXT    NOT NULL UNIQUE,
  type            TEXT    NOT NULL,
  customer_id     INTEGER REFERENCES customers(id) ON DELETE RESTRICT,
  discount_type   TEXT    NOT NULL DEFAULT 'none',
  discount_value  INTEGER NOT NULL DEFAULT 0,
  subtotal        INTEGER NOT NULL DEFAULT 0,
  discount_amount INTEGER NOT NULL DEFAULT 0,
  total           INTEGER NOT NULL DEFAULT 0,
  payment_type    TEXT    NOT NULL,
  account_code    TEXT,
  note            TEXT    NOT NULL DEFAULT '',
  created_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_invoices_type_date ON invoices(type, created_at);
CREATE INDEX IF NOT EXISTS idx_invoices_customer ON invoices(customer_id);

CREATE TABLE IF NOT EXISTS invoice_items (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  invoice_id         INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
  product_id         INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
  name_snapshot      TEXT    NOT NULL,
  sku_snapshot       TEXT    NOT NULL,
  qty                INTEGER NOT NULL,
  unit_price         INTEGER NOT NULL,
  line_total         INTEGER NOT NULL,
  buy_price_snapshot INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_items_invoice ON invoice_items(invoice_id);
CREATE INDEX IF NOT EXISTS idx_items_product ON invoice_items(product_id);

CREATE TABLE IF NOT EXISTS customer_ledger (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id  INTEGER NOT NULL REFERENCES customers(id) ON DELETE RESTRICT,
  kind         TEXT    NOT NULL,
  amount       INTEGER NOT NULL,
  invoice_id   INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
  note         TEXT    NOT NULL DEFAULT '',
  created_at   TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_ledger_customer ON customer_ledger(customer_id, created_at);

CREATE TABLE IF NOT EXISTS cash_transactions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  account_id  INTEGER NOT NULL REFERENCES cash_accounts(id) ON DELETE RESTRICT,
  direction   TEXT    NOT NULL,
  amount      INTEGER NOT NULL,
  kind        TEXT    NOT NULL,
  invoice_id  INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
  customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
  note        TEXT    NOT NULL DEFAULT '',
  created_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_cash_date ON cash_transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_cash_account ON cash_transactions(account_id);

CREATE TABLE IF NOT EXISTS sync_queue (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  action        TEXT NOT NULL,
  product_id    INTEGER REFERENCES products(id) ON DELETE CASCADE,
  wc_product_id INTEGER,
  payload       TEXT NOT NULL DEFAULT '{}',
  status        TEXT NOT NULL DEFAULT 'pending',
  error         TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL,
  done_at       TEXT
);

CREATE TABLE IF NOT EXISTS sync_log (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  direction  TEXT NOT NULL,
  action     TEXT NOT NULL,
  product_id INTEGER,
  message    TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS stocktakes (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  note         TEXT NOT NULL DEFAULT '',
  status       TEXT NOT NULL DEFAULT 'completed',
  created_at   TEXT NOT NULL,
  completed_at TEXT
);

CREATE TABLE IF NOT EXISTS stocktake_items (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  stocktake_id INTEGER NOT NULL REFERENCES stocktakes(id) ON DELETE CASCADE,
  product_id   INTEGER NOT NULL REFERENCES products(id) ON DELETE RESTRICT,
  system_qty   INTEGER NOT NULL,
  counted_qty  INTEGER NOT NULL,
  diff_qty     INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_stocktake_items ON stocktake_items(stocktake_id);

CREATE TABLE IF NOT EXISTS wc_orders (
  id              INTEGER PRIMARY KEY,
  number          TEXT    NOT NULL DEFAULT '',
  status          TEXT    NOT NULL DEFAULT '',
  total           INTEGER NOT NULL DEFAULT 0,
  customer_name   TEXT    NOT NULL DEFAULT '',
  customer_phone  TEXT    NOT NULL DEFAULT '',
  customer_email  TEXT    NOT NULL DEFAULT '',
  item_count      INTEGER NOT NULL DEFAULT 0,
  created_at      TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_wc_orders_date ON wc_orders(created_at);
