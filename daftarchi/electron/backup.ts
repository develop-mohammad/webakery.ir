import fs from 'node:fs'
import path from 'node:path'
import { app } from 'electron'
import type { AppPaths } from '../shared/types'

/** پوشه داده: در توسعه کنار پروژه، در نصب‌شده در AppData */
export function getAppPaths(): AppPaths {
  const userData = app.isPackaged
    ? app.getPath('userData')
    : path.join(process.cwd(), 'data')
  return {
    userData,
    backups: path.join(userData, 'backups'),
    dbPath: path.join(userData, 'daftarchi.sqlite'),
  }
}

/** کپی دیتابیس با سقف ۳۰ نسخه */
export function backupDatabase(dbPath: string, backupsDir: string): void {
  if (!fs.existsSync(dbPath)) return
  fs.mkdirSync(backupsDir, { recursive: true })
  const stamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19)
  const dest = path.join(backupsDir, `daftarchi-${stamp}.sqlite`)
  fs.copyFileSync(dbPath, dest)

  const files = fs
    .readdirSync(backupsDir)
    .filter((name) => name.endsWith('.sqlite'))
    .map((name) => ({
      name,
      mtime: fs.statSync(path.join(backupsDir, name)).mtimeMs,
    }))
    .sort((a, b) => a.mtime - b.mtime)

  while (files.length > 30) {
    const oldest = files.shift()
    if (oldest) fs.unlinkSync(path.join(backupsDir, oldest.name))
  }
}
