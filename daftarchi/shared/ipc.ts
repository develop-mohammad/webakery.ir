/** کانال‌های IPC بین رندرر و فرایند اصلی */

export const IPC = {
  ping: 'ping',
  settingsGetAll: 'settings:getAll',
  settingsSet: 'settings:set',
  settingsSetMany: 'settings:setMany',
  appInfo: 'app:info',
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
}
