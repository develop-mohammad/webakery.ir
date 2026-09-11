/// <reference path="../env.d.ts" />
import fs from 'node:fs'
import path from 'node:path'
import Database from 'better-sqlite3'
import schemaSql from './schema.sql?raw'

let db: Database.Database | null = null

const DEFAULT_SETTINGS: Record<string, string> = {
  shop_name: 'دفترچی',
  shop_phone: '',
  shop_address: '',
  shop_logo_path: '',
  sale_counter: '0',
  purchase_counter: '0',
  theme: 'light',
  wc_url: '',
  wc_key: '',
  wc_secret: '',
  wc_currency: 'toman',
  wc_push_price: '1',
  wc_push_stock: '1',
  wc_last_pull_at: '',
}

const DEFAULT_ACCOUNTS = [
  { code: 'cash', name: 'نقدی' },
  { code: 'bank', name: 'بانک' },
  { code: 'gateway', name: 'درگاه سایت' },
]

export function getDb(): Database.Database {
  if (!db) throw new Error('دیتابیس هنوز باز نشده است')
  return db
}

export function openDatabase(dbPath: string): Database.Database {
  fs.mkdirSync(path.dirname(dbPath), { recursive: true })
  db = new Database(dbPath)
  db.pragma('journal_mode = WAL')
  db.pragma('foreign_keys = ON')
  migrate(db)
  return db
}

export function closeDatabase(): void {
  db?.close()
  db = null
}

function migrate(database: Database.Database): void {
  database.exec(schemaSql)
  ensureColumn(database, 'categories', 'wc_category_id', 'INTEGER')
  database.exec('CREATE INDEX IF NOT EXISTS idx_categories_wc ON categories(wc_category_id)')

  const version = database
    .prepare('SELECT value FROM schema_meta WHERE key = ?')
    .get('version') as { value: string } | undefined
  if (!version) {
    database.prepare('INSERT INTO schema_meta (key, value) VALUES (?, ?)').run('version', '2')
  } else if (Number(version.value) < 2) {
    database.prepare('UPDATE schema_meta SET value = ? WHERE key = ?').run('2', 'version')
  }

  const insertSetting = database.prepare(
    'INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)',
  )
  for (const [key, value] of Object.entries(DEFAULT_SETTINGS)) {
    insertSetting.run(key, value)
  }

  const insertAccount = database.prepare(
    'INSERT OR IGNORE INTO cash_accounts (code, name, balance) VALUES (?, ?, 0)',
  )
  for (const account of DEFAULT_ACCOUNTS) {
    insertAccount.run(account.code, account.name)
  }
}

function ensureColumn(
  database: Database.Database,
  table: string,
  column: string,
  type: string,
): void {
  const cols = database.prepare(`PRAGMA table_info(${table})`).all() as { name: string }[]
  if (!cols.some((col) => col.name === column)) {
    database.exec(`ALTER TABLE ${table} ADD COLUMN ${column} ${type}`)
  }
}

export function getAllSettings(): Record<string, string> {
  const rows = getDb().prepare('SELECT key, value FROM settings').all() as {
    key: string
    value: string
  }[]
  const map: Record<string, string> = {}
  for (const row of rows) map[row.key] = row.value
  return map
}

export function setSetting(key: string, value: string): void {
  getDb()
    .prepare(
      `INSERT INTO settings (key, value) VALUES (?, ?)
       ON CONFLICT(key) DO UPDATE SET value = excluded.value`,
    )
    .run(key, value)
}

export function setSettings(entries: Record<string, string>): void {
  const trx = getDb().transaction((items: Record<string, string>) => {
    for (const [key, value] of Object.entries(items)) setSetting(key, value)
  })
  trx(entries)
}
