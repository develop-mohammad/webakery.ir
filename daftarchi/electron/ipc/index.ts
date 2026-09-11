import { app, ipcMain } from 'electron'
import { IPC } from '../../shared/ipc'
import type { AppInfo, SettingsMap } from '../../shared/ipc'
import { getAllSettings, setSetting, setSettings } from '../database/db'
import { getAppPaths } from '../backup'

export function registerIpc(): void {
  ipcMain.handle(IPC.ping, () => 'ok')

  ipcMain.handle(IPC.settingsGetAll, (): SettingsMap => getAllSettings())

  ipcMain.handle(IPC.settingsSet, (_event, key: string, value: string) => {
    if (typeof key !== 'string' || typeof value !== 'string') {
      throw new Error('کلید یا مقدار نامعتبر است')
    }
    setSetting(key, value)
    return getAllSettings()
  })

  ipcMain.handle(IPC.settingsSetMany, (_event, entries: SettingsMap) => {
    if (!entries || typeof entries !== 'object') {
      throw new Error('تنظیمات نامعتبر است')
    }
    const clean: SettingsMap = {}
    for (const [key, value] of Object.entries(entries)) {
      if (typeof key === 'string' && typeof value === 'string') clean[key] = value
    }
    setSettings(clean)
    return getAllSettings()
  })

  ipcMain.handle(IPC.appInfo, (): AppInfo => {
    const paths = getAppPaths()
    return {
      version: app.getVersion(),
      dbPath: paths.dbPath,
      backupsDir: paths.backups,
      packaged: app.isPackaged,
    }
  })
}
